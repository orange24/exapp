<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

/**
 * วันทำการของร้าน ซึ่งไม่ใช่วันตามปฏิทิน
 *
 * สาขาที่ปิดรอบตี 3 มีวันทำการที่ 11 กินเวลาตั้งแต่ตี 3 ของวันที่ 11 ไปจนถึง
 * ตี 3 ของวันที่ 12 รายการที่พนักงานทำตอนเที่ยงคืนครึ่งของวันที่ 12 ยังเป็น
 * ยอดของวันที่ 11 อยู่
 *
 * เวลาตัดรอบตั้งแยกได้ตามสาขา สาขาสนามบินปิดตี 3 สาขาในห้างปิดสี่ทุ่ม เวลา
 * เดียวกันสองสาขาจึงอยู่คนละวันทำการได้ และนั่นถูกต้อง
 *
 * ลำดับการหาค่าตัดรอบที่มีผล:
 *   1. ค่าที่ตราไว้บนแถว working_days ของวันนั้น — ประวัติต้องนิ่ง การเปลี่ยน
 *      ตั้งค่าวันนี้ต้องไม่ทำให้ยอดของวันที่ปิดไปแล้วขยับ
 *   2. ค่าของสาขา (branches.cut_off_time)
 *   3. ค่ากลางของระบบ (WORKING_CUT_OFF)
 *
 * ขอบของช่วงเป็น เปิดซ้าย-ปิดขวา: (dayStart, dayEnd] ตรงกับที่คิวรี่ทั่วระบบ
 * ใช้ ('>' กับ '<=') ห้ามเปลี่ยน ไม่งั้นตัวเลขรายงานย้อนหลังขยับ
 */
class WorkingDayClock
{
    public const SETTING_KEY = 'WORKING_CUT_OFF';

    public const DEFAULT_CUT_OFF = '03:00:00';

    private ?string $globalCutoff = null;

    /** @var array<int, ?string> branchId => ค่าที่ตั้งไว้ที่สาขา */
    private array $branchCutoff = [];

    /** @var array<int, ?int> counterId => branchId */
    private array $counterBranch = [];

    /** @var array<int, ?int>|null เคาน์เตอร์ทั้งหมด อ่านทีเดียว */
    private ?array $counterMap = null;

    /** @var array<string, ?string> "branchId|date" => ค่าที่ตราไว้ตอนเปิดวัน */
    private array $stamped = [];

    // ─────────────────────────────────────────────────────────────────
    // ค่าตัดรอบ
    // ─────────────────────────────────────────────────────────────────

    /**
     * เวลาตัดรอบที่มีผล ในรูป HH:MM:SS
     *
     * ส่ง $forDate มาด้วยเมื่อต้องการค่าที่มีผล "ตอนนั้น" ไม่ใช่ค่าที่ตั้งอยู่
     * วันนี้ — รายงานย้อนหลังต้องใช้แบบนั้น
     */
    public function cutoff(?int $branchId = null, string|Carbon|null $forDate = null): string
    {
        if ($branchId !== null && $forDate !== null) {
            $stamped = $this->stampedCutoff($branchId, Carbon::parse($forDate)->format('Y-m-d'));

            if ($stamped !== null) {
                return $stamped;
            }
        }

        if ($branchId !== null) {
            $own = $this->branchCutoff($branchId);

            if ($own !== null) {
                return $own;
            }
        }

        return $this->globalCutoff();
    }

    /** ค่ากลางของระบบ ใช้กับสาขาที่ไม่ได้ตั้งค่าเอง */
    public function globalCutoff(): string
    {
        return $this->globalCutoff ??= $this->normalise(
            (string) Setting::get(self::SETTING_KEY, self::DEFAULT_CUT_OFF)
        );
    }

    /** ลืมค่าที่จำไว้ — ใช้หลังผู้ใช้แก้ค่าตั้งค่าในคำขอเดียวกัน */
    public function forget(): void
    {
        $this->globalCutoff = null;
        $this->branchCutoff = [];
        $this->counterBranch = [];
        $this->counterMap = null;
        $this->stamped = [];
    }

    // ─────────────────────────────────────────────────────────────────
    // วันทำการ
    // ─────────────────────────────────────────────────────────────────

