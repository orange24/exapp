<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master: 1 row per month per branch
        Schema::create('bot_report_masters', function (Blueprint $table) {
            $table->id();
            $table->string('report_month', 7); // YYYY-MM
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('institution_code', 13)->default('');
            $table->string('license_no', 20)->default('');
            $table->string('company_name', 255)->default('');
            $table->string('branch_name', 255)->default('');
            $table->string('branch_address', 500)->default('');
            $table->enum('status', ['draft', 'confirmed', 'exported'])->default('draft');
            $table->unsignedBigInteger('generated_by');
            $table->timestamp('generated_at')->useCurrent();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('generated_by')->references('id')->on('users');
            $table->unique(['report_month', 'branch_id'], 'bot_report_month_branch');
        });

        // Detail: cloned transaction rows (editable)
        Schema::create('bot_report_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_report_id');
            $table->unsignedBigInteger('source_transaction_id')->nullable()->comment('original transactions_master.id');
            $table->unsignedBigInteger('source_detail_id')->nullable()->comment('original transactions_detail.id');
            $table->enum('trns_type', ['BUY', 'SELL']);
            $table->date('trns_date');
            $table->string('customer_type', 50)->default('ชาวต่างชาติ');
            $table->string('customer_name', 255)->default('');
            $table->string('id_type_code', 10)->default('324002');
            $table->string('id_number', 50)->default('');
            $table->string('nationality', 10)->default('');
            $table->string('purpose', 100)->default('เดินทาง/ท่องเที่ยว');
            $table->string('fx_point', 50)->default('สถานประกอบการ');
            $table->string('fx_channel', 20)->default('0753600001');
            $table->string('currency_code', 10);
            $table->decimal('exchange_rate', 15, 8)->default(0);
            $table->decimal('fx_amount', 15, 2)->default(0);
            $table->string('thb_point', 50)->default('สถานประกอบการ');
            $table->string('thb_channel', 20)->default('0753600001');
            $table->decimal('thb_amount', 15, 2)->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('bot_report_id')->references('id')->on('bot_report_masters')->cascadeOnDelete();
            $table->index(['bot_report_id', 'trns_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_report_transactions');
        Schema::dropIfExists('bot_report_masters');
    }
};
