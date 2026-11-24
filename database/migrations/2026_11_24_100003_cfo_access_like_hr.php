<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CFO gets every page HR has (asked for 2026-11-24): HR (employees, attendance, shifts, exits,
 * letters, imports), Leave Management, Payroll, Biometric, Assets, Recruitment and HR's settings pages.
 *
 *  - every menu entry the HR role has is given to the CFO role (with the headings above them);
 *  - every permission HR holds, the CFO holds at least at HR's level (a higher one the CFO already
 *    has is kept, e.g. Finance);
 *  - RoleAccess now lists the CFO beside HR, Admin and CEO, so a CFO role made afresh (after
 *    Data Clean) gets the same; the pages that check the role by name (Attendance Import,
 *    Biometric Settings) let the CFO in too.
 * Not changed: who approves leave and attendance corrections at the HR step, and who gets HR's
 * alert mails - that stays with HR.
 *
 * Safe to run more than once. down() takes back the menus and permissions the CFO did not have
 * before (a note of them is kept in storage/app/migration_cfo_access_like_hr.json).
 */
return new class extends Migration {

    private const NOTE = 'migration_cfo_access_like_hr';

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_role') || !Schema::hasTable('role_menus')) {
            return;
        }

        $levels = DB::table('permission_types')->pluck('id', 'name');      // all, added, owned, both, none
        $rank = fn ($typeId) => match ((int) $typeId) {
            (int) ($levels['all'] ?? 4) => 4,
            (int) ($levels['both'] ?? 3) => 3,
            (int) ($levels['added'] ?? 1), (int) ($levels['owned'] ?? 2) => 2,
            default => 0,
        };

        $added = ['menus' => [], 'permissions' => []];

        foreach (DB::table('roles')->where('name', 'cfo')->get(['id', 'company_id']) as $cfo) {
            $hr = DB::table('roles')->where('name', 'hr')
                ->when($cfo->company_id, fn ($q) => $q->where('company_id', $cfo->company_id))->value('id');

            if (!$hr) {
                continue;
            }

            // HR's permissions, at least at HR's level
            $mine = DB::table('permission_role')->where('role_id', $cfo->id)->pluck('permission_type_id', 'permission_id');

            foreach (DB::table('permission_role')->where('role_id', $hr)->get(['permission_id', 'permission_type_id']) as $p) {
                if ($rank($p->permission_type_id) > $rank($mine[$p->permission_id] ?? 0)) {
                    $added['permissions'][] = [$cfo->id, $p->permission_id, $mine[$p->permission_id] ?? null];
                    DB::table('permission_role')->updateOrInsert(['role_id' => $cfo->id, 'permission_id' => $p->permission_id],
                        ['permission_type_id' => $p->permission_type_id]);
                }
            }

            // HR's menu entries
            foreach (DB::table('role_menus')->where('role_id', $hr)->pluck('menu_id') as $menuId) {
                if (!DB::table('role_menus')->where('role_id', $cfo->id)->where('menu_id', $menuId)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $cfo->id, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                    $added['menus'][] = [$cfo->id, $menuId];
                }
            }

            // the standard HR / Admin / CEO / CFO access (RoleAccess), which also refreshes each CFO's
            // own permissions and sidebar
            if (class_exists(\App\Services\Employee\RoleAccess::class)) {
                \App\Services\Employee\RoleAccess::apply((int) $cfo->id);
            }

            foreach (DB::table('role_user')->where('role_id', $cfo->id)->pluck('user_id') as $userId) {
                cache()->forget('sidebar_user_perms_' . $userId);
                cache()->forget('sidebar_menus_' . $userId);
            }
        }

        // what this run gave, kept in storage/app so down() can take exactly that back
        // (a second run adds nothing new, so the note is not changed by it)
        if ($added['menus'] || $added['permissions']) {
            $old = json_decode((string) @file_get_contents(storage_path('app/' . self::NOTE . '.json')), true) ?: ['menus' => [], 'permissions' => []];
            @file_put_contents(storage_path('app/' . self::NOTE . '.json'), json_encode([
                'menus' => array_merge($old['menus'], $added['menus']),
                'permissions' => array_merge($old['permissions'], $added['permissions']),
            ]));
        }

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

    public function down(): void
    {
        $note = json_decode((string) @file_get_contents(storage_path('app/' . self::NOTE . '.json')), true);

        if (!$note) {
            return;
        }

        foreach ($note['menus'] ?? [] as [$roleId, $menuId]) {
            DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->delete();
        }

        foreach (array_reverse($note['permissions'] ?? []) as [$roleId, $permissionId, $before]) {
            $before === null
                ? DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete()
                : DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->update(['permission_type_id' => $before]);
        }

        @unlink(storage_path('app/' . self::NOTE . '.json'));

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
