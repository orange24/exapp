<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ราคาหนึ่งเคาน์เตอร์ หนึ่งชนิดธนบัตร หนึ่งวัน ต้องมีแถวเดียว
 *
 * 2026-10-12 บน production การกระจายชุดราคา #357 ถูกกดซ้อนกันสองครั้ง
 * ทั้งสองครั้งผ่านด่านตรวจสถานะไปพร้อมกันและต่างคนต่างมองไม่เห็นแถวที่อีกฝ่าย
 * ยังไม่ commit ผลคือราคาทุกตัวของ 3 เคาน์เตอร์มีสองแถว และจอแสดงราคาหน้าร้าน
 * โชว์ซ้อนกัน ตารางนี้มีแต่ index ธรรมดา ไม่มีอะไรห้ามไว้เลย
 *
 * ด่านที่ชั้นแอปถูกแก้แล้วที่ SuperrichRateService::distributeBatch() แต่ยัง
 * มีทางเขียนอีกสองทาง และอาจมีทางที่สี่ในอนาคต กุญแจที่ฐานข้อมูลคือด่าน
 * สุดท้ายที่ไม่ขึ้นกับว่าใครเขียน
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->removeDuplicates();

        if (! $this->hasIndex('uniq_counter_denom_date')) {
            Schema::table('counter_rates', function (Blueprint $table) {
                $table->unique(['counter_id', 'denomination_id', 'rate_date'], 'uniq_counter_denom_date');
            });
        }

        // index เดิมคลุมคอลัมน์ชุดเดียวกันเรียงเหมือนกัน กุญแจใหม่แทนได้หมด
        if ($this->hasIndex('idx_counter_denom_date')) {
            Schema::table('counter_rates', function (Blueprint $table) {
                $table->dropIndex('idx_counter_denom_date');
            });
        }
    }

    public function down(): void
    {
        if (! $this->hasIndex('idx_counter_denom_date')) {
            Schema::table('counter_rates', function (Blueprint $table) {
                $table->index(['counter_id', 'denomination_id', 'rate_date'], 'idx_counter_denom_date');
            });
        }

        if ($this->hasIndex('uniq_counter_denom_date')) {
            Schema::table('counter_rates', function (Blueprint $table) {
                $table->dropUnique('uniq_counter_denom_date');
            });
        }
    }

    /**
     * ล้างแถวซ้ำที่มีอยู่แล้ว ไม่งั้นสร้างกุญแจไม่ขึ้น
     *
     * เก็บแถวที่ถูกแก้ล่าสุด เพราะเป็นแถวที่ระบบใช้มาตลอด — การแก้ราคาด้วยมือ
     * หลังจากนั้นวิ่งไปลงแถวแรกที่คิวรี่เจอ ค่าที่พนักงานพิมพ์ล่าสุดจึงอยู่ที่นั่น
     * เสมอกันให้เอา id น้อยกว่า
     */
    private function removeDuplicates(): void
    {
        $groups = [];

        foreach (DB::table('counter_rates')->orderBy('id')->get() as $row) {
            $key = $row->counter_id . '|' . ($row->denomination_id ?? 'null')
                . '|' . substr((string) $row->rate_date, 0, 10);
            $groups[$key][] = $row;
        }

        $doomed = [];

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, fn ($a, $b) => [$b->updated_at, -$b->id] <=> [$a->updated_at, -$a->id]);

            foreach (array_slice($group, 1) as $extra) {
                $doomed[] = $extra->id;
            }
        }

        foreach (array_chunk($doomed, 200) as $chunk) {
            DB::table('counter_rates')->whereIn('id', $chunk)->delete();
        }
    }

    private function hasIndex(string $name): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return ! empty(array_filter(
                Schema::getIndexes('counter_rates'),
                fn ($i) => $i['name'] === $name,
            ));
        }

        return ! empty(DB::select("SHOW INDEX FROM counter_rates WHERE Key_name = ?", [$name]));
    }
};
