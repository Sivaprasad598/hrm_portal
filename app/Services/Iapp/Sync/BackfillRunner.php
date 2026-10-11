<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappAttendanceRecord;
use App\Models\IappBackfillRun;
use App\Models\IappDaySync;
use App\Models\IappDeviceTransaction;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Drives a punch pull forward in short, resumable steps.
 *
 * The feed costs roughly two minutes per day of history, and the devices retain
 * about twenty months of it — far past any request timeout. Queueing does not
 * help either: this installation runs the `sync` queue driver, so a dispatched
 * job executes inline in the same request.
 *
 * So a run is state rather than a process. start() records which dates to pull,
 * step() moves as far as it can inside a time budget and returns, and the caller
 * — the calendar's poll, or a console command — keeps calling until the run
 * reports done. Nothing is lost if the tab closes: progress is committed after
 * every chunk.
 *
 * A run always carries an explicit list of dates, so one day, a handful picked
 * out of the calendar, a whole month and a long backfill are all walked the same
 * way. Each date is finished and recorded before the next begins, which is what
 * lets the calendar turn cells green one at a time.
 *
 * Read-only against iApp, like the rest of the module.
 */
class BackfillRunner
{

    /**
     * Wall-clock budget for one step. Comfortably inside PHP's default 30s
     * limit, and short enough that the progress bar keeps moving.
     */
    public const STEP_SECONDS = 20;

    /**
     * History pulled per chunk. Six hours keeps each ingest small enough to
     * hold in memory while still amortising the auth handshake.
     */
    public const CHUNK_SECONDS = 21600;

    /**
     * Consecutive failed chunks tolerated before the run pauses itself. The
     * link to the device server drops occasionally, and stranding an overnight
     * pull on a single blip would be worse than retrying.
     */
    public const MAX_CONSECUTIVE_ERRORS = 4;

    /**
     * Earliest punch this platform still holds, as measured: queries filtered
     * from 2020, 2022, 2023 and 2024 all return the same first row,
     * 2025-01-10T13:21:13. Only a default for the "everything" preset.
     */
    public const EARLIEST_KNOWN = '2025-01-10';

    public function __construct(
        protected IappConfig $config
    ) {
    }

    /**
     * Open a run over an explicit set of dates, or hand back the one already in
     * flight.
     *
     * Two concurrent pulls would fight over the same punches, so an active run
     * always wins over a request for a new one.
     *
     * @param  array<int, string>  $dates  Y-m-d, in any order
     */
    public function start(array $dates, string $scope = 'range', ?int $userId = null): ?IappBackfillRun
    {
        if ($existing = $this->active()) {
            return $existing;
        }

        $dates = $this->cleanDates($dates);

        if ($dates === []) {
            return null;
        }

        return IappBackfillRun::create([
            'company_id' => $this->config->companyId(),
            'from_date' => $dates[0],
            'to_date' => end($dates),
            'sync_dates' => $dates,
            'date_index' => 0,
            'scope' => $scope,
            'cursor_at' => null,
            'status' => IappBackfillRun::STATUS_RUNNING,
            'started_by' => $userId,
            'started_at' => now(),
            'message' => count($dates) === 1
                ? 'Queued ' . Carbon::parse($dates[0])->format('d M Y') . '.'
                : 'Queued ' . count($dates) . ' days.',
        ]);
    }

