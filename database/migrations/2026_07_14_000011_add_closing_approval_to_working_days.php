<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->longText('closing_items')->nullable()->after('note')
                ->comment('รายการสต็อกจริงที่ส่งมอบ (JSON: denomination_id, expected, actual, variance)');
            $table->enum('closing_status', ['pending', 'approved', 'rejected'])->nullable()->after('closing_items');
            $table->unsignedBigInteger('closing_approved_by')->nullable()->after('closing_status');
            $table->timestamp('closing_approved_at')->nullable()->after('closing_approved_by');
            $table->text('closing_notes')->nullable()->after('closing_approved_at')
                ->comment('เหตุผลกรณี reject หรือหมายเหตุอื่นๆ');

            $table->foreign('closing_approved_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->dropForeign(['closing_approved_by']);
            $table->dropColumn([
                'closing_items',
                'closing_status',
                'closing_approved_by',
                'closing_approved_at',
                'closing_notes',
            ]);
        });
    }
};
