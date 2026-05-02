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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['individual', 'corporate'])->default('individual');
            $table->enum('id_type', ['passport', 'national_id', 'other'])->default('passport');
            $table->string('id_number', 50)->nullable();
            $table->string('name_th', 200)->nullable();
            $table->string('name_en', 200)->nullable();
            $table->string('first_name', 100)->nullable();    // from OCR
            $table->string('last_name', 100)->nullable();     // from OCR
            $table->string('nationality', 100)->nullable();   // from OCR
            $table->date('date_of_birth')->nullable();        // from OCR
            $table->date('passport_expiry')->nullable();      // from OCR
            $table->string('passport_mrz', 255)->nullable();  // raw MRZ line from OCR
            $table->string('passport_photo', 255)->nullable(); // storage path
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['id_type', 'id_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