    /**
     * Normalise a requested date set: real dates, no duplicates, nothing in the
     * future, ordered oldest first.
     *
     * @param  array<int, string>  $dates
     * @return array<int, string>
     */
    public function cleanDates(array $dates): array
    {
        $today = now()->toDateString();
        $clean = [];

        foreach ($dates as $date) {
            try {
                $parsed = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            // Tomorrow holds nothing to pull, and a future date would step
            // through empty windows to no purpose.
            if ($parsed > $today) {
                continue;
            }

            $clean[$parsed] = true;
        }

        $clean = array_keys($clean);
        sort($clean);

        return $clean;
    }

    /**
     * Every date in an inclusive range.
     *
     * @return array<int, string>
     */
    public function datesInRange(Carbon $from, Carbon $to): array
    {
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $dates = [];
        $day = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();

        while ($day->lte($last)) {
            $dates[] = $day->toDateString();
            $day->addDay();
        }

        return $dates;
    }

    public function active(): ?IappBackfillRun
    {
        return IappBackfillRun::withoutGlobalScopes()
            ->when($this->config->companyId(), fn ($q, $id) => $q->where('company_id', $id))
            ->whereIn('status', [IappBackfillRun::STATUS_RUNNING, IappBackfillRun::STATUS_PAUSED])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Pull as much as fits in one time budget.
     *
     * Each chunk is committed before the next begins, so a timeout mid-step
     * costs at most one chunk rather than the whole run.
     */
    public function step(IappBackfillRun $run, int $budgetSeconds = self::STEP_SECONDS): IappBackfillRun
    {
        if ($run->status !== IappBackfillRun::STATUS_RUNNING) {
            return $run;
        }

        $dates = $run->dateList();
        $deadline = microtime(true) + $budgetSeconds;
        $sync = new PunchSync(new IappClient($this->config));

        try {
            while (microtime(true) < $deadline) {
                $index = (int) $run->date_index;

                if ($index >= count($dates)) {
                    break;
                }

                $date = $dates[$index];
                $dayStart = Carbon::parse($date)->startOfDay();
                $dayEnd = Carbon::parse($date)->endOfDay();

                // Resume inside the day if a previous step stopped part-way;
                // `gt` is exclusive, so a fresh day starts a second early to
                // keep a punch landing exactly at midnight.
                $from = ($run->cursor_at && $run->cursor_at->gte($dayStart) && $run->cursor_at->lt($dayEnd))
                    ? $run->cursor_at->copy()
                    : $dayStart->copy()->subSecond();

                $to = $from->copy()->addSeconds(self::CHUNK_SECONDS);

                if ($to->gt($dayEnd)) {
                    $to = $dayEnd->copy();
                }

                $chunkTimer = microtime(true);
                $result = $sync->ingest($from, $to);

                $run->forceFill([
                    'cursor_at' => $to,
                    'punches_fetched' => $run->punches_fetched + $result['fetched'],
                    'punches_created' => $run->punches_created + $result['created'],
                    'steps' => $run->steps + 1,
                    'elapsed_ms' => $run->elapsed_ms + (int) round((microtime(true) - $chunkTimer) * 1000),
                    // A chunk that lands clears the failure streak: the counter
                    // tracks failures in a row, not failures in total.
                    'error_count' => 0,
                    'last_error' => null,
                ])->save();

                // The day is only finished — rebuilt, recorded, and handed on
                // to the next — once its final chunk has landed.
                if ($to->gte($dayEnd)) {
                    $this->completeDay($run, $date);

                    $run->forceFill([
                        'date_index' => $index + 1,
                        'cursor_at' => null,
                        'message' => sprintf(
                            '%s of %s days done — %s punches stored.',
                            number_format($index + 1),
                            number_format(count($dates)),
                            number_format($run->punches_created)
                        ),
                    ])->save();

                    continue;
                }

                $run->forceFill([
                    'message' => sprintf(
                        'Pulling %s, reached %s.',
                        Carbon::parse($date)->format('d M Y'),
                        $to->format('H:i')
                    ),
                ])->save();
            }

            if ((int) $run->date_index >= count($dates)) {
                $this->finish($run, count($dates));
            }
        } catch (\Throwable $e) {
            Log::error('iApp backfill step failed', ['run' => $run->id, 'message' => $e->getMessage()]);

            $failures = (int) $run->error_count + 1;
            $exhausted = $failures >= self::MAX_CONSECUTIVE_ERRORS;

            // Never failed outright: the cursor is committed, so the run can
            // always carry on from where it stopped. It keeps trying until the
            // failures stack up, then parks itself for someone to look at.
            $run->forceFill([
                'status' => $exhausted ? IappBackfillRun::STATUS_PAUSED : IappBackfillRun::STATUS_RUNNING,
                'error_count' => $failures,
                'last_error' => $e->getMessage(),
                'message' => $exhausted
                    ? 'Paused after ' . $failures . ' failed attempts. Resume to try again.'
                    : 'Hit an error, retrying (' . $failures . ' of ' . self::MAX_CONSECUTIVE_ERRORS . ').',
            ])->save();
        }

        return $run->refresh();
    }

    /**
     * Rebuild one finished day's attendance and record what it holds.
     */
    protected function completeDay(IappBackfillRun $run, string $date): void
    {
        $from = Carbon::parse($date)->startOfDay();
        $to = Carbon::parse($date)->endOfDay();

        $built = (new PunchAggregator($this->config))->build($from, $to);

        $this->recordDay($date, $run->id, $run->started_by);

        $run->forceFill(['days_built' => $run->days_built + $built['days']])->save();
    }

    /**
     * Write the calendar's per-day status from what is actually stored.
     *
     * Counted from the tables rather than from the pull, so a re-sync that adds
     * nothing still reports the day correctly.
     */
    public function recordDay(string $date, ?int $runId = null, ?int $userId = null, ?string $error = null): void
    {
        $companyId = $this->config->companyId();

        $punches = IappDeviceTransaction::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('punch_time', [$date . ' 00:00:00', $date . ' 23:59:59']);

        $count = (clone $punches)->count();
        $employees = (clone $punches)->distinct()->count('emp_code');

        $days = IappAttendanceRecord::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('attendance_date', $date)
            ->count();

        IappDaySync::record($companyId, $date, $count, $employees, $days, $runId, $userId, 0, $error);
    }

    protected function finish(IappBackfillRun $run, int $dayCount): void
    {
        $run->forceFill([
            'status' => IappBackfillRun::STATUS_DONE,
            'finished_at' => now(),
            'message' => sprintf(
                'Completed %s day%s — %s punches stored.',
                number_format($dayCount),
                $dayCount === 1 ? '' : 's',
                number_format($run->punches_created)
            ),
        ])->save();
    }

    public function pause(IappBackfillRun $run): IappBackfillRun
    {
        if ($run->status === IappBackfillRun::STATUS_RUNNING) {
            $run->forceFill([
                'status' => IappBackfillRun::STATUS_PAUSED,
                'message' => 'Paused. Resume to carry on where it stopped.',
            ])->save();
        }

        return $run;
    }

    public function resume(IappBackfillRun $run): IappBackfillRun
    {
        if ($run->status === IappBackfillRun::STATUS_PAUSED) {
            $run->forceFill([
                'status' => IappBackfillRun::STATUS_RUNNING,
                'last_error' => null,
                'error_count' => 0,
                'message' => 'Resuming.',
            ])->save();
        }

        return $run;
    }

    public function cancel(IappBackfillRun $run): IappBackfillRun
    {
        if ($run->isActive()) {
            $run->forceFill([
                'status' => IappBackfillRun::STATUS_CANCELLED,
                'finished_at' => now(),
                'message' => 'Cancelled. Days already pulled are kept.',
            ])->save();
        }

        return $run;
    }

    public function earliestAvailable(): Carbon
    {
        return Carbon::parse(self::EARLIEST_KNOWN)->startOfDay();
    }

}
