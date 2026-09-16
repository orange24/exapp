<?php

namespace App\Console\Commands;

use App\Models\CounterStock;
use App\Models\StockMovement;
use App\Models\TransactionMaster;
use App\Models\WorkingDay;
use App\Services\ThbCashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * สร้าง movement เงินบาทย้อนหลังจากข้อมูลที่มีอยู่
 *
 * ก่อนหน้านี้ระบบไม่เคยบันทึกเงินบาทเป็นสต็อกเลย — มีแค่ working_days
 * .opening_thb_cash ที่พนักงานกรอกมือตอนเปิดวันแล้วไม่มีใครคำนวณต่อ คำสั่งนี้
 * ไล่สร้าง movement ให้ยอดคงเหลือตั้งต้นพอใช้งานได้
 *
 * แยกเป็น command ไม่ใส่ใน migration โดยเจตนา — migration ถูกรันอัตโนมัติตอน
 * deploy (entrypoint.sh migrate --force) ซึ่งจะไปเขียนข้อมูลบน production
 * โดยที่ไม่มีใครกดอะไร งานที่แตะข้อมูลจริงควรต้องสั่งเองและดู --dry-run ก่อน
 *
 * ⚠️ ยอดที่ได้จะไม่ตรงกับเงินจริงในลิ้นชัก เพราะการเติมเงิน/ส่งคืนของจริงไม่เคย
 * ถูกบันทึกไว้ที่ไหน หลังรันต้องปิดยอดวันแรกแล้วนับเงินจริง ให้ variance
 * adjustment ปรับเข้าที่
 */
class BackfillThbCash extends Command
{
    protected $signature = 'thb:backfill
                            {--dry-run : แสดงยอดที่จะได้ต่อเคาน์เตอร์ แต่ไม่เขียนอะไร}
                            {--force : เขียนทับแม้จะมี movement เงินบาทอยู่แล้ว}';

    protected $description = 'สร้าง movement เงินบาทย้อนหลังจาก working_days และรายการซื้อขายที่มีอยู่';

    public function handle(ThbCashService $thb): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Database: <comment>' . DB::connection()->getDatabaseName() . '</comment>');
        $this->newLine();

        $existing = StockMovement::query()->thb()->count();

        if ($existing > 0 && ! $this->option('force')) {
            $this->error("มี movement เงินบาทอยู่แล้ว {$existing} แถว — หยุดเพื่อกัน backfill ซ้ำ");
            $this->line('ถ้าต้องการรันจริงๆ ใช้ --force (ยอดจะถูกบวกทับของเดิม)');

            return self::FAILURE;
        }

        // ── รวบรวมรายการที่จะสร้าง ───────────────────────────────────
        $planned = [];

        // 1) เงินทุนที่พนักงานกรอกตอนเปิดวัน → thb_in ที่จุดเริ่มวันทำการนั้น
        $workingDays = WorkingDay::where('opening_thb_cash', '>', 0)
            ->orderBy('work_date')
            ->get();

        foreach ($workingDays as $wd) {
            $planned[] = [
                'counter_id' => (int) $wd->counter_id,
                'type' => StockMovement::THB_IN,
                'amount' => (float) $wd->opening_thb_cash,
                'ref_type' => ThbCashService::REF_TOPUP,
                'ref_id' => (int) $wd->id,
                'at' => $thb->dayStart($wd->work_date),
                'note' => 'ยกยอดเงินทุนเดิมก่อนเริ่มบันทึกเงินบาทเป็นสต็อก',
            ];
        }

        // 2) บิลซื้อขายที่ไม่ได้ยกเลิก
        //
        // บิลที่ยกเลิกแล้วถูกข้ามทั้งตัวตั้งและตัวกลับรายการ — ผลสุทธิเท่ากัน
        // แต่ไม่ต้องเขียน logic reversal ย้อนหลัง
        //
        // BUYING: detail.total = บาท / SELLING: detail.amount = บาท (สลับกัน)
        $transactions = TransactionMaster::where('flag_cancel', 'N')
            ->with('details')
            ->orderBy('trns_datetime')
            ->get();

