<?php

namespace App\Http\Controllers;

use App\Models\IappAttendanceLink;
use App\Models\IappAttendanceRecord;
use App\Models\IappAttendanceSyncRun;
use App\Models\IappBackfillRun;
use App\Models\IappDaySync;
use App\Models\IappDevice;
use App\Models\IappDeviceTransaction;
use App\Models\IappEmployee;
use App\Models\IappSetting;
use App\Models\IappSyncLog;
use App\Models\Team;
use App\Models\User;
use App\Helper\Reply;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Reports\AttendanceExceptions;
use App\Services\Iapp\Reports\EmployeeAttendanceProfile;
use App\Services\Iapp\Reports\DepartedEnrolments;
use App\Services\Iapp\Reports\EmployeeComparison;
use App\Services\Iapp\Reports\ShiftReport;
use App\Services\Iapp\Shifts\ShiftDetector;
use App\Services\Iapp\Sync\AttendanceSync;
use App\Services\Iapp\Sync\BackfillRunner;
use App\Services\Iapp\Sync\EmployeeMatcher;
use App\Services\Iapp\Sync\EmployeeSync;
use App\Services\Iapp\Sync\PunchAggregator;
use App\Services\Iapp\Sync\PunchSync;
use App\Services\Iapp\Pipeline\AttendancePipeline;
use App\Services\Iapp\Pipeline\PipelineStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only screens over the data pulled from the iApp biometric platform.
 *
 * Nothing here writes to `attendances` — promotion is the sync layer's job.
 * These pages exist so device data can be inspected in the ERP before and
 * after it becomes attendance.
 */
