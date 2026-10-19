<?php

namespace App\Console\Commands;

use App\Models\AttendanceDailyRun;
use App\Models\AttendanceReview;
use App\Models\IappAttendanceSyncRun;
use App\Models\IappSetting;
use App\Models\IappSyncLog;
use App\Models\User;
use App\Notifications\AttendanceDailyDigest;
use App\Notifications\AttendanceExceptionNotice;
use App\Services\Attendance\DailyAttendanceReview;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Pipeline\AttendancePipeline;
use App\Services\Iapp\Pipeline\PipelineStatus;
use App\Services\Iapp\Reconcile\BiometricReconciler;
use App\Services\Iapp\Sync\AttendanceSync;
use App\Services\Iapp\Sync\PunchAggregator;
use App\Services\Iapp\Sync\PunchSync;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The nightly biometric routine. The scheduler asks every 15 minutes; each
 * step of a date runs once its time has come and the step before it is done
 * (times on Biometric Settings):
 *
 *   01:00  pull     the previous day from the selected devices
 *   02:00  build    Device Attendance from the punches
 *   03:00  write    ERP attendance, review every employee, cross-check device
 *                   against ERP (BiometricReconciler → Exceptions)
 *   09:00  notify   HR's summary and each employee's own exceptions
 *
 * A pull that cannot reach the device is retried on every tick; at the notify
 * time the day goes ahead on what is on record, marked "device incomplete",
 * and is pulled again on the following nights for a week. Because each step
 * only looks at whether its time has passed, a server that was off overnight
 * catches up on its first tick.
 *
 * Night shifts: at 3 AM a shift that started the evening before is still
 * running. Its day is reviewed without holding the missing end against it, and
 * the following night the day before yesterday is rebuilt and checked again
 * quietly, once the shift is over.
 */
class RunDailyAttendance extends Command
{

