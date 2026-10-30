<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Employees see their own documents and upload to them (Profile > Documents):
 * view_documents at "owned", add_documents at "all" (it has only all / none;
 * EmployeeDocController::store keeps such a person to their own record).
 * On the Employee role and on everyone holding it whose level is still "none" -
 * HR and Admin, who have more, keep it.
 */
return new class extends Migration {

    const GRANTS = ['view_documents' => 'owned', 'add_documents' => 'all'];

    public function up(): void
    {
        $this->set(fn ($level) => $level, 'none');
    }

    public function down(): void
    {
        $this->set(fn () => 'none', null);
    }

    /** @param callable $to the level to give; $onlyFrom: only rows now at this level (null: those at the granted level) */
    protected function set(callable $to, ?string $onlyFrom): void
    {
        $roleId = DB::table('roles')->where('name', 'employee')->value('id');

        if (!$roleId) {
            return;
        }

        $types = DB::table('permission_types')->pluck('id', 'name');
        $userIds = DB::table('role_user')->where('role_id', $roleId)->pluck('user_id');

        foreach (self::GRANTS as $name => $level) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            if (!$permissionId) {
                continue;
            }

            $target = $types[$to($level)];
            $from = $types[$onlyFrom ?? $level];

            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], ['permission_type_id' => $target]);

            DB::table('user_permissions')
                ->where('permission_id', $permissionId)
                ->whereIn('user_id', $userIds)
                ->where('permission_type_id', $from)
                ->update(['permission_type_id' => $target]);
        }
    }
};
