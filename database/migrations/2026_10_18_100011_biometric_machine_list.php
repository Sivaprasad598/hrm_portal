<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biometric data for this company's employees only, from Visakhapatnam:
 *   - only_known_employees: punches and device-list entries are kept only for
 *     codes that belong to an employee in the system;
 *   - register_locations: the locations read from the machine's employee list.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_settings', 'only_known_employees')) {
                $table->boolean('only_known_employees')->default(true);
            }
            if (!Schema::hasColumn('iapp_settings', 'register_locations')) {
                $table->json('register_locations')->nullable();
            }
        });

        DB::table('iapp_settings')->update([
            'only_known_employees' => 1,
            'register_locations' => json_encode(['Visakhapatnam']),
        ]);
    }

    public function down(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            foreach (['only_known_employees', 'register_locations'] as $column) {
                if (Schema::hasColumn('iapp_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

};
