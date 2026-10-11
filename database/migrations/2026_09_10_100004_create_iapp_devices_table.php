<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devices from deviceMaster/getdevices.
     *
     * device_mode matters beyond display: "In"/"Out" tells us the direction of
     * every punch that controller produces, which is the only way to derive
     * in/out from a deviceTrans feed that carries no direction field of its own.
     */
    public function up(): void
    {
        if (Schema::hasTable('iapp_devices')) {
            return;
        }

        Schema::create('iapp_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();

            $table->string('controller_code', 50);
            $table->unsignedInteger('controller_id')->nullable();
            $table->string('controller_name')->nullable();
            $table->string('location_code', 10)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('mac_address', 50)->nullable();
            $table->string('controller_model')->nullable();

            // "Y" active / "N" inactive as returned by iApp.
            $table->string('controller_status', 5)->nullable();
            // D = Default, tg = Toggle, In = In only, Out = Out only
            $table->string('device_mode', 10)->nullable();
            $table->string('controller_mode')->nullable();
            $table->string('entry_auth_mode')->nullable();
            $table->string('exit_auth_mode')->nullable();

            $table->string('gate_no', 20)->nullable();
            $table->string('gate_name')->nullable();
            $table->string('reference_code', 50)->nullable();

            $table->dateTime('last_activity_on')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'controller_code'], 'iapp_device_natural_key');
            $table->index('device_mode', 'iapp_device_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_devices');
    }
};
