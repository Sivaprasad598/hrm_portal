<?php

namespace App\Services\Iapp\Pipeline;

use App\Models\IappAttendanceSyncRun;
use App\Models\IappSyncLog;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Sync\AttendanceSync;
use App\Services\Iapp\Sync\PunchAggregator;
use App\Services\Iapp\Sync\PunchSync;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Device → ERP attendance in one go:
 *
 *   1. pull the punches from the device        (Punch Log)
 *   2. build one day per person from them        (Device Attendance)
 *   3. write those days into ERP attendance      (HR → Attendance, which payroll reads)
 *
 * Step 3 runs when "Push to ERP attendance automatically" is on, or when the
 * caller asks for it. It uses skip mode: days this module wrote before are
 * rebuilt from the punches as they stand, and a day that already holds
 * attendance from anywhere else (a web clock-in, a manual entry) is left alone.
 *
 * Used by the schedule, the Overview screen's "Pull now", and the one-employee
 * pull, so all three behave identically.
 */
class AttendancePipeline
{

    public const LOCK = 'biometric-pipeline';

    public const LAST_RUN_KEY = 'biometric.pipeline.last';

    public function __construct(protected ?IappConfig $config = null)
    {
        $this->config ??= new IappConfig();
    }

    /**
     * @param  ?string  $empCode  one employee's device code, or null for everybody
     * @param  ?bool    $push     write into ERP attendance; null follows the setting
     * @return array{ok: bool, message: string, pull: ?IappSyncLog, built: ?array, push: ?IappAttendanceSyncRun}
     */
    public function run(Carbon $from, Carbon $to, string $trigger = 'manual', ?int $actorId = null, ?string $empCode = null, ?bool $push = null): array
    {
        if (!$this->config->isConfigured()) {
            return $this->finish(false, 'The device connection is not configured (Biometric → Settings).', $trigger, $empCode);
        }

        // Two pulls at once would race on the same rows, and the scheduler's own
        // overlap guard does not work here (its cache is per process).
        // One lock for every pull: a one-employee pull writes the same rows as a full one.
        $lock = Cache::store('file')->lock(self::LOCK, 3600);

        if (!$lock->get()) {
            // Not recorded as the last run: the run in progress will record itself.
            return ['ok' => false, 'message' => 'A pull is already running. Try again in a few minutes.',
                'pull' => null, 'built' => null, 'push' => null];
        }

        try {
            // A pull killed partway (a closed window, a reboot) never records its
            // end; with the lock now ours, nothing else can still be running it.
            IappSyncLog::withoutGlobalScopes()
                ->where('status', IappSyncLog::STATUS_RUNNING)
                ->where('started_at', '<', now()->subHours(2))
                ->update([
                    'status' => IappSyncLog::STATUS_FAILED,
                    'finished_at' => now(),
                    'message' => 'Run did not finish — the process was stopped before it completed.',
                ]);

            $pull = (new PunchSync(new IappClient($this->config)))->run($from, $to, $trigger, $actorId, $empCode);

            if ($pull->status === IappSyncLog::STATUS_FAILED) {
                return $this->finish(false, 'Pull from the device failed: ' . $pull->message, $trigger, $empCode, $pull);
            }

            $built = (new PunchAggregator($this->config))->build($from, $to, $empCode ? [$empCode] : null);

            $run = null;
            $push ??= (bool) $this->config->setting()?->auto_promote;

            if ($push) {
                $userIds = null;

                if ($empCode) {
                    $userIds = \App\Models\IappEmployee::withoutGlobalScopes()
                        ->where('emp_code', $empCode)->where('map_status', 'mapped')
                        ->whereNotNull('user_id')->pluck('user_id')->all();
                }

                if (!$empCode || $userIds !== []) {
                    $run = (new AttendanceSync($this->config))->run($from, $to, IappAttendanceSyncRun::MODE_SKIP, $userIds, $actorId);
                }
            }

            $message = sprintf(
                '%s%d punches pulled, %d day(s) built for %d employee(s)%s.',
                $empCode ? $empCode . ': ' : '',
                $pull->records_fetched,
                $built['days'],
                $built['employees'],
                $run
                    ? sprintf('; %d attendance row(s) written to ERP for %d employee(s), %d day(s) left as they were', $run->rows_created, $run->employees, $run->days_skipped)
                    : ($push ? '; not linked to an employee, so nothing was written to ERP attendance' : '; not pushed to ERP attendance (automatic push is off)')
            );

            return $this->finish(true, $message, $trigger, $empCode, $pull, $built, $run);
        } finally {
            $lock->release();
        }
    }

    /** The configured look-back up to now: what the schedule pulls. */
    public function runRecent(string $trigger = 'schedule', ?int $actorId = null): array
    {
        $days = (int) ($this->config->setting()?->resync_days ?? 3);

        // Re-pulled deliberately: a controller that was offline uploads its
        // buffer once it reconnects, so earlier days can still be arriving.
        return $this->run(now()->subDays(max(1, $days))->startOfDay(), now()->endOfDay(), $trigger, $actorId);
    }

    /** What the last run did, for the Overview screen. */
    public static function lastRun(): ?array
    {
        return Cache::store('file')->get(self::LAST_RUN_KEY);
    }

    protected function finish(bool $ok, string $message, string $trigger, ?string $empCode, ?IappSyncLog $pull = null, ?array $built = null, ?IappAttendanceSyncRun $push = null): array
    {
        if (!$empCode) {
            Cache::store('file')->forever(self::LAST_RUN_KEY, [
                'ok' => $ok, 'message' => $message, 'trigger' => $trigger, 'at' => now()->toIso8601String(),
            ]);
        }

        return ['ok' => $ok, 'message' => $message, 'pull' => $pull, 'built' => $built, 'push' => $push];
    }

}
