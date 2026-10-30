<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Breaks an employee marks from the mobile app (lunch, tea, other).
 *
 * Recorded only: worked hours still run from first punch in to last punch out
 * (a flexible shift is 9 hours including breaks), so a break changes nothing in
 * attendance or payroll; HR sees them in the day's details. attendance_breaks is
 * the company's break schedule, not this.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_break_logs')) {
            return;
        }

        Schema::create('attendance_break_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('attendance_id')->nullable()->index();
            $table->string('type', 20);                 // lunch | tea | other
            $table->string('note', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('start_latitude', 10, 8)->nullable();
            $table->decimal('start_longitude', 11, 8)->nullable();
            $table->decimal('end_latitude', 10, 8)->nullable();
            $table->decimal('end_longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_break_logs');
    }
};