        foreach ($transactions as $trns) {
            $isBuy = $trns->trns_type === 'BUYING';
            $baht = (float) $trns->details->sum($isBuy ? 'total' : 'amount');

            if ($baht <= 0) {
                continue;
            }

            $planned[] = [
                'counter_id' => (int) $trns->counter_id,
                'type' => $isBuy ? StockMovement::THB_OUT : StockMovement::THB_IN,
                'amount' => $isBuy ? -$baht : $baht,
                'ref_type' => ThbCashService::REF_TRANSACTION,
                'ref_id' => (int) $trns->id,
                'at' => $trns->trns_datetime,
                'note' => 'ย้อนหลังจากบิล ' . $trns->trns_no,
            ];
        }

        if (empty($planned)) {
            $this->info('ไม่มีข้อมูลให้ backfill');

            return self::SUCCESS;
        }

        // ── สรุปให้ดูก่อน ──────────────────────────────────────────────
        $byCounter = [];
        foreach ($planned as $row) {
            $byCounter[$row['counter_id']] ??= ['in' => 0.0, 'out' => 0.0, 'rows' => 0];
            $byCounter[$row['counter_id']]['rows']++;
            $byCounter[$row['counter_id']][$row['amount'] >= 0 ? 'in' : 'out'] += $row['amount'];
        }
        ksort($byCounter);

        $this->table(
            ['counter_id', 'จำนวน movement', 'รับเข้า', 'จ่ายออก', 'คงเหลือ'],
            collect($byCounter)->map(fn ($v, $id) => [
                $id,
                $v['rows'],
                number_format($v['in'], 2),
                number_format($v['out'], 2),
                number_format($v['in'] + $v['out'], 2),
            ])->values()->all(),
        );

        $this->line('รวม <comment>' . count($planned) . '</comment> movement จาก '
            . $workingDays->count() . ' วันทำการ และ ' . $transactions->count() . ' บิล');

        if ($dryRun) {
            $this->newLine();
            $this->warn('--dry-run: ไม่ได้เขียนอะไรลงฐานข้อมูล');

            return self::SUCCESS;
        }

        if (! $this->confirm('เขียนลงฐานข้อมูล ' . DB::connection()->getDatabaseName() . ' เลยหรือไม่?', false)) {
            $this->line('ยกเลิก');

            return self::SUCCESS;
        }

        // ── เขียนจริง ─────────────────────────────────────────────────
        // เรียงตามเวลาก่อนเขียน เพื่อให้ยอดใน counter_stock เดินตามลำดับจริง
        usort($planned, fn ($a, $b) => $a['at'] <=> $b['at']);

        $userId = (int) (\App\Models\User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'superadmin']))
            ->value('id') ?? 0);

        $bar = $this->output->createProgressBar(count($planned));
        $bar->start();

        DB::transaction(function () use ($planned, $thb, $userId, $bar) {
            foreach ($planned as $row) {
                $thb->apply(
                    $row['counter_id'],
                    $row['type'],
                    $row['amount'],
                    $row['ref_type'],
                    $row['ref_id'],
                    $userId,
                    $row['note'],
                    $row['at'],
                );
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('เขียนเสร็จแล้ว — ยอดคงเหลือปัจจุบัน:');
        $this->table(
            ['counter_id', 'ยอดบาทคงเหลือ'],
            CounterStock::query()->thb()->orderBy('counter_id')->get()
                ->map(fn ($s) => [$s->counter_id, number_format((float) $s->quantity, 2)])
                ->all(),
        );

        $this->newLine();
        $this->warn('ยอดนี้ยังไม่ตรงกับเงินจริงในลิ้นชัก — ให้ปิดยอดวันแรกแล้วนับเงินจริง');
        $this->warn('ผลต่างจะถูกปรับเข้าระบบตอน admin อนุมัติยอดปิด');

        return self::SUCCESS;
    }
}
