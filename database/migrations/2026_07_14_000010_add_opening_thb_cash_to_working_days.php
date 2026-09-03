<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->decimal('opening_thb_cash', 15, 2)->default(0)->after('work_date')
                ->comment('เงินทุนหมุน THB ที่ staff รับจากส่วนกลางตอนเปิดวัน');
        });
    }

    public function down(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->dropColumn('opening_thb_cash');
        });
    }
};
