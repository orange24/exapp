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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained('branches')->after('email');
            $table->foreignId('role_id')->nullable()->constrained('roles')->after('branch_id');
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->string('tenant_code', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['is_active', 'tenant_code']);
        });
    }
};