    /** วันทำการที่เวลาขณะนี้ตกอยู่ */
    public function currentDate(?int $branchId = null): string
    {
        return $this->dateAt(Carbon::now(), $branchId);
    }

    /**
     * วันทำการที่เวลาหนึ่งตกอยู่
     *
     * คิดด้วยค่าที่ตั้งอยู่ก่อน แล้วตรวจกับช่วงจริงของวันข้างเคียง เพราะค่าที่
     * ตราไว้บนวันเก่าอาจต่างจากค่าที่ตั้งอยู่วันนี้ ถ้าการเปลี่ยนค่าทำให้เกิด
     * ช่องว่างที่ไม่มีวันทำการใดครอบ ให้ถือวันทำการล่าสุดที่เริ่มไปแล้ว —
     * กะที่พนักงานยังนั่งอยู่ต้องไม่หายไปเพราะผู้จัดการกดเปลี่ยนตั้งค่า
     */
    public function dateAt(string|Carbon $at, ?int $branchId = null): string
    {
        $at = $at instanceof Carbon ? $at->copy() : Carbon::parse($at);

        $guess = $this->arithmeticDateAt($at, $branchId);

        if ($branchId === null) {
            return $guess;
        }

        $best = null;

        foreach ([$guess, $this->shift($guess, 1), $this->shift($guess, -1)] as $candidate) {
            $start = $this->dayStart($candidate, $branchId);

            if ($start->gte($at)) {
                continue;
            }

            if ($this->dayEnd($candidate, $branchId)->gte($at)) {
                return $candidate;
            }

            // เริ่มไปแล้วแต่จบไปแล้วด้วย — เก็บไว้เป็นตัวสำรองที่ใกล้ที่สุด
            if ($best === null || $start->gt($this->dayStart($best, $branchId))) {
                $best = $candidate;
            }
        }

        return $best ?? $guess;
    }

    /** ขอบซ้ายของวันทำการ — ไม่รวมตัวมันเอง */
    public function dayStart(string|Carbon $date, ?int $branchId = null): Carbon
    {
        $date = Carbon::parse($date)->format('Y-m-d');

        return Carbon::parse($date . ' ' . $this->cutoff($branchId, $date));
    }

    /** ขอบขวาของวันทำการ — รวมตัวมันเอง */
    public function dayEnd(string|Carbon $date, ?int $branchId = null): Carbon
    {
        return $this->dayStart($date, $branchId)->addDay();
    }

