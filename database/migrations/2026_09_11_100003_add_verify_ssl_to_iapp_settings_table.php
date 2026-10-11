<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-company TLS verification setting.
     *
     * Defaults to verifying. It is turned off only where the server presents a
     * self-signed certificate — which is the case when iApp is reached by IP,
     * since an IP address cannot hold a publicly trusted certificate. Making
     * it a stored setting rather than only an .env flag means a site that later
     * gets a proper certificate can switch it back on from the settings screen.
     */
    public function up(): void
    {
        if (!Schema::hasTable('iapp_settings')) {
            return;
        }

        if (!Schema::hasColumn('iapp_settings', 'verify_ssl')) {
            Schema::table('iapp_settings', function (Blueprint $table) {
                $table->boolean('verify_ssl')->default(true)->after('base_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('iapp_settings') && Schema::hasColumn('iapp_settings', 'verify_ssl')) {
            Schema::table('iapp_settings', function (Blueprint $table) {
                $table->dropColumn('verify_ssl');
            });
        }
    }
};
