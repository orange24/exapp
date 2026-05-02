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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 20)->unique();
            $table->string('branch_name', 100);
            $table->enum('type', ['hq', 'branch'])->default('branch');
            $table->string('city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('tenant_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
