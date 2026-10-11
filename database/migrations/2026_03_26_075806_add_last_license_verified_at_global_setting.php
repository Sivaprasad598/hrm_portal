<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('global_settings', 'last_license_verified_at')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $table->timestamp('last_license_verified_at')->nullable()->default(null)->after('supported_until');
            });
        }
        if (!Schema::hasColumn('global_settings', 'supported_until')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $table->timestamp('supported_until')->nullable()->default(null)->after('purchase_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('global_settings', 'last_license_verified_at')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $table->dropColumn('last_license_verified_at');
            });
        }
    }
};
