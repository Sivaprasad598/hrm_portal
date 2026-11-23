<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR -> Attendance Import (AttendanceImportController): a pay period's attendance
 * from a spreadsheet until payroll for it is generated.
 *
 *  - attendance_imports: who uploaded what for which period, and the attendance
 *    rows each upload replaced (kept, so a correction can be traced or undone).
 *  - the menu entry, under HR next to Employee Import, for the admin and HR roles.
 *
 * Safe to run more than once; down() removes both.
 */
return new class extends Migration {

    private const ROLES = ['admin', 'hr'];

    public function up(): void
    {
        if (!Schema::hasTable('attendance_imports')) {
            Schema::create('attendance_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('period', 7)->index();            // 2026-11: the pay cycle's month
                $table->string('period_label', 60)->nullable();  // 21 Oct – 20 Nov 2026
                $table->string('file_name', 191)->nullable();
                $table->unsignedInteger('days_created')->default(0);
                $table->unsignedInteger('days_replaced')->default(0);
                $table->unsignedInteger('days_skipped')->default(0);
                $table->longText('replaced_rows')->nullable();   // JSON: the attendance rows taken out
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $hr = DB::table('sidebar_menus')->where('slug', 'hr')->whereNull('parent_id')->first(['id', 'company_id']);

        if (!$hr) {
            return;
        }

        $id = DB::table('sidebar_menus')->where('route', 'attendance-import.index')->value('id');

        if (!$id) {
            $after = (int) DB::table('sidebar_menus')->where('route', 'employee-import.index')->value('sort_order');
            $columns = Schema::getColumnListing('sidebar_menus');
            $row = ['company_id' => $hr->company_id, 'parent_id' => $hr->id, 'name' => 'Attendance Import', 'slug' => 'attendance-import',
                'route' => 'attendance-import.index', 'icon' => null, 'level' => 1,
                'sort_order' => $after ? $after + 1 : (int) DB::table('sidebar_menus')->where('parent_id', $hr->id)->max('sort_order') + 1,
                'is_active' => 1, 'module' => 'attendance', 'created_at' => now(), 'updated_at' => now()];
            $id = DB::table('sidebar_menus')->insertGetId(array_intersect_key($row, array_flip($columns)));
        }

        if (Schema::hasTable('role_menus')) {
            foreach (DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') as $roleId) {
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
            $ids = DB::table('sidebar_menus')->where('route', 'attendance-import.index')->pluck('id');

            if (Schema::hasTable('role_menus')) {
                DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
            }

            DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
            $this->forgetMenus();
        }

        Schema::dropIfExists('attendance_imports');
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