class BiometricController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.biometric';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array('attendance', user_modules()));
            // Every screen here shows the whole device register — the punch log,
            // the mapping list, the comparison, the employee dropdown on the
            // profile. None of it is scoped to the person signed in, so seeing
            // one's own attendance is not enough; it takes the permission to see
            // everybody's. An employee with 'owned' could otherwise read the
            // whole workforce's movements.
            abort_403(user()->permission('view_attendance') !== 'all');

            // Then the Biometric permission this screen or action belongs to
            // (Settings -> Roles & Permissions -> Attendance): HR sees and fixes
            // the day-to-day; device data, sync and logs are technical.
            $needs = self::ACTION_PERMISSIONS[$request->route()?->getActionMethod()] ?? 'manage_biometric_system';
            abort_403(!collect((array) $needs)->contains(fn ($p) => user()->permission($p) === 'all'));

            return $next($request);
        });
    }

    /** Action => the permission it needs (any of a list). Anything not listed is technical. */
    public const ACTION_PERMISSIONS = [
        'overview' => 'view_biometric', 'review' => 'view_biometric', 'exceptions' => 'view_biometric', 'profile' => 'view_biometric',
        'punchLog' => 'view_biometric',
        'resolveReview' => 'manage_biometric_exceptions', 'runReview' => 'manage_biometric_exceptions',
        'exceptionAction' => 'manage_biometric_exceptions', 'resyncDay' => 'manage_biometric_exceptions',
        // "Get latest punches": HR's refresh, or the technical pull with any range.
        'sync' => ['manage_biometric_exceptions', 'manage_biometric_system'],
        'employees' => 'manage_biometric_mapping', 'syncEmployees' => 'manage_biometric_mapping', 'uploadMachineList' => 'manage_biometric_mapping',
        'mapEmployee' => 'manage_biometric_mapping', 'bulkMapEmployees' => 'manage_biometric_mapping',
        'comparison' => 'manage_biometric_mapping', 'createFromDevice' => 'manage_biometric_mapping',
    ];

    /** What the signed-in person may do here, for the screens to show only that. */
    public static function access(): array
    {
        $can = fn ($p) => user()->permission($p) === 'all';

        return ['view' => $can('view_biometric'), 'resolve' => $can('manage_biometric_exceptions'),
            'mapping' => $can('manage_biometric_mapping'), 'system' => $can('manage_biometric_system')];
    }

    /**
     * Day-wise device attendance, straight from iApp's getDetail feed.
     */
    public function attendance(Request $request)
    {
        $this->pageTitle = 'app.menu.deviceAttendance';
        $this->activeTab = 'attendance';

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->departmentId = $request->department_id;
        $this->userId = $request->user_id;
        $this->dayType = $request->day_type;
        $this->controllerCode = $request->controller_code;

        $this->records = IappAttendanceRecord::with('user:id,name,image')
            ->betweenDates($startDate, $endDate)
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->day_type, fn ($q) => $q->where('day_type', $request->day_type))
            ->when($request->controller_code, function ($q) use ($request) {
                // controller_code holds the arrival reader; controller_codes
                // lists every device touched that day, so a filter on a
                // mid-day door still finds the record.
                $q->where(function ($sub) use ($request) {
                    $sub->where('controller_code', $request->controller_code)
                        ->orWhereJsonContains('controller_codes', $request->controller_code);
                });
            })
            ->when($request->department_id, function ($q) use ($request) {
                $q->whereHas('user.employeeDetail', fn ($sub) => $sub->where('department_id', $request->department_id));
            })
            ->orderByDesc('attendance_date')
            ->orderBy('emp_id')
            ->paginate(50)
            ->withQueryString();

        $this->markWhatReachedTheErp($this->records);

        $this->summary = $this->buildSummary($startDate, $endDate);
        $this->departments = Team::allDepartments();
        $this->employees = User::allEmployees(null, true);
        $this->devices = $this->deviceOptions();
        $this->punchCount = IappDeviceTransaction::whereBetween('punch_time', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ])->count();

        return view('biometric.attendance', $this->data);
    }

    /**
     * Pull punches for the selected range and rebuild the attendance days.
     *
     * Two steps, deliberately: fetch from iApp, then derive. Re-running is
     * safe — the punch upsert is keyed on a hash and the rebuild replaces
     * only rows it previously derived.
     */
    /**
     * Pull from the device now: everybody, or one employee (emp_code).
     *
     * Runs the whole AttendancePipeline - pull, build Device Attendance, and
     * write ERP attendance when automatic push is on (or push=1) - so a manual
     * pull leaves things exactly as the schedule would.
     */
    public function sync(Request $request)
    {
        abort_403(!in_array(user()->permission('view_attendance'), ['all', 'added', 'owned', 'both']));

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $from = Carbon::parse($startDate)->startOfDay();
        $to = Carbon::parse($endDate)->endOfDay();
        $empCode = trim((string) $request->emp_code) ?: null;

        // HR's "Get latest punches" is yesterday and today, for everybody; a
        // chosen range or person is the technical pull.
        if (user()->permission('manage_biometric_system') !== 'all') {
            $today = (new PipelineStatus())->today();
            $from = $today->copy()->subDay()->startOfDay();
            $to = $today->copy()->endOfDay();
            $empCode = null;
        }

        // A pull walks the feed 100 rows at a time and can run for minutes.
        // PHP holds the session file locked for a whole request, so without
        // this the rest of the UI freezes for the duration - every other page
        // the same browser asks for queues behind this one. Nothing after this
        // point writes to the session.
        session()->save();

        // A wide range means many sequential calls, linear in punches. One
        // employee's punches are a small fraction of the feed, so a longer
        // stretch is fine for them.
        $limit = $empCode ? 92 : 31;

        if ($from->diffInDays($to) > $limit) {
            return Reply::error(sprintf('Please select a range of %d days or fewer.', $limit));
        }

        $result = (new AttendancePipeline())->run(
            $from,
            $to,
            'manual',
            user()->id,
            $empCode,
            $request->has('push') ? $request->boolean('push') : null
        );

        return $result['ok']
            ? Reply::successWithData($result['message'], ['log_id' => $result['pull']?->id])
            : Reply::error($result['message']);
    }

    /**
     * Daily Review: every active employee's day, judged the morning after -
     * absent without leave, late, short hours, missing exit, leave clashes -
     * with what HR has resolved. Filled by attendance:daily; "Review again"
     * re-runs it for the date shown.
     */
    public function review(Request $request)
    {
        $this->pageTitle = 'app.menu.dailyReview';
        $this->activeTab = 'review';
        $this->canAct = user()->permission('manage_biometric_exceptions') === 'all';

        $tz = company()->timezone;
        $yesterday = now($tz)->subDay()->startOfDay();

        try {
            $this->date = $request->date ? Carbon::parse($request->date, $tz)->startOfDay() : $yesterday;
        } catch (\Throwable) {
            $this->date = $yesterday;
        }

        $this->show = in_array($request->show, ['exceptions', 'all', 'open'], true) ? $request->show : 'exceptions';
        $this->statusFilter = array_key_exists((string) $request->status, \App\Models\AttendanceReview::STATUSES) ? $request->status : null;
        $this->flagFilter = array_key_exists((string) $request->flag, \App\Models\AttendanceReview::FLAGS) ? $request->flag : null;

        $all = \App\Models\AttendanceReview::with('user:id,name,image', 'resolvedBy:id,name')
            ->where('date', $this->date->toDateString())
            ->get();

        $this->counts = [
            'employees' => $all->count(),
            'exceptions' => $all->where('is_exception', true)->count(),
            'open' => $all->where('is_exception', true)->whereNull('resolved_at')->count(),
            'absent' => $all->where('status', \App\Models\AttendanceReview::ABSENT)->count(),
            'short' => $all->whereIn('status', [\App\Models\AttendanceReview::HALF_DAY, \App\Models\AttendanceReview::SHORT_HOURS])->count(),
            'missing_exit' => $all->filter(fn ($r) => array_intersect($r->flags ?? [], ['missing_exit', 'single_punch']) !== [])->count(),
            'late' => $all->filter(fn ($r) => in_array('late', $r->flags ?? [], true))->count(),
            'leave' => $all->whereIn('status', [\App\Models\AttendanceReview::LEAVE_WORKED, \App\Models\AttendanceReview::LEAVE_PENDING])->count(),
        ];

        $this->reviews = $all
            ->when($this->show === 'exceptions', fn ($c) => $c->where('is_exception', true))
            ->when($this->show === 'open', fn ($c) => $c->where('is_exception', true)->whereNull('resolved_at'))
            ->when($this->statusFilter, fn ($c) => $c->where('status', $this->statusFilter))
            ->when($this->flagFilter, fn ($c) => $c->filter(fn ($r) => in_array($this->flagFilter, $r->flags ?? [], true)))
            ->sortBy(fn ($r) => [$r->is_exception ? 0 : 1, $r->resolved_at ? 1 : 0, array_search($r->status, array_keys(\App\Models\AttendanceReview::STATUSES)), $r->user?->name])
            ->values();

        // Employee 360 is keyed by the device register, not the user.
        $this->profileIds = IappEmployee::whereIn('user_id', $all->pluck('user_id'))->where('map_status', IappEmployee::STATUS_MAPPED)->pluck('id', 'user_id');
        $this->run = \App\Models\AttendanceDailyRun::where('date', $this->date->toDateString())->first();
        $this->setting = IappSetting::first();
        $this->canGoForward = $this->date->lt($yesterday);

        return view('biometric.review', $this->data);
    }

    /** Mark one employee-day resolved (with a note), or open it again. */
    public function resolveReview(Request $request)
    {
        abort_403(!in_array(user()->permission('view_attendance'), ['all', 'added', 'owned', 'both']));

        $this->validate($request, ['id' => 'required|integer', 'note' => 'nullable|string|max:500', 'reopen' => 'nullable|boolean']);

        $review = \App\Models\AttendanceReview::findOrFail($request->id);

        if ($request->boolean('reopen')) {
            $review->forceFill(['resolved_at' => null, 'resolved_by' => null])->save();

            return Reply::success('Opened again.');
        }

        $review->forceFill(['resolved_at' => now(), 'resolved_by' => user()->id, 'note' => $request->note])->save();

        return Reply::success('Marked resolved.');
    }

    /** Run the daily routine for the date shown: pull (optional), review. No notifications. */
    public function runReview(Request $request)
    {
        abort_403(!in_array(user()->permission('view_attendance'), ['all', 'added', 'owned', 'both']));

        $this->validate($request, ['date' => 'required|date']);

        // The pull can take minutes; free the session so the rest of the UI keeps working.
        session()->save();

        $options = ['--date' => Carbon::parse($request->date)->toDateString(), '--no-notify' => true];

        if (!$request->boolean('pull')) {
            $options['--no-pull'] = true;
        }

        \Illuminate\Support\Facades\Artisan::call('attendance:daily', $options);

        $run = \App\Models\AttendanceDailyRun::where('date', $options['--date'])->first();

        return $run && $run->status === \App\Models\AttendanceDailyRun::STATUS_REVIEWED
            ? Reply::success('Reviewed: ' . $run->summary . ($run->device_complete ? '' : ' — device data incomplete' . ($run->last_error ? ' (' . $run->last_error . ')' : '')))
            : Reply::error('Could not review: ' . ($run?->last_error ?: 'see Sync Status'));
    }

    /**
     * The biometric -> attendance -> payslip chain on one screen: where each
     * step stands, what needs attention, and the buttons to run it now.
     */
    public function overview()
    {
        $this->pageTitle = 'app.menu.biometricOverview';
        $this->activeTab = 'overview';

        $status = new PipelineStatus();
        $this->status = $status;
        $this->setting = IappSetting::first();
        $this->lastRun = AttendancePipeline::lastRun();
        $this->lastPull = $status->lastPull();
        $this->lastPush = $status->lastPush();
        $this->dataThrough = $status->deviceDataThrough();

        $this->currentCycle = $status->currentCycle();
        $this->lastCycle = $status->lastEndedCycle();
        $this->pendingPush = $status->pendingPushDays(
            $this->lastCycle->start->copy()->startOfDay(),
            $status->today()
        );
        $this->unmapped = $status->unmappedEmployees();
        // The same count the Exceptions screen shows for the current cycle.
        $this->exceptionDays = (new AttendanceExceptions(
            $this->currentCycle->start->copy(),
            $status->today()
        ))->query()->count();
        $this->departed = app(\App\Services\Iapp\Reports\DepartedEnrolments::class)->outstanding();

        $this->lastPayslipRun = \Illuminate\Support\Facades\Cache::store('file')->get(\App\Console\Commands\GenerateDuePayslips::LAST_RUN_KEY);
        $this->lastCycleSlips = \Illuminate\Support\Facades\DB::table('payslips')
            ->where('period', $this->lastCycle->label)->where('status', 'active')->whereNull('deleted_at')
            ->selectRaw('approval_status, count(*) as n')->groupBy('approval_status')->pluck('n', 'approval_status');

        // Yesterday's daily run and what it left open.
        $this->yesterday = $status->today()->copy()->subDay();
        $this->dailyRun = \App\Models\AttendanceDailyRun::where('date', $this->yesterday->toDateString())->first();
        $this->openReviews = \App\Models\AttendanceReview::where('date', $this->yesterday->toDateString())
            ->where('is_exception', true)->whereNull('resolved_at')->count();

        // For the one-employee pull: everybody linked to a device code.
        $this->mappedEmployees = IappEmployee::where('map_status', IappEmployee::STATUS_MAPPED)
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->orderBy('employee_name')
            ->get(['emp_code', 'employee_name', 'user_id']);

        $this->access = self::access();
        $this->todayStats = $this->todayStats($status->today());

        return view('biometric.overview', $this->data);
    }

    /**
     * Today at a glance, for HR: who is in, late, on leave, not in yet - as far
     * as the punches pulled so far go.
     */
    protected function todayStats(Carbon $today): array
    {
        $tz = company()->timezone ?: 'Asia/Kolkata';
        $from = Carbon::parse($today->toDateString(), $tz)->startOfDay()->utc();
        $to = $from->copy()->addDay();

        $active = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')->whereExists(fn ($q) => $q->from('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->whereColumn('ru.user_id', 'u.id')->where('r.name', 'employee'))
            ->where(fn ($q) => $q->whereNull('ed.joining_date')->orWhere('ed.joining_date', '<=', $today->toDateString()))
            ->pluck('u.id');

        $in = DB::table('attendances')->whereIn('user_id', $active)->whereBetween('clock_in_time', [$from, $to]);
        $present = (clone $in)->distinct()->count('user_id');
        $late = (clone $in)->where('late', 'yes')->distinct()->count('user_id');
        $onLeave = DB::table('leaves')->whereIn('user_id', $active)->where('status', 'approved')
            ->whereDate('leave_date', $today->toDateString())->distinct()->count('user_id');

        // The company's default shift says whether today is worked and when it starts.
        $shift = \App\Services\Attendance\ShiftResolver::shared((int) company()->id)->shiftOn(0, $today->toDateString());
        $workingDay = \App\Services\Attendance\ShiftResolver::worksOn($shift, $today->toDateString());
        $started = !$shift || empty($shift->office_start_time)
            || now($tz)->gte(Carbon::parse($today->toDateString() . ' ' . $shift->office_start_time, $tz));

        $holiday = \App\Models\Holiday::whereDate('date', $today->toDateString())->value('occassion');

        return [
            'date' => $today, 'active' => $active->count(), 'present' => $present, 'late' => $late, 'on_leave' => $onLeave,
            'not_in' => max(0, $active->count() - $present - $onLeave),
            'holiday' => $holiday, 'week_off' => !$workingDay,
            // "Not in yet" is a concern only on a working day once the shift has begun.
            'not_in_alert' => $workingDay && !$holiday && $started,
        ];
    }

    /**
     * Month calendar of what has been pulled, and the controls to pull more.
     *
     * The day cells are the point: HR works a day at a time, so each one shows
     * whether that date has been synced, what it holds, and offers a re-sync.
     */
    public function calendar(Request $request, BackfillRunner $runner)
    {
        $this->pageTitle = 'app.menu.syncCalendar';
        $this->activeTab = 'calendar';

        $month = $this->resolveMonth($request);

        $this->month = $month;
        $this->monthLabel = $month->format('F Y');
        $this->prevMonth = $month->copy()->subMonth()->format('Y-m');
        $this->nextMonth = $month->copy()->addMonth()->format('Y-m');
        // Nothing to pull past today, so the forward arrow stops at this month.
        $this->canGoForward = $month->copy()->addMonth()->startOfMonth()->lte(now()->startOfMonth());

        $this->monthOptions = $this->monthOptions($runner);
        $this->weeks = $this->calendarWeeks($month);
        $this->summary = $this->monthSummary($this->weeks);
        $this->activeRun = $runner->active();
        $this->earliest = $runner->earliestAvailable();

        // Re-sync picks its own devices; ticked by default as Settings has them.
        $this->deviceOptions = self::deviceOptions()->all();
        $this->selectedDevices = (new \App\Services\Iapp\IappConfig())->pullDevices() ?? array_keys($this->deviceOptions);

        return view('biometric.calendar', $this->data);
    }

    /**
     * Begin pulling, or adopt the run already in flight.
     *
     * Accepts the three shapes the calendar offers: specific dates picked in
     * the grid, a whole month, or an arbitrary range. All become a list of
     * dates, so the runner treats them identically.
     */
    public function startBackfill(Request $request, BackfillRunner $runner)
    {
        // It writes: punches, device days and, through them, attendance.
        abort_403(!in_array(user()->permission('add_attendance'), ['all', 'added']));

        $scope = $request->scope ?: 'range';

        $dates = match ($scope) {
            'days' => (array) $request->dates,
            'month' => $runner->datesInRange(
                Carbon::parse($request->month . '-01')->startOfMonth(),
                Carbon::parse($request->month . '-01')->endOfMonth()
            ),
            default => $runner->datesInRange(
                Carbon::parse($request->start_date ?: now()->toDateString()),
                Carbon::parse($request->end_date ?: now()->toDateString())
            ),
        };

        $dates = $runner->cleanDates($dates);

        if ($dates === []) {
            return Reply::error('Select at least one date that is not in the future.');
        }

        // Which devices this re-sync pulls. Every device ticked is the same as
        // "all", so it is stored as such and follows devices added later.
        $known = array_map('strval', self::deviceOptions()->keys()->all());
        $devices = array_values(array_intersect(array_map('strval', (array) $request->devices), $known));

        if ($request->has('devices') && $devices === []) {
            return Reply::error('Tick at least one device to pull from.');
        }

        $devices = (!$request->has('devices') || count($devices) === count($known)) ? null : $devices;

        $run = $runner->start($dates, $scope, user()->id, $devices);

        if (!$run) {
            return Reply::error('Nothing to sync for that selection.');
        }

        return Reply::successWithData('Sync started.', ['run' => $this->runPayload($run)]);
    }

    /**
     * Advance an in-flight pull by one time-boxed step.
     *
     * Returns the run's state, plus the day cells it has finished, so the
     * calendar can repaint them without a reload.
     */
    public function stepBackfill(Request $request, BackfillRunner $runner)
    {
        // It writes: punches, device days and, through them, attendance.
        abort_403(!in_array(user()->permission('add_attendance'), ['all', 'added']));

        $run = $this->findRun($request) ?? $runner->active();

        if (!$run) {
            return Reply::error('That sync run no longer exists.');
        }

        $run = $runner->step($run);

        return Reply::successWithData($run->message ?: 'Working.', [
            'run' => $this->runPayload($run),
            'days' => $this->dayStates($run->dateList()),
        ]);
    }

    /**
     * Current state of a pull, for a page opened while one is already running.
     */
    public function backfillStatus(Request $request, BackfillRunner $runner)
    {
        $run = $this->findRun($request) ?? $runner->active();

        return Reply::successWithData('ok', [
            'run' => $run ? $this->runPayload($run) : null,
            'days' => $run ? $this->dayStates($run->dateList()) : [],
        ]);
    }

    /**
     * Pause, resume or cancel a pull. Days already pulled are always kept.
     */
    public function controlBackfill(Request $request, BackfillRunner $runner)
    {
        // It writes: punches, device days and, through them, attendance.
        abort_403(!in_array(user()->permission('add_attendance'), ['all', 'added']));

        $run = $this->findRun($request) ?? $runner->active();

        if (!$run) {
            return Reply::error('There is no sync run to act on.');
        }

        $run = match ($request->action) {
            'pause' => $runner->pause($run),
            'resume' => $runner->resume($run),
            'cancel' => $runner->cancel($run),
            default => $run,
        };

        return Reply::successWithData($run->message ?: 'Updated.', ['run' => $this->runPayload($run)]);
    }

    private function findRun(Request $request): ?IappBackfillRun
    {
        return $request->run_id
            ? IappBackfillRun::withoutGlobalScopes()->find($request->run_id)
            : null;
    }

    /**
     * The month being shown, defaulting to the current one and never running
     * past it.
     */
    private function resolveMonth(Request $request): Carbon
    {
        $month = $request->month
            ? Carbon::parse($request->month . '-01')->startOfMonth()
            : now()->startOfMonth();

        return $month->gt(now()->startOfMonth()) ? now()->startOfMonth() : $month;
    }

    /**
     * Months offered in the picker: every month from the earliest data the
     * platform holds up to this one, newest first.
     */
    private function monthOptions(BackfillRunner $runner): array
    {
        $options = [];
        $cursor = $runner->earliestAvailable()->startOfMonth();
        $last = now()->startOfMonth();

        while ($cursor->lte($last)) {
            $options[] = ['value' => $cursor->format('Y-m'), 'label' => $cursor->format('F Y')];
            $cursor->addMonth();
        }

        return array_reverse($options);
    }

    /**
     * The month laid out as calendar weeks, Monday first, with each day's sync
     * state attached.
     *
     * Leading and trailing cells come from the neighbouring months so the grid
     * stays square; they are marked so the view can grey them out.
     */
    private function calendarWeeks(Carbon $month): array
    {
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $states = $this->dayStates($this->datesBetween($gridStart, $gridEnd));

        $weeks = [];
        $week = [];
        $day = $gridStart->copy();

        while ($day->lte($gridEnd)) {
            $date = $day->toDateString();

            $week[] = [
                'date' => $date,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'is_today' => $day->isToday(),
                'is_future' => $day->copy()->startOfDay()->gt(now()->startOfDay()),
                'is_weekend' => $day->isWeekend(),
            ] + $states[$date];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $day->addDay();
        }

        return $weeks;
    }

    /**
     * Sync state for a set of dates, in one query.
     *
     * A date with no ledger row has never been pulled - that is the distinction
     * the calendar exists to show, so it is never conflated with an empty day.
     *
     * @param  array<int, string>  $dates
     */
    private function dayStates(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $rows = IappDaySync::whereIn('sync_date', $dates)->get()
            ->keyBy(fn ($row) => $row->sync_date->toDateString());

        $states = [];

        foreach ($dates as $date) {
            $row = $rows[$date] ?? null;

            $states[$date] = [
                'status' => $row->status ?? IappDaySync::STATUS_NEVER,
                'status_label' => $row
                    ? $row->status_label
                    : IappDaySync::LABELS[IappDaySync::STATUS_NEVER],
                'status_color' => $row
                    ? $row->status_color
                    : IappDaySync::COLORS[IappDaySync::STATUS_NEVER],
                'punches' => (int) ($row->punch_count ?? 0),
                'employees' => (int) ($row->employee_count ?? 0),
                'attendance_days' => (int) ($row->attendance_days ?? 0),
                'synced_at' => $row?->last_synced_at?->format('d M Y H:i'),
                'sync_count' => (int) ($row->sync_count ?? 0),
                'error' => $row->last_error ?? null,
            ];
        }

        return $states;
    }

    /** @return array<int, string> */
    private function datesBetween(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $day = $from->copy()->startOfDay();

        while ($day->lte($to)) {
            $dates[] = $day->toDateString();
            $day->addDay();
        }

        return $dates;
    }

    /**
     * Headline counts for the month shown, ignoring the padding cells.
     */
    private function monthSummary(array $weeks): array
    {
        $summary = ['days' => 0, 'synced' => 0, 'never' => 0, 'empty' => 0, 'failed' => 0, 'punches' => 0];

        foreach ($weeks as $week) {
            foreach ($week as $cell) {
                if (!$cell['in_month'] || $cell['is_future']) {
                    continue;
                }

                $summary['days']++;
                $summary['punches'] += $cell['punches'];

                match ($cell['status']) {
                    IappDaySync::STATUS_OK => $summary['synced']++,
                    IappDaySync::STATUS_EMPTY => $summary['empty']++,
                    IappDaySync::STATUS_FAILED => $summary['failed']++,
                    default => $summary['never']++,
                };
            }
        }

        return $summary;
    }

    /**
     * The shape the sync panel renders from.
     */
    private function runPayload(IappBackfillRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'status_label' => $run->status_label,
            'status_color' => $run->status_color,
            'scope' => $run->scope,
            'progress' => $run->progress(),
            'days_done' => $run->daysDone(),
            'day_count' => $run->dayCount(),
            'current_date' => $run->currentDate(),
            'from' => $run->from_date->format('d M Y'),
            'to' => $run->to_date->format('d M Y'),
            'punches_created' => (int) $run->punches_created,
            'punches_fetched' => (int) $run->punches_fetched,
            'days_built' => (int) $run->days_built,
            'message' => $run->message,
            'error' => $run->last_error,
            'error_count' => (int) $run->error_count,
            'active' => $run->isActive(),
            'running' => $run->status === IappBackfillRun::STATUS_RUNNING,
        ];
    }

    /**
     * Push device punches into the ERP's own attendance.
     *
     * Deliberate and previewed: this is the only screen in the module that
     * writes to a table the rest of the ERP owns, so nothing happens until the
     * range is reviewed and confirmed.
     */
    public function attendanceSync(Request $request, AttendanceSync $sync)
    {
        $this->pageTitle = 'app.menu.attendanceSync';
        $this->activeTab = 'attendance-sync';

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->mapping = $sync->mapping();
        $this->recentRuns = IappAttendanceSyncRun::with('startedBy:id,name')
            ->orderByDesc('id')->limit(10)->get();

        return view('biometric.attendance-sync', $this->data);
    }

    /**
     * What a sync over the chosen range would do, written to nothing.
     */
    public function previewAttendanceSync(Request $request, AttendanceSync $sync)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $preview = $sync->preview(
            Carbon::parse($startDate),
            Carbon::parse($endDate),
            $this->requestedUserIds($request)
        );

        $mapping = $preview['mapping'];

        return Reply::successWithData('ok', [
            'rows' => $preview['rows'],
            'totals' => $preview['totals'],
            'unmapped' => array_map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => $e->name,
                'card' => $e->employee_card_id,
            ], $mapping['unmapped']),
            'mapped_count' => count($mapping['mapped_users']),
            'employee_count' => count($mapping['employees']),
            'map_url' => route('biometric.employees'),
        ]);
    }

    /**
     * Write the previewed days into `attendances`.
     */
    public function runAttendanceSync(Request $request, AttendanceSync $sync)
    {
        // This writes real attendance, so it needs the same permission the ERP's
        // own attendance screens require to create a record — the module-wide
        // middleware only asks for view_attendance, which a read-only user has.
        abort_403(!in_array(user()->permission('add_attendance'), ['all', 'added']));

        $mode = $request->conflict_mode === IappAttendanceSyncRun::MODE_OVERRIDE
            ? IappAttendanceSyncRun::MODE_OVERRIDE
            : IappAttendanceSyncRun::MODE_SKIP;

        // An override deletes attendance this module did not create, so it is
        // gated on the delete permission as well.
        if ($mode === IappAttendanceSyncRun::MODE_OVERRIDE) {
            abort_403(user()->permission('delete_attendance') !== 'all');
        }

        [$startDate, $endDate] = $this->resolveDateRange($request);

        // Blank means every previewed day; a list restricts to those chosen.
        $only = $request->filled('days') ? (array) $request->days : null;

        // A month for every employee is thousands of days: PHP's 120-second
        // web limit killed it part-way (status "done", nothing written). No
        // limit for this one action, the session released so the rest of the
        // app stays usable meanwhile, and it carries on to the end even if
        // the browser stops waiting.
        session()->save();
        @set_time_limit(0);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '1024M');

        $run = $sync->run(
            Carbon::parse($startDate),
            Carbon::parse($endDate),
            $mode,
            $this->requestedUserIds($request),
            user()->id,
            $only,
            $request->boolean('collapse', true)
        );

        return Reply::successWithData($run->message, [
            'run' => [
                'id' => $run->id,
                'created' => (int) $run->rows_created,
                'replaced' => (int) $run->rows_replaced,
                'skipped' => (int) $run->days_skipped,
                'days' => (int) $run->days,
                'employees' => (int) $run->employees,
                'message' => $run->message,
            ],
        ]);
    }

    /**
     * The attendance one day already holds, for the review popup.
     */
    public function reviewAttendanceDay(Request $request, AttendanceSync $sync)
    {
        $request->validate(['user_id' => 'required|integer', 'date' => 'required|date']);

        $date = Carbon::parse($request->date)->toDateString();

        return Reply::successWithData('ok', [
            'rows' => $sync->existingAttendance((int) $request->user_id, $date),
            'date' => Carbon::parse($date)->format('d M Y'),
            'employee' => User::where('id', $request->user_id)->value('name'),
        ]);
    }

    /** @return array<int,int>|null */
    private function requestedUserIds(Request $request): ?array
    {
        if (!$request->filled('employees')) {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->employees)));

        return $ids === [] ? null : $ids;
    }

    /**
     * The ERP's employee list and the devices' side by side.
     *
     * People are enrolled on the devices before they exist in the ERP, so the
     * two lists are nothing like the same size. This is where that gap is
     * visible and actionable.
     */
    public function comparison(EmployeeComparison $comparison)
    {
        $this->pageTitle = 'app.menu.employeeComparison';
        $this->activeTab = 'comparison';

        $data = $comparison->build();

        $this->matched = $data['matched'];
        $this->erpOnly = $data['erp_only'];
        $this->deviceOnly = $data['device_only'];
        $this->counts = $data['counts'];
        $this->canCreate = in_array(user()->permission('add_employees'), ['all', 'added']);

        return view('biometric.comparison', $this->data);
    }

    /**
     * Create ERP employees from biometric codes.
     *
     * Only the name and the code are written — everything else the employee
     * form asks for is left for HR, because the devices do not hold it and
     * inventing a PAN or a bank account would be worse than leaving it blank.
     */
    public function createFromDevice(Request $request, EmployeeComparison $comparison)
    {
        // Creating an employee needs the permission the ERP's own employee
        // screens require; the module middleware only checks attendance access.
        abort_403(!in_array(user()->permission('add_employees'), ['all', 'added']));

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $devices = IappEmployee::withoutGlobalScopes()->whereIn('id', $request->ids)->get();

        $created = 0;
        $failed = [];

        foreach ($devices as $device) {
            $result = $comparison->createFromDevice($device, user()->id);

            $result['ok'] ? $created++ : $failed[] = $device->emp_code . ' — ' . $result['message'];
        }

        $message = sprintf('%d employee(s) created. They have a name and a biometric code; '
            . 'the rest of their details still need filling in.', $created);

        if ($failed !== []) {
            $message .= ' Skipped: ' . implode('; ', array_slice($failed, 0, 3))
                . (count($failed) > 3 ? ' and ' . (count($failed) - 3) . ' more' : '');
        }

        // Counts as well as the message: the screen sends people in batches and
        // adds these up to show progress, so a long run reports as it goes
        // rather than sitting silent until every one is done.
        $counts = ['created' => $created, 'skipped' => count($failed), 'reasons' => $failed];

        return $created === 0
            ? array_merge(Reply::error($message), $counts)
            : Reply::successWithData($message, $counts);
    }

    /**
     * Raw punches from the access controllers.
     */
    public function punchLog(Request $request)
    {
        $this->pageTitle = 'app.menu.punchLog';
        $this->activeTab = 'punch-log';

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $request->user_id;
        $this->controllerCode = $request->controller_code;

        $this->punches = IappDeviceTransaction::with('user:id,name,image')
            ->whereBetween('punch_time', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->controller_code, fn ($q) => $q->where('controller_code', $request->controller_code))
            ->orderByDesc('punch_time')
            ->paginate(100)
            ->withQueryString();

        $this->controllers = $this->deviceOptions();
        $this->employees = User::allEmployees(null, true);

        return view('biometric.punch-log', $this->data);
    }

    /**
     * Biometric employee register and its mapping to ERP employees.
     *
     * The device side is read-only: this screen only ever sets which ERP user
     * a biometric code belongs to. Nothing is written back to iApp.
     */
    public function employees(Request $request)
    {
        $this->pageTitle = 'app.menu.biometricEmployees';
        $this->activeTab = 'employees';

        // The table is a work queue, so it opens on what still needs linking.
        // But when nothing does, that left the page showing an empty table and
        // a message telling the user to sync — while a perfectly good code sat
        // one filter away. With no queue to work, show everything instead.
        $requestedStatus = $request->map_status;

        if (!$requestedStatus) {
            $requestedStatus = IappEmployee::unmapped()->exists() ? 'unmapped' : 'all';
        }

        $this->mapStatus = $requestedStatus;
        $this->search = trim((string) $request->search);

        $query = IappEmployee::with(['user:id,name,image', 'mappedBy:id,name']);

        $this->conflicts = \App\Services\Iapp\Sync\MappingSync::openConflicts();

        match ($this->mapStatus) {
            'mapped' => $query->mapped(),
            'ignored' => $query->ignored(),
            'attention' => $query->whereIn('emp_code', array_keys($this->conflicts) ?: ['-']),
            'all' => null,
            default => $query->unmapped(),
        };

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('emp_code', 'like', $term)
                    ->orWhere('employee_name', 'like', $term)
                    ->orWhere('card_id', 'like', $term);
            });
        }

        // Most-active codes first: those are the ones worth mapping.
        $this->employeeRecords = $query->orderByDesc('punch_count')
            ->orderBy('emp_code')
            ->paginate(50)
            ->withQueryString();

        // Suggestions are computed for the visible page only, against one
        // preloaded copy of the ERP employee list.
        // One config instance per request: constructing it reads the settings
        // row, so building it twice would query twice for no reason.
        $config = new IappConfig;
        $matcher = (new EmployeeMatcher($config))->loadEmployees();

        // Built locally and assigned once. $this->data is exposed through
        // __get/__set, and writing into an array element of an overloaded
        // property has no effect — PHP raises "Indirect modification of
        // overloaded property" and the value is silently discarded.
        $suggestions = [];

        foreach ($this->employeeRecords as $record) {
            if (!$record->isMapped()) {
                $suggestions[$record->id] = $matcher->candidatesFor($record, 3);
            }
        }

        $this->suggestions = $suggestions;

        // Same reason as above: complete the array before assigning it, never
        // write into it afterwards.
        $counts = [
            'mapped' => IappEmployee::mapped()->count(),
            'unmapped' => IappEmployee::unmapped()->count(),
            'ignored' => IappEmployee::ignored()->count(),
        ];
        $counts['total'] = array_sum($counts);
        $counts['attention'] = count($this->conflicts);

        $this->counts = $counts;

        // Flattened once: the picker repeats for every row, so touching a
        // relation inside the loop would be one query per row per employee.
        $this->erpEmployees = User::allEmployees(null, true)
            ->loadMissing('employeeDetail:id,user_id,employee_id')
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'code' => optional($employee->employeeDetail)->employee_id,
            ])
            ->values();

        $this->matchField = $config->matchField();

        // The same matching read from the employee side. The table below answers
        // "who is this code?"; this answers "is everyone covered?", which is the
        // question that decides whether attendance sync can do anything.
        $this->rollCall = $matcher->rollCall();

        // Every code still free to assign, for the manual picker. Busiest first:
        // a code with thousands of punches is far likelier to be the person
        // being looked for than a stray test enrolment.
        $this->unmappedCodes = IappEmployee::unmapped()
            ->orderByDesc('punch_count')
            ->get(['id', 'emp_code', 'employee_name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'emp_code' => (string) $row->emp_code,
                'employee_name' => $row->employee_name,
            ])
            ->all();

        $this->rollCallDone = count(array_filter($this->rollCall, fn ($r) => $r['status'] === 'mapped'));

        return view('biometric.employees', $this->data);
    }

    /**
     * Rebuild the register from stored punches and auto-map exact matches.
     */
    public function syncEmployees(EmployeeSync $employeeSync)
    {
        abort_403(user()->permission('edit_employees') !== 'all');

        session()->save();

        // Every device code linked to the employee with that Employee ID, where
        // the names agree too (MappingSync). Conflicts become exceptions.
        $result = (new \App\Services\Iapp\Sync\MappingSync())->run(user()->id);

        // Nothing on the device side yet (a fresh start, or after Data Clean):
        // say so, instead of "synced" with every count at zero.
        if (!IappEmployee::withoutGlobalScopes()->exists()) {
            return Reply::error('No device codes are known yet, so there is nothing to link. Click "Upload device employee list" and choose '
                . 'the biometric Employee report (Employee.xlsx), or pull punches under Setup -> Sync Calendar; then Sync all employees.');
        }

        $message = sprintf(
            '%d linked by Employee ID, %d moved to their Employee ID code, %d already linked. %d conflict(s) need you. %d device code(s) are not an Employee ID (see Employee Comparison). %d active employee(s) have no device code.',
            $result['linked'], $result['moved'], $result['already'], $result['conflicts'], $result['device_only'], $result['employees_without_device']
        );

        return Reply::successWithData($message, [
            'result' => $result,
            // Conflicts: straight to them.
            'next' => $result['conflicts'] > 0 ? route('biometric.employees', ['map_status' => 'attention']) : null,
        ]);
    }

    /**
     * The machine's own employee list (the biometric software's Employee report)
     * as the device register, so every enrolled employee is compared, not only
     * those who have punched.
     */
    public function uploadMachineList(Request $request)
    {
        abort_403(user()->permission('edit_employees') !== 'all');

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        session()->save();

        $setting = IappSetting::where('company_id', company()->id)->first();
        $result = (new \App\Services\Iapp\Sync\MachineListImport())->load(
            $request->file('file')->getRealPath(),
            (array) ($setting->register_locations ?? []),
            (bool) ($setting->only_known_employees ?? true),
            user()->id
        );

        if (!$result['ok']) {
            return Reply::error($result['message']);
        }

        $sync = $result['sync'];
        $message = sprintf(
            '%d entries read: %d loaded, %d at other locations, %d not employees here. %d linked, %d already linked. %d name conflict(s) set aside. %d active employee(s) still have no device code.',
            $result['read'], $result['loaded'], $result['other_location'], $result['not_employee'],
            $sync['linked'] + $sync['moved'], $sync['already'], count($result['name_conflicts']), $sync['employees_without_device']
        );

        return Reply::successWithData($message, ['conflicts' => $result['name_conflicts']]);
    }

    /**
     * Link, unlink or ignore one biometric code.
     */
    public function mapEmployee(Request $request, $id, EmployeeSync $employeeSync)
    {
        abort_403(user()->permission('edit_employees') !== 'all');

        $request->validate([
            'action' => 'required|in:map,unmap,ignore',
            'user_id' => 'required_if:action,map|nullable|exists:users,id',
        ]);

        $employee = IappEmployee::findOrFail($id);

        if ($request->action === 'map') {
            $result = $employeeSync->linkToEmployee(
                $employee,
                (int) $request->user_id,
                IappEmployee::METHOD_MANUAL,
                'manual',
                user()->id
            );

            if (!$result['ok']) {
                return Reply::error($result['message']);
            }
        } elseif ($request->action === 'ignore') {
            $employeeSync->unlinkFromEmployee($employee, user()->id);
            $employee->ignore(user()->id, $request->note);
        } else {
            $employeeSync->unlinkFromEmployee($employee, user()->id);
        }

        $applied = $employeeSync->applyMappings();

        return Reply::success(sprintf(
            'Mapping updated. %d punch(es) and %d attendance day(s) re-linked.',
            $applied['punches'],
            $applied['attendance']
        ));
    }

    /**
     * Apply several mappings in one action.
     *
     * Each row is applied independently so one clash cannot abandon the rest;
     * the response reports what succeeded and what did not.
     */
    public function bulkMapEmployees(Request $request, EmployeeSync $employeeSync)
    {
        abort_403(user()->permission('edit_employees') !== 'all');

        $request->validate([
            'action' => 'required|in:map,unmap,ignore,accept_suggestions',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'pairs' => 'nullable|array',
        ]);

        $employees = IappEmployee::whereIn('id', $request->ids)->get();
        $matcher = null;

        if ($request->action === 'accept_suggestions') {
            $matcher = (new EmployeeMatcher(new IappConfig))->loadEmployees();
        }

        $done = 0;
        $failed = [];

        foreach ($employees as $employee) {
            if ($request->action === 'unmap') {
                $employeeSync->unlinkFromEmployee($employee, user()->id);
                $done++;

                continue;
            }

            if ($request->action === 'ignore') {
                $employeeSync->unlinkFromEmployee($employee, user()->id);
                $employee->ignore(user()->id, 'Bulk ignored');
                $done++;

                continue;
            }

            // Either an explicit per-row choice, or the top-ranked suggestion.
            $userId = $request->action === 'accept_suggestions'
                ? ($matcher->candidatesFor($employee, 1)[0]['user_id'] ?? null)
                : (int) ($request->input('pairs.' . $employee->id) ?: 0);

            if (!$userId) {
                $failed[] = $employee->emp_code . ' (no employee chosen)';

                continue;
            }

            $result = $employeeSync->linkToEmployee(
                $employee,
                (int) $userId,
                $request->action === 'accept_suggestions' ? IappEmployee::METHOD_AUTO : IappEmployee::METHOD_MANUAL,
                $request->action === 'accept_suggestions' ? 'suggestion' : 'manual',
                user()->id
            );

            $result['ok'] ? $done++ : $failed[] = $employee->emp_code;
        }

        $applied = $employeeSync->applyMappings();

        $message = sprintf('%d of %d updated. %d punch(es) re-linked.', $done, $employees->count(), $applied['punches']);

        if ($failed !== []) {
            $message .= ' Skipped: ' . implode(', ', array_slice($failed, 0, 5))
                . (count($failed) > 5 ? ' and ' . (count($failed) - 5) . ' more' : '');
        }

        return $failed !== [] && $done === 0 ? Reply::error($message) : Reply::success($message);
    }

    /**
     * One employee's attendance profile: KPIs, day-wise detail and places.
     *
     * Driven by the biometric identity rather than the ERP user, because a
     * code can exist on the devices before the employee is created in the ERP
     * — which is the normal order here.
     */
    public function profile(Request $request)
    {
        $this->pageTitle = 'app.menu.attendanceProfile';
        $this->activeTab = 'profile';

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        // The whole register, most active first. Filtering happens in the
        // dropdown itself, so there is no search term to apply here and no
        // reason to cap the list — 173 rows is nothing to send, and capping it
        // at 50 silently hid the quieter two thirds of the workforce.
        $this->candidates = IappEmployee::with('user:id,name')
            ->orderByDesc('punch_count')
            ->orderBy('emp_code')
            ->get();

        $this->selected = $request->employee
            ? IappEmployee::with('user:id,name')->find($request->employee)
            : null;

        $this->profileData = null;
        $this->deviceNames = $this->deviceOptions();

        if ($this->selected) {
            $this->profileData = (new EmployeeAttendanceProfile(
                $this->selected,
                Carbon::parse($startDate),
                Carbon::parse($endDate)
            ))->build();
        }

        return view('biometric.profile', $this->data);
    }

    /**
     * Attendance days that need review before they feed hours or payroll.
     */
    /**
     * What the nightly cross-check found: sync issues (device and ERP do not
     * agree, or the data never arrived) and missing punches.
     */
    public function exceptions(Request $request)
    {
        $this->pageTitle = 'app.menu.attendanceExceptions';
        $this->activeTab = 'exceptions';

        $this->category = in_array($request->category, [\App\Models\BiometricException::SYNC, \App\Models\BiometricException::PUNCH], true)
            ? $request->category : null;
        $this->status = in_array($request->status, ['open', 'resolved', 'ignored', 'all'], true) ? $request->status : 'open';
        $this->type = array_key_exists((string) $request->type, \App\Models\BiometricException::TYPES) ? $request->type : null;
        $this->startDate = $request->start_date ?: null;
        $this->endDate = $request->end_date ?: null;
        $this->search = trim((string) $request->search);

        $query = \App\Models\BiometricException::with(['user:id,name', 'resolver:id,name'])
            ->when($this->category, fn ($q, $c) => $q->where('category', $c))
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q, $t) => $q->where('type', $t))
            ->when($this->startDate, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($this->endDate, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%' . $this->search . '%')
                ->orWhere('emp_code', 'like', '%' . $this->search . '%')));

        $this->exceptionRows = $query->orderByDesc('date')->orderBy('category')->orderBy('type')->paginate(50)->withQueryString();

        $open = \App\Models\BiometricException::where('status', 'open');
        $this->tiles = [
            'sync' => (clone $open)->where('category', 'sync')->count(),
            'punch' => (clone $open)->where('category', 'punch')->count(),
            'unlinked' => (clone $open)->where('type', 'unlinked_code')->count(),
            'resolved_week' => \App\Models\BiometricException::where('status', 'resolved')->where('resolved_at', '>=', now()->subDays(7))->count(),
        ];

        $this->lastRun = \App\Models\AttendanceDailyRun::whereNotNull('reconciled_at')->orderByDesc('reconciled_at')->first();

        // Punches with no employee code at all (a failed read, a tailgater): last 7 days.
        $report = new AttendanceExceptions(now()->subDays(7)->startOfDay(), now()->endOfDay());
        $this->unidentified = $report->unidentifiedPunches();
        $this->devices = $this->deviceOptions();
        $this->profileIds = IappEmployee::whereIn('emp_code', $this->exceptionRows->pluck('emp_code')->filter()->unique()->all())
            ->pluck('id', 'emp_code')->all();
        $this->canAct = user()->permission('manage_biometric_exceptions') === 'all';

        return view('biometric.exceptions', $this->data);
    }

    /** Resolve, ignore or reopen exceptions, one or many. */
    public function exceptionAction(Request $request)
    {
        abort_403(!in_array(user()->permission('add_attendance'), ['all', 'added'], true));

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:resolve,ignore,reopen',
            'note' => 'nullable|max:1000',
        ]);

        $rows = \App\Models\BiometricException::whereIn('id', $request->ids)->get();

        foreach ($rows as $row) {
            if ($request->action === 'reopen') {
                $row->forceFill(['status' => 'open', 'resolution' => null, 'resolved_at' => null, 'resolved_by' => null])->save();

                continue;
            }

            $row->forceFill([
                'status' => $request->action === 'ignore' ? 'ignored' : 'resolved',
                'resolution' => 'manual',
                'resolved_at' => now(),
                'resolved_by' => user()->id,
                'note' => $request->note ?: $row->note,
            ])->save();

            // Ignoring an unlinked code ignores the code itself: it is somebody
            // who is not employed here, and should stop being reported.
            if ($request->action === 'ignore' && $row->type === 'unlinked_code' && $row->emp_code) {
                IappEmployee::where('emp_code', $row->emp_code)->whereNull('user_id')->get()
                    ->each(fn (IappEmployee $e) => $e->ignore(user()->id, $request->note ?: 'Ignored from Exceptions'));
            }
        }

        return Reply::success(sprintf('%d exception(s) %s.', $rows->count(), match ($request->action) {
            'ignore' => 'ignored', 'reopen' => 'reopened', default => 'resolved',
        }));
    }

    /**
     * Pull one day again from the device, rebuild it, write ERP attendance and
     * cross-check it: what the night does, for one date, now.
     */
    public function resyncDay(Request $request)
    {
        abort_403(!in_array(user()->permission('add_attendance'), ['all', 'added'], true));

        $request->validate(['date' => 'required|date|before_or_equal:today']);

        session()->save();   // the pull can take a minute; do not hold the session lock

        $tz = company()->timezone ?: 'Asia/Kolkata';
        $date = Carbon::parse($request->date, $tz)->startOfDay();
        $until = $date->copy()->addDay()->endOfDay();
        $until = $until->isFuture() ? Carbon::now($tz) : $until;

        $result = (new AttendancePipeline())->run($date, $until, 'manual', user()->id, null, true);

        if (!$result['ok']) {
            return Reply::error($result['message']);
        }

        $run = \App\Models\AttendanceDailyRun::where('date', $date->toDateString())->first();
        (new \App\Services\Attendance\DailyAttendanceReview())->review($date, true, $until);
        $checked = (new \App\Services\Iapp\Reconcile\BiometricReconciler(company()->id))->run($date, true, $until);

        $run?->forceFill(['device_complete' => true, 'last_error' => null, 'reconciled_at' => now()])->save();

        return Reply::success(sprintf('%s re-synced. %s Cross-check: %d sync issue(s), %d punch issue(s) remain; %d closed.',
            $date->format('d M Y'), $result['message'], $checked['sync'], $checked['punch'], $checked['closed']));
    }

    /**
     * Shift adherence across employees, measured against ERP shifts.
     */
    public function shifts(Request $request)
    {
        $this->pageTitle = 'app.menu.biometricShifts';
        $this->activeTab = 'shifts';

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $controllerCode = $request->controller_code;
        $match = array_key_exists((string) $request->match, ShiftDetector::MATCH_LABELS) ? $request->match : null;

        $this->controllerCode = $controllerCode;
        $this->matchFilter = $match;

        $report = (new ShiftReport(Carbon::parse($startDate), Carbon::parse($endDate), company()->id))
            ->build($controllerCode);

        $rows = $match
            ? array_values(array_filter($report['rows'], fn ($row) => $row['match'] === $match))
            : $report['rows'];

        $this->shiftRows = $rows;
        $this->shiftSummary = $report['summary'];
        $this->shiftConflict = $report['conflict'];
        $this->defaultShift = $report['default_shift'];
        $this->devices = $this->deviceOptions();

        // String-prefixed keys so codes like "02301694" and "2301694" stay
        // distinct rather than colliding as integer array keys.
        $this->employeeIds = IappEmployee::whereIn('emp_code', array_column($rows, 'emp_code'))
            ->get(['id', 'emp_code'])
            ->mapWithKeys(fn ($employee) => ['c:' . $employee->emp_code => $employee->id])
            ->all();

        return view('biometric.shifts', $this->data);
    }

    /**
     * Device filter options, one entry per controller.
     *
     * getdevices returns a row per gate, so the raw table has several rows per
     * controller; a filter list built from it directly would repeat each
     * device once per door.
     *
     * Also used by Biometric Settings for the devices-to-pull list.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    public static function deviceOptions()
    {
        return IappDevice::orderBy('controller_name')
            ->get(['controller_code', 'controller_name', 'location_code'])
            ->unique('controller_code')
            ->mapWithKeys(fn ($device) => [
                $device->controller_code => trim(
                    ($device->controller_name ?: $device->controller_code)
                    . ($device->location_code ? ' (' . $device->location_code . ')' : '')
                ),
            ]);
    }

    /**
     * Controllers and readers known to iApp.
     */
    public function devices()
    {
        $this->pageTitle = 'app.menu.biometricDevices';
        $this->activeTab = 'devices';

        $this->devices = IappDevice::orderBy('location_code')
            ->orderBy('controller_name')
            ->orderBy('gate_no')
            ->get();

        // getdevices returns one row per gate, so several rows share a
        // controller. Counting rows would report 14 "devices" where iApp's own
        // device list shows 5 — every count below is per controller.
        $byController = $this->devices->groupBy('controller_code');

        $this->deviceCount = $byController->count();
        $this->gateCount = $this->devices->count();

        $this->activeCount = $byController
            ->filter(fn ($gates) => $gates->first()->controller_status === 'Y')
            ->count();

        $this->inactiveCount = $this->deviceCount - $this->activeCount;

        // Only In/Out readers give a punch a fixed direction from the device
        // itself. The punch feed carries its own InOut flag, so this is
        // informational rather than a prerequisite.
        $this->directionalCount = $byController
            ->filter(fn ($gates) => in_array($gates->first()->device_mode, [IappDevice::MODE_IN, IappDevice::MODE_OUT], true))
            ->count();

        $this->locationCount = $this->devices->pluck('location_code')->unique()->count();

        return view('biometric.devices', $this->data);
    }

    /**
     * Sync run history.
     */
    public function syncLogs()
    {
        $this->pageTitle = 'app.menu.biometricSync';
        $this->activeTab = 'sync';

        $this->logs = IappSyncLog::with('triggeredBy:id,name')
            ->orderByDesc('id')
            ->paginate(30);

        $this->setting = IappSetting::first();

        // Rows this module actually put into the ERP's attendance, which is
        // the only promotion that now exists.
        $this->attendanceRowCount = IappAttendanceLink::where('link_type', IappAttendanceLink::TYPE_CREATED)
            ->whereNotNull('attendance_id')
            ->count();

        $this->unmatchedCount = IappAttendanceRecord::whereNull('user_id')->count();

        return view('biometric.sync-logs', $this->data);
    }

    /**
     * What is outstanding on the devices: enrolments belonging to people who
     * have left.
     *
     * The module never writes to a device, so nothing here removes anything.
     * It says who should be taken off and why, and flags anyone whose code is
     * still being used after their last working day.
     */
    public function actions(DepartedEnrolments $departed)
    {
        $this->pageTitle = 'app.menu.biometricActions';
        $this->activeTab = 'actions';

        $report = $departed->build();
        $this->rows = $report['rows'];
        $this->counts = $report['counts'];

        // Somebody who has left and has no enrolment needs nothing done on the
        // devices, but saying so is the difference between "nothing to do" and
        // "nobody has left".
        $this->departedWithoutEnrolment = count(app(\App\Services\Employment\Separation::class)->everyoneWhoHasLeft())
            - $report['counts']['total'];

        return view('biometric.actions', $this->data);
    }

    /**
     * Say, per row, whether that day reached the ERP's attendance.
     *
     * The staging table carries a `promote_status` column from an earlier
     * design where a record pointed at the attendance row it became. Attendance
     * Sync no longer works that way — it records what it wrote in
     * `iapp_attendance_links` — so the column stays on 'pending' for ever and
     * the Device Attendance screen badged every day "Pending" even when the day
     * was in attendance and had already been paid on a payslip.
     *
     * The link table is the only record of what was written, so the badge is
     * read from there. One query for the page, not one per row.
     */
    private function markWhatReachedTheErp($records): void
    {
        $dates = collect($records->items())->pluck('attendance_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->unique()->all();
        $users = collect($records->items())->pluck('user_id')->filter()->unique()->all();

        $written = ($dates === [] || $users === [])
            ? collect()
            : IappAttendanceLink::where('link_type', IappAttendanceLink::TYPE_CREATED)
                ->whereIn('user_id', $users)
                ->whereIn('attendance_date', $dates)
                ->get(['user_id', 'attendance_date'])
                ->map(fn ($l) => $l->user_id . '|' . substr((string) $l->attendance_date, 0, 10))
                ->flip();

        foreach ($records as $record) {
            $record->erp_written = $record->user_id !== null
                && $written->has($record->user_id . '|' . substr((string) $record->attendance_date, 0, 10));
        }
    }

    /**
     * Defaults to the current month when no range is supplied.
     */
    private function resolveDateRange(Request $request): array
    {
        $start = $request->start_date
            ? Carbon::parse($request->start_date)->toDateString()
            : now()->startOfMonth()->toDateString();

        $end = $request->end_date
            ? Carbon::parse($request->end_date)->toDateString()
            : now()->endOfMonth()->toDateString();

        // Carbon 3 returns a signed difference, so an inverted range would slip
        // past the sync range guard and query a window that can never match.
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    /**
     * Day-type totals for the range, as one grouped query rather than a
     * count per card.
     */
    private function buildSummary(string $startDate, string $endDate): array
    {
        $counts = IappAttendanceRecord::betweenDates($startDate, $endDate)
            ->selectRaw('day_type, COUNT(*) as total')
            ->groupBy('day_type')
            ->pluck('total', 'day_type');

        return [
            'total' => (int) $counts->sum(),
            'present' => (int) $counts->get(IappAttendanceRecord::DAY_PRESENT, 0),
            'absent' => (int) $counts->get(IappAttendanceRecord::DAY_ABSENT, 0),
            'leave' => (int) $counts->get(IappAttendanceRecord::DAY_LEAVE, 0),
            'week_off' => (int) $counts->get(IappAttendanceRecord::DAY_WEEK_OFF, 0),
            'holiday' => (int) $counts->get(IappAttendanceRecord::DAY_HOLIDAY, 0),
        ];
    }

}
