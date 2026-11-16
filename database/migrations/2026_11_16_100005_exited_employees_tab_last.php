<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR -> Exiting Employees: the tabs read Resignations, Termination, Exited
 * Employees - the people who have left, last. Tabs follow the menu order, so
 * Termination and Exited Employees swap places. Found by slug, so the same on
 * every server; down() swaps them back.
 */
return new class extends Migration {

    public function up(): void
    {
        $this->order(['resignation-management-index', 'employee-termination-index', 'employee-exit-index']);
    }

    public function down(): void
    {
        $this->order(['resignation-management-index', 'employee-exit-index', 'employee-termination-index']);
    }

    /** The given pages, in this order, on the sort places they hold between them. */
    private function order(array $slugs): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $rows = DB::table('sidebar_menus')->whereIn('slug', $slugs)->get(['id', 'slug', 'sort_order']);

        if ($rows->count() !== count($slugs)) {
            return;
        }

        $places = $rows->pluck('sort_order')->map(fn ($v) => (int) $v)->sort()->values();

        foreach ($slugs as $i => $slug) {
            DB::table('sidebar_menus')->where('slug', $slug)->update(['sort_order' => $places[$i], 'updated_at' => now()]);
        }

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
