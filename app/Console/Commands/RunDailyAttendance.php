<?php

namespace App\Console\Commands;

use App\Models\AttendanceDailyRun;
use App\Models\AttendanceReview;
use App\Models\IappSetting;
use App\Models\User;
use App\Notifications\AttendanceDailyDigest;
use App\Notifications\AttendanceExceptionNotice;
use App\Services\Attendance\DailyAttendanceReview;
use App\Services\Iapp\Pipeline\AttendancePipeline;
use App\Services\Iapp\Pipeline\PipelineStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The daily attendance routine, run by the scheduler every 15 minutes and doing
 * nothing until the daily time (Biometric Settings, 11:00 by default):
 *
 *   1. pull yesterday from the device and write it into ERP attendance
 *   2. review every active employee's day (DailyAttendanceReview)
 *   3. tell HR and admins (one summary) and each employee about their own exception
 *   4. let the payslip run look at whether a pay cycle has just ended
 *
 * The device link drops out, so a failed pull is retried on every tick until
 * the evening cutoff; after that the day is reviewed on what is on record and
 * marked "device incomplete", and pulled again on the following days until it
 * goes through. A day already done is never notified twice.
 */
class RunDailyAttendance extends Command
{

    protected $signature = 'attendance:daily
                            {--date= : Review this date (YYYY-MM-DD) now, pulling it first}
                            {--scheduled : Run by the scheduler: waits for the daily time and honours "Scheduled sync"}
                            {--no-pull : Review what is on record without pulling from the device}
                            {--no-notify : Do not send any notifications}';

    protected $description = 'Pull yesterday from the device, write ERP attendance, review every employee and notify';

    /** After this local time a failed pull stops being retried for the day. */
    public const GIVE_UP_AT = '19:00';

    /** How far back a failed or incomplete day is retried. */
    public const LOOK_BACK_DAYS = 7;

    protected string $tz;

    public function handle(): int
    {
        $this->tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
        $now = Carbon::now($this->tz);
        $setting = IappSetting::first();
        $status = new PipelineStatus();

        if ($this->option('scheduled')) {
            if ($setting && !$setting->sync_enabled) {
                return self::SUCCESS;   // switched off on Biometric Settings
            }

            $at = $setting?->daily_pull_time ?: '11:00';

            if ($now->lt(Carbon::parse($now->toDateString() . ' ' . $at, $this->tz))) {
                return self::SUCCESS;   // not time yet
            }
        }

        $reviewedSomething = false;

        foreach ($this->dates($now) as $date) {
            $reviewedSomething = $this->process($date, $now, $status, $setting) || $reviewedSomething;
        }

        // A day reviewed may complete a pay cycle's data; the payslip run decides.
        if ($reviewedSomething) {
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

        // Earlier days only come back if they are on record as not finished.
        $retry = AttendanceDailyRun::query()
            ->whereBetween('date', [$yesterday->copy()->subDays(self::LOOK_BACK_DAYS)->toDateString(), $yesterday->copy()->subDay()->toDateString()])
            ->where(fn ($q) => $q->where('status', '!=', AttendanceDailyRun::STATUS_REVIEWED)->orWhere('device_complete', false))
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d, $this->tz)->startOfDay())
            ->all();

        return array_merge($retry, [$yesterday]);
    }

    /** @return bool whether the date was (re)reviewed */
    protected function process(Carbon $date, Carbon $now, PipelineStatus $status, ?IappSetting $setting): bool
    {
        $key = $date->toDateString();
        $run = AttendanceDailyRun::firstOrCreate(
            ['company_id' => $setting?->company_id, 'date' => $key],
            ['status' => AttendanceDailyRun::STATUS_PENDING]
        );

        $explicit = (bool) $this->option('date');
        $deviceInUse = $status->deviceInUse();

        // A full pull that already reached past this date (a manual Pull now, a
        // catch-up) has brought the day in; no need to ask the device again.
        // Yesterday is always pulled fresh at the daily time, so late uploads from
        // a controller that was offline are caught; this only spares older days.
        $isYesterday = $date->isSameDay($now->copy()->subDay());

        if ($deviceInUse && !$isYesterday && !$run->device_complete && ($through = $status->deviceDataThrough()) && $through->gte($date)) {
            $run->forceFill(['device_complete' => true, 'pulled_at' => $run->pulled_at ?? now()])->save();
        }

        if (!$explicit && $run->status === AttendanceDailyRun::STATUS_REVIEWED && ($run->device_complete || !$deviceInUse)) {
            return false;   // done
        }

        // 1. pull, unless this date's device data is already in
        $needsPull = $deviceInUse && !$this->option('no-pull') && !$run->device_complete;

        if ($needsPull) {
            $run->attempts++;
            $result = (new AttendancePipeline())->run($date->copy()->startOfDay(), $date->copy()->endOfDay(), 'daily', null, null, true);

            if ($result['ok']) {
                $run->forceFill([
                    'status' => AttendanceDailyRun::STATUS_PULLED,
                    'device_complete' => true,
                    'pulled_at' => now(),
                    'last_error' => null,
                ])->save();
                $this->info("{$key}: {$result['message']}");
            }
            else {
                $run->forceFill(['last_error' => $result['message']])->save();
                $this->warn("{$key}: {$result['message']}");

                $giveUp = Carbon::parse($now->toDateString() . ' ' . self::GIVE_UP_AT, $this->tz);

                // Keep trying through the day; review on what is on record only
                // once yesterday has run out of chances (or for older days, which
                // were reviewed already and are only being topped up).
                if ($this->option('scheduled') && ($run->status === AttendanceDailyRun::STATUS_REVIEWED || ($isYesterday && $now->lt($giveUp)))) {
                    return false;
                }
            }
        }

        // 2. review
        $deviceComplete = (bool) $run->device_complete || !$deviceInUse;
        $summary = (new DailyAttendanceReview())->review($date, $deviceComplete);

        $run->forceFill([
            'status' => AttendanceDailyRun::STATUS_REVIEWED,
            'reviewed_at' => now(),
            'summary' => $this->describe($summary),
        ])->save();

        $this->info("{$key}: reviewed — {$run->summary}" . ($deviceComplete ? '' : ' (device data incomplete)'));

        // 3. notify, once per date
        if (!$this->option('no-notify') && !$run->notified_at) {
            $this->notifyHr($key, $summary, $deviceComplete);

            if ($setting?->notify_employees ?? true) {
                $this->notifyEmployees($key);
            }

            $run->forceFill(['notified_at' => now()])->save();
        }

        return true;
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

    protected function describe(array $summary): string
    {
        $parts = [($summary['exceptions'] ?? 0) . ' exception(s)'];

        foreach (AttendanceReview::STATUSES as $status => [$label]) {
            if (($summary[$status] ?? 0) > 0) {
                $parts[] = $summary[$status] . ' ' . strtolower($label);
            }
        }

        return implode(', ', $parts);
    }

}
