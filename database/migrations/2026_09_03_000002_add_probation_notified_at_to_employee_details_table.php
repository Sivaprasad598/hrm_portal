<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_details', 'probation_notified_at')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->timestamp('probation_notified_at')->nullable()->after('probation_end_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_details', 'probation_notified_at')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->dropColumn('probation_notified_at');
            });
        }
    }
};