    /**
     * ช่วงเวลาของวันทำการในรูปข้อความ พร้อมใส่ลงคิวรี่ได้ตรงๆ
     *
     * @return array{0: string, 1: string}
     */
    public function window(string|Carbon $date, ?int $branchId = null): array
    {
        return [
            $this->dayStart($date, $branchId)->format('Y-m-d H:i:s'),
            $this->dayEnd($date, $branchId)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * ช่วงเวลาที่คลุมหลายวันทำการ — ตั้งแต่เปิดวันแรกจนปิดวันสุดท้าย
     *
     * @return array{0: string, 1: string}
     */
    public function range(string|Carbon $from, string|Carbon $to, ?int $branchId = null): array
    {
        return [
            $this->dayStart($from, $branchId)->format('Y-m-d H:i:s'),
            $this->dayEnd($to, $branchId)->format('Y-m-d H:i:s'),
        ];
    }

    /** ช่วงเวลาของวันทำการปัจจุบัน */
    public function currentWindow(?int $branchId = null): array
    {
        return $this->window($this->currentDate($branchId), $branchId);
    }

    // ─────────────────────────────────────────────────────────────────
    // มองจากเคาน์เตอร์ — จุดเรียกส่วนใหญ่รู้แค่เคาน์เตอร์
    // ─────────────────────────────────────────────────────────────────

    public function branchOfCounter(int $counterId): ?int
    {
        if (array_key_exists($counterId, $this->counterBranch)) {
            return $this->counterBranch[$counterId];
        }

        $branchId = DB::table('counters')->where('id', $counterId)->value('branch_id');

        return $this->counterBranch[$counterId] = $branchId === null ? null : (int) $branchId;
    }

    public function currentDateForCounter(int $counterId): string
    {
        return $this->currentDate($this->branchOfCounter($counterId));
    }

    public function windowForCounter(int $counterId, string|Carbon $date): array
    {
        return $this->window($date, $this->branchOfCounter($counterId));
    }

    public function currentWindowForCounter(int $counterId): array
    {
        return $this->currentWindow($this->branchOfCounter($counterId));
    }

    // ─────────────────────────────────────────────────────────────────
    // คิวรี่ที่คลุมหลายสาขา
    // ─────────────────────────────────────────────────────────────────

    /**
     * จำกัดคิวรี่ให้อยู่ในวันทำการหนึ่ง โดยแต่ละสาขาใช้ช่วงเวลาของตัวเอง
     *
     * รายงานรวมหลายสาขาไม่มีช่วงเวลาเดียวที่ถูกต้องสำหรับทุกแถว ถ้าสาขาหนึ่ง
     * ปิดตี 3 อีกสาขาปิดสี่ทุ่ม การใช้ช่วงเดียวจะทำให้สาขาหนึ่งนับรายการของ
     * วันถัดไปเข้ามาด้วย จึงต้องแยกเงื่อนไขตามสาขาแล้วเชื่อมด้วย OR
     *
     * สาขาที่ใช้เวลาตัดรอบเหมือนกันถูกรวมเป็นเงื่อนไขเดียว ระบบที่ยังใช้ค่า
     * เดียวทั้งหมดจึงได้คิวรี่หน้าตาเหมือนเดิมเป๊ะ
     *
     * @param  array<int>  $counterIds  ว่าง = ทุกเคาน์เตอร์
     */
    public function constrainToWorkingDay(
        EloquentBuilder|QueryBuilder $query,
        string $timeColumn,
        string $counterColumn,
        string|Carbon $date,
        array $counterIds = [],
    ): void {
        $this->constrain($query, $timeColumn, $counterColumn, $counterIds,
            fn (?int $branchId) => $this->window($date, $branchId));
    }

    /**
     * จำกัดคิวรี่ให้อยู่ในช่วงหลายวันทำการ โดยแต่ละสาขาใช้ช่วงเวลาของตัวเอง
     *
     * @param  array<int>  $counterIds  ว่าง = ทุกเคาน์เตอร์
     */
    public function constrainToWorkingDayRange(
        EloquentBuilder|QueryBuilder $query,
        string $timeColumn,
        string $counterColumn,
        string|Carbon $from,
        string|Carbon $to,
        array $counterIds = [],
    ): void {
        $this->constrain($query, $timeColumn, $counterColumn, $counterIds,
            fn (?int $branchId) => $this->range($from, $to, $branchId));
    }

    /** @param  array<int>  $counterIds */
    private function constrain(
        EloquentBuilder|QueryBuilder $query,
        string $timeColumn,
        string $counterColumn,
        array $counterIds,
        \Closure $windowFor,
    ): void {
        $groups = $this->groupCountersByWindow($counterIds, $windowFor);

        // ทุกสาขาใช้ช่วงเดียวกัน — ไม่ต้องแยกเงื่อนไข ปล่อยให้คิวรี่เรียบเหมือนเดิม
        if (count($groups) <= 1) {
            [$start, $end] = $groups === [] ? $windowFor(null) : array_values($groups)[0]['window'];

            $query->where($timeColumn, '>', $start)->where($timeColumn, '<=', $end);

            if ($counterIds !== []) {
                $query->whereIn($counterColumn, $counterIds);
            }

            return;
        }

        $query->where(function ($q) use ($groups, $timeColumn, $counterColumn) {
            foreach ($groups as $group) {
                [$start, $end] = $group['window'];

                $q->orWhere(function ($inner) use ($group, $start, $end, $timeColumn, $counterColumn) {
                    $inner->whereIn($counterColumn, $group['counters'])
                        ->where($timeColumn, '>', $start)
                        ->where($timeColumn, '<=', $end);
                });
            }
        });
    }

    /**
     * จับกลุ่มเคาน์เตอร์ตามช่วงเวลาที่ได้ เพื่อให้สาขาที่ตัดรอบเหมือนกันรวมกัน
     *
     * @param  array<int>  $counterIds
     * @return array<string, array{window: array{0: string, 1: string}, counters: array<int>}>
     */
    private function groupCountersByWindow(array $counterIds, \Closure $windowFor): array
    {
        $wanted = $counterIds === [] ? null : array_flip($counterIds);
        $groups = [];

        foreach ($this->counterMap() as $counterId => $branchId) {
            if ($wanted !== null && ! isset($wanted[$counterId])) {
                continue;
            }

            $window = $windowFor($branchId);
            $key = $window[0] . '|' . $window[1];

            $groups[$key]['window'] = $window;
            $groups[$key]['counters'][] = $counterId;
        }

        return $groups;
    }

    /**
     * เคาน์เตอร์ทั้งหมดกับสาขาที่สังกัด อ่านครั้งเดียวต่อคำขอ
     *
     * รายงานหนึ่งหน้าเรียกตัวจำกัดคิวรี่หลายรอบ ถ้าอ่านตารางใหม่ทุกรอบก็เป็น
     * คิวรี่ทิ้งเปล่าหลายนัดโดยไม่ได้อะไรเพิ่ม จำนวนเคาน์เตอร์ทั้งระบบมีไม่กี่สิบ
     *
     * @return array<int, ?int> counterId => branchId
     */
    private function counterMap(): array
    {
        if ($this->counterMap !== null) {
            return $this->counterMap;
        }

        $map = [];

        foreach (DB::table('counters')->get(['id', 'branch_id']) as $row) {
            $map[(int) $row->id] = $row->branch_id === null ? null : (int) $row->branch_id;
        }

        // เผื่อไว้ให้ branchOfCounter ไม่ต้องยิงคิวรี่อีกรอบ
        $this->counterBranch += $map;

        return $this->counterMap = $map;
    }

    // ─────────────────────────────────────────────────────────────────
    // ภายใน
    // ─────────────────────────────────────────────────────────────────

    /** วันทำการตามเลขคณิตล้วน ใช้ค่าที่ตั้งอยู่ ไม่ดูค่าที่ตราไว้ */
    private function arithmeticDateAt(Carbon $at, ?int $branchId): string
    {
        $cut = Carbon::parse($at->format('Y-m-d') . ' ' . $this->cutoff($branchId));

        // ก่อนถึงเวลาตัดรอบยังนับเป็นวันทำการของเมื่อวาน
        return $at->gt($cut)
            ? $at->format('Y-m-d')
            : $at->copy()->subDay()->format('Y-m-d');
    }

    private function shift(string $date, int $days): string
    {
        return Carbon::parse($date)->addDays($days)->format('Y-m-d');
    }

    private function branchCutoff(int $branchId): ?string
    {
        if (array_key_exists($branchId, $this->branchCutoff)) {
            return $this->branchCutoff[$branchId];
        }

        $raw = DB::table('branches')->where('id', $branchId)->value('cut_off_time');

        return $this->branchCutoff[$branchId] = $this->normaliseOrNull($raw);
    }

    private function stampedCutoff(int $branchId, string $date): ?string
    {
        $key = $branchId . '|' . $date;

        if (array_key_exists($key, $this->stamped)) {
            return $this->stamped[$key];
        }

        $raw = DB::table('working_days')
            ->join('counters', 'counters.id', '=', 'working_days.counter_id')
            ->where('counters.branch_id', $branchId)
            ->whereDate('working_days.work_date', $date)
            ->whereNotNull('working_days.cut_off_time')
            ->value('working_days.cut_off_time');

        return $this->stamped[$key] = $this->normaliseOrNull($raw);
    }

    private function normaliseOrNull(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return $this->normalise((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ค่าในฐานข้อมูลเคยถูกบันทึกทั้งแบบ 03:00 และ 03:00:00 — รับทั้งสองแบบ
     * ถ้าเจอค่าที่อ่านไม่ออกให้ถอยไปใช้ค่าปริยาย ดีกว่าทำให้ทุกหน้าล่ม
     */
    private function normalise(string $raw): string
    {
        try {
            return Carbon::createFromTimeString($raw)->format('H:i:s');
        } catch (\Throwable) {
            return self::DEFAULT_CUT_OFF;
        }
    }
}
