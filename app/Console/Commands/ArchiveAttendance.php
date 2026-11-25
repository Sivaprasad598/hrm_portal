<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the database small (monthly, from the schedule):
 *
 *  - punches and attendance sessions older than the kept months (12 by default) move to their
 *    archive tables - still in the database and searchable (Employee 360, Punch Log read them);
 *  - the daily summary (attendance_reviews: one row per employee per day) is never archived, so
 *    month and year figures stay right for any period;
 *  - housekeeping: biometric sync logs, finished attendance-write runs and day fingerprints older
 *    than 90 days are deleted;
 *  - the space is given back (OPTIMIZE TABLE).
 *
 * In chunks, so nothing is locked for long. --dry shows what would move.
 */
class ArchiveAttendance extends Command
{

    protected $signature = 'attendance:archive
                            {--months=12 : Months kept in the live tables}
                            {--dry : Only count what would move}';

    protected $description = 'Move punches and attendance sessions older than a year to their archive tables; clear old logs';

    protected const CHUNK = 5000;

    public function handle(): int
    {
        @set_time_limit(0);

        $months = max(3, (int) $this->option('months'));
        $cutoff = now()->startOfMonth()->subMonths($months);
        $dry = (bool) $this->option('dry');

        $this->info(($dry ? '[dry] ' : '') . 'Archiving before ' . $cutoff->toDateString());

        $moved = [
            'punches' => $this->move('iapp_device_transactions', 'punch_time', $cutoff, $dry),
            'attendance sessions' => $this->move('attendances', 'clock_in_time', $cutoff, $dry),
        ];

        if (!$dry) {
            // id maps of archived attendance (the rows they point at moved) and old fingerprints
            if (Schema::hasTable('iapp_attendance_links')) {
                $this->deleteWhere('iapp_attendance_links', fn ($q) => $q->where('attendance_date', '<', $cutoff->toDateString()));
            }

            $logCutoff = now()->subDays(90);

            foreach ([['iapp_sync_logs', 'started_at'], ['iapp_attendance_sync_runs', 'created_at'], ['iapp_day_writes', 'written_at']] as [$table, $column]) {
                if (Schema::hasTable($table)) {
                    $this->deleteWhere($table, fn ($q) => $q->where($column, '<', $logCutoff));
                }
            }

            foreach (['iapp_device_transactions', 'attendances', 'iapp_attendance_links', 'iapp_sync_logs'] as $table) {
                try {
                    Schema::hasTable($table) && DB::statement('OPTIMIZE TABLE `' . $table . '`');
                } catch (\Throwable $e) {
                    // not allowed on every host
                }
            }
        }

        foreach ($moved as $what => $n) {
            $this->line(sprintf('  %s: %d %s', $what, $n, $dry ? 'would move' : 'moved'));
        }

        return self::SUCCESS;
    }

    /** Rows older than the cutoff, copied to <table>_archive and removed, a chunk at a time. */
    protected function move(string $table, string $column, Carbon $cutoff, bool $dry): int
    {
        $archive = $table . '_archive';

        if (!Schema::hasTable($table) || !Schema::hasTable($archive)) {
            return 0;
        }

        if ($dry) {
            return DB::table($table)->where($column, '<', $cutoff)->count();
        }

        $total = 0;

        do {
            $ids = DB::table($table)->where($column, '<', $cutoff)->orderBy('id')->limit(self::CHUNK)->pluck('id')->all();

            if ($ids === []) {
                break;
            }

            DB::transaction(function () use ($table, $archive, $ids) {
                $list = implode(',', array_map('intval', $ids));
                DB::statement("INSERT IGNORE INTO `{$archive}` SELECT * FROM `{$table}` WHERE id IN ({$list})");
                DB::table($table)->whereIn('id', $ids)->delete();
            });

            $total += count($ids);
        } while (count($ids) === self::CHUNK);

        return $total;
    }

    protected function deleteWhere(string $table, \Closure $where): void
    {
        $key = Schema::hasColumn($table, 'id') ? 'id' : null;

        do {
            if ($key) {
                $ids = $where(DB::table($table))->limit(self::CHUNK)->pluck('id')->all();
                $ids && DB::table($table)->whereIn('id', $ids)->delete();
                $n = count($ids);
            } else {
                $n = $where(DB::table($table))->limit(self::CHUNK)->delete();
            }
        } while ($n === self::CHUNK);
    }

}
