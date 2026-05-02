<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no', 30)->unique();   // G20260501-0001
            $table->date('entry_date');
            $table->string('description', 500)->nullable();
            $table->enum('type', ['auto', 'manual'])->default('auto');  // auto from transaction, or manual
            $table->string('source_type', 50)->nullable();   // transaction, adjustment, transfer
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->boolean('is_posted')->default(false);
            $table->timestamps();
            $table->index(['entry_date', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
