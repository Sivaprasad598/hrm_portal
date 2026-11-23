<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance Import keeps what each upload saved (the days, as P / HD1 / HD2 / A with their times
 * and what they were before), so an upload in "Recent uploads" can be opened and read later.
 * Safe to run more than once.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('attendance_imports') && !Schema::hasColumn('attendance_imports', 'changes')) {
            Schema::table('attendance_imports', function (Blueprint $table) {
                $table->longText('changes')->nullable()->after('replaced_rows');   // JSON: the days the upload saved
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_imports') && Schema::hasColumn('attendance_imports', 'changes')) {
            Schema::table('attendance_imports', function (Blueprint $table) {
                $table->dropColumn('changes');
            });
        }
    }

};
