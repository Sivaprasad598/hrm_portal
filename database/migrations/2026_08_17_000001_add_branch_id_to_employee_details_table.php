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
        if (!Schema::hasColumn('employee_details', 'branch_id')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->unsignedInteger('branch_id')->nullable()->after('department_id');
                $table->foreign(['branch_id'])->references(['id'])->on('branches')->onUpdate('CASCADE')->onDelete('SET NULL');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employee_details', 'branch_id')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
