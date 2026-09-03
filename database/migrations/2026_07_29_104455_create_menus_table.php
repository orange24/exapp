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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('Unique identifier, e.g., transaction.buy');
            $table->string('label_th', 255)->comment('ชื่อเมนูภาษาไทย');
            $table->string('label_en', 255)->comment('Menu label in English');
            $table->string('route', 255)->nullable()->comment('Laravel route name');
            $table->string('icon', 100)->nullable()->comment('Icon identifier');
            $table->foreignId('parent_id')->nullable()->constrained('menus')->onDelete('cascade');
            $table->integer('order')->default(0)->comment('Display order within parent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'order']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
