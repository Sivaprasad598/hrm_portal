<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds a real "LOP" (Loss of Pay) leave type, paid = 0, so it can be
     * selected in the Create Leave form's "Leave Type" dropdown the same
     * way Casual/Sick already are — needed for the attendance calendar's
     * "Assign Status" → LOP action to create a genuine leave record
     * instead of only a display override.
     */
    public function up(): void
    {
        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            $exists = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('type_name', 'LOP')
                ->exists();

            if ($exists) {
                continue;
            }

            // leaveTypeCondition() (app/Models/LeaveType.php) treats a NULL
            // department/designation/role as "excludes everyone", not "no
            // restriction" — so every department/designation/role for this
            // company has to be listed explicitly for LOP to be selectable
            // for any employee, mirroring how Casual/Sick are set up.
            $departmentIds = DB::table('teams')->where('company_id', $companyId)->pluck('id');
            $designationIds = DB::table('designations')->where('company_id', $companyId)->pluck('id');
            $roleIds = DB::table('roles')->where('company_id', $companyId)->pluck('id');

            $leaveTypeId = DB::table('leave_types')->insertGetId([
                'company_id' => $companyId,
                'type_name' => 'LOP',
                'color' => '#e53e3e',
                'no_of_leaves' => 0,
                'paid' => 0,
                'monthly_limit' => 0,
                'effective_type' => 'days',
                'unused_leave' => 'lapse',
                'encashed' => 0,
                'allowed_probation' => 1,
                'allowed_notice' => 1,
                'gender' => '["male","female","others"]',
                'marital_status' => '["single","married","widower","widow","separate","divorced","engaged"]',
                'department' => $departmentIds->toJson(),
                'designation' => $designationIds->toJson(),
                'role' => $roleIds->toJson(),
                // 'unlimited', not 'yearly' — LOP has no quota/cap by
                // design (no_of_leaves = 0 above isn't a real allowance),
                // and LeaveController::edit()'s Leave Type dropdown hides
                // any type with 0 remaining unless it's flagged unlimited.
                'leavetype' => 'unlimited',
                'over_utilization' => 'not_allowed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // LeaveTypeObserver::created() would normally backfill an
            // EmployeeLeaveQuota row per employee, but it explicitly skips
            // that while running in console/migration context — so it has
            // to be done here instead, or LOP would never appear in the
            // Create Leave form's Leave Type dropdown for any employee.
            $userIds = DB::table('employee_details')->pluck('user_id');

            foreach ($userIds as $userId) {
                Artisan::call('app:recalculate-leaves-quotas', [
                    'company' => $companyId,
                    'user' => $userId,
                    'leaveType' => $leaveTypeId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $leaveTypeIds = DB::table('leave_types')->where('type_name', 'LOP')->pluck('id');

        DB::table('employee_leave_quotas')->whereIn('leave_type_id', $leaveTypeIds)->delete();
        DB::table('leave_types')->whereIn('id', $leaveTypeIds)->delete();
    }
};
