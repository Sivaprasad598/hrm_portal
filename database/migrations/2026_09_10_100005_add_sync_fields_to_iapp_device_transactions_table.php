<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * deviceTrans carries no punch direction of its own, so direction is
     * resolved from the controller that produced the punch (iapp_devices.
     * device_mode) and stored here. Without it, raw punches can't be paired
     * into in/out sessions.
     */
    public function up(): void
    {
        if (!Schema::hasTable('iapp_device_transactions')) {
            return;
        }

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_device_transactions', 'punch_direction')) {
                $table->string('punch_direction', 10)->nullable()->after('punch_time');
            }

            if (!Schema::hasColumn('iapp_device_transactions', 'direction_source')) {
                $table->string('direction_source', 20)->nullable()->after('punch_direction');
            }

            if (!Schema::hasColumn('iapp_device_transactions', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('direction_source');
            }
        });

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->index(['company_id', 'punch_time'], 'iapp_trans_company_punch_idx');
            $table->index(['user_id', 'punch_time'], 'iapp_trans_user_punch_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_device_transactions')) {
            return;
        }

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->dropIndex('iapp_trans_company_punch_idx');
            $table->dropIndex('iapp_trans_user_punch_idx');
            $table->dropColumn(['punch_direction', 'direction_source', 'synced_at']);
        });
    }
};
