<?php

namespace Tests\Feature\Rate;

use App\Models\CounterRate;
use App\Models\CurrencyDenomination;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ทุกคอลัมน์ที่เก็บเรต/ต้นทุนในระบบนี้เป็น DECIMAL(12,6) และหน้าตั้งราคาหลัก
 * แก้ไขที่ step="0.000001" — ส่วนอื่นต้องไม่บีบให้หยาบกว่านั้น
 *
 * สกุลที่ค่าต่ำมากคือตัวที่เจ็บ: VND ~0.00122 ถ้าเหลือ 4 ตำแหน่งจะกลายเป็น
 * 0.0012 ซึ่งคลาด 1.6% ส่วน USD ~33.42 ไม่รู้สึกอะไรเลย ปัญหาแบบนี้จึงรอด
 * สายตาไปได้นานจนกว่าจะมีคนแลกเงินดองจริง
 */
class RatePrecisionAuditTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $trader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // BankSaleManager::mount() ปฏิเสธทุก role ที่ไม่ใช่ trader
        $this->trader = User::create([
            'name' => 'Trader',
            'email' => 'rate-audit-trader@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'trader')->value('id'),
            'branch_id' => $this->branch->id,
            'managed_branch_ids' => [$this->branch->id],
            'is_active' => true,
        ]);
    }

    private function vndDenomination(): CurrencyDenomination
    {
        return CurrencyDenomination::firstOrCreate(
            ['currency_code' => 'VND', 'denom_label' => '500000-10000'],
            ['display_name' => 'VND 500000-10000', 'seq' => 90, 'is_active' => true]
        );
    }

    // ───────── กลุ่ม 1: ค่าที่คำนวณผิดจริง ─────────

    public function test_bank_sale_rate_prefill_keeps_six_decimals(): void
    {
        $denom = $this->vndDenomination();

        CounterRate::create([
            'counter_id' => $this->counter->id,
            'denomination_id' => $denom->id,
            'currency_code' => 'VND',
            'rate_date' => today(),
            'rate_buy' => 0.00122,
            'rate_sell' => 0.00135,
            'set_by' => $this->adminUser->id,
        ]);

        $component = Livewire::actingAs($this->trader)
            ->test(\App\Livewire\Rate\BankSaleManager::class)
            ->set('rowDenominationId', (string) $denom->id);

        // trader รับค่าตั้งต้นนี้ไปใช้ได้เลย ถ้ามันเพี้ยนตั้งแต่ต้น
        // รายการซื้อจากธนาคารจะถูกบันทึกด้วยเรทที่ผิด
        $this->assertSame('0.00122', $component->get('rowRate'));
    }

    // ───────── กลุ่ม 2: ช่องกรอกที่พิมพ์ 6 ตำแหน่งไม่ได้ ─────────

    public function test_every_rate_input_in_views_allows_six_decimals(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            foreach (file($file) as $i => $line) {
                if (! preg_match('/step="([^"]+)"/', $line, $m)) {
                    continue;
                }
                if (! preg_match('/rate|price/i', $line)) {
                    continue;
                }
                if ((float) $m[1] > 0.000001) {
                    $offenders[] = $this->relative($file) . ':' . ($i + 1) . ' step="' . $m[1] . '"';
                }
            }
        }

        $this->assertSame([], $offenders, "ช่องกรอกเรตต้องรับ 6 ทศนิยม:\n" . implode("\n", $offenders));
    }

    // ───────── กลุ่ม 3: การแสดงผลที่ตัดทศนิยม ─────────

    public function test_no_view_formats_a_rate_or_cost_below_six_decimals(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            foreach (file($file) as $i => $line) {
                // number_format(<อะไรที่เป็นเรต/ต้นทุน>, N) ที่ N < 6
                if (! preg_match('/number_format\s*\(([^,]+),\s*(\d)\s*\)/', $line, $m)) {
                    continue;
                }
                if (! preg_match('/rate|avg|unit_price/i', $m[1])) {
                    continue;
                }
                // ผลคูณคือยอดเงินบาท ไม่ใช่เรต — amount * rate ใช้ 2 ตำแหน่งถูกแล้ว
                if (str_contains($m[1], '*')) {
                    continue;
                }
                if ((int) $m[2] < 6) {
                    $offenders[] = $this->relative($file) . ':' . ($i + 1) . ' ' . trim($m[0]);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "ใช้ format_rate() แทน — มันแสดง 6 ตำแหน่งแล้วตัดศูนย์ท้ายให้เอง:\n" . implode("\n", $offenders)
        );
    }

    // ───────── กลุ่ม 4: การปัดเศษในโค้ด ─────────

    public function test_no_php_rounds_a_rate_below_six_decimals(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $file) {
            foreach (file($file) as $i => $line) {
                if (! preg_match('/round\s*\(([^;]*?),\s*(\d)\s*\)/', $line, $m)) {
                    continue;
                }
                // เฉพาะที่ปัด "เรต" ไม่ใช่ยอดเงินบาท
                if (! preg_match('/\$rate\b|rate_buy|rate_sell|unit_price|adj_rate/i', $m[1])) {
                    continue;
                }
                // ผลคูณคือยอดเงินบาท — amount * rate ใช้ 2 ตำแหน่งถูกแล้ว
                if (str_contains($m[1], '*')) {
                    continue;
                }
                if ((int) $m[2] < 6) {
                    $offenders[] = $this->relative($file) . ':' . ($i + 1) . ' ' . trim($m[0]);
                }
            }
        }

        $this->assertSame([], $offenders, "ปัดเรตต้องใช้ 6 ตำแหน่งให้ตรงกับคอลัมน์:\n" . implode("\n", $offenders));
    }

    // ───────── กลุ่ม 5: float -> string ที่พลิกเป็น scientific notation ─────────

    public function test_no_rate_is_cast_straight_from_float_to_string(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $file) {
            foreach (file($file) as $i => $line) {
                // (string) $อะไรก็ตามที่เป็นเรต — ต่ำกว่า 1e-5 จะได้ "-1.0E-5"
                // ต้องรองรับ nullsafe ด้วย: (string) $adj?->adj_rate_buy
                if (! preg_match('/\(string\)\s*(\$[^,;)\s]+)/', $line, $m)) {
                    continue;
                }
                if (! preg_match('/rate|adj|avg_cost|unit_price/i', $m[1])) {
                    continue;
                }
                $offenders[] = $this->relative($file) . ':' . ($i + 1) . ' ' . trim($m[0]);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "ใช้ rate_input_value() แทน — PHP พลิกเป็น scientific notation ใต้ 1e-5:\n" . implode("\n", $offenders)
        );
    }

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        return $this->filesUnder(resource_path('views'), '.blade.php');
    }

    /** @return array<int, string> */
    private function phpFiles(): array
    {
        return array_merge(
            $this->filesUnder(app_path('Services'), '.php'),
            $this->filesUnder(app_path('Livewire'), '.php'),
            $this->filesUnder(app_path('Http/Controllers'), '.php'),
        );
    }

    /** @return array<int, string> */
    private function filesUnder(string $dir, string $suffix): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), $suffix)) {
                $out[] = $f->getPathname();
            }
        }

        sort($out);

        return $out;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . '/', '', $path);
    }
}
