<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * My Work (project-tracker.my-work): each employee's open work, where they log hours, units done
 * and the next step in one go. Adds why an item was sent back (tracker_progress_logs.note) and the
 * menu My Space -> My Work for the roles that have My Space -> My Assets. Idempotent; down()
 * removes both.
 */
return new class extends Migration {

    private const ROUTE = 'project-tracker.my-work';

    public function up(): void
    {
        if (Schema::hasTable('tracker_progress_logs') && !Schema::hasColumn('tracker_progress_logs', 'note')) {
            Schema::table('tracker_progress_logs', function (Blueprint $table) {
                $table->text('note')->nullable()->after('completed');
            });
        }

        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $space = DB::table('sidebar_menus')->where('slug', 'my-space')->whereNull('parent_id')->first(['id', 'company_id']);
        if (!$space) {
            return;
        }

        $id = DB::table('sidebar_menus')->where('route', self::ROUTE)->value('id');
        if (!$id) {
            $columns = array_flip(Schema::getColumnListing('sidebar_menus'));
            $id = DB::table('sidebar_menus')->insertGetId(array_intersect_key([
                'company_id' => $space->company_id, 'parent_id' => $space->id, 'name' => 'My Work', 'slug' => 'my-work', 'route' => self::ROUTE,
                'level' => 1, 'sort_order' => 0, 'is_active' => 1, 'icon' => null, 'role_rule' => 'has_employee_record',
                'created_at' => now(), 'updated_at' => now(),
            ], $columns));
        }

        if (Schema::hasTable('role_menus')) {
            $assets = DB::table('sidebar_menus')->where('slug', 'my-assets')->value('id');
            foreach ($assets ? DB::table('role_menus')->where('menu_id', $assets)->pluck('role_id') : [] as $roleId) {
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
            $ids = DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id');
            if (Schema::hasTable('role_menus')) {
                DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
            }
            DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
            $this->forgetMenus();
        }

        if (Schema::hasTable('tracker_progress_logs') && Schema::hasColumn('tracker_progress_logs', 'note')) {
            Schema::table('tracker_progress_logs', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
