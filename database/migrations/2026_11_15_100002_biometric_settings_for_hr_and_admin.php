<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biometric Settings (the device connection, sync and automation) is for HR and
 * the Admin again, as BiometricSettingController intends: it is gated on
 * manage_attendance_setting. Its sidebar entry still asked for
 * manage_biometric_system, the technical permission only the Super Admin holds,
 * so HR and the Admin could not see it. Found by its route, so the same on every
 * server; down() puts the old permission back.
 */
return new class extends Migration {

    private const ROUTE = 'biometric-settings.index';

    public function up(): void
    {
        $this->gate('manage_biometric_system', 'manage_attendance_setting');
    }

    public function down(): void
    {
        $this->gate('manage_attendance_setting', 'manage_biometric_system');
    }

    private function gate(string $from, string $to): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasColumn('sidebar_menus', 'permission_key')) {
            return;
        }

        DB::table('sidebar_menus')->where('route', self::ROUTE)->where('permission_key', $from)
            ->update(['permission_key' => $to, 'updated_at' => now()]);

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
