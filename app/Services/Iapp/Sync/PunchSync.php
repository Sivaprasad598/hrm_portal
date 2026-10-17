<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappDeviceTransaction;
use App\Models\IappSyncLog;
use App\Services\Iapp\DeviceTransEndpoint;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pulls raw punches from iApp into iapp_device_transactions.
 *
 * The feed is the authoritative record for this deployment — attd/getDetail
 * is not available on this server, so attendance is reconstructed from these
 * punches rather than from a day-wise summary.
 *
 * Writes only to this module's own tables.
 */
class PunchSync
{

    /** Rows per bulk upsert. Keeps each statement well inside max_allowed_packet. */
    protected const CHUNK = 500;

    protected DeviceTransEndpoint $endpoint;

    protected EmployeeResolver $resolver;

    public function __construct(
        protected IappClient $client,
        ?DeviceTransEndpoint $endpoint = null,
        ?EmployeeResolver $resolver = null
    ) {
        $this->endpoint = $endpoint ?? new DeviceTransEndpoint($client);
        $this->resolver = $resolver ?? new EmployeeResolver($client->config());
    }

    public function config(): IappConfig
    {
        return $this->client->config();
    }

    /**
     * Sync punches in [$from, $until].
     *
     * @param  string  $trigger  'manual', 'schedule' or 'command'
     * @return IappSyncLog  the completed run record
     */
    /**
     * @param  ?string  $empCode  pull only this device code (one employee); null pulls everybody
     */
    public function run(Carbon $from, ?Carbon $until = null, string $trigger = 'manual', ?int $userId = null, ?string $empCode = null): IappSyncLog
    {
        $startedAt = now();
        $timer = microtime(true);

        $log = new IappSyncLog([
            'sync_type' => IappSyncLog::TYPE_DEVICE_TRANS,
            'trigger' => $trigger,
            'triggered_by' => $userId,
            'from_date' => $from->toDateString(),
            'to_date' => ($until ?? now())->toDateString(),
            'status' => IappSyncLog::STATUS_RUNNING,
            'started_at' => $startedAt,
            'emp_code' => $empCode ?: null,
        ]);

        $log->company_id = $this->config()->companyId();
        $log->save();

        $fetched = 0;
        $unique = 0;

        try {
            if (!$this->config()->isConfigured()) {
                throw new \RuntimeException('iApp connection is not configured.');
            }

            $result = $this->ingest($from, $until, $empCode);
            $fetched = $result['fetched'];
            $unique = $result['unique'];

            $log->fill([
                'records_fetched' => $fetched,
                'records_created' => $result['created'],
                'records_updated' => $result['updated'],
                'records_skipped' => $fetched - $unique,
                'records_unmatched' => $this->resolver->unmatchedCount(),
                // Capped: a misconfigured match column could otherwise put
                // thousands of codes into one JSON column.
                'unmatched_codes' => array_slice($this->resolver->unmatchedCodes(), 0, 200),
                'status' => $this->resolver->unmatchedCount() > 0
                    ? IappSyncLog::STATUS_PARTIAL
                    : IappSyncLog::STATUS_SUCCESS,
                'message' => sprintf(
                    '%s%d punches (%d unique), %d matched, %d unmatched.',
                    $empCode ? $empCode . ': ' : '',
                    $fetched,
                    $unique,
                    $this->resolver->matchedCount(),
                    $this->resolver->unmatchedCount()
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error('iApp punch sync failed', ['message' => $e->getMessage()]);

            $log->fill([
                'records_fetched' => $fetched,
                'status' => IappSyncLog::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);
        }

        $log->finished_at = now();
        $log->duration_ms = (int) round((microtime(true) - $timer) * 1000);
        $log->save();

        // A one-employee pull says nothing about the company's feed as a whole,
        // so only full pulls move "last synced" and the Sync Calendar.
        if (!$empCode) {
            $this->recordOutcome($log);
            $this->recordCalendar($log, $from, $until ?? now());
        }

        return $log;
    }

    /**
     * One Sync Calendar row per date a full pull covered, so pulls made from
     * the schedule or the Pull button show up there as well as backfills.
     */
    protected function recordCalendar(IappSyncLog $log, Carbon $from, Carbon $until): void
    {
        if ($log->status === IappSyncLog::STATUS_FAILED) {
            return;
        }

        $companyId = $this->config()->companyId();

        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            $date = $day->toDateString();
            $punches = \App\Models\IappDeviceTransaction::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->whereBetween('punch_time', [$date . ' 00:00:00', $date . ' 23:59:59']);

            \App\Models\IappDaySync::record(
                $companyId,
                $date,
                (clone $punches)->count(),
                (clone $punches)->distinct('emp_code')->count('emp_code'),
                \App\Models\IappAttendanceRecord::withoutGlobalScopes()
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->whereDate('attendance_date', $date)->count(),
                null,
                $log->triggered_by
            );
        }
    }

    /**
     * Pull and store punches for one window, without opening a log record.
     *
     * Kept separate from run() so a long backfill can walk a range in short
     * steps: buffering a month of punches to persist them in one go would run
     * the request out of memory long before it ran out of time.
     *
     * @return array{fetched:int, unique:int, created:int, updated:int}
     */
    public function ingest(Carbon $from, ?Carbon $until = null, ?string $empCode = null): array
    {
        $fetched = 0;
        $unique = [];

        // De-duplicated before writing: one controller reports each gate
        // separately, so a single swipe arrives three or four times.
        foreach ($this->endpoint->stream($from, $until, 20000, $empCode) as $rows) {
            $fetched += count($rows);

            foreach ($rows as $row) {
                $normalised = DeviceTransEndpoint::normalise($row);

                if ($normalised === null) {
                    continue;
                }

                $unique[$normalised['punch_hash']] = $normalised;
            }
        }

        $this->resolver->load(array_column($unique, 'emp_code'));

        $result = $this->persist($unique);

        return [
            'fetched' => $fetched,
            'unique' => count($unique),
            'created' => $result['created'],
            'updated' => $result['updated'],
        ];
    }

    /**
     * Convenience: today plus the configured look-back.
     *
     * Re-pulling recent days matters because punches can arrive late — a
     * controller that was offline uploads its buffer once it reconnects.
     */
    public function runRecent(string $trigger = 'schedule', ?int $userId = null): IappSyncLog
    {
        $days = (int) ($this->config()->setting()?->resync_days ?? 3);

        return $this->run(
            now()->subDays(max(0, $days))->startOfDay(),
            now(),
            $trigger,
            $userId
        );
    }

    /**
     * Bulk upsert, keyed on punch_hash.
     *
     * @param  array<string, array>  $unique
     * @return array{created:int, updated:int}
     */
    protected function persist(array $unique): array
    {
        if ($unique === []) {
            return ['created' => 0, 'updated' => 0];
        }

        $before = IappDeviceTransaction::count();
        $companyId = $this->config()->companyId();
        $now = now();
        $written = 0;

        foreach (array_chunk($unique, self::CHUNK) as $chunk) {
            $payload = [];

            foreach ($chunk as $row) {
                $payload[] = [
                    'punch_hash' => $row['punch_hash'],
                    'company_id' => $companyId,
                    'user_id' => $this->resolver->resolve($row['emp_code']),
                    'emp_code' => $row['emp_code'],
                    'employee_name' => $row['employee_name'] ?? null,
                    'controller_code' => $row['controller_code'],
                    'card_id' => $row['card_id'],
                    'punch_time' => $row['punch_time']->format('Y-m-d H:i:s'),
                    'punch_direction' => $row['punch_direction'],
                    'direction_source' => $row['direction_source'],
                    'raw_payload' => json_encode($row['raw_payload']),
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // user_id is deliberately absent from the update list. It is set
            // when the punch is first stored and afterwards only ever changed by
            // applyMappings(), which reconciles every code at once. Leaving it
            // here let a re-sync recompute ownership from a stale lookup and
            // write NULL over a perfectly good mapping — 462 punches lost their
            // owner that way.
            IappDeviceTransaction::upsert(
                $payload,
                ['punch_hash'],
                ['emp_code', 'punch_direction', 'direction_source', 'raw_payload', 'synced_at', 'updated_at']
            );

            $written += count($payload);
        }

        $created = max(0, IappDeviceTransaction::count() - $before);

        return ['created' => $created, 'updated' => max(0, $written - $created)];
    }

    /**
     * Mirror the outcome onto the settings row so the dashboard can show
     * connection health without scanning the log table.
     */
    protected function recordOutcome(IappSyncLog $log): void
    {
        $setting = $this->config()->setting();

        if (!$setting) {
            return;
        }

        $setting->forceFill([
            'last_sync_at' => $log->finished_at,
            'last_sync_status' => $log->status,
            'last_sync_message' => $log->message,
        ])->save();
    }

}
