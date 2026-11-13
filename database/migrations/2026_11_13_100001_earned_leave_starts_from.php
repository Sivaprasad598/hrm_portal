<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave Settings -> Encashment -> "Earned Leave starts from" (month and year).
 * Earned Leave is earned (1 a month) only from that month on; before it, nothing.
 * Empty: from the start of the leave year / the joining month, as before.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('leave_settings') && !Schema::hasColumn('leave_settings', 'earned_leave_starts_from')) {
            Schema::table('leave_settings', function (Blueprint $table) {
                $table->date('earned_leave_starts_from')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_settings', 'earned_leave_starts_from')) {
            Schema::table('leave_settings', fn (Blueprint $table) => $table->dropColumn('earned_leave_starts_from'));
        }
    }

};
