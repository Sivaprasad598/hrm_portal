<?php

use App\Services\Employee\EmployeeDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each designation carries a default access role, and General Shift (Monday to
 * Saturday) is every employee's default shift.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasColumn('designations', 'role_id')) {
            Schema::table('designations', function (Blueprint $table) {
                $table->unsignedInteger('role_id')->nullable()->after('name');
            });
        }

        // The role each designation's name suggests.
        foreach (DB::table('designations')->whereNull('role_id')->get(['id', 'name']) as $designation) {
            $roleId = EmployeeDefaults::guessRoleId((string) $designation->name);

            if ($roleId) {
                DB::table('designations')->where('id', $designation->id)->update(['role_id' => $roleId]);
            }
        }

        // General Shift: Monday to Saturday, and the company default.
        $general = DB::table('employee_shifts')->where('shift_name', 'General Shift')->first(['id']);

        if ($general) {
            DB::table('employee_shifts')->where('id', $general->id)
                ->update(['office_open_days' => json_encode(['1', '2', '3', '4', '5', '6']), 'updated_at' => now()]);
            DB::table('attendance_settings')->update(['default_employee_shift' => $general->id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('designations', 'role_id')) {
            Schema::table('designations', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }
    }

};
