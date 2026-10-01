<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('list_code', 20);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // running | success | failed | aborted_sanity_check
            $table->string('status', 30)->default('running');

            $table->date('source_as_of')->nullable();

            // amlo_public_scraper | amlo_aps_api
            $table->string('source_adapter', 40);

            $table->foreignId('forced_by')->nullable()->constrained('users');

            $table->integer('entries_before')->default(0);
            $table->integer('entries_parsed')->default(0);
            $table->integer('entries_added')->default(0);
            $table->integer('entries_removed')->default(0);
            $table->integer('entries_updated')->default(0);

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['list_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_sync_runs');
    }
};
