<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ทุกสาขาต้องมีบริษัท — รวมสาขาที่มีอยู่ก่อนหน้านี้ด้วย
 *
 * ข้อมูลบริษัทเดิมอยู่บนแถวสาขา (company_name/tax_id/license_no) ย้ายขึ้นมา
 * สร้างเป็นบริษัทตั้งต้นหนึ่งราย แล้วผูกทุกสาขาเข้ากับบริษัทนั้น ถ้าไม่ย้าย
 * ให้ตอนนี้ ระบบที่รันอยู่จะมีสาขาที่ไม่สังกัดบริษัทไหนเลย แล้วทุกหน้าที่
 * scope ด้วยบริษัทจะมองไม่เห็นข้อมูลเดิมทั้งหมด
 *
 * company_id เป็น nullable เพราะ MySQL รุ่นเก่าของเซิร์ฟเวอร์นี้เติมคอลัมน์
 * NOT NULL ที่มี foreign key ลงตารางที่มีข้อมูลอยู่แล้วไม่ได้ในขั้นตอนเดียว
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('branches', 'company_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')
                    ->constrained('companies')->nullOnDelete();
            });
        }

        $this->backfill();
    }

    /**
     * สร้างบริษัทตั้งต้นจากข้อมูลที่ติดอยู่กับสาขา แล้วผูกสาขาทั้งหมดเข้าไป
     *
     * เอาข้อมูลบริษัทจากสาขาแรกที่กรอกไว้ ถ้าไม่มีสาขาไหนกรอกเลยก็ตั้งชื่อ
     * กลาง ๆ ไว้ให้ผู้ดูแลมาแก้ทีหลัง ดีกว่าปล่อยให้ไม่มีบริษัทแล้วข้อมูลหาย
     */
    private function backfill(): void
    {
        if (DB::table('branches')->whereNull('company_id')->count() === 0) {
            return;
        }

        $existing = DB::table('companies')->orderBy('id')->first();

        if ($existing !== null) {
            DB::table('branches')->whereNull('company_id')->update(['company_id' => $existing->id]);

            return;
        }

        $source = DB::table('branches')->whereNotNull('company_name')
            ->where('company_name', '!=', '')->first();

        $companyId = DB::table('companies')->insertGetId([
            'company_code' => 'MAIN',
            'company_name' => $source->company_name ?? 'บริษัทหลัก',
            'tax_id' => $source->tax_id ?? null,
            'license_no' => $source->license_no ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('branches')->whereNull('company_id')->update(['company_id' => $companyId]);
    }

    public function down(): void
    {
        if ($this->hasColumn('branches', 'company_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }

    /** Schema::hasColumn() พังบน MySQL รุ่นเก่าของเซิร์ฟเวอร์นี้ — sqlite ตอนเทสใช้ปกติได้ */
    private function hasColumn(string $table, string $column): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return ! empty(DB::select("SHOW COLUMNS FROM {$table} LIKE '{$column}'"));
        }

        return Schema::hasColumn($table, $column);
    }
};
