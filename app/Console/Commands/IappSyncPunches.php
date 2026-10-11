<?php

namespace App\Console\Commands;

use App\Models\IappSyncLog;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Sync\PunchAggregator;
use App\Services\Iapp\Sync\PunchSync;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pulls punches from the biometric devices and rebuilds attendance days.
 *
 * Read-only against iApp — see IappClient, which refuses any write path.
 */
class IappSyncPunches extends Command
{

    protected $signature = 'iapp:sync-punches
                            {--from= : Start date (YYYY-MM-DD). Defaults to the configured look-back.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}
                            {--days= : Shorthand for the last N days, including today.}
                            {--no-build : Pull punches only, skip rebuilding attendance days.}';

    protected $description = 'Pull biometric punches from iApp and rebuild the attendance days derived from them';

    public function handle(): int
    {
        $config = new IappConfig();

        if (!$config->isConfigured()) {
            $this->error('iApp connection is not configured. Set it under Biometric settings or in .env.');

            return self::FAILURE;
        }

        [$from, $to] = $this->resolveRange($config);

        $this->info(sprintf(
            'Pulling punches %s .. %s from %s',
            $from->toDateString(),
            $to->toDateString(),
            $config->baseUrl()
        ));

        $client = new IappClient($config);
        $sync = new PunchSync($client);

        $log = $sync->run($from, $to, 'command');

        $this->line(sprintf(
            '  fetched=%d created=%d updated=%d duplicates=%d unmatched=%d (%s)',
            $log->records_fetched,
            $log->records_created,
            $log->records_updated,
            $log->records_skipped,
            $log->records_unmatched,
            $log->duration_for_humans
        ));

        if ($log->status === IappSyncLog::STATUS_FAILED) {
            $this->error('Punch sync failed: ' . $log->message);

            return self::FAILURE;
        }

        if (!$this->option('no-build')) {
            $built = (new PunchAggregator($config))->build($from, $to);

            $this->line(sprintf(
                '  attendance: %d day(s) for %d employee(s) (%d new)',
                $built['days'],
                $built['employees'],
                $built['created']
            ));
        }

        if ($log->records_unmatched > 0) {
            $this->warn(sprintf(
                '  %d device code(s) are not mapped to an ERP employee — see Biometric > Employee Mapping.',
                $log->records_unmatched
            ));
        }

        $this->info('Done.');

        return self::SUCCESS;
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
