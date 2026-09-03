<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AutoJournalService::reverseEntry() has always written type='reversal' when
 * un-posting a cancelled transaction/transfer, but the enum only ever allowed
 * 'auto'/'manual' — so every reversal (e.g. InventoryService::cancelTransfer())
 * failed with a MySQL data-truncation error and rolled back the whole cancel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->enum('type', ['auto', 'manual', 'reversal'])->default('auto')->change();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->enum('type', ['auto', 'manual'])->default('auto')->change();
        });
    }
};
