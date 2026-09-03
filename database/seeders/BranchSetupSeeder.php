<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Database\Seeder;

/**
 * ผังสาขาสำหรับรอบทดสอบ — สีลมเป็นคลังกลาง (center warehouse)
 *
 * เขียนทับสาขาเดิม id 1-5 แทนการลบ เพราะ counter_stock / transactions /
 * users ผูกกับ branch_id อยู่ ลบแล้วข้อมูลที่คีย์ไว้หายยกชุด
 * รันซ้ำได้ (updateOrCreate ทั้งสาขาและเคาน์เตอร์)
 */
class BranchSetupSeeder extends Seeder
{
    /** สาขาละ 1 เคาน์เตอร์ [id ที่จะเขียนทับ (null = สร้างใหม่), code, ชื่อ, ชื่อสั้นใช้ตั้งชื่อเคาน์เตอร์, ประเภท, เมือง] */
    private const BRANCHES = [
        [1,    'SILOM',   'สีลม (Center Warehouse)', 'สีลม',    'hq',     'กรุงเทพฯ'],
        [2,    'OLDTOWN', 'Oldtown',                 'Oldtown', 'branch', 'ภูเก็ต'],
        [3,    'AIRPORT', 'Airport',                 'Airport', 'branch', 'กรุงเทพฯ'],
        [4,    'KATA',    'Kata',                    'Kata',    'branch', 'ภูเก็ต'],
        [5,    'KHAOLAK', 'Khaolak',                 'Khaolak', 'branch', 'พังงา'],
        [null, 'BOOTH1',  'บูธ 1',                    'บูธ 1',    'branch', 'กรุงเทพฯ'],
        [null, 'BOOTH2',  'บูธ 2',                    'บูธ 2',    'branch', 'กรุงเทพฯ'],
        [null, 'BOOTH3',  'บูธ 3',                    'บูธ 3',    'branch', 'กรุงเทพฯ'],
    ];

    public function run(): void
    {
        foreach (self::BRANCHES as [$id, $code, $name, $shortName, $type, $city]) {
            $attributes = [
                'branch_code' => $code,
                'branch_name' => $name,
                'type'        => $type,
                'city'        => $city,
                'is_active'   => true,
            ];

            // สาขาเดิมชี้ด้วย id เพื่อให้สต็อก/รายการที่ผูกไว้ยังอยู่กับที่เดิม
            $branch = $id
                ? Branch::updateOrCreate(['id' => $id], $attributes)
                : Branch::updateOrCreate(['branch_code' => $code], $attributes);

            $this->syncCounter($branch, $code, $shortName);
        }
    }

    /**
     * เคาน์เตอร์เดียวต่อสาขา และเป็นปลายทางรับของจากธนาคาร (กองกลาง) ด้วย
     * ทุกสาขาต้องมี ไม่งั้นหน้าซื้อจากธนาคารหาปลายทางไม่เจอ
     *
     * เคาน์เตอร์ตัวที่ 2 ที่เคยมีถูกปิด (is_active=false) ไม่ลบ เพราะ transactions
     * กับ stock_movements ยังอ้าง counter_id เดิมอยู่
     */
    private function syncCounter(Branch $branch, string $code, string $shortName): void
    {
        $existing = Counter::where('branch_id', $branch->id)->orderBy('id')->get();

        $attributes = [
            'counter_code' => "{$code}-C1",
            'counter_name' => "เคาน์เตอร์ 1 ({$shortName})",
            'branch_id'    => $branch->id,
            'is_active'    => true,
        ];

        if ($first = $existing->first()) {
            $first->update($attributes);
        } else {
            Counter::updateOrCreate(['counter_code' => $attributes['counter_code']], $attributes);
        }

        $existing->skip(1)->each(fn (Counter $extra) => $extra->update(['is_active' => false]));
    }
}
