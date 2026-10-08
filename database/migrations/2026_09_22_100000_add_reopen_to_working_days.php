<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->unsignedBigInteger('reopened_by')->nullable()->after('closing_notes');
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->text('reopen_reason')->nullable()->after('reopened_at')
                ->comment('บันทึกการเปิดวันใหม่ ต่อท้ายบรรทัดละครั้ง — วันหนึ่งมีได้แถวเดียว');

            $table->foreign('reopened_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->dropForeign(['reopened_by']);
            $table->dropColumn(['reopened_by', 'reopened_at', 'reopen_reason']);
        });
    }
};
