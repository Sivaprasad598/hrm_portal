<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Finance (GST invoices) and the clients they are raised to, for HR, Admin, CEO and CFO as well as the
 * Super Admin (asked for 2026-11-23, "for now").
 *
 * The invoice pages already let these roles in (GstInvoice::canUse), but the Finance menu and the
 * Clients page go by the standard permissions, which only the Super Admin held: so the menu never
 * showed and Clients refused them. They get, on their role and on each person holding it:
 *   view / add / edit invoices, view / add / edit clients and client contacts.
 * Deleting stays with the Super Admin; Finance Settings is unchanged. The Clients menu entry is
 * given to HR too (the others had it).
 *
 * Safe to run more than once. down() sets these back to "none" for these roles and their people
 * (which is what they were before).
 */
return new class extends Migration {

    private const ROLES = ['hr', 'manager_admin', 'ceo', 'cfo'];

    private const PERMISSIONS = [
        'view_invoices', 'add_invoices', 'edit_invoices',
        'view_clients', 'add_clients', 'edit_clients',
        'view_client_contacts', 'add_client_contacts', 'edit_client_contacts',
    ];

    public function up(): void
    {
        $this->set('all');

        if (Schema::hasTable('sidebar_menus') && Schema::hasTable('role_menus')) {
            $clients = DB::table('sidebar_menus')->where('route', 'clients.index')->value('id');

            foreach ($clients ? DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') : [] as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $clients)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $clients, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        $this->set('none');

        if (Schema::hasTable('sidebar_menus') && Schema::hasTable('role_menus')) {
            $clients = DB::table('sidebar_menus')->where('route', 'clients.index')->value('id');

            if ($clients) {
                DB::table('role_menus')->where('menu_id', $clients)
                    ->whereIn('role_id', DB::table('roles')->where('name', 'hr')->select('id'))->delete();
            }
        }

        $this->forgetMenus();
    }

    /** These permissions at one level, on the roles and on everybody holding them. */
    private function set(string $level): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $type = DB::table('permission_types')->where('name', $level)->value('id');
        $permissions = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');
        $roles = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');

        if (!$type || $permissions->isEmpty() || $roles->isEmpty()) {
            return;
        }

        $people = DB::table('role_user')->whereIn('role_id', $roles)->pluck('user_id')->unique();
        // the Super Admin keeps theirs whatever happens here
        $people = $people->diff(DB::table('role_user')->whereIn('role_id', DB::table('roles')->where('name', 'admin')->select('id'))->pluck('user_id'));

        foreach ($permissions as $permissionId) {
            foreach ($roles as $roleId) {
                DB::table('permission_role')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], ['permission_type_id' => $type]);
            }

            foreach ($people as $userId) {
                DB::table('user_permissions')->updateOrInsert(['permission_id' => $permissionId, 'user_id' => $userId],
                    ['permission_type_id' => $type, 'updated_at' => now()]);
            }
        }

        // the sidebar keeps each person's permissions in the cache (sidebar_user_perms): read them again
        foreach ($people as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
        }
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
