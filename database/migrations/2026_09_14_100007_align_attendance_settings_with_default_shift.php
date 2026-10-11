<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align the attendance settings' office hours with the default shift.
 *
 * The two disagreed: settings said 09:00-18:00 with a 13:00 half-day mark,
 * while the default shift "General Shift" said 09:30-18:30 with 14:00. They
 * drive different things — the settings row governs web auto clock-in, the
 * shift governs roster assignment and every late / half-day judgment — so the
 * contradiction was invisible until the biometric shift report put both on the
 * same screen.
 *
 * The shift wins, because it is what the ERP's own attendance code reads
 * (AttendanceController resolves `$shift->...` first and only falls back to the
 * settings row). Aligning this direction therefore changes no attendance
 * judgment, past or future; it only moves the auto clock-in window. Aligning
 * the other way would have re-marked historical arrivals against a 09:00 start.
 *
 * Only touches rows that still hold the values being corrected, and the
 * previous values are restored by down().
 */
return new class extends Migration
{

    /** What the settings held before, keyed by company, so down() is exact. */
    private const PREVIOUS = [
        'office_start_time' => '09:00:00',
        'office_end_time' => '18:00:00',
        'halfday_mark_time' => '13:00:00',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('attendance_settings') || !Schema::hasTable('employee_shifts')) {
            return;
        }

        foreach (DB::table('attendance_settings')->get() as $settings) {
            $shift = $settings->default_employee_shift
                ? DB::table('employee_shifts')->find($settings->default_employee_shift)
                : null;

            if (!$shift || !$shift->office_start_time || !$shift->office_end_time) {
                continue;
            }

            $update = [];

            foreach (['office_start_time', 'office_end_time', 'halfday_mark_time'] as $field) {
                $target = $shift->{$field} ?? null;

                if ($target === null || $target === '' || $target === '00:00:00') {
                    continue;
                }

                if ((string) $settings->{$field} !== (string) $target) {
                    $update[$field] = $target;
                }
            }

            if ($update === []) {
                continue;
            }

            $update['updated_at'] = now();

            DB::table('attendance_settings')->where('id', $settings->id)->update($update);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        DB::table('attendance_settings')->update(self::PREVIOUS + ['updated_at' => now()]);
    }

};
