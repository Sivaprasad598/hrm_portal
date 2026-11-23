<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll -> Payroll Import (PayrollImportController): a pay period's payslips from a
 * spreadsheet, generated on confirmation.
 *
 *  - payroll_imports: who imported what for which period, in which format, whether
 *    attendance deductions applied, and the payslips it generated.
 *  - the menu entry, under Payroll after Payroll Run, for the roles that have Payroll Run.
 *
 * Safe to run more than once; down() removes both.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('payroll_imports')) {
            Schema::create('payroll_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('period', 7)->index();              // 2026-11: the pay cycle's month
                $table->string('period_label', 60)->nullable();    // 21 Oct – 20 Nov 2026
                $table->string('file_name', 191)->nullable();
                $table->string('format', 20)->nullable();          // simple | detailed
                $table->boolean('attendance')->default(true);      // attendance deductions applied
                $table->unsignedInteger('payslips')->default(0);
                $table->decimal('total_net', 14, 2)->default(0);
                $table->unsignedInteger('skipped')->default(0);
                $table->text('payslip_ids')->nullable();           // JSON
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $payroll = DB::table('sidebar_menus')->where('slug', 'payroll')->whereNull('parent_id')->first(['id', 'company_id']);

        if (!$payroll) {
            return;
        }

        $id = DB::table('sidebar_menus')->where('route', 'payroll-import.index')->value('id');

        if (!$id) {
            $run = DB::table('sidebar_menus')->where('route', 'payroll-run.index')->first(['sort_order']);
            $columns = Schema::getColumnListing('sidebar_menus');
            $row = ['company_id' => $payroll->company_id, 'parent_id' => $payroll->id, 'name' => 'Payroll Import', 'slug' => 'payroll-import',
                'route' => 'payroll-import.index', 'icon' => null, 'level' => 1, 'sort_order' => (int) ($run->sort_order ?? 2),
                // as Payroll Run: no module, shown to whoever may prepare payroll
                'is_active' => 1, 'module' => null, 'permission_key' => 'payroll_prepare', 'permission_rule' => 'setting_all',
                'created_at' => now(), 'updated_at' => now()];
            $id = DB::table('sidebar_menus')->insertGetId(array_intersect_key($row, array_flip($columns)));
        }

        if (Schema::hasTable('role_menus')) {
            $runMenu = DB::table('sidebar_menus')->where('route', 'payroll-run.index')->value('id');
            $roles = $runMenu ? DB::table('role_menus')->where('menu_id', $runMenu)->pluck('role_id')->all() : [];
            $roles = $roles ?: DB::table('roles')->where('name', 'admin')->pluck('id')->all();

            foreach ($roles as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $id)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_menus')) {
            $ids = DB::table('sidebar_menus')->where('route', 'payroll-import.index')->pluck('id');

            if (Schema::hasTable('role_menus')) {
                DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
            }

            DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
            $this->forgetMenus();
        }

        Schema::dropIfExists('payroll_imports');
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
