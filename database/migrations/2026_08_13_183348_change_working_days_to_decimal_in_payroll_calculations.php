<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * working_days was tinyint(3) unsigned — fine while it only ever held whole
 * calendar-day counts, but Reductions.php's payroll "Working Days" figure can
 * now carry a half-day (.5) fraction (Present + 0.5 × Half Day), which an
 * integer column would silently round/truncate on save. Widen it to match
 * days_present's existing decimal(4,1) unsigned type.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payroll_calculations MODIFY working_days DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payroll_calculations MODIFY working_days TINYINT UNSIGNED NOT NULL DEFAULT 0');
    }
};
