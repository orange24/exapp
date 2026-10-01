<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_screenings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions_master');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('counter_id')->nullable()->constrained('counters');
            $table->foreignId('screened_by')->nullable()->constrained('users');
            $table->timestamp('screened_at');

            // snapshot ของสิ่งที่ตรวจ ณ ขณะนั้น
            // ต้องเก็บเพราะลูกค้าอาจแก้ชื่อทีหลังแล้วหลักฐานจะเพี้ยน
            $table->string('input_name')->nullable();
            $table->string('input_id_type', 30)->nullable();
            $table->string('input_id_number', 100)->nullable();
            $table->string('input_nationality', 100)->nullable();
            $table->string('input_dob', 50)->nullable();

            $table->foreignId('sync_run_id')->nullable()->constrained('sanction_sync_runs');

            // transaction | customer_create | rescan
            $table->string('trigger', 30);

            // clear | potential_match | confirmed_match
            $table->string('result', 30);

            $table->decimal('top_score', 5, 2)->default(0);

            // null | false_positive | true_match | escalated
            $table->string('decision', 30)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();

            $table->timestamps();

            $table->index(['result', 'decision']);
            $table->index(['customer_id', 'screened_at']);
            $table->index(['branch_id', 'screened_at']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_screenings');
    }
};
