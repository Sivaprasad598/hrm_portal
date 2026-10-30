<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Payroll Dashboard stands on its own at the top of Payroll, instead of
 * being a tab inside Monthly Payroll (see PageGroups).
 */
return new class extends Migration {

    public function up(): void
    {
        $this->move(0);
    }

    public function down(): void
    {
        $this->move(7);
    }

    protected function move(int $order): void
    {
        DB::table('sidebar_menus')->where('slug', 'payroll-dashboard-index')->update(['sort_order' => $order]);

        foreach (DB::table('users')->pluck('id') as $userId) {
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

};
