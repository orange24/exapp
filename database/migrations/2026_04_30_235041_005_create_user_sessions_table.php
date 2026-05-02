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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('session_id', 100)->unique();   // Laravel session ID
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 30)->nullable();  // desktop, mobile, tablet
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('country', 100)->nullable();     // from GeoIP if available
            $table->string('city', 100)->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->boolean('is_terminated')->default(false);
            $table->foreignId('terminated_by')->nullable()->constrained('users');
            $table->timestamp('terminated_at')->nullable();
            $table->string('terminated_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_terminated']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
