<?php

use App\Services\Employee\EmployeeDefaults;
use App\Services\Leaves\LeaveTypeScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data the code now keeps right by itself, put right once for a database that
 * was filled before (safe to run on any database, any number of times):
 *
 *   - every leave type covers every department, designation and role: the
 *     Excel import used to create departments and designations without adding
 *     them, so nobody in them could apply for leave;
 *   - the sheet's "Murali Nagar" branch is Visakhapatnam;
 *   - every designation with no access role gets the one its name suggests.
 */
return new class extends Migration {

    public function up(): void
    {
        // Murali Nagar is Visakhapatnam: rename it, or merge it into an existing Visakhapatnam.
        $murali = DB::table('branches')->whereRaw("REPLACE(LOWER(branch_name), ' ', '') = 'muralinagar'")->get(['id']);
        $vizag = DB::table('branches')->whereRaw('LOWER(branch_name) = ?', ['visakhapatnam'])->value('id');

        foreach ($murali as $branch) {
            if ($vizag) {
                DB::table('employee_details')->where('branch_id', $branch->id)->update(['branch_id' => $vizag]);
                DB::table('branches')->where('id', $branch->id)->delete();
            } else {
                DB::table('branches')->where('id', $branch->id)->update(['branch_name' => 'Visakhapatnam', 'updated_at' => now()]);
                $vizag = $branch->id;
            }
        }

        // Designations without a role: the one their name suggests.
        if (Schema::hasColumn('designations', 'role_id')) {
            foreach (DB::table('designations')->whereNull('role_id')->get(['id', 'name']) as $designation) {
                $roleId = EmployeeDefaults::guessRoleId((string) $designation->name);

                if ($roleId) {
                    DB::table('designations')->where('id', $designation->id)->update(['role_id' => $roleId]);
                }
            }
        }

        // Leave types cover every department, designation and role (Client aside).
        LeaveTypeScope::include('department', DB::table('teams')->pluck('id')->all());
        LeaveTypeScope::include('designation', DB::table('designations')->pluck('id')->all());
        LeaveTypeScope::include('role', DB::table('roles')->where('name', '<>', 'client')->pluck('id')->all());
    }

    public function down(): void
    {
        // Data only: nothing to undo.
    }

};
