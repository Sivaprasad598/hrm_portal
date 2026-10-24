<?php

use App\Services\Employee\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime stored day by day (App\Services\Payroll\OtDaily): the daily
 * attendance run writes each employee's overtime for the day as soon as the
 * day is counted, and writes it again if the attendance is corrected. The OT
 * bank is these days plus the ledger of what was used (ot_ledger).
 *
 * And the Payroll -> OT Hours page.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('ot_daily')) {
            Schema::create('ot_daily', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->date('date');
                $table->string('day_type', 10);          // weekday | weekend | holiday
                $table->dateTime('first_in')->nullable();
                $table->dateTime('last_out')->nullable();
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('ot_minutes');
                $table->decimal('rate', 4, 2)->default(1);
                $table->unsignedInteger('bank_minutes');  // ot x rate, within the bank limit
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'date']);
                $table->index(['company_id', 'date']);
            });
        }

        // Payroll -> OT Hours, for whoever runs payroll.
        $payroll = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'payroll')->first();

        if ($payroll && !DB::table('sidebar_menus')->where('route', 'ot-hours.index')->exists()) {
            $id = DB::table('sidebar_menus')->insertGetId([
                'company_id' => $payroll->company_id, 'parent_id' => $payroll->id, 'name' => 'OT Hours', 'slug' => 'ot-hours',
                'route' => 'ot-hours.index', 'level' => 1,
                'sort_order' => (int) DB::table('sidebar_menus')->where('parent_id', $payroll->id)->max('sort_order') + 1,
                'is_active' => 1, 'permission_key' => 'manage_payroll', 'permission_rule' => 'setting_all',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach (DB::table('role_menus')->where('menu_id', $payroll->id)->pluck('role_id') as $roleId) {
                DB::table('role_menus')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        RoleAccess::applyAll();
        \App\Models\User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        $ids = DB::table('sidebar_menus')->where('route', 'ot-hours.index')->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('ot_daily');
    }

};
