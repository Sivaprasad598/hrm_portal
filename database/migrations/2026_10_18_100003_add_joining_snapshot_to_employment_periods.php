<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The joining checklist ticks and declaration details of an employment that
 * ended, kept with it when a rejoin starts the next one afresh.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employment_periods', function (Blueprint $table) {
            if (!Schema::hasColumn('employment_periods', 'joining_snapshot')) {
                $table->json('joining_snapshot')->nullable()->after('employee_ctc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employment_periods', fn (Blueprint $table) => $table->dropColumn('joining_snapshot'));
    }
};