    protected $signature = 'attendance:daily
                            {--date= : Run this date (YYYY-MM-DD) now, every step, pulling it first}
                            {--scheduled : Run by the scheduler: waits for each step\'s time and honours "Nightly automatic run"}
                            {--no-pull : Use what is on record without pulling from the device}
                            {--no-notify : Do not send any notifications}';

    protected $description = 'Nightly biometric routine: pull, build Device Attendance, write ERP + cross-check, notify';

    /** How far back a failed or incomplete day is retried. */
    public const LOOK_BACK_DAYS = 7;

    protected string $tz;

    protected ?IappSetting $setting;

    protected PipelineStatus $status;

    public function handle(): int
    {
        // A month of attendance for every employee needs more than PHP's usual
        // 128 MB (the shift lookup keeps each person's roster); at 128 MB the
        // nightly run died with "Allowed memory size exhausted" and exit 255.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $this->tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
        $this->setting = IappSetting::first();
        $this->status = new PipelineStatus();
        $now = Carbon::now($this->tz);

        if ($this->option('scheduled') && $this->setting && !$this->setting->sync_enabled) {
            return self::SUCCESS;   // switched off on Biometric Settings
        }

        $reconciled = false;

        foreach ($this->dates($now) as $date) {
            $reconciled = $this->process($date, $now) || $reconciled;
        }

        if ($reconciled && !$this->option('date')) {
            // Last night's evening shifts have finished by now: the day before
            // yesterday is rebuilt from the punches pulled since and checked again.
            $this->topUp($now->copy()->subDays(2)->startOfDay(), $now);
            $this->refreshRecent($now);
        }

        // A day written may complete a pay cycle's data; the payslip run decides.
        if ($reconciled) {
            Artisan::call('payroll:generate-due');
            $this->line(trim(Artisan::output()));
        }

        return self::SUCCESS;
    }

    /** @return Carbon[] oldest first */
    protected function dates(Carbon $now): array
    {
        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'), $this->tz)->startOfDay()];
        }

        $yesterday = $now->copy()->subDay()->startOfDay();

        // Earlier days only come back while something about them is unfinished.
        $retry = AttendanceDailyRun::query()
            ->whereBetween('date', [$yesterday->copy()->subDays(self::LOOK_BACK_DAYS)->toDateString(), $yesterday->copy()->subDay()->toDateString()])
            ->where(fn ($q) => $q->where('status', '!=', AttendanceDailyRun::STATUS_REVIEWED)
                ->orWhere('device_complete', false)
                ->orWhereNull('notified_at'))
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d, $this->tz)->startOfDay())
            ->all();

        return array_merge($retry, [$yesterday]);
    }

    /** When a step of this date is due: its time on the night after the date. */
    protected function due(Carbon $date, string $step): Carbon
    {
        $time = $this->setting?->stepTime($step) ?? IappSetting::DEFAULT_TIMES[$step];

        return Carbon::parse($date->copy()->addDay()->toDateString() . ' ' . $time, $this->tz);
    }

    /** @return bool whether the date was written and cross-checked in this run */
    protected function process(Carbon $date, Carbon $now): bool
    {
        $key = $date->toDateString();
        $explicit = (bool) $this->option('date');
        $deviceInUse = $this->status->deviceInUse();
        $ready = fn (string $step) => $explicit || $now->gte($this->due($date, $step));

        $run = AttendanceDailyRun::firstOrCreate(
            ['company_id' => $this->setting?->company_id, 'date' => $key],
            ['status' => AttendanceDailyRun::STATUS_PENDING]
        );

        // 1. pull
        $wantsPull = $deviceInUse && !$this->option('no-pull') && ($explicit || !$run->device_complete);

        if ($wantsPull && $ready('pull_time')) {
            $pulled = $this->pull($run, $date, $now);

            // A late day coming in changes what was built and written from it.
            if ($pulled && !$explicit && $run->status === AttendanceDailyRun::STATUS_REVIEWED) {
                $run->forceFill(['status' => AttendanceDailyRun::STATUS_PULLED, 'built_at' => null])->save();
            }
        }

        // The device could not be reached: wait, unless it is time to tell people,
        // in which case the day goes ahead on what is on record.
        $waiting = !$run->device_complete && $deviceInUse && !$explicit && $now->lt($this->due($date, 'notify_time'));

        if ($waiting && $run->status !== AttendanceDailyRun::STATUS_REVIEWED) {
            return false;
        }

        // 2. build Device Attendance
        if ($ready('build_time') && ($explicit || !$run->built_at)) {
            $built = (new PunchAggregator(new IappConfig()))->build($date->copy()->startOfDay(), $date->copy()->endOfDay());
            $run->forceFill(['built_at' => now(), 'status' => $run->status === AttendanceDailyRun::STATUS_PENDING
                ? AttendanceDailyRun::STATUS_PULLED : $run->status])->save();
            $this->info("{$key}: built {$built['days']} device day(s) for {$built['employees']} code(s)");
        }

        // 3. write ERP, review, cross-check
        $reconciled = false;

        if ($run->built_at && $ready('reconcile_time') && ($explicit || $run->status !== AttendanceDailyRun::STATUS_REVIEWED)) {
            $deviceComplete = (bool) $run->device_complete || !$deviceInUse;
            $through = $run->pulled_at ? Carbon::parse($run->pulled_at)->setTimezone($this->tz) : $now->copy();

            $written = $this->write($date);
            $summary = (new DailyAttendanceReview())->review($date, $deviceComplete, $through);
            $checked = (new BiometricReconciler($this->setting?->company_id))->run($date, $deviceComplete, $through, $run->last_error);

            $run->forceFill([
                'status' => AttendanceDailyRun::STATUS_REVIEWED,
                'reviewed_at' => now(),
                'reconciled_at' => now(),
                'summary' => mb_substr($this->describe($summary, $checked), 0, 500),
            ])->save();

            $this->info("{$key}: {$written} | reviewed — {$run->summary}" . ($deviceComplete ? '' : ' (device data incomplete)'));
            $reconciled = true;
        }

        // 4. notify, once per date, in working hours
        if ($run->status === AttendanceDailyRun::STATUS_REVIEWED && !$run->notified_at && $ready('notify_time')) {
            // A day found unannounced long after the fact (the server was off for
            // days) is closed quietly: news about last week helps nobody.
            $stale = !$explicit && $date->lt($now->copy()->subDays(2)->startOfDay());

            if (!$this->option('no-notify') && !$stale) {
                $summary = AttendanceReview::withoutGlobalScopes()->where('date', $key)->get()
                    ->groupBy('status')->map->count()->all();
                $summary['exceptions'] = AttendanceReview::withoutGlobalScopes()->where('date', $key)->where('is_exception', true)->count();

                $this->notifyHr($key, $summary, (bool) $run->device_complete || !$deviceInUse);

                if ($this->setting?->notify_employees ?? true) {
                    // The day before as well: a night shift was still running at its
                    // first review and is judged on the following night.
                    $this->notifyEmployees($date->copy()->subDay()->toDateString());
                    $this->notifyEmployees($key);
                }
            }

            $run->forceFill(['notified_at' => now()])->save();
        }

        return $reconciled;
    }

    /** Pull the date (through the next morning, for night shifts) from the selected devices. */
    protected function pull(AttendanceDailyRun $run, Carbon $date, Carbon $now): bool
    {
        $key = $date->toDateString();
        $lock = Cache::store('file')->lock(AttendancePipeline::LOCK, 3600);

        if (!$lock->get()) {
            $this->warn("{$key}: another pull is running; trying again on the next tick.");

            return false;
        }

        try {
            $config = new IappConfig();
            $until = $date->copy()->addDay()->endOfDay();
            $until = $until->gt($now) ? $now->copy() : $until;

            $run->attempts++;
            $log = (new PunchSync(new IappClient($config)))->run($date->copy()->startOfDay(), $until, 'daily');

            if ($log->status === IappSyncLog::STATUS_FAILED) {
                $run->forceFill(['last_error' => $log->message])->save();
                $this->warn("{$key}: {$log->message}");

                return false;
            }

            $run->forceFill([
                'device_complete' => true,
                'pulled_at' => $until->copy()->setTimezone(config('app.timezone')),
                'devices' => $config->pullDevices(),
                'last_error' => null,
                'status' => $run->status === AttendanceDailyRun::STATUS_PENDING ? AttendanceDailyRun::STATUS_PULLED : $run->status,
            ])->save();
            $this->info("{$key}: {$log->message}");

            return true;
        } finally {
            $lock->release();
        }
    }

    /** Device days into ERP attendance, when automatic writing is on. */
    protected function write(Carbon $date): string
    {
        if (!($this->setting?->auto_promote)) {
            return 'ERP not written (automatic writing is off)';
        }

        $run = (new AttendanceSync(new IappConfig()))->run($date->copy()->startOfDay(), $date->copy()->endOfDay(), IappAttendanceSyncRun::MODE_SKIP);

        return $run->message;
    }

    /** Rebuild, rewrite and re-check a day already done, without telling anybody again. */
    protected function topUp(Carbon $date, Carbon $now): void
    {
        $run = AttendanceDailyRun::where('date', $date->toDateString())->where('status', AttendanceDailyRun::STATUS_REVIEWED)->first();

        if (!$run) {
            return;
        }

        (new PunchAggregator(new IappConfig()))->build($date->copy()->startOfDay(), $date->copy()->endOfDay());
        $this->write($date);
        (new DailyAttendanceReview())->review($date, (bool) $run->device_complete, $now);
        (new BiometricReconciler($this->setting?->company_id))->run($date, (bool) $run->device_complete, $now, $run->last_error);
        $run->forceFill(['reconciled_at' => now()])->save();
    }

    /**
     * Safety net for anything the instant refresh missed (a leave approved
     * through a bulk import, a row edited in the database): the past week is
     * judged again quietly.
     */
    protected function refreshRecent(Carbon $now): void
    {
        $review = new DailyAttendanceReview();
        $yesterday = $now->copy()->subDay()->startOfDay();

        AttendanceDailyRun::query()
            ->whereBetween('date', [$yesterday->copy()->subDays(self::LOOK_BACK_DAYS)->toDateString(), $yesterday->copy()->subDays(3)->toDateString()])
            ->where('status', AttendanceDailyRun::STATUS_REVIEWED)
            ->get()
            ->each(fn ($run) => $review->review(Carbon::parse($run->date->toDateString(), $this->tz), (bool) $run->device_complete));
    }

    protected function notifyHr(string $date, array $summary, bool $deviceComplete): void
    {
        if (($summary['exceptions'] ?? 0) === 0 && $deviceComplete) {
            return;
        }

        $recipients = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'hr']))
            ->get();

        Notification::send($recipients, new AttendanceDailyDigest($date, $summary, $deviceComplete));
    }

    /**
     * Each employee hears about their own day once: no attendance and no leave,
     * leave not approved, a missing exit, or too few hours to earn the day.
     */
    protected function notifyEmployees(string $date): void
    {
        $reviews = AttendanceReview::with('user')
            ->where('date', $date)
            ->whereNull('employee_notified_at')
            ->whereNull('resolved_at')
            ->get()
            // Still running when reviewed: judged the following night, told then.
            ->reject(fn (AttendanceReview $review) => in_array('shift_in_progress', $review->flags ?? [], true))
            ->filter(function (AttendanceReview $review) {
                $flags = $review->flags ?? [];

                return in_array($review->status, [AttendanceReview::ABSENT, AttendanceReview::LEAVE_PENDING, AttendanceReview::SHORT_HOURS], true)
                    || in_array('missing_exit', $flags, true)
                    || in_array('single_punch', $flags, true);
            })
            // Unreliable device data must not accuse anybody of being absent.
            ->reject(fn (AttendanceReview $review) => in_array('device_incomplete', $review->flags ?? [], true) && $review->status === AttendanceReview::ABSENT)
            // Only people who record attendance at all: on the device, or using web
            // clock-in lately. Somebody who never does (a director, a remote
            // consultant) would otherwise be told "absent" every single day; HR
            // still sees them in the summary.
            ->filter(fn (AttendanceReview $review) => !in_array('not_on_device', $review->flags ?? [], true)
                || in_array($review->user_id, $this->webUsers(), true));

        foreach ($reviews as $review) {
            if ($review->user && $review->user->status === 'active') {
                $review->user->notify(new AttendanceExceptionNotice($review));
                $review->forceFill(['employee_notified_at' => now()])->save();
            }
        }
    }

    /** @return int[] users who clocked in on the web in the last 30 days */
    protected function webUsers(): array
    {
        return $this->webUsers ??= DB::table('attendances')
            ->where('clock_in_time', '>=', now()->subDays(30))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('iapp_attendance_links as l')
                    ->whereColumn('l.attendance_id', 'attendances.id');
            })
            ->distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    protected ?array $webUsers = null;

    protected function describe(array $summary, array $checked): string
    {
        $parts = [($summary['exceptions'] ?? 0) . ' review exception(s)'];

        foreach (AttendanceReview::STATUSES as $status => [$label]) {
            if (($summary[$status] ?? 0) > 0) {
                $parts[] = $summary[$status] . ' ' . strtolower($label);
            }
        }

        $parts[] = sprintf('cross-check: %d sync issue(s), %d punch issue(s)', $checked['sync'], $checked['punch']);

        return implode(', ', $parts);
    }

}
