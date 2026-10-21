<?php

use App\Services\Employee\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asset management: the company's assets (laptops, phones, SIMs, ID cards...),
 * who holds each one, and everything that happened to it.
 *
 *   asset_types        Laptop, Mobile, SIM ... (seeded, editable)
 *   assets             the register; status is available / assigned / repair / lost / disposed
 *   asset_assignments  one row per hand-over, closed by the return
 *   asset_histories    the timeline shown on an asset
 *
 * One permission, manage_assets (all / none, like manage_payroll), for the
 * Super Admin, HR, Admin and CEO roles; and an "Assets" sidebar group after
 * Payroll.
 */
return new class extends Migration {

    private const TYPES = ['Laptop', 'Desktop', 'Mobile', 'SIM Card', 'ID Card', 'Access Card', 'Vehicle', 'Furniture', 'Other'];
    private const ALL = 4;
    private const NONE = 5;

    public function up(): void
    {
        if (!Schema::hasTable('asset_types')) {
            Schema::create('asset_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('name', 100);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('asset_type_id')->nullable()->constrained('asset_types')->nullOnDelete();
                $table->string('name');
                $table->string('asset_code', 50);
                $table->string('serial_number')->nullable();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('purchase_cost', 12, 2)->nullable();
                $table->string('vendor')->nullable();
                $table->date('warranty_until')->nullable();
                $table->unsignedInteger('branch_id')->nullable()->index();
                $table->string('condition', 20)->default('good');
                $table->string('status', 20)->default('available')->index();
                $table->string('image')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'asset_code']);
            });
        }

        if (!Schema::hasTable('asset_assignments')) {
            Schema::create('asset_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->unsignedInteger('user_id')->index();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->date('assigned_on');
                $table->date('expected_return_on')->nullable();
                $table->string('condition_out', 20)->nullable();
                $table->text('assign_notes')->nullable();
                $table->unsignedInteger('assigned_by')->nullable();
                $table->date('returned_on')->nullable()->index();
                $table->string('condition_in', 20)->nullable();
                $table->text('return_notes')->nullable();
                $table->decimal('recovery_amount', 12, 2)->nullable();
                $table->unsignedInteger('returned_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asset_histories')) {
            Schema::create('asset_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('action', 30);
                $table->string('status_from', 20)->nullable();
                $table->string('status_to', 20)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('done_by')->nullable();
                $table->timestamps();
            });
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            if (!DB::table('asset_types')->where('company_id', $companyId)->exists()) {
                DB::table('asset_types')->insert(array_map(fn ($name) => [
                    'company_id' => $companyId, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                ], self::TYPES));
            }
        }

        $this->permission();
        $this->sidebar();

        RoleAccess::applyAll();
        \App\Models\User::forgetAllMenuCaches();
    }

    private function permission(): void
    {
        if (DB::table('permissions')->where('name', 'manage_assets')->exists()) {
            return;
        }

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'manage_assets',
            'display_name' => 'Manage Assets',
            'description' => 'Keep the asset register, hand assets to employees and take them back.',
            'module_id' => DB::table('modules')->where('module_name', 'employees')->value('id'),
            'is_custom' => 1,
            'allowed_permissions' => '{"all":4, "none":5}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $holders = array_merge(['admin'], RoleAccess::ROLES);

        foreach (DB::table('roles')->get(['id', 'name']) as $role) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'permission_type_id' => in_array($role->name, $holders, true) ? self::ALL : self::NONE,
            ]);
        }

        // user_permissions is what the application reads, written per user when
        // a role is assigned; fill it in now for everybody on file.
        $holding = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('r.name', $holders)->pluck('ru.user_id')->flip();

        $rows = DB::table('users')->pluck('id')->map(fn ($userId) => [
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'permission_type_id' => $holding->has($userId) ? self::ALL : self::NONE,
        ])->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    private function sidebar(): void
    {
        if (DB::table('sidebar_menus')->where('slug', 'assets')->whereNull('parent_id')->exists()) {
            return;
        }

        $payroll = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'payroll')->first();
        $position = $payroll ? $payroll->sort_order + 1 : 8;
        $companyId = $payroll->company_id ?? null;

        DB::table('sidebar_menus')->whereNull('parent_id')->where('sort_order', '>=', $position)->increment('sort_order');

        $parentId = DB::table('sidebar_menus')->insertGetId([
            'company_id' => $companyId, 'parent_id' => null, 'name' => 'Assets', 'slug' => 'assets', 'route' => null,
            'icon' => 'bi bi-laptop', 'level' => 0, 'sort_order' => $position, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $children = [
            ['Asset List', 'assets-index', 'assets.index'],
            ['Assigned Assets', 'assets-assigned', 'assets.assigned'],
            ['Asset Types', 'asset-types-index', 'asset-types.index'],
        ];

        foreach ($children as $i => [$name, $slug, $route]) {
            DB::table('sidebar_menus')->insert([
                'company_id' => $companyId, 'parent_id' => $parentId, 'name' => $name, 'slug' => $slug, 'route' => $route,
                'level' => 1, 'sort_order' => $i + 1, 'is_active' => 1,
                'permission_key' => 'manage_assets', 'permission_rule' => 'setting_all',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuIds = DB::table('sidebar_menus')->where('slug', 'assets')->whereNull('parent_id')->pluck('id');
        $childIds = DB::table('sidebar_menus')->whereIn('parent_id', $menuIds)->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $menuIds->merge($childIds))->delete();
        DB::table('sidebar_menus')->whereIn('id', $childIds->merge($menuIds))->delete();

        if ($permissionId = DB::table('permissions')->where('name', 'manage_assets')->value('id')) {
            DB::table('user_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('asset_histories');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_types');
    }

};
