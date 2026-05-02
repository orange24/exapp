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
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('doc_type', 50);          // passport, national_id, visa, other
            $table->string('file_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'doc_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
