<?php

namespace App\Console\Commands;

use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Pipeline\AttendancePipeline;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pulls punches from the biometric devices, rebuilds the attendance days derived
 * from them, and writes those days into ERP attendance — the whole device → ERP
 * chain, through the same AttendancePipeline the Overview screen uses.
 *
 * Read-only against iApp — see IappClient, which refuses any write path.
 */
class IappSyncPunches extends Command
{

    protected $signature = 'iapp:sync-punches
                            {--from= : Start date (YYYY-MM-DD). Defaults to the configured look-back.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}
                            {--days= : Shorthand for the last N days, including today.}
                            {--employee= : Pull only this device code (one employee).}
                            {--push : Write into ERP attendance even if automatic push is off.}
                            {--no-push : Do not write into ERP attendance.}
                            {--scheduled : Run by the scheduler: skipped while "Sync enabled" is off.}';

    protected $description = 'Pull biometric punches, rebuild device attendance days and write them into ERP attendance';

    public function handle(): int
    {
        // A month of attendance for every employee needs more than PHP's usual
        // 128 MB (the shift lookup keeps each person's roster); at 128 MB the
        // nightly run died with "Allowed memory size exhausted" and exit 255.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $config = new IappConfig();

        if (!$config->isConfigured()) {
            $this->error('iApp connection is not configured. Set it under Biometric settings or in .env.');

            return self::FAILURE;
        }

        if ($this->option('scheduled') && !$config->setting()?->sync_enabled) {
            $this->line('Automatic sync is switched off (Biometric → Settings). Nothing done.');

            return self::SUCCESS;
        }

        // The schedule checks every few minutes; "Sync every (minutes)" decides
        // how often a pull actually happens. Measured from the last full pull,
        // so a missed tick (a sleeping PC) is caught up on the next one.
        if ($this->option('scheduled')) {
            $interval = max(5, (int) ($config->setting()?->sync_interval_minutes ?: 5));
            $last = (new \App\Services\Iapp\Pipeline\PipelineStatus($config))->lastPull();

            // A few minutes' slack: a pull that started late (the scheduler ran the
            // nightly routine first) must not make the next tick skip, which left
            // 30 minutes between pulls. 15 -> pulls when the last began 10+ minutes ago.
            // The scheduler now asks every 5 minutes, so 2 minutes of slack is
            // enough and keeps each setting at its own pace: 5 -> every tick,
            // 10 -> every 10, 15 -> every 15 (a 5-minute slack made 15 pull every 10).
            // Measured from the 5-minute tick the last pull belonged to, not from
            // the second it began: attendance:daily runs first on the quarter
            // hours and can hold the pull back 2-3 minutes, which made the next
            // tick look early and skip (15 became 20 minutes between pulls).
            // started 10:23 (tick 10:20) -> every 5: next at 10:25, every 15: 10:35.
            $tick = $last?->started_at
                ? $last->started_at->copy()->second(0)->subMinutes($last->started_at->minute % 5)
                : null;

            if ($tick && $tick->gt(now()->subMinutes($interval)->addMinute())) {
                return self::SUCCESS;
            }
        }

        [$from, $to] = $this->resolveRange($config);
        $push = $this->option('no-push') ? false : ($this->option('push') ? true : null);

        $this->info(sprintf('Pulling %s .. %s%s', $from->toDateString(), $to->toDateString(),
            $this->option('employee') ? ' for ' . $this->option('employee') : ''));

        $result = (new AttendancePipeline($config))->run(
            $from,
            $to,
            $this->option('scheduled') ? 'schedule' : 'command',
            null,
            $this->option('employee') ?: null,
            $push
        );

        $result['ok'] ? $this->info($result['message']) : $this->error($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(IappConfig $config): array
    {
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : now()->endOfDay();

        if ($this->option('from')) {
            return [Carbon::parse($this->option('from'))->startOfDay(), $to];
        }

        // Past days are re-pulled deliberately: a controller that was offline
        // uploads its buffer once it reconnects, so yesterday's data can still
        // be arriving today.
        $days = $this->option('days')
            ?? $config->setting()?->resync_days
            ?? 3;

        return [now()->subDays(max(0, (int) $days))->startOfDay(), $to];
    }

}
