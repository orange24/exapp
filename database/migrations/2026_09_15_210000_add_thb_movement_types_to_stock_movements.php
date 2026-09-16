<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เงินบาทในลิ้นชักเคาน์เตอร์ถูกบันทึกลง stock_movements เหมือนสกุลเงินอื่น
 * แต่ใช้ currency_code='THB' + denomination_id=NULL — query ของ FC ทุกจุด
 * กรอง whereNotNull('denomination_id') อยู่แล้ว จึงไม่ปนกัน
 *
 * ใช้ type แยก (thb_in/thb_out) ไม่ reuse buy/sell เพราะ
 * InventoryService::reverseMovement() มีสาขาพิเศษสำหรับ 'buy' ที่เรียก
 * recalculateAvgCost(int $denominationId) — THB ที่ denomination เป็น null
 * จะ TypeError ทันทีถ้าเข้าไปทางนั้น
 *
 * ใช้ ->change() ไม่ใช่ raw ALTER ... MODIFY เพราะต้องทำงานได้ทั้ง MySQL
 * (production/uat) และ sqlite (test suite) — sqlite เก็บ enum เป็น varchar
 * พร้อม check constraint ถ้าไม่ขยาย constraint การ insert thb_in จะถูกปฏิเสธ
 * (มี precedent ที่ 2026_09_03_150000_add_reversal_to_journal_entries_type_enum)
 */
return new class extends Migration
{
    private const OLD = ['buy', 'sell', 'borrow', 'return', 'disbursement', 'adjustment', 'transfer_in', 'transfer_out'];
    private const NEW = ['buy', 'sell', 'borrow', 'return', 'disbursement', 'adjustment', 'transfer_in', 'transfer_out', 'thb_in', 'thb_out'];

    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', self::NEW)->change();
        });
    }

    public function down(): void
    {
        // แถว THB ต้องหายไปก่อน ไม่งั้น MySQL จะ truncate ค่าเป็นสตริงว่างเงียบๆ
        // และ sqlite จะ fail ตอนสร้าง check constraint ใหม่
        DB::table('stock_movements')
            ->whereIn('movement_type', ['thb_in', 'thb_out'])
            ->delete();

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', self::OLD)->change();
        });
    }
};
