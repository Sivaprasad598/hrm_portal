<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The biometric routine moves to the night, in three steps:
 *
 *   01:00  pull the previous day from the selected devices
 *   02:00  build Device Attendance from the punches
 *   03:00  write ERP attendance, review every employee and cross-check device
 *          against ERP; whatever does not agree becomes a biometric exception
 *   09:00  notifications (nobody is emailed at 3 AM)
 *
 * Which devices are pulled is a setting: all of them, or the ones ticked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_settings', 'device_scope')) {
                $table->string('device_scope', 10)->default('all')->after('location_code');   // all | selected
                $table->json('pull_devices')->nullable()->after('device_scope');               // controller codes
            }

            if (!Schema::hasColumn('iapp_settings', 'pull_time')) {
                $table->string('pull_time', 5)->default('01:00')->after('daily_pull_time');
                $table->string('build_time', 5)->default('02:00')->after('pull_time');
                $table->string('reconcile_time', 5)->default('03:00')->after('build_time');
                $table->string('notify_time', 5)->default('09:00')->after('reconcile_time');
            }
        });

        // This site: the two location-01 devices for now (Muralinagar-ii and its
        // face reader); the Hyderabad controllers can be added and re-synced later.
        DB::table('iapp_settings')->update([
            'device_scope' => 'selected',
            'pull_devices' => json_encode(['00001', '2484401']),
            'pull_time' => '01:00',
            'build_time' => '02:00',
            'reconcile_time' => '03:00',
            'notify_time' => '09:00',
        ]);

        Schema::table('attendance_daily_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_daily_runs', 'built_at')) {
                $table->timestamp('built_at')->nullable()->after('pulled_at');
                $table->timestamp('reconciled_at')->nullable()->after('reviewed_at');
                $table->json('devices')->nullable()->after('device_complete');   // codes the pull covered
            }
        });

        // A re-sync names its devices; null means whatever Settings selects.
        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_backfill_runs', 'devices')) {
                $table->json('devices')->nullable()->after('scope');
            }
        });

        // Sidebar: the four everyday screens. The rest are on the biometric
        // pages' Setup menu (Data / Configure / History & tools).
        DB::table('sidebar_menus')
            ->whereIn('route', ['biometric.attendance', 'biometric.attendance-sync', 'biometric.employees'])
            ->update(['is_active' => 0]);

        if (!Schema::hasTable('biometric_exceptions')) {
            Schema::create('biometric_exceptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->date('date')->index();
                $table->string('category', 10);            // sync | punch
                $table->string('type', 40)->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('emp_code', 50)->nullable();
                $table->string('controller_code', 30)->nullable();
                $table->string('title', 255);
                $table->json('details')->nullable();
                $table->string('status', 12)->default('open');   // open | resolved | ignored
                $table->string('resolution', 12)->nullable();     // auto | manual
                $table->text('note')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                // One row per problem: the same problem found again tomorrow updates it.
                $table->string('fingerprint', 64)->unique();
                $table->timestamps();

                $table->index(['status', 'category', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_exceptions');

        DB::table('sidebar_menus')
            ->whereIn('route', ['biometric.attendance', 'biometric.attendance-sync', 'biometric.employees'])
            ->update(['is_active' => 1]);

        if (Schema::hasColumn('iapp_backfill_runs', 'devices')) {
            Schema::table('iapp_backfill_runs', fn (Blueprint $table) => $table->dropColumn('devices'));
        }

        Schema::table('attendance_daily_runs', function (Blueprint $table) {
            foreach (['built_at', 'reconciled_at', 'devices'] as $column) {
                if (Schema::hasColumn('attendance_daily_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('iapp_settings', function (Blueprint $table) {
            foreach (['device_scope', 'pull_devices', 'pull_time', 'build_time', 'reconcile_time', 'notify_time'] as $column) {
                if (Schema::hasColumn('iapp_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
