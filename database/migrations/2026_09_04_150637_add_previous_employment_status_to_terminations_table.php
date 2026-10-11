<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('terminations', 'previous_employment_status')) {
            Schema::table('terminations', function (Blueprint $table) {
                // Snapshot of employee_details.employment_status right before this
                // termination was recorded, so "Undo" can restore it accurately
                // instead of guessing a default.
                $table->string('previous_employment_status', 20)->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        Schema::table('terminations', function (Blueprint $table) {
            $table->dropColumn('previous_employment_status');
        });
    }
};
