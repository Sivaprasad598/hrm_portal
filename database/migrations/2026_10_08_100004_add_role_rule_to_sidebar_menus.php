<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The third kind of condition the hand-written sidebar used.
 *
 * Besides a permission and a module, several entries were gated on the role
 * itself — `in_array('admin', user_roles())` for the payroll screens,
 * `!in_array('client', user_roles())` for most of HR. Without this column the
 * first verification pass handed employees and clients nineteen admin pages
 * they had never been able to see.
 *
 * Values: admin, not_client, employee_or_admin, not_employee. Null means the
 * entry was never gated on role.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            $table->string('role_rule', 40)->nullable()->after('permission_rule');
        });
    }

    public function down(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            $table->dropColumn('role_rule');
        });
    }

};
