<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test sign-ins are marked (users.is_test_account) and kept out of everybody's view of the
 * company (asked for 2026-11-24): no list, picker, register, count, dashboard, birthday or nightly
 * job includes them. Test accounts see one another (so approvals, payroll and teams can be tried
 * end to end), and the Super Admin sees them only in HR -> Employees, tagged TEST
 * (App\Services\Access\SystemAccounts::hidden()).
 *
 * Marked here: the seven test sign-ins. The Super Admin can mark or unmark anyone later.
 * Safe to run more than once; down() drops the flag (they show as staff again).
 */
return new class extends Migration {

    private const EMAILS = [
        'admin@arowanasoft.com', 'employee@arowanasoft.com', 'hr@arowanasoft.com', 'manager@arowanasoft.com',
        'ceo@arowanasoft.com', 'cfo@arowanasoft.com', 'vp@arowanasoft.com',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'is_test_account')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_test_account')->default(false)->index();
            });
        }

        // only the test sign-ins (their names start "Test "), never a real person who shares an address
        DB::table('users')->whereIn('email', self::EMAILS)->where('name', 'like', 'Test %')->update(['is_test_account' => true]);

        $this->forget();
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_test_account')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['is_test_account']);
                $table->dropColumn('is_test_account');
            });
        }

        $this->forget();
    }

    private function forget(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
