<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave Settings -> Sandwich rule: weekly offs, holidays and roster Day Offs that
 * fall between days of one leave request count as leave too. Off by default, so
 * nothing changes until it is switched on.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('leave_settings', 'sandwich_rule')) {
            Schema::table('leave_settings', function (Blueprint $table) {
                $table->boolean('sandwich_rule')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_settings', 'sandwich_rule')) {
            Schema::table('leave_settings', function (Blueprint $table) {
                $table->dropColumn('sandwich_rule');
            });
        }
    }
};
