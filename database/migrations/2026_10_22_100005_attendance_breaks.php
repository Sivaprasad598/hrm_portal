<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Breaks with a start and an end time (Attendance Settings -> Working Hours):
 * any number of them (lunch, snacks, tea...), some marked default; each shift
 * picks its own (employee_shifts.break_ids), or takes the defaults.
 *
 * The lunch and snacks minutes set so far become two default breaks.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('attendance_breaks')) {
            Schema::create('attendance_breaks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('name', 60);
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_default')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            foreach (DB::table('attendance_settings')->get() as $s) {
                $lunch = (int) ($s->wh_lunch_minutes ?? 45);
                $snack = (int) ($s->wh_snack_minutes ?? 15);
                $rows = [];

                if ($lunch > 0) {
                    $rows[] = ['name' => 'Lunch', 'start_time' => '13:00:00', 'end_time' => date('H:i:s', strtotime('13:00') + $lunch * 60), 'sort_order' => 1];
                }

                if ($snack > 0) {
                    $rows[] = ['name' => 'Snacks', 'start_time' => '16:00:00', 'end_time' => date('H:i:s', strtotime('16:00') + $snack * 60), 'sort_order' => 2];
                }

                foreach ($rows as $row) {
                    DB::table('attendance_breaks')->insert($row + ['company_id' => $s->company_id, 'is_default' => true,
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        if (!Schema::hasColumn('employee_shifts', 'break_ids')) {
            Schema::table('employee_shifts', function (Blueprint $table) {
                $table->json('break_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_shifts', 'break_ids')) {
            Schema::table('employee_shifts', fn (Blueprint $t) => $t->dropColumn('break_ids'));
        }

        Schema::dropIfExists('attendance_breaks');
    }

};
