<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * iApp offers two ways to obtain a token, and this deployment uses the
     * username/password one (authenticateClient with the .env secret is not
     * what the live server is set up for). Store both sets of credentials and
     * let the settings screen choose which to send.
     *
     * Also corrects the device natural key: getdevices returns one row per
     * *gate*, so several rows legitimately share a controller_code — keying on
     * controller_code alone would collapse four doors into one.
     */
    public function up(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_settings', 'auth_method')) {
                $table->string('auth_method', 20)->default('user')->after('base_url');
            }

            if (!Schema::hasColumn('iapp_settings', 'username')) {
                $table->string('username')->nullable()->after('auth_method');
            }

            // Encrypted on the model, same as client_secret.
            if (!Schema::hasColumn('iapp_settings', 'password')) {
                $table->text('password')->nullable()->after('username');
            }

            // Observed token lifetime is 20 minutes; the cache window must stay
            // under it. Configurable so a firmware change doesn't need a deploy.
            if (!Schema::hasColumn('iapp_settings', 'token_cache_minutes')) {
                $table->unsignedSmallInteger('token_cache_minutes')->default(15)->after('resync_days');
            }
        });

        if (Schema::hasTable('iapp_devices')) {
            Schema::table('iapp_devices', function (Blueprint $table) {
                if (!Schema::hasColumn('iapp_devices', 'gate_key')) {
                    // gate_no can be absent; a stable non-null key keeps the
                    // unique index usable for upserts.
                    $table->string('gate_key', 30)->default('')->after('gate_no');
                }
            });

            Schema::table('iapp_devices', function (Blueprint $table) {
                $table->dropUnique('iapp_device_natural_key');
                $table->unique(
                    ['company_id', 'location_code', 'controller_code', 'gate_key'],
                    'iapp_device_natural_key'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            $table->dropColumn(['auth_method', 'username', 'password', 'token_cache_minutes']);
        });

        if (Schema::hasTable('iapp_devices')) {
            Schema::table('iapp_devices', function (Blueprint $table) {
                $table->dropUnique('iapp_device_natural_key');
                $table->unique(['company_id', 'controller_code'], 'iapp_device_natural_key');
                $table->dropColumn('gate_key');
            });
        }
    }
};
