<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_id');
            $table->date('work_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedBigInteger('opened_by');
            $table->timestamp('opened_at')->useCurrent();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('counter_id')->references('id')->on('counters');
            $table->foreign('opened_by')->references('id')->on('users');
            $table->foreign('closed_by')->references('id')->on('users');

            $table->unique(['counter_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_days');
    }
};
