<?php

use App\Models\IappDaySync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the day ledger from punches pulled before the ledger existed.
 *
 * Without this, days that were synced earlier show on the calendar as "never
 * synced" — the one thing the ledger is there to get right. Only dates that
 * already hold punches are seeded: a date with none is genuinely unknown, since
 * nothing recorded whether it was ever asked for.
 *
 * Idempotent: dates that already have a ledger row are left alone, so a re-run
 * cannot overwrite a real sync result with a reconstructed one.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('iapp_day_syncs') || !Schema::hasTable('iapp_device_transactions')) {
            return;
        }

        $existing = DB::table('iapp_day_syncs')
            ->select('company_id', 'sync_date')
            ->get()
            ->map(fn ($row) => $row->company_id . '|' . substr((string) $row->sync_date, 0, 10))
            ->flip();

        $days = DB::table('iapp_device_transactions')
            ->selectRaw('company_id, DATE(punch_time) as day, COUNT(*) as punches,
                         COUNT(DISTINCT emp_code) as staff, MAX(synced_at) as synced_at')
            ->groupBy('company_id', 'day')
            ->get();

        $now = now();
        $rows = [];

        foreach ($days as $day) {
            $date = substr((string) $day->day, 0, 10);

            if ($existing->has($day->company_id . '|' . $date)) {
                continue;
            }

            $attendance = DB::table('iapp_attendance_records')
                ->where('company_id', $day->company_id)
                ->whereDate('attendance_date', $date)
                ->count();

            $rows[] = [
                'company_id' => $day->company_id,
                'sync_date' => $date,
                // The punches carry their own sync timestamp, which is a truer
                // record of when the day was pulled than "now" would be.
                'last_synced_at' => $day->synced_at ?: $now,
                'status' => IappDaySync::STATUS_OK,
                'punch_count' => (int) $day->punches,
                'employee_count' => (int) $day->staff,
                'attendance_days' => $attendance,
                'sync_count' => 1,
                'duration_ms' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('iapp_day_syncs')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Seeded rows are indistinguishable from real ones by design, so this
        // is deliberately not reversed. Dropping the table removes them.
    }

};
