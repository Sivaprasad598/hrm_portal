<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Team;
use App\Models\User;
use App\Helper\Reply;
use App\Models\Leave;
use App\Models\Holiday;
use Carbon\CarbonPeriod;
use App\Models\Attendance;
use Carbon\CarbonInterval;
use App\Models\Designation;
use App\Traits\ImportExcel;
use App\Traits\EmployeeDashboard;
use Illuminate\Http\Request;
use App\Models\CompanyAddress;
use App\Exports\AttendanceExport;
use App\Exports\AttendanceClockExport;
use App\Imports\AttendanceImport;
use App\Jobs\ImportAttendanceJob;
use App\Models\AttendanceSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\EmployeeShiftSchedule;
use App\Exports\AttendanceByMemberExport;
use App\Http\Requests\ClockIn\ClockInRequest;
use App\Http\Requests\Attendance\StoreAttendance;
use App\Http\Requests\Admin\Employee\ImportRequest;
use App\Http\Requests\Attendance\StoreBulkAttendance;
use App\Http\Requests\Admin\Employee\ImportProcessRequest;
use App\Models\Company;
use App\Models\LogTimeFor;
use App\Models\EmployeeShift;
use App\Scopes\ActiveScope;
use App\DataTables\AttendanceRegularisationDataTable;
use App\Events\AttendanceReminderEvent;
use App\Events\RequestRegularisationEvent;
use App\Events\RequestRegulariseEvent;
use App\Http\Requests\AttendanceRegularisation\RejectReason;
use App\Http\Requests\AttendanceRegularisation\StoreAttendanceRegularisation;
use App\Http\Requests\AttendanceRegularisation\UpdateAttendanceRegularisation;
use App\Http\Requests\AttendanceSetting\AttendanceRegularisationSetting;
use App\Models\AttendanceRegularisation;
use App\Models\Role;
use App\Helper\Files;
use ReflectionClass;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Session;

class AttendanceController extends AccountBaseController
{

    use ImportExcel, EmployeeDashboard {
        EmployeeDashboard::attendanceShift as protected attendanceShiftFromTrait;
    }

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.attendance';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('attendance', $this->user->modules));
            $this->viewAttendancePermission = user()->permission('view_attendance');

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $attendance = Attendance::find($request->employee_id);

        if ($request->employee_id) {
            abort_403(!(
                $this->viewAttendancePermission == 'all'
                || ($this->viewAttendancePermission == 'added' && $attendance->added_by == user()->id)
                || ($this->viewAttendancePermission == 'owned' && $attendance->user_id == user()->id)
                || ($this->viewAttendancePermission == 'both' && ($attendance->added_by == user()->id || $attendance->user_id == user()->id))));
        } else {
            abort_403(!in_array($this->viewAttendancePermission, ['all', 'added', 'owned', 'both']));
        }

        if (request()->ajax()) {
            // The salary month (the pay cycle, 21st to 20th) is drawn from the
            // payroll count itself; the calendar month keeps the usual grid.
            return $request->period === 'cycle' ? $this->cycleData($request) : $this->summaryData($request);
        }

        if ($this->viewAttendancePermission == 'owned') {
            $this->employees = User::where('id', user()->id)->get();
        } else {
            $this->employees = User::allEmployees(null, false, ($this->viewAttendancePermission == 'all' ? 'all' : null));
        }

        $this->attendanceRegularisation = AttendanceSetting::first();
        $regulariseRoles = $this->attendanceRegularisation ? $this->attendanceRegularisation->attendance_regularize_roles : null;
        $regulariseRolesArray = json_decode($regulariseRoles, true) ?? [];
        $userRoles = user_roles();

        $lastRole = end($userRoles);
        $condition = in_array($lastRole, $regulariseRolesArray) || $lastRole === 'admin';

        if(!in_array('admin', user_roles()) && !$condition)
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('user_id', user()->id)->where('status', 'pending')->count();
        }
        else
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('status', 'pending')->count();
        }

        $now = now();
        $this->year = $now->format('Y');
        $this->month = $now->format('m');
        $this->departments = Team::all();
        $this->designations = Designation::all();

        // Opened from payroll for one salary period: period=cycle&month&year&employee.
        $this->period = $request->period === 'cycle' ? 'cycle' : 'month';
        $this->selectedMonth = (int) ($request->month ?: $now->month);
        $this->selectedYear = (int) ($request->year ?: $now->year);
        $this->selectedEmployee = $request->employee;

        return view('attendances.index', $this->data);
    }

    /** Employees per page in the attendance grids. */
    public const GRID_PAGE_SIZE = 50;

    /**
     * One page of the grid's employees, with what the pager needs.
     *
     * A month for every employee in one response took 20 s and 5 MB, which a
     * shared server's time limit turns into an error; a page is a few seconds.
     */
    protected function gridPage($query, $request)
    {
        $total = (clone $query)->get(['users.id'])->count();
        $pages = max(1, (int) ceil($total / self::GRID_PAGE_SIZE));
        $page = min($pages, max(1, (int) $request->page));

        $this->pager = [
            'page' => $page, 'pages' => $pages, 'total' => $total,
            'from' => $total ? ($page - 1) * self::GRID_PAGE_SIZE + 1 : 0,
            'to' => min($total, $page * self::GRID_PAGE_SIZE),
        ];

        return $query->forPage($page, self::GRID_PAGE_SIZE)->get();
    }

    /**
     * The attendance grid for a salary month: the pay cycle the given payroll
     * month ends in, day by day, as payroll counts it (AttendanceDays). Each
     * cell is what the day was paid as, and the totals are the Working Days,
     * Days Present and LOP that the payroll run and the payslip use.
     */
    public function cycleData(Request $request)
    {
        $cycle = \App\Services\Payroll\PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $viewEmployeePermission = user()->permission('view_employees');

        $employees = User::join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('designations', 'designations.id', '=', 'employee_details.designation_id')
            ->select('users.id', 'users.name', 'users.image', 'employee_details.employee_id', 'designations.name as designation_name')
            ->onlyEmployee()
            ->withoutGlobalScope(ActiveScope::class)
            ->where(function ($query) use ($cycle) {
                $query->where('users.status', 'active')
                    ->orWhereDate('users.inactive_date', '>=', $cycle->start->toDateString());
            })
            ->groupBy('users.id')
            ->orderBy('users.name');

        if ($request->department && $request->department != 'all') {
            $employees->where('employee_details.department_id', $request->department);
        }

        if ($request->designation && $request->designation != 'all') {
            $employees->where('employee_details.designation_id', $request->designation);
        }

        if ($request->userId && $request->userId != 'all') {
            $employees->where('users.id', $request->userId);
        }

        if ($this->viewAttendancePermission == 'owned' || $viewEmployeePermission == 'owned') {
            $employees->where('users.id', user()->id);
        } elseif ($viewEmployeePermission == 'both') {
            $employees->where(fn ($q) => $q->where('employee_details.user_id', user()->id)->orWhere('employee_details.added_by', user()->id));
        } elseif ($viewEmployeePermission == 'added') {
            $employees->where('employee_details.added_by', user()->id);
        }

        $counter = new \App\Services\Payroll\AttendanceDays(company()->timezone ?: 'Asia/Kolkata');
        $rows = [];

        foreach ($this->gridPage($employees, $request) as $employee) {
            $rows[] = ['employee' => $employee, 'count' => $counter->forRange($employee->id, $cycle->start, $cycle->end)];
        }

        $this->cycle = $cycle;
        $this->cycleRows = $rows;
        $this->cycleDates = collect(CarbonPeriod::create($cycle->start->copy()->startOfDay(), $cycle->end->copy()->startOfDay()));
        $this->statusMeta = PayrollAttendanceController::STATUSES;
        $this->month = (int) $request->month;
        $this->year = (int) $request->year;
        $this->canOpenPayrollView = user()->permission('manage_payroll') === 'all'
            || $this->viewAttendancePermission === 'all' || in_array('admin', user_roles());
        // A day opens its details first: the attendance popup (sessions, edit, delete) when
        // there are punches, otherwise how payroll counted it, with "Mark attendance".
        $this->canEditAttendance = user()->permission('add_attendance') === 'all';
        $tz = company()->timezone ?: 'Asia/Kolkata';
        $attendanceIds = [];

        foreach (Attendance::whereIn('user_id', collect($rows)->pluck('employee.id')->all() ?: [0])
            ->whereBetween('clock_in_time', [$cycle->start->copy()->subDay()->startOfDay(), $cycle->end->copy()->addDays(2)->startOfDay()])
            ->orderBy('clock_in_time')->get(['id', 'user_id', 'clock_in_time']) as $a) {
            $attendanceIds[$a->user_id][$a->clock_in_time->copy()->timezone($tz)->toDateString()] ??= $a->id;
        }

        $this->attendanceIds = $attendanceIds;

        $view = view('attendances.ajax.cycle_data', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    /** A day without punches in the salary-month grid: how payroll counted it, and its leave or holiday. */
    public function cycleDay($userId, $date)
    {
        abort_403(!in_array(user()->permission('view_attendance'), ['all', 'added', 'owned', 'both']));
        abort_403(user()->permission('view_attendance') === 'owned' && (int) $userId !== (int) user()->id);

        $day = Carbon::parse($date)->startOfDay();
        $this->employee = User::withoutGlobalScope(ActiveScope::class)->with('employeeDetail.designation')->findOrFail($userId);
        $count = (new \App\Services\Payroll\AttendanceDays(company()->timezone ?: 'Asia/Kolkata'))->forRange((int) $userId, $day, $day);
        $this->day = $count['days'][0] ?? null;
        $this->date = $day;
        $this->statusMeta = PayrollAttendanceController::STATUSES;
        $this->leaves = DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->where('l.user_id', $userId)->whereDate('l.leave_date', $day->toDateString())
            ->get(['l.duration', 'l.half_day_type', 'l.status', 'l.paid', 'l.reason', 't.type_name']);
        $this->holiday = Holiday::whereDate('date', $day->toDateString())->value('occassion');
        $this->canEditAttendance = user()->permission('add_attendance') === 'all' && $day->lte(now());

        return view('attendances.ajax.cycle_day', $this->data);
    }

    public function summaryData($request)
    {
        $viewEmployeePermission = user()->permission('view_employees');
        $employees = User::with(
            [
                'employeeDetail.designation:id,name',
                'attendance' => function ($query) use ($request) {
                    $startOfMonth = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
                    $officestartTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $startOfMonth, company()->timezone);
                    $startOfMonth = $startOfMonth->subMinutes($officestartTimeDB->offset / 60);

                    $endOfMonth = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth();
                    $officeEndTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $endOfMonth, company()->timezone);
                    $endOfMonth = $endOfMonth->subMinutes($officeEndTimeDB->offset / 60);

                    $query->whereBetween('attendances.clock_in_time', [$startOfMonth, $endOfMonth]);

                    // $query->orwhereRaw('MONTH(attendances.clock_in_time) = ?', [$request->month])
                    // ->orwhereRaw('YEAR(attendances.clock_in_time) = ?', [$request->year]);

                    if ($this->viewAttendancePermission == 'added') {
                        $query->where('attendances.added_by', user()->id);
                    } elseif ($this->viewAttendancePermission == 'owned') {
                        $query->where('attendances.user_id', user()->id);
                    }
                },
                'leaves' => function ($query) use ($request) {
                    $query->whereRaw('MONTH(leaves.leave_date) = ?', [$request->month])
                        ->whereRaw('YEAR(leaves.leave_date) = ?', [$request->year])
                        ->where('status', 'approved');
                },
                'shifts' => function ($query) use ($request) {
                    $query->whereRaw('MONTH(employee_shift_schedules.date) = ?', [$request->month])
                        ->whereRaw('YEAR(employee_shift_schedules.date) = ?', [$request->year]);
                },
                'leaves.type',
                'shifts.shift',
                'attendance.shift'
            ]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.inactive_date', 'users.email', 'users.created_at', 'employee_details.department_id', 'employee_details.user_id', 'employee_details.added_by', 'users.image')
            ->onlyEmployee()
            ->withoutGlobalScope(ActiveScope::class)
            ->where(function ($query) use ($request) {
                // $query->whereNull('users.inactive_date')
                $query->where('users.status', 'active')
                    ->orWhere(function ($subQuery) use ($request) {
                        $subQuery->whereRaw('YEAR(users.inactive_date) >= ?', [$request->year])
                            ->whereRaw('MONTH(users.inactive_date) >= ?', [$request->month]);
                    });
            })
            ->groupBy('users.id');

        if ($request->department != 'all') {
            $employees = $employees->where('employee_details.department_id', $request->department);
        }

        if ($request->designation != 'all') {
            $employees = $employees->where('employee_details.designation_id', $request->designation);
        }

        if ($request->userId != 'all') {
            $employees = $employees->where('users.id', $request->userId);
        }

        if ($viewEmployeePermission == 'owned') {
            $employees = $employees->where('users.id', user()->id);
        } elseif ($viewEmployeePermission == 'both') {
            $employees = $employees->where('employee_details.user_id', user()->id)
                ->orWhere('employee_details.added_by', user()->id);
        } elseif ($viewEmployeePermission == 'added') {
            $employees = $employees->where('employee_details.added_by', user()->id);
        }

        $employees = $this->gridPage($employees->orderBy('users.name'), $request);
        $user = user();
        $this->holidays = Holiday::whereRaw('MONTH(holidays.date) = ?', [$request->month])->whereRaw('YEAR(holidays.date) = ?', [$request->year])->get();

        $final = [];
        $holidayOccasions = [];
        $leaveReasons = [];

        $this->daysInMonth = Carbon::parse('01-' . $request->month . '-' . $request->year)->daysInMonth;
        $now = now()->timezone($this->company->timezone);
        $requestedDate = Carbon::parse(Carbon::parse('01-' . $request->month . '-' . $request->year))->endOfMonth();

        foreach ($employees as $employee) {

            $dataBeforeJoin = null;

            $dataTillToday = array_fill(1, $now->copy()->format('d'), 'Absent');
            $dataTillRequestedDate = array_fill(1, (int)$this->daysInMonth, 'Absent');
            $daysTofill = ((int)$this->daysInMonth - (int)$now->copy()->format('d'));

            if (($now->copy()->format('d') != $this->daysInMonth) && !$requestedDate->isPast()) {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), (($daysTofill >= 0 ? $daysTofill : 0)), '-');
            } else {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), (($daysTofill >= 0 ? $daysTofill : 0)), 'Absent');
            }

            if (!$requestedDate->isPast()) {
                $final[$employee->id . '#' . $employee->name] = array_replace($dataTillToday, $dataFromTomorrow);
            } else {
                $final[$employee->id . '#' . $employee->name] = array_replace($dataTillRequestedDate, $dataFromTomorrow);
            }

            $shiftScheduleCollection = $employee->shifts->keyBy('date');


            foreach ($employee->shifts as $shifts) {
                if ($shifts->shift->shift_name == 'Day Off') {
                    $final[$employee->id . '#' . $employee->name][$shifts->date->day] = 'Day Off';
                }
            }

            $firstAttendanceProcessed = [];

            // Total worked seconds per day, summed across every session that day (a day can have
            // multiple attendance rows — a lunch break, several door-swipe sessions, etc.) — used
            // below to flag Half Day when real hours worked fall well short of the shift, since
            // bulk-imported attendance never computes half_day the way self-clock-in does.
            $workedSecondsByDay = [];

            // From the first punch to the last, as payroll counts a day. The
            // biometric sync stores every pair of gate swipes as its own row,
            // often seconds long; adding those up made a 10:05-18:24 day 0.6 h
            // and showed it Absent here while payroll paid it as Present.
            $dayBounds = [];

            foreach ($employee->attendance as $attendance) {
                $localDateTimeString = $attendance->clock_in_time->timezone(company()->timezone)->toDateTimeString();
                $dayKey = Carbon::createFromFormat('Y-m-d H:i:s', $localDateTimeString, 'UTC')->startOfDay()->toDateTimeString();
                $in = $attendance->clock_in_time->getTimestamp();
                $out = $attendance->clock_out_time ? $attendance->clock_out_time->getTimestamp() : $in;
                $dayBounds[$dayKey] = [
                    min($dayBounds[$dayKey][0] ?? $in, $in),
                    max($dayBounds[$dayKey][1] ?? $out, $out, $in),
                ];
            }

            foreach ($dayBounds as $dayKey => [$first, $last]) {
                $workedSecondsByDay[$dayKey] = max(0, $last - $first);
            }

            foreach ($employee->attendance as $attendance) {
                $clockInTimeUTC = $attendance->clock_in_time->timezone(company()->timezone)->toDateTimeString();
                $clockInTime = Carbon::createFromFormat('Y-m-d H:i:s', $clockInTimeUTC, 'UTC');
                $startOfDayKey = $clockInTime->startOfDay()->toDateTimeString();

                $shiftSchedule = $shiftScheduleCollection[$startOfDayKey] ?? null;

                if ($shiftSchedule) {
                    $shift = $shiftSchedule->shift;
                    $shiftStartTime = Carbon::parse($clockInTime->toDateString() . ' ' . $shift->office_start_time);
                    $shiftEndTime = Carbon::parse($clockInTime->toDateString() . ' ' . $shift->office_end_time);

                    // Determine if the attendance is within the shift time, the previous day's shift, or otherwise
                    $isWithinShift = $clockInTime->between($shiftStartTime, $shiftEndTime);
                    $isPreviousShift = $clockInTime->betweenIncluded($shiftStartTime->subDay(), $shiftEndTime->subDay());
                    $isAssignedShift = $attendance->employee_shift_id == $shift->id;
                } else {
                    $isWithinShift = $isPreviousShift = $isAssignedShift = false;
                }

                if (!isset($isHalfDay[$employee->id][$startOfDayKey]) && !isset($isLate[$employee->id][$startOfDayKey])) {
                    $isHalfDay[$employee->id][$startOfDayKey] = $isLate[$employee->id][$startOfDayKey] = false;
                }

                if (!isset($isBelowMinimumHours[$employee->id][$startOfDayKey])) {
                    $isBelowMinimumHours[$employee->id][$startOfDayKey] = false;
                }

                // Check if this is the first attendance of the day for this employee
                if (!isset($firstAttendanceProcessed[$employee->id][$startOfDayKey])) {
                    $firstAttendanceProcessed[$employee->id][$startOfDayKey] = true; // Mark as processed

                    // Apply "half day" or "late" logic only if it's the first attendance
                    $isLate[$employee->id][$startOfDayKey] = $attendance->late == 'yes';

                    $shift = $attendance->shift;
                    $hasConfiguredPolicy = $shift && !is_null($shift->min_working_hours_full_day) && !is_null($shift->min_working_hours_half_day);

                    if ($hasConfiguredPolicy) {
                        // Shift has a real policy (Attendance Settings > Shift > Add New Shift) —
                        // always trust actual worked hours over the stored half_day flag. half_day
                        // is set by AttendanceObserver::creating() using the wall-clock time at the
                        // moment the row is created, which is correct for live self clock-in (where
                        // "now" is the clock-in) but meaningless for bulk-imported historical rows —
                        // every row from one import run gets stamped with whatever late/half-day
                        // state applied at the moment the import script happened to execute.
                        $workedSeconds = $workedSecondsByDay[$startOfDayKey] ?? 0;

                        // The grace period is subtracted from the required hours to get the real
                        // cutoff — e.g. 8h required with a 15min grace means >= 7h45m still counts
                        // as a full Present day.
                        $fullDaySeconds = ($shift->min_working_hours_full_day * 3600)
                            - (($shift->min_working_hours_full_day_grace ?? 0) * 60);
                        $halfDaySeconds = ($shift->min_working_hours_half_day * 3600)
                            - (($shift->min_working_hours_half_day_grace ?? 0) * 60);

                        if ($workedSeconds < $halfDaySeconds) {
                            $isBelowMinimumHours[$employee->id][$startOfDayKey] = true;
                            $isHalfDay[$employee->id][$startOfDayKey] = false;
                        } elseif ($workedSeconds < $fullDaySeconds) {
                            $isHalfDay[$employee->id][$startOfDayKey] = true;
                        } else {
                            $isHalfDay[$employee->id][$startOfDayKey] = false;
                        }
                    } else {
                        $isHalfDay[$employee->id][$startOfDayKey] = $attendance->half_day == 'yes';

                        // No policy configured on this shift (e.g. shifts created before this
                        // feature, or flexible shifts) — fall back to a simple percentage of the
                        // shift's total duration: under a quarter doesn't count as attendance at
                        // all (Absent), a quarter to half is a Half Day, and at least half is a
                        // full Present.
                        if (!$isHalfDay[$employee->id][$startOfDayKey] && $shift) {
                            $workedSeconds = $workedSecondsByDay[$startOfDayKey] ?? 0;
                            $shiftStart = Carbon::parse($shift->office_start_time);
                            $shiftEnd = Carbon::parse($shift->office_end_time);
                            $shiftSeconds = $shiftEnd->lessThanOrEqualTo($shiftStart)
                                ? abs($shiftEnd->copy()->addDay()->diffInSeconds($shiftStart))
                                : abs($shiftEnd->diffInSeconds($shiftStart));

                            if ($shiftSeconds > 0 && $workedSeconds < ($shiftSeconds * 0.25)) {
                                $isBelowMinimumHours[$employee->id][$startOfDayKey] = true;
                            } elseif ($shiftSeconds > 0 && $workedSeconds < ($shiftSeconds * 0.5)) {
                                $isHalfDay[$employee->id][$startOfDayKey] = true;
                            }
                        }
                    }
                }

                if ($isBelowMinimumHours[$employee->id][$startOfDayKey]) {
                    // Worked too little to count as attendance at all — show as Absent instead of
                    // a check/half-day icon, and skip building the tooltip/link below.
                    if ($isWithinShift || $isAssignedShift || $isPreviousShift) {
                        $dayToAssign = $isPreviousShift ? $clockInTime->copy()->subDay()->day : $clockInTime->day;
                    } else {
                        $dayToAssign = $clockInTime->day;
                    }

                    // A scheduled Day Off (set above from the shift roster) always wins over a
                    // stray clock-in that day — otherwise a single punch silently erases the Day
                    // Off icon even though the roster still says the employee wasn't due in.
                    if (($final[$employee->id . '#' . $employee->name][$dayToAssign] ?? null) !== 'Day Off') {
                        $final[$employee->id . '#' . $employee->name][$dayToAssign] = 'Absent';
                    }

                    continue;
                }

                // Late is intentionally not shown as its own icon here — Present still displays
                // as Present when late; the Late Coming (LC) badge on the attendance calendar
                // (account/attendances/{user}/calendar) is where lateness is surfaced instead.
                if ($isHalfDay[$employee->id][$startOfDayKey]) {
                    $iconClassKey = 'star-half-alt text-red';
                } elseif ($attendance->work_from_type === 'home') {
                    $iconClassKey = 'home text-primary';
                } else {
                    $iconClassKey = 'check text-success';
                }

                // Tooltip title based on attendance status or presence
                $tooltipTitle = $attendance->employee_shift_id && $attendance->shift ? $attendance->shift->shift_name : __('app.present');
                if ($attendance->work_from_type === 'home') {
                    $tooltipTitle = $tooltipTitle . ' — ' . __('modules.attendance.workFromHomeLegend');
                }

                // Construct the attendance HTML once
                $attendanceHtml = '<a href="javascript:;" data-toggle="tooltip" data-original-title="' . e($tooltipTitle) . '" class="view-attendance" data-attendance-id="' . $attendance->id . '"><i class="fa fa-' . $iconClassKey . '"></i></a>';

                // Determine the day to assign the attendanceHtml
                if ($isWithinShift || $isAssignedShift || $isPreviousShift) {
                    $dayToAssign = $isPreviousShift ? $clockInTime->copy()->subDay()->day : $clockInTime->day;
                } else {
                    $dayToAssign = $clockInTime->day;
                }

                // Same Day Off precedence as above — a clock-in on a scheduled day off should not
                // silently replace the Day Off icon.
                if (($final[$employee->id . '#' . $employee->name][$dayToAssign] ?? null) !== 'Day Off') {
                    $final[$employee->id . '#' . $employee->name][$dayToAssign] = $attendanceHtml;
                }
            }

            $emplolyeeName = view('components.employee', [
                'user' => $employee
            ]);

            $final[$employee->id . '#' . $employee->name][] = $emplolyeeName;

            // Joining date is informational only here — real attendance/import data before an
            // employee's joining_date must still display (e.g. a bulk import of pre-joining
            // punch history), so pre-join dates are no longer blanked out with a dash.

            if (Carbon::parse('01-' . $request->month . '-' . $request->year)->isFuture()) {
                $dataBeforeJoin = array_fill(1, $this->daysInMonth, '-');
            }

            if (!is_null($dataBeforeJoin)) {
                $final[$employee->id . '#' . $employee->name] = array_replace($final[$employee->id . '#' . $employee->name], $dataBeforeJoin);
            }

            foreach ($employee->leaves as $leave) {
                if ($leave->duration == 'half day') {
                    if ($final[$employee->id . '#' . $employee->name][$leave->leave_date->day] == '-' || $final[$employee->id . '#' . $employee->name][$leave->leave_date->day] == 'Absent') {
                        $final[$employee->id . '#' . $employee->name][$leave->leave_date->day] = 'Half Day';
                    }
                } else {
                    $final[$employee->id . '#' . $employee->name][$leave->leave_date->day] = 'Leave';
                    $leaveReasons[$employee->id][$leave->leave_date->day] = $leave->type->type_name . ': ' . $leave->reason;
                }
            }

            foreach ($this->holidays as $holiday) {
                $departmentId = $employee->employeeDetail->department_id;
                $designationId = $employee->employeeDetail->designation_id;
                $employmentType = $employee->employeeDetail->employment_type;


                $holidayDepartment = (!is_null($holiday->department_id_json)) ? json_decode($holiday->department_id_json) : [];
                $holidayDesignation = (!is_null($holiday->designation_id_json)) ? json_decode($holiday->designation_id_json) : [];
                $holidayEmploymentType = (!is_null($holiday->employment_type_json)) ? json_decode($holiday->employment_type_json) : [];

                if (((in_array($departmentId, $holidayDepartment) || $holiday->department_id_json == null) &&
                    (in_array($designationId, $holidayDesignation) || $holiday->designation_id_json == null) &&
                    (in_array($employmentType, $holidayEmploymentType) || $holiday->employment_type_json == null))) {


                    if ($final[$employee->id . '#' . $employee->name][$holiday->date->day] == 'Absent' || $final[$employee->id . '#' . $employee->name][$holiday->date->day] == '-') {
                        $final[$employee->id . '#' . $employee->name][$holiday->date->day] = 'Holiday';
                        $holidayOccasions[$holiday->date->day] = $holiday->occassion;
                    }
                }
            }
        }

        // A day the employee's shift does not work (Sunday on General Shift) is
        // their week off, not an absence - as payroll pays it. Before the
        // manual overrides, which still win.
        $resolver = \App\Services\Attendance\ShiftResolver::shared(company()?->id);

        foreach ($employees as $employee) {
            $key = $employee->id . '#' . $employee->name;

            for ($day = 1; $day <= (int) $this->daysInMonth; $day++) {
                if (($final[$key][$day] ?? null) !== 'Absent') {
                    continue;
                }

                $date = Carbon::create((int) $request->year, (int) $request->month, $day)->toDateString();

                if (!\App\Services\Attendance\ShiftResolver::worksOn($resolver->shiftOn((int) $employee->id, $date), $date)) {
                    $final[$key][$day] = 'Day Off';
                }
            }
        }

        // Manual overrides from the calendar panel's "Assign Status" action
        // win over everything else computed above, exactly like the calendar
        // panel itself (AttendanceController::calendar()'s resolveStatusKey).
        $overridesByUser = DB::table('attendance_day_overrides')
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereYear('override_date', $request->year)
            ->whereMonth('override_date', $request->month)
            ->get()
            ->groupBy('user_id');

        foreach ($employees as $employee) {
            $userOverrides = $overridesByUser->get($employee->id);

            if (!$userOverrides) {
                continue;
            }

            foreach ($userOverrides as $override) {
                $day = (int) Carbon::parse($override->override_date)->format('j');
                $final[$employee->id . '#' . $employee->name][$day] = 'Override:' . $override->status;
            }
        }

        // "N late comings = 1 half day" (Attendance Settings → Half Day
        // Settings) — flag employees who've crossed the threshold so an
        // admin can review and apply the deduction manually; nothing here
        // changes attendance data on its own.
        $halfDayThreshold = max(1, (int) ($this->company->attendanceSetting->half_day_late_threshold ?? 3));

        $lateMonthStart = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
        $lateMonthStartOffset = Carbon::createFromFormat('Y-m-d H:i:s', $lateMonthStart, $this->company->timezone);
        $lateMonthStart = $lateMonthStart->copy()->subMinutes($lateMonthStartOffset->offset / 60);

        $lateMonthEnd = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth();
        $lateMonthEndOffset = Carbon::createFromFormat('Y-m-d H:i:s', $lateMonthEnd, $this->company->timezone);
        $lateMonthEnd = $lateMonthEnd->copy()->subMinutes($lateMonthEndOffset->offset / 60);

        $lateCounts = Attendance::whereIn('user_id', $employees->pluck('id'))
            ->where('late', 'yes')
            ->whereBetween('clock_in_time', [$lateMonthStart, $lateMonthEnd])
            ->select('user_id', DB::raw('count(*) as cnt'))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $appliedDeductionCounts = Attendance::whereIn('user_id', $employees->pluck('id'))
            ->where('half_day_due_to_late', 1)
            ->whereBetween('clock_in_time', [$lateMonthStart, $lateMonthEnd])
            ->select('user_id', DB::raw('count(*) as cnt'))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $pendingHalfDayDeductions = [];

        foreach ($employees as $employee) {
            $lateCount = (int) ($lateCounts[$employee->id] ?? 0);
            $appliedCount = (int) ($appliedDeductionCounts[$employee->id] ?? 0);
            $pending = intdiv($lateCount, $halfDayThreshold) - $appliedCount;

            if ($pending > 0) {
                $pendingHalfDayDeductions[$employee->id] = [
                    'pending' => $pending,
                    'lateCount' => $lateCount,
                    'threshold' => $halfDayThreshold,
                ];
            }
        }

        $this->pendingHalfDayDeductions = $pendingHalfDayDeductions;

        $this->employeeAttendence = $final;
        $this->holidayOccasions = $holidayOccasions;
        $this->leaveReasons = $leaveReasons;

        $this->weekMap = Holiday::weekMap('D');

        $this->month = $request->month;
        $this->year = $request->year;

        $view = view('attendances.ajax.summary_data', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $viewPermission = user()->permission('view_attendance');
        $attendance = Attendance::with('user', 'user.employeeDetail', 'location')->findOrFail($id);

        $attendanceSettings = EmployeeShiftSchedule::with('shift')->where('user_id', $attendance->user_id)
            ->whereDate('date', Carbon::parse($attendance->clock_in_time)->toDateString())
            ->first();

        $this->hasAssignedShift = (bool) $attendanceSettings;

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;
        } else {
            $this->attendanceSettings = AttendanceSetting::first()->shift; // Do not get this from session here
        }

        abort_403(
            !(
                $viewPermission == 'all'
                || ($viewPermission == 'added' && $attendance->added_by == user()->id)
                || ($viewPermission == 'owned' && $attendance->user->id == user()->id)
                || ($viewPermission == 'both' && ($attendance->added_by == user()->id || $attendance->user->id == user()->id))
            )
        );

        $this->attendanceActivity = Attendance::userAttendanceByDate($attendance->clock_in_time, $attendance->clock_in_time, $attendance->user_id);

        $this->attendanceActivity->load('shift');

        $attendanceActivity = clone $this->attendanceActivity;
        $attendanceActivity = $attendanceActivity->reverse()->values();

        $settingStartTime = Carbon::createFromFormat('H:i:s', $this->attendanceSettings->office_start_time, $this->company->timezone);
        $defaultEndTime = $settingEndTime = Carbon::createFromFormat('H:i:s', $this->attendanceSettings->office_end_time, $this->company->timezone);

        if ($settingStartTime->gt($settingEndTime)) {
            $settingEndTime->addDay();
        }

        if ($settingEndTime->greaterThan(now()->timezone($this->company->timezone))) {
            $defaultEndTime = now()->timezone($this->company->timezone);
        }

        $completedSessions = $attendanceActivity->filter(function ($item) {
            return !is_null($item->clock_out_time);
        })->unique(function ($item) {
            return $item->clock_in_time . '|' . $item->clock_out_time;
        })->sortBy('clock_in_time')->values();

        $this->totalTime = 0;
        $outsideSeconds = 0;
        $sessionStart = null;
        $sessionEnd = null;

        // Sessions can overlap or duplicate each other — e.g. multiple door sensors
        // recording the same physical arrival/exit seconds apart, common in imported
        // biometric data (see the "Total Time in Office" inflation this used to cause:
        // 16h+ summed from 10 sessions inside an actual 9h day). Summing each session's
        // raw duration double-counts that overlap, so merge into non-overlapping blocks
        // first — the total covered time is then correct no matter how many (possibly
        // duplicate) session rows recorded it.
        $mergedIntervals = [];

        foreach ($completedSessions as $activity) {
            $clockInTime = Carbon::parse($activity->clock_in_time)->timezone($this->company->timezone);
            $clockOutTime = Carbon::parse($activity->clock_out_time)->timezone($this->company->timezone);

            if ($clockOutTime->lessThan($clockInTime)) {
                // Bad data (clock-out before clock-in) — skip rather than let it corrupt the merge.
                continue;
            }

            $start = $clockInTime->timestamp;
            $end = $clockOutTime->timestamp;
            $lastIndex = count($mergedIntervals) - 1;

            if ($lastIndex >= 0 && $start <= $mergedIntervals[$lastIndex]['end']) {
                $mergedIntervals[$lastIndex]['end'] = max($mergedIntervals[$lastIndex]['end'], $end);
            } else {
                $mergedIntervals[] = ['start' => $start, 'end' => $end];
            }
        }

        foreach ($mergedIntervals as $index => $interval) {
            $this->totalTime += $interval['end'] - $interval['start'];

            if ($index > 0) {
                $outsideSeconds += $interval['start'] - $mergedIntervals[$index - 1]['end'];
            }
        }

        if (!empty($mergedIntervals)) {
            $sessionStart = Carbon::createFromTimestamp($mergedIntervals[0]['start'], $this->company->timezone);
            $sessionEnd = Carbon::createFromTimestamp(end($mergedIntervals)['end'], $this->company->timezone);
            $this->attendanceDate = $sessionStart;
            $this->startTime = $sessionStart;
        }

        if ($sessionEnd) {
            $this->endTime = $sessionEnd;
            $this->lastClockOut = $attendanceActivity->last();
        } else {
            $this->attendanceDate = Carbon::parse($attendance->clock_in_time)->timezone($this->company->timezone);
            $this->startTime = Carbon::parse($attendance->clock_in_time)->timezone($this->company->timezone);
            $this->endTime = $attendance->clock_out_time ? Carbon::parse($attendance->clock_out_time)->timezone($this->company->timezone) : now()->timezone($this->company->timezone);
        }

        $this->maxClockIn = $attendanceActivity->count() < $this->attendanceSettings->clockin_in_day;

        $totalSeconds = $this->totalTime;
        $this->totalTimeFormatted = CarbonInterval::formatHuman($totalSeconds, true);
        $this->totalTimeInOffice = CarbonInterval::formatHuman($totalSeconds, true);
        $this->totalSessions = $completedSessions->count();
        $this->firstClockInTimeFormatted = $this->startTime ? $this->startTime->translatedFormat(company()->time_format) : '--';
        $this->lastClockOutTimeFormatted = $this->endTime ? $this->endTime->translatedFormat(company()->time_format) : '--';
        $workingSpanSeconds = ($sessionStart && $sessionEnd) ? $sessionStart->diffInSeconds($sessionEnd) : 0;
        $this->totalWorkingSpan = CarbonInterval::formatHuman($workingSpanSeconds, true);
        $this->totalTimeOutside = CarbonInterval::formatHuman($outsideSeconds, true);

        // Compute Late / Early Exit / Overtime against the employee's assigned shift for the day
        $shiftStart = Carbon::createFromFormat('Y-m-d H:i:s', $this->attendanceDate->format('Y-m-d') . ' ' . $this->attendanceSettings->office_start_time, $this->company->timezone);
        $shiftEnd = Carbon::createFromFormat('Y-m-d H:i:s', $this->attendanceDate->format('Y-m-d') . ' ' . $this->attendanceSettings->office_end_time, $this->company->timezone);

        if ($shiftStart->gt($shiftEnd)) {
            $shiftEnd->addDay();
        }

        $shiftSeconds = $shiftStart->diffInSeconds($shiftEnd);

        $lateSeconds = 0;
        $earlyExitSeconds = 0;
        $overtimeSeconds = 0;
        $this->dayStatus = null;

        if ($this->attendanceSettings->shift_name != 'Day Off') {
            // Present / Half Day / Absent verdict for the day, same rule as
            // AttendanceController::summaryData() (the main Attendance grid) and
            // Attendance_Dashboard.php: worked seconds (summed across every session
            // that day) against the shift's configured Min Working Hours, with its
            // Grace Period subtracted from the required hours to get the real cutoff.
            // Falls back to a percentage of the shift's total duration when no policy
            // is configured on the shift, same fallback used in those other two places.
            $hasConfiguredPolicy = !is_null($this->attendanceSettings->min_working_hours_full_day)
                && !is_null($this->attendanceSettings->min_working_hours_half_day);

            if ($hasConfiguredPolicy) {
                $fullDaySeconds = ($this->attendanceSettings->min_working_hours_full_day * 3600)
                    - (($this->attendanceSettings->min_working_hours_full_day_grace ?? 0) * 60);
                $halfDaySeconds = ($this->attendanceSettings->min_working_hours_half_day * 3600)
                    - (($this->attendanceSettings->min_working_hours_half_day_grace ?? 0) * 60);
            } else {
                $fullDaySeconds = $shiftSeconds * 0.5;
                $halfDaySeconds = $shiftSeconds * 0.25;
            }

            if ($totalSeconds < $halfDaySeconds) {
                $this->dayStatus = 'Absent';
            } elseif ($totalSeconds < $fullDaySeconds) {
                $this->dayStatus = 'Half Day';
            } else {
                $this->dayStatus = 'Present';
            }
            // Late is only flagged once the clock-in passes the shift's configured grace
            // period — Shift Start Time + Late Mark After (minutes) = Allowed Clock-In
            // Time — not the instant it ticks past the exact shift start. "Late By" itself
            // still counts from the real shift start, so it reads as the true minutes late.
            $allowedClockInTime = $shiftStart->copy()->addMinutes((int) ($this->attendanceSettings->late_mark_duration ?? 0));

            if ($this->startTime->gt($allowedClockInTime)) {
                $lateSeconds = $shiftStart->diffInSeconds($this->startTime);
            }

            if (!isset($this->notClockedOut) || !$this->notClockedOut) {
                if ($this->endTime->lt($shiftEnd)) {
                    $earlyExitSeconds = $this->endTime->diffInSeconds($shiftEnd);
                }

                // Overtime is measured against the actual working span (first clock-in to
                // last clock-out), not the sum of every completed session's duration —
                // summing sessions double-counts overlapping/duplicate punches (common in
                // imported data) and wildly overstates overtime.
                $overtimeSeconds = max(0, $workingSpanSeconds - $shiftSeconds);
            }
        }

        $this->shiftHours = CarbonInterval::formatHuman($shiftSeconds, true);
        $totalShiftHoursNumber = round($shiftSeconds / 3600, 2);
        $this->totalShiftHoursLabel = (fmod($totalShiftHoursNumber, 1) === 0.0 ? (int) $totalShiftHoursNumber : $totalShiftHoursNumber) . ' ' . __('modules.attendance.hoursShort');
        $this->lateTime = $lateSeconds > 0 ? CarbonInterval::formatHuman($lateSeconds, true) : '--';
        $this->earlyExitTime = $earlyExitSeconds > 0 ? CarbonInterval::formatHuman($earlyExitSeconds, true) : '--';
        $this->overtime = $overtimeSeconds > 0 ? CarbonInterval::formatHuman($overtimeSeconds, true) : '--';

        /** @phpstan-ignore-next-line */
        $this->totalTime = CarbonInterval::formatHuman($this->totalTime, true);

        $this->attendance = $attendance;

        return view('attendances.ajax.show', $this->data);
    }

    public function edit($id)
    {

        $attendance = Attendance::findOrFail($id);

        // Optional pre-fill (e.g. re-picking a different status for an
        // already-assigned date from the attendance calendar) — mutated
        // in-memory only (not saved) so the existing view logic below,
        // which reads straight off $row/$attendance, reflects the newly
        // chosen values without needing its own separate prefill branches.
        if (request()->filled('late')) {
            $attendance->late = request()->late;
        }

        if (request()->filled('half_day')) {
            $attendance->half_day = request()->half_day;
        }

        if (request()->filled('work_from_type')) {
            $attendance->work_from_type = request()->work_from_type;
        }

        $attendanceSettings = EmployeeShiftSchedule::with('shift')->where('user_id', $attendance->user_id)->where('date', $attendance->clock_in_time->format('Y-m-d'))->first();


        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;
        } else {

            $this->attendanceSettings = attendance_setting()->shift; // Do not get this from session here
        }

        $this->checkTodayHoliday = false;

        $this->date = $attendance->clock_in_time->timezone($this->company->timezone)->format('Y-m-d');
        $this->row = $attendance;
        $this->clock_in = 1;
        $this->userid = $attendance->user_id;
        $this->total_clock_in = Attendance::where('user_id', $attendance->user_id)
            ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $attendance->clock_in_time->format('Y-m-d')) // $his->date
            ->whereNull('attendances.clock_out_time')->count();
        $this->type = 'edit';
        $this->location = CompanyAddress::all();
        $this->attendanceUser = User::findOrFail($attendance->user_id);

        $this->maxAttendanceInDay = $this->attendanceSettings->clockin_in_day;
        $this->pageTitle = __('modules.attendance.markAttendance');

        // Matches AttendanceController::create()'s pattern — without this, an AJAX caller
        // (e.g. the attendance calendar's "Assign Status" flow, which expects
        // {status, html, title} JSON via $.easyAjax) gets this view's raw, unstyled HTML
        // back instead, so `response.status == 'success'` is never true and nothing
        // visibly happens: the right panel never gets populated.
        if (request()->ajax()) {
            $html = view('attendances.ajax.edit', $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view('attendances.ajax.edit', $this->data);
    }

    public function update(ClockInRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $carbonDate = Carbon::parse($request->attendance_date, $this->company->timezone);

        $date = $carbonDate->format('Y-m-d');
        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_in_time, $this->company->timezone);

        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_out_time, $this->company->timezone);

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                $clockOut = $clockOut->addDay();
            }
        } else {
            $clockOut = null;
        }

        $attendances = Attendance::where('user_id', $request->user_id)
            ->where(function ($query) use ($clockIn, $clockOut) {
                $query->where(function ($q) use ($clockIn, $clockOut) {
                    $q->where('clock_in_time', '<', $clockOut ?? $clockIn->copy()->addDay())
                        ->where('clock_out_time', '>', $clockIn);
                })
                    ->orWhere(function ($q) use ($clockIn) {
                        $q->whereNull('clock_out_time')
                            ->where('clock_in_time', '<=', $clockIn);
                    });
            })
            ->get();

        $recordBeingEdited = $attendances->find($id);

        $conflictingAttendance = $attendances->filter(function ($item) use ($clockIn, $clockOut, $recordBeingEdited) {
            if ($item && $recordBeingEdited && $item->id === $recordBeingEdited->id) {
                return false;
            }

            $clockInTime = $item->clock_in_time->timezone($this->company->timezone);

            if (is_null($item->clock_out_time)) {
                return false;
            }

            $clockOutTime = $item->clock_out_time->timezone($this->company->timezone);

            return (
                ($clockInTime->lt($clockOut) && $clockOutTime->gt($clockIn))
            );
        })->first();

        if ($conflictingAttendance) {
            return Reply::error(__('messages.attendanceMarked'));
        }

        $attendance->user_id = $request->user_id;
        $attendance->clock_in_time = $clockIn->copy()->timezone(config('app.timezone'));
        $attendance->clock_in_ip = $request->clock_in_ip;
        $attendance->clock_out_time = $clockOut?->copy()->timezone(config('app.timezone'));
        $attendance->auto_clock_out = 0;
        $attendance->clock_out_ip = $request->clock_out_ip;
        $attendance->working_from = $request->working_from;
        $attendance->work_from_type = $request->work_from_type;
        $attendance->location_id = $request->location;
        $attendance->clock_out_time_working_from = $request->clock_out_time_working_from;
        $attendance->clock_out_time_work_from_type = $request->clock_out_time_work_from_type;
        $attendance->clock_out_time_location_id = $request->clock_out_time_location_id;
        $attendance->late = ($request->has('late')) ? 'yes' : 'no';
        $attendance->half_day = ($request->has('halfday')) ? 'yes' : 'no';
        $attendance->half_day_type = ($request->has('half_day_duration') && $request->has('halfday')) ? $request->half_day_duration : null;
        $attendance->save();

        return Reply::success(__('messages.attendanceSaveSuccess'));
    }

    public function mark(Request $request, $userid, $day, $month, $year)
    {
        $this->date = Carbon::createFromFormat('d-m-Y', $day . '-' . $month . '-' . $year)->format('Y-m-d');

        $attendanceSettings = EmployeeShiftSchedule::with('shift')->where('user_id', $userid)->where('date', $this->date)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;
        } else {
            $this->attendanceSettings = attendance_setting()->shift; // Do not get this from session here
        }

        $this->checkTodayHoliday = Holiday::where('date', $this->date)
            ->where(function ($query) {
                $query->orWhere('department_id_json', 'like', '%"' . user()->employeeDetail->department_id . '"%')
                    ->orWhereNull('department_id_json');
            })
            ->where(function ($query) {
                $query->orWhere('designation_id_json', 'like', '%"' . user()->employeeDetail->designation_id . '"%')
                    ->orWhereNull('designation_id_json');
            })
            ->where(function ($query) {
                if (!is_Null(user()->employeeDetail->employment_type)) {
                    $query->orWhere('employment_type_json', 'like', '%"' . user()->employeeDetail->employment_type . '"%')
                        ->orWhereNull('employment_type_json');
                }
            })
            ->first();

        $this->row = Attendance::attendanceByUserDate($userid, $this->date);
        $this->clock_in = 0;
        $this->total_clock_in = Attendance::where('user_id', $userid)
            ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $this->date)
            ->whereNull('attendances.clock_out_time')->count();

        $this->userid = $userid;
        $this->attendanceUser = User::withoutGlobalScope(ActiveScope::class)->findOrFail($userid);
        $this->type = 'add';
        $this->maxAttendanceInDay = $this->attendanceSettings->clockin_in_day;
        $this->location = CompanyAddress::all();

        return view('attendances.ajax.edit', $this->data);
    }

    public function store(StoreAttendance $request)
    {
        $carbonDate = Carbon::parse($request->attendance_date, $this->company->timezone);
        $date = $carbonDate->format('Y-m-d');

        $clockInTime = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_in_time, 'UTC');
        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_in_time, $this->company->timezone);

        $attendanceSettings = EmployeeShiftSchedule::with('shift')->where('user_id', $request->user_id)->where('date', $date)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;
        } else {
            $this->attendanceSettings = AttendanceSetting::first()->shift; // Do not get this from session here
        }

        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_out_time, $this->company->timezone);

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                $clockOut = $clockOut->addDay();
            }
        } else {
            $clockOut = null;
        }

        $attendance = Attendance::where('user_id', $request->user_id)
            ->whereBetween('clock_in_time', [$carbonDate->copy()->subDay(), $carbonDate->copy()->addDay()])
            ->whereNull('clock_out_time')
            ->get();

        $attendance = $attendance->filter(function ($item) use ($date) {
            return $item->clock_in_time->timezone($this->company->timezone)->format('Y-m-d') == $date;
        })->first();

        $startTimestamp = $date . ' ' . $this->attendanceSettings->office_start_time;
        $endTimestamp = $date . ' ' . $this->attendanceSettings->office_end_time;
        $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $startTimestamp, $this->company->timezone);
        $officeEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $endTimestamp, $this->company->timezone);

        if ($this->attendanceSettings->shift_type == 'strict') {
            $clockInCount = Attendance::getTotalUserClockInWithTime($officeStartTime, $officeEndTime, $request->user_id);
        } else {
            $clockInCount = Attendance::whereDate('clock_in_time', $officeStartTime->copy()->toDateString())
                ->where('user_id', $request->user_id)
                ->count();
        }

        $checkTodayShift = EmployeeShiftSchedule::with('shift')->where('user_id', user()->id)
            ->where('date', now(company()->timezone)->toDateString())
            ->first();
        if ($checkTodayShift) {
            $clockInTodayDayShift = $clockInTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time);
        } else {
            $clockInTodayDayShift = false;
        }

        $checkPreviousDayShift = EmployeeShiftSchedule::with('shift')->where('user_id', user()->id)
            ->where('date', now($this->company->timezone)->subDay()->toDateString())
            ->first();
        if ($checkPreviousDayShift) {
            $clockInPreviousDayShift = $clockInTime->betweenIncluded($checkPreviousDayShift->shift_start_time, $checkPreviousDayShift->shift_end_time);
        } else {
            $clockInPreviousDayShift = false;
        }

        $employeeShiftId = null;
        if ($clockInTodayDayShift) {
            $employeeShiftId = $this->attendanceSettings->id;
        } elseif ($clockInPreviousDayShift) {
            $employeeShiftId = $checkPreviousDayShift->shift->id;
        }

        $shiftStartTime = $clockIn->format('Y-m-d') . ' ' . $this->attendanceSettings->office_start_time;

        if (Carbon::parse($this->attendanceSettings->office_start_time)->gt(Carbon::parse($this->attendanceSettings->office_end_time))) {
            $shiftEndTime = $clockIn->copy()->addDay()->format('Y-m-d') . ' ' . $this->attendanceSettings->office_end_time;
        } else {
            $shiftEndTime = $clockIn->format('Y-m-d') . ' ' . $this->attendanceSettings->office_end_time;
        }

        if ($attendance && $request->user_id) {
            return Reply::error(__('messages.attendanceMarked'));
        } else {
            $attendances = Attendance::where('user_id', $request->user_id)
                ->whereBetween('clock_in_time', [$carbonDate->copy()->subDay(), $carbonDate->copy()->addDay()])
                ->get();

            $attendances = $attendances->filter(function ($item) use ($date) {
                return $item->clock_in_time->timezone($this->company->timezone)->format('Y-m-d') == $date;
            });

            foreach ($attendances as $item) {
                if (!(!$item->clock_in_time->timezone($this->company->timezone)->lt($clockIn) && $item->clock_out_time->timezone($this->company->timezone)->gt($clockIn) && !$item->clock_in_time->timezone($this->company->timezone)->lt($clockOut))) {
                    if (!($item->clock_out_time->timezone($this->company->timezone)->lt($clockIn))) {
                        return Reply::error(__('messages.attendanceMarked'));
                    }
                }
            }
        }

        if (!is_null($attendance) && !$request->user_id) {
            $attendance->update([
                'user_id' => $request->user_id,
                'clock_in_time' => $clockIn->copy()->timezone(config('app.timezone')),
                'clock_in_ip' => $request->clock_in_ip,
                'clock_out_time' => $clockOut?->copy()->timezone(config('app.timezone')),
                'clock_out_ip' => $request->clock_out_ip,
                'working_from' => $request->working_from,
                'location_id' => $request->location,
                'work_from_type' => $request->work_from_type,
                'clock_out_time_working_from' => $request->clock_out_time_working_from,
                'clock_out_time_work_from_type' => $request->clock_out_time_work_from_type,
                'clock_out_time_location_id' => $request->clock_out_time_location_id,
                'employee_shift_id' => $employeeShiftId,
                'shift_start_time' => $shiftStartTime,
                'shift_end_time' => $shiftEndTime,
                'late' => ($request->has('late')) ? 'yes' : 'no',
                'half_day' => ($request->has('halfday')) ? 'yes' : 'no',
                'half_day_type' => ($request->has('half_day_duration') && $request->has('halfday')) ? $request->half_day_duration : null
            ]);
        } else {
            $leave = Leave::where([
                ['user_id', $request->user_id],
                ['leave_date', $request->attendance_date]
            ])
                ->whereIn('duration', ['half day', 'single', 'multiple'])
                ->whereIn('status', ['approved', 'pending'])
                ->first();

            if (
                isset($leave) &&
                (
                    ($leave->duration == 'half day' && ($request->half_day_duration == $leave->half_day_type)) ||
                    ($leave->duration == 'single' || $leave->duration == 'multiple')
                )
            ) {
                // if (isset($leave)) {
                $leave->update(['status' => 'rejected']);
            }

            // Check maximum attendance in a day
            if ($clockInCount < $this->attendanceSettings->clockin_in_day || $request->user_id) {
                Attendance::create([
                    'user_id' => $request->user_id,
                    'clock_in_time' => $clockIn->copy()->timezone(config('app.timezone')),
                    'clock_in_ip' => $request->clock_in_ip,
                    'clock_out_time' => $clockOut?->copy()->timezone(config('app.timezone')),
                    'clock_out_ip' => $request->clock_out_ip,
                    'working_from' => $request->working_from,
                    'location_id' => $request->location,
                    'clock_out_time_working_from' => $request->clock_out_time_working_from,
                    'clock_out_time_work_from_type' => $request->clock_out_time_work_from_type,
                    'clock_out_time_location_id' => $request->clock_out_time_location_id,
                    'late' => ($request->has('late')) ? 'yes' : 'no',
                    'employee_shift_id' => $employeeShiftId,
                    'shift_start_time' => $shiftStartTime,
                    'shift_end_time' => $shiftEndTime,
                    'work_from_type' => $request->work_from_type,
                    'half_day' => ($request->has('halfday')) ? 'yes' : 'no',
                    'half_day_type' => ($request->has('half_day_duration') && $request->has('halfday')) ? $request->half_day_duration : null
                ]);
            } else {
                return Reply::error(__('messages.maxClockin'));
            }
        }

        return Reply::success(__('messages.attendanceSaveSuccess'));
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function byMember()
    {
        $this->pageTitle = 'modules.attendance.attendanceByMember';

        abort_403(!(in_array($this->viewAttendancePermission, ['all', 'added', 'owned', 'both'])));

        if ($this->viewAttendancePermission == 'owned') {
            $this->employees = User::where('id', user()->id)->get();
        } else {
            $this->employees = User::allEmployees(null, false, ($this->viewAttendancePermission == 'all' ? 'all' : null));
        }

        $this->attendanceRegularisation = AttendanceSetting::first();
        $regulariseRoles = $this->attendanceRegularisation->attendance_regularize_roles;
        $regulariseRolesArray = json_decode($regulariseRoles, true) ?? [];
        $userRoles = user_roles();

        $lastRole = end($userRoles);
        $condition = in_array($lastRole, $regulariseRolesArray) || $lastRole === 'admin';

        if(!in_array('admin', user_roles()) && !$condition)
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('user_id', user()->id)->where('status', 'pending')->count();
        }
        else
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('status', 'pending')->count();
        }

        $now = now();
        $this->year = $now->format('Y');
        $this->month = $now->format('m');

        return view('attendances.by_member', $this->data);
    }

    public function employeeData(Request $request, $startDate = null, $endDate = null, $userId = null)
    {
        // todo ::~
        $ant = []; // Array For attendance Data indexed by similar date
        $dateWiseData = []; // Array For Combine Data

        $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $request->month . '-' . $request->year)->startOfMonth()->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $userId = $request->userId;

        // Same timezone-adjusted range used by summaryData so by_member sees the same records
        $queryStart = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
        $officeStartTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $queryStart, company()->timezone);
        $queryStart = $queryStart->copy()->subMinutes($officeStartTimeDB->offset / 60);

        $queryEnd = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth();
        $officeEndTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $queryEnd, company()->timezone);
        $queryEnd = $queryEnd->copy()->subMinutes($officeEndTimeDB->offset / 60);

        $attendances = Attendance::without('company')
            ->with('shift')
            ->join('users', 'users.id', '=', 'attendances.user_id')
            ->leftJoin('company_addresses as clock_in_location', 'clock_in_location.id', '=', 'attendances.location_id')
            ->leftJoin('company_addresses as clock_out_location', 'clock_out_location.id', '=', 'attendances.clock_out_time_location_id')
            ->whereBetween('attendances.clock_in_time', [$queryStart, $queryEnd])
            ->where('attendances.user_id', '=', $userId)
            ->orderBy('attendances.clock_in_time', 'desc')
            ->select('attendances.*', 'users.*', 'attendances.id as aId', 'clock_in_location.location as location', 'clock_out_location.location as clockOutLocation')
            ->get();

        if ($this->viewAttendancePermission == 'added') {
            $attendances = $attendances->where('added_by', user()->id)->values();
        } elseif ($this->viewAttendancePermission == 'owned') {
            $attendances = $attendances->where('user_id', user()->id)->values();
        }

        $holidays = Holiday::getHolidayByDates($startDate, $endDate, $userId); // Getting Holiday Data
        $userId = $request->userId;

        $totalWorkingDays = $startDate->daysInMonth;

        $totalWorkingDays = $totalWorkingDays - count($holidays);
        $daysPresent = Attendance::countDaysPresentByUser($startDate, $endDate, $userId);
        $daysLate = Attendance::countDaysLateByUser($startDate, $endDate, $userId);
        $halfDays = Attendance::countHalfDaysByUser($startDate, $endDate, $userId);
        $daysAbsent = (($totalWorkingDays - $daysPresent) < 0) ? '0' : ($totalWorkingDays - $daysPresent);
        $holidayCount = Count($holidays);
        $attendanceSettings = AttendanceSetting::first();

        $lastDays = $attendanceSettings->last_days;

        $totalRequestData = AttendanceRegularisation::where('user_id', $userId);

        $totalRequestCheck = AttendanceRegularisation::where('user_id', $userId)
        ->whereDate('clock_in_time', '>=', $startDate)
        ->whereDate('clock_in_time', '<=', $endDate)
        ->get();

        if($attendanceSettings->adjustment_type == 'current_month'){
            $checkStartDate = \Carbon\Carbon::now()->startOfMonth();
            $checkEndDate = \Carbon\Carbon::now()->endOfMonth();

        }
        elseif($attendanceSettings->adjustment_type == 'current_week'){
            $checkStartDate = \Carbon\Carbon::now()->startOfWeek();
            $checkEndDate = \Carbon\Carbon::now()->endOfWeek();

        }
        elseif($attendanceSettings->adjustment_type == 'current_quarter'){
            $checkStartDate = \Carbon\Carbon::now()->startOfQuarter();
            $checkEndDate = \Carbon\Carbon::now()->endOfQuarter();
        }
        else{
            $checkStartDate = \Carbon\Carbon::now()->startOfYear();
            $checkEndDate = \Carbon\Carbon::now()->endOfYear();
        }

        $conditionWiseCount = $totalRequestData
            ->whereDate('clock_in_time', '>=', $checkStartDate->format('Y-m-d'))
            ->whereDate('clock_in_time', '<=', $checkEndDate->format('Y-m-d'))
            ->count();

        $employeeDetail = \App\Models\EmployeeDetails::where('user_id', $userId)->first();
        $employeeJoiningDate = $employeeDetail->joining_date;

        // Getting Leaves Data
        $leavesDates = Leave::where('user_id', $userId)
            ->where('leave_date', '>=', $startDate)
            ->where('leave_date', '<=', $endDate)
            ->where('status', 'approved')
            ->select('leave_date', 'reason', 'duration')
            ->get()->keyBy('date')->toArray();

        $holidayData = $holidays->keyBy('holiday_date');
        $holidayArray = $holidayData->toArray();

        // Day assignment matches summaryData — every fetched attendance is shown
        $monthStart = $startDate->toDateString();
        $monthEnd = $endDate->toDateString();
        $shiftSchedulesByDate = EmployeeShiftSchedule::with('shift')
            ->where('user_id', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get()
            ->keyBy(function ($schedule) {
                return Carbon::parse($schedule->date)->toDateString();
            });

        foreach ($attendances as $attand) {
            $clockInTimeUTC = $attand->clock_in_time->timezone(company()->timezone)->toDateTimeString();
            $clockInTime = Carbon::createFromFormat('Y-m-d H:i:s', $clockInTimeUTC, 'UTC');
            $attendanceDate = $clockInTime->toDateString();
            $dayToAssign = $attendanceDate;

            $shiftSchedule = $shiftSchedulesByDate[$attendanceDate] ?? null;

            if ($shiftSchedule && $shiftSchedule->shift) {
                $shift = $shiftSchedule->shift;
                $shiftStartTime = Carbon::parse($attendanceDate . ' ' . $shift->office_start_time);
                $shiftEndTime = Carbon::parse($attendanceDate . ' ' . $shift->office_end_time);

                if ($shiftStartTime->gt($shiftEndTime)) {
                    $shiftEndTime = $shiftEndTime->addDay();
                }

                $isWithinShift = $clockInTime->between($shiftStartTime, $shiftEndTime);
                $isPreviousShift = $clockInTime->betweenIncluded(
                    $shiftStartTime->copy()->subDay(),
                    $shiftEndTime->copy()->subDay()
                );
                $isAssignedShift = $attand->employee_shift_id == $shift->id;

                if ($isWithinShift || $isAssignedShift || $isPreviousShift) {
                    $dayToAssign = $isPreviousShift
                        ? $clockInTime->copy()->subDay()->toDateString()
                        : $attendanceDate;
                }
            }

            if ($dayToAssign >= $monthStart && $dayToAssign <= $monthEnd) {
                $ant[$dayToAssign][] = $attand;
            }
        }

        $shiftSchedules = EmployeeShiftSchedule::with('shift')
            ->select(DB::raw('DATE_FORMAT(date, "%Y-%m-%d") as day_off_date'))
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('shift', function ($query) {
                $query->where('shift_name', 'Day Off');
            })
            ->get();
        $dayOffData = $shiftSchedules->keyBy('day_off_date');
        $dayOffArray = $dayOffData->toArray();

        // Build day rows newest-first. Carbon 3 returns signed diffInDays, so the old
        // `diffInDays($startDate) > 0` loop never ran and only the 1st of the month appeared.
        $period = CarbonPeriod::create(
            $startDate->copy()->startOfDay(),
            $endDate->copy()->startOfDay()
        );

        foreach (array_reverse(iterator_to_array($period)) as $date) {
            if (!($date->isPast() || $date->isToday())) {
                continue;
            }

            $dateKey = $date->toDateString();

            $dateWiseData[$dateKey] = [
                'holiday' => false,
                'attendance' => false,
                'leave' => false,
                'day_off' => false
            ];

            if (array_key_exists($dateKey, $dayOffArray)) {
                $dateWiseData[$dateKey]['day_off'] = $dayOffData[$dateKey];
            }

            if (array_key_exists($dateKey, $holidayArray)) {
                $dateWiseData[$dateKey]['holiday'] = $holidayData[$dateKey];
            }

            if (array_key_exists($dateKey, $ant)) {
                $dateWiseData[$dateKey]['attendance'] = $ant[$dateKey];
            }

            if (array_key_exists($dateKey, $leavesDates)) {
                $dateWiseData[$dateKey]['leave'] = $leavesDates[$dateKey];
            }
        }

        // Getting View data
        $view = view('attendances.ajax.user_attendance', [
            'dateWiseData' => $dateWiseData,
            'global' => $this->company,
            'totalRequestCheck' => $totalRequestCheck,
            'lastDays' => $lastDays,
            'checkStartDate' => $checkStartDate,
            'checkEndDate' => $checkEndDate,
            'conditionWiseCount' => $conditionWiseCount,
            'employeeJoiningDate' => $employeeJoiningDate,
            'attendanceSettings' => $attendanceSettings,
            'userId' => $userId
        ])->render();

        $this->totalRequestCheck = $totalRequestCheck;
        $this->lastDays = $lastDays;
        $this->checkStartDate = $checkStartDate;
        $this->checkEndDate = $checkEndDate;
        $this->conditionWiseCount = $conditionWiseCount;
        $this->employeeJoiningDate = $employeeJoiningDate;
        $this->attendanceSettings = $attendanceSettings;

        return Reply::dataOnly(['status' => 'success', 'data' => $view, 'daysPresent' => $daysPresent, 'daysLate' => $daysLate, 'halfDays' => $halfDays, 'totalWorkingDays' => $totalWorkingDays, 'absentDays' => $daysAbsent, 'holidays' => $holidayCount]);
    }

    /**
     * Single employee, single month, day-by-day calendar grid (opened from
     * the "View Calendar" icon on the attendance summary table).
     */
    public function calendar($id, $month, $year)
    {
        $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $month . '-' . $year)->startOfMonth()->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $queryStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $officeStartTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $queryStart, company()->timezone);
        $queryStart = $queryStart->copy()->subMinutes($officeStartTimeDB->offset / 60);

        $queryEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $officeEndTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $queryEnd, company()->timezone);
        $queryEnd = $queryEnd->copy()->subMinutes($officeEndTimeDB->offset / 60);

        $attendances = Attendance::without('company')
            ->where('user_id', $id)
            ->whereBetween('clock_in_time', [$queryStart, $queryEnd])
            ->orderBy('clock_in_time')
            ->get();

        $holidays = Holiday::getHolidayByDates($startDate, $endDate, $id)->keyBy('holiday_date');

        $leaves = Leave::where('user_id', $id)
            ->where('leave_date', '>=', $startDate)
            ->where('leave_date', '<=', $endDate)
            ->where('status', 'approved')
            ->select('leave_date', 'reason', 'duration')
            ->get()->keyBy(function ($leave) {
                return Carbon::parse($leave->leave_date)->toDateString();
            });

        $dayOffs = EmployeeShiftSchedule::with('shift')
            ->where('user_id', $id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('shift', function ($query) {
                $query->where('shift_name', 'Day Off');
            })
            ->get()
            ->keyBy(function ($schedule) {
                return Carbon::parse($schedule->date)->toDateString();
            });

        $overrides = DB::table('attendance_day_overrides')
            ->where('user_id', $id)
            ->whereBetween('override_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(function ($row) {
                return Carbon::parse($row->override_date)->toDateString();
            });

        $attendancesByDate = [];

        foreach ($attendances as $attendance) {
            $dateKey = $attendance->clock_in_time->timezone(company()->timezone)->toDateString();
            $attendancesByDate[$dateKey][] = $attendance;
        }

        $dateWiseData = [];

        foreach (CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()) as $date) {
            $dateKey = $date->toDateString();

            $dateWiseData[$dateKey] = [
                'date' => $date->copy(),
                'is_future' => $date->isFuture(),
                'attendance' => $attendancesByDate[$dateKey] ?? null,
                'holiday' => $holidays->get($dateKey),
                'leave' => $leaves->get($dateKey),
                'day_off' => $dayOffs->get($dateKey),
                'override' => optional($overrides->get($dateKey))->status,
            ];
        }

        $this->calendarUser = User::withoutGlobalScope(ActiveScope::class)->findOrFail($id);
        $this->dateWiseData = $dateWiseData;
        $this->month = (int) $month;
        $this->year = (int) $year;
        $this->leadingBlanks = $startDate->dayOfWeek;
        $this->monthDashboard = $this->buildMonthlyLeaveDashboard($id, (int) $month, (int) $year);

        return view('attendances.ajax.calendar', $this->data);
    }

    /**
     * Persist (or clear) a manual status override for one employee/day, set
     * from the calendar's "Assign Status" panel. Stored in its own table
     * rather than `attendances`/`leaves`/`holidays` since those already carry
     * different semantics (clock records, leave applications, company-wide
     * holidays) that a one-off manual override shouldn't be forced into.
     */
    public function saveDayStatus(Request $request)
    {
        $addAttendancePermission = user()->permission('add_attendance');
        abort_403(!in_array($addAttendancePermission, ['all', 'added']));

        $allowedStatuses = ['cl', 'sl', 'lop', 'holiday', 'present', 'absent', 'leave', 'wfh', 'halfday', 'late', 'dayoff', 'cl-half', 'sl-half', 'lop-half'];

        $request->validate([
            'user_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'nullable|in:' . implode(',', $allowedStatuses),
        ]);

        $userId = (int) $request->user_id;
        $date = $request->date;
        $status = $request->status;

        if (empty($status)) {
            DB::table('attendance_day_overrides')
                ->where('user_id', $userId)
                ->where('override_date', $date)
                ->delete();

            return Reply::success(__('messages.recordSaved'));
        }

        DB::table('attendance_day_overrides')->updateOrInsert(
            ['user_id' => $userId, 'override_date' => $date],
            [
                'company_id' => company()->id,
                'status' => $status,
                'created_by' => user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return Reply::success(__('messages.recordSaved'));
    }

    /**
     * "N late comings = 1 half day" (Attendance Settings → Half Day
     * Settings). Applies exactly one pending deduction: finds the oldest
     * late attendance record in the given month that hasn't already been
     * used for a deduction, and flips it to Half Day. Never runs on its
     * own — only when an admin explicitly clicks Apply after reviewing the
     * flagged count on the attendance table.
     */
    public function applyLateHalfDay(Request $request)
    {
        $addAttendancePermission = user()->permission('add_attendance');
        abort_403(!in_array($addAttendancePermission, ['all', 'added']));

        $request->validate([
            'user_id' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        $userId = (int) $request->user_id;

        $monthStart = Carbon::createFromDate((int) $request->year, (int) $request->month, 1)->startOfMonth();
        $monthStartOffset = Carbon::createFromFormat('Y-m-d H:i:s', $monthStart, $this->company->timezone);
        $monthStart = $monthStart->copy()->subMinutes($monthStartOffset->offset / 60);

        $monthEnd = Carbon::createFromDate((int) $request->year, (int) $request->month, 1)->endOfMonth();
        $monthEndOffset = Carbon::createFromFormat('Y-m-d H:i:s', $monthEnd, $this->company->timezone);
        $monthEnd = $monthEnd->copy()->subMinutes($monthEndOffset->offset / 60);

        $threshold = max(1, (int) ($this->company->attendanceSetting->half_day_late_threshold ?? 3));

        $lateCount = Attendance::where('user_id', $userId)
            ->where('late', 'yes')
            ->whereBetween('clock_in_time', [$monthStart, $monthEnd])
            ->count();

        $appliedCount = Attendance::where('user_id', $userId)
            ->where('half_day_due_to_late', 1)
            ->whereBetween('clock_in_time', [$monthStart, $monthEnd])
            ->count();

        if (intdiv($lateCount, $threshold) - $appliedCount <= 0) {
            return Reply::error(__('messages.recordNotFound'));
        }

        $target = Attendance::where('user_id', $userId)
            ->where('late', 'yes')
            ->where('half_day_due_to_late', 0)
            ->whereBetween('clock_in_time', [$monthStart, $monthEnd])
            ->orderBy('clock_in_time')
            ->first();

        if (!$target) {
            return Reply::error(__('messages.recordNotFound'));
        }

        $target->half_day = 'yes';
        $target->half_day_type = $target->half_day_type ?: 'first_half';
        $target->half_day_due_to_late = 1;
        $target->last_updated_by = user()->id;
        $target->save();

        return Reply::success(__('messages.recordSaved'));
    }

    /**
     * CL/SL allocation, usage, carry-forward and LOP for one employee for the
     * given month — same policy-applied algorithm used on the Leave
     * Management dashboard, ported to PHP and run sequentially from January
     * up to the target month since carry-forward is cumulative.
     */
    private function buildMonthlyLeaveDashboard($userId, int $month, int $year): array
    {
        $policy = [
            'cl' => ['monthlyAllocation' => 1.0, 'carryForward' => true, 'halfDayRate' => 2],
            'sl' => ['monthlyAllocation' => 1.0, 'carryForward' => false, 'halfDayRate' => 2],
        ];

        $classify = function (string $typeName): ?string {
            $name = strtolower(trim($typeName));

            if (str_starts_with($name, 'casual')) {
                return 'cl';
            }

            if (str_starts_with($name, 'sick')) {
                return 'sl';
            }

            return null;
        };

        // Allocation/carry-forward now come straight from the Leave Settings
        // (leave_types) page, which is the single source of truth for these
        // rules — no more separate leave_policy table.
        $leaveTypeRows = DB::table('leave_types')
            ->where('company_id', company()->id)
            ->whereNull('deleted_at')
            ->select(['type_name', 'no_of_leaves', 'leavetype', 'monthly_limit', 'unused_leave'])
            ->get();

        foreach ($leaveTypeRows as $leaveTypeRow) {
            $key = $classify($leaveTypeRow->type_name);

            if ($key === null) {
                continue;
            }

            $noOfLeaves = (float) $leaveTypeRow->no_of_leaves;
            $monthlyLimit = (float) $leaveTypeRow->monthly_limit;

            if ($leaveTypeRow->leavetype === 'monthly') {
                $monthlyAllocation = $noOfLeaves;
            } elseif ($leaveTypeRow->leavetype === 'yearly') {
                $monthlyAllocation = $monthlyLimit > 0 ? $monthlyLimit : round($noOfLeaves / 12, 2);
            } else {
                $monthlyAllocation = 999999; // unlimited
            }

            $policy[$key] = [
                'monthlyAllocation' => $monthlyAllocation,
                'carryForward' => $leaveTypeRow->unused_leave === 'carry forward',
                'halfDayRate' => 2, // matches the app-wide 0.5-day half-day convention (LeaveHelper.php)
            ];
        }

        $monthlyRaw = [];

        for ($m = 1; $m <= $month; $m++) {
            $monthlyRaw[$m] = ['clUsed' => 0, 'slUsed' => 0, 'halfDays' => 0, 'clPending' => 0, 'slPending' => 0];
        }

        $leaveRows = DB::table('leaves as l')
            ->join('leave_types as lt', 'lt.id', '=', 'l.leave_type_id')
            ->where('l.user_id', $userId)
            ->whereYear('l.leave_date', $year)
            ->whereMonth('l.leave_date', '<=', $month)
            ->whereIn('l.status', ['approved', 'pending'])
            ->select(['lt.type_name', 'l.leave_date', 'l.status', 'l.half_day_type'])
            ->get();

        foreach ($leaveRows as $leaveRow) {
            $key = $classify($leaveRow->type_name);

            if ($key === null) {
                continue;
            }

            $m = (int) date('n', strtotime($leaveRow->leave_date));

            if ($leaveRow->status === 'pending') {
                $monthlyRaw[$m][$key . 'Pending']++;
                continue;
            }

            if (!empty($leaveRow->half_day_type)) {
                $monthlyRaw[$m]['halfDays']++;
            }
            else {
                $monthlyRaw[$m][$key . 'Used']++;
            }
        }

        $carryInCL = 0;
        $dashboard = [];

        for ($m = 1; $m <= $month; $m++) {
            $row = $monthlyRaw[$m];
            $clAllocated = $policy['cl']['monthlyAllocation'];
            $slAllocated = $policy['sl']['monthlyAllocation'];

            $halfToSL = intdiv($row['halfDays'], $policy['sl']['halfDayRate']);
            $totalSLUsed = $row['slUsed'] + $halfToSL;
            $slRemaining = $slAllocated - $totalSLUsed;

            $clDeductionForSL = 0;

            if ($slRemaining < 0) {
                $clDeductionForSL = abs($slRemaining);
                $slRemaining = 0;
            }

            $totalCLUsed = $row['clUsed'] + $clDeductionForSL;
            $clAvailable = $clAllocated + $carryInCL;
            $clRemaining = $clAvailable - $totalCLUsed;

            $lop = 0;

            if ($clRemaining < 0) {
                $lop = abs($clRemaining);
                $clRemaining = 0;
            }

            $thisCarryIn = $carryInCL;
            $carryInCL = ($policy['cl']['carryForward'] && $clRemaining > 0) ? $clRemaining : 0;

            if ($m === $month) {
                $dashboard = [
                    'clAllocated' => $clAllocated,
                    'slAllocated' => $slAllocated,
                    'clUsed' => $row['clUsed'],
                    'slUsed' => $row['slUsed'],
                    'halfDays' => $row['halfDays'],
                    'clPending' => $row['clPending'],
                    'slPending' => $row['slPending'],
                    'carryForwardCL' => $thisCarryIn,
                    'remainingCL' => $clRemaining,
                    'remainingSL' => $slRemaining,
                    'lop' => $lop,
                ];
            }
        }

        return $dashboard;
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $addPermission = user()->permission('add_attendance');

        abort_403(!($addPermission == 'all' || $addPermission == 'added'));
        $this->employees = User::allEmployees(null, true);
        $this->departments = Team::allDepartments();
        $this->pageTitle = __('modules.attendance.markAttendance');
        $this->year = now()->format('Y');
        $this->month = now()->format('m');
        $this->location = CompanyAddress::all();

        $dateFormat = Company::DATE_FORMATS;
        $this->dateformat = (isset($dateFormat[$this->company->date_format])) ? $dateFormat[$this->company->date_format] : 'DD-MM-YYYY';

        if ($addPermission == 'added') {
            $this->defaultAssign = user();
        } else if (isset(request()->default_assign)) {
            $this->defaultAssign = User::with('roles', 'employeeDetail', 'employeeDetail.department')->findOrFail(request()->default_assign);
        }

        // Optional pre-fill (e.g. from the attendance calendar's "Assign
        // Status" action) — a specific date plus the Late/Half Day/Work
        // From Home flags to pre-select. All optional; normal callers of
        // this route don't pass them and nothing below changes for them.
        if (request()->filled('date')) {
            $this->prefillDate = request()->date;
            $this->prefillLate = request()->late === 'yes';
            $this->prefillHalfDay = request()->half_day === 'yes';
            $this->prefillWorkFromType = request()->work_from_type;
        }

        if (request()->ajax()) {
            $html = view('attendances.ajax.create', $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'attendances.ajax.create';

        return view('attendances.create', $this->data);
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkMark(StoreBulkAttendance $request)
    {
        $employees = $request->user_id;
        $employeeData = User::withoutGlobalScope(ActiveScope::class)
            ->with('employeeDetail')->whereIn('id', $employees)->get();

        $date = Carbon::createFromFormat('d-m-Y', '01-' . $request->month . '-' . $request->year)->format('Y-m-d');
        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_in_time, $this->company->timezone);

        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_out_time, $this->company->timezone);

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                $clockOut = $clockOut->addDay();
            }
        }

        $period = [];

        if ($request->mark_attendance_by == 'month') {
            $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $request->month . '-' . $request->year, $this->company->timezone)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        } else {
            // Handle date format with " To " separator
            if (strpos($request->multi_date, ' To ') !== false) {
                $dates = explode(' To ', $request->multi_date);
            } else {
                $dates = explode(',', $request->multi_date);
            }

            if ($this->company->date_format == 'd F, Y') {
                $data = preg_match_all('/\d{1,2}\s\w+,\s\d{4}/', $request->multi_date, $dates);
                $dates = $dates[0];
            }
            $startDate = Carbon::createFromFormat($this->company->date_format, trim($dates[0]), $this->company->timezone)->startOfDay();
            $endDate = Carbon::createFromFormat($this->company->date_format, trim($dates[count($dates) - 1]), $this->company->timezone)->endOfDay();
        }

        $multiDates = CarbonPeriod::create($startDate, $endDate);
        $holidayError = false;
        $singleDatePeriod = $startDate->equalTo($endDate);

        foreach ($multiDates as $multiDate) {
            array_push($period, $multiDate);
        }

        $insertData = [];
        $currentDate = now($this->company->timezone);
        $showClockIn = AttendanceSetting::first();

        foreach ($employees as $key => $userId) {
            $userData = $employeeData->where('id', $userId)->first();

            if (request()->has('overwrite_attendance')) {
                // Convert dates to UTC for database query (clock_in_time is stored in UTC)
                // Start of day in company timezone, then convert to UTC
                $startDateUtc = $startDate->copy()->startOfDay()->utc();
                // End of day in company timezone, then convert to UTC
                $endDateUtc = $endDate->copy()->endOfDay()->utc();

                Attendance::where('user_id', $userId)
                    ->whereBetween('clock_in_time', [$startDateUtc, $endDateUtc])
                    ->delete();
            }

            // Retrieve holidays based on employee details
            $holidaysForUser = Holiday::where(function ($query) use ($userData) {
                $query->where(function ($subquery) use ($userData) {
                    $subquery->where(function ($q) use ($userData) {
                        $q->where('department_id_json', 'like', '%"' . $userData->employeeDetail->department_id . '"%')
                            ->orWhereNull('department_id_json');
                    });
                    $subquery->where(function ($q) use ($userData) {
                        $q->where('designation_id_json', 'like', '%"' . $userData->employeeDetail->designation_id . '"%')
                            ->orWhereNull('designation_id_json');
                    });
                    $subquery->where(function ($q) use ($userData) {
                        $q->where('employment_type_json', 'like', '%"' . $userData->employeeDetail->employment_type . '"%')
                            ->orWhereNull('employment_type_json');
                    });
                });
            })->get()->pluck('date')->map(function ($date) {
                return $date->format('Y-m-d');
            })->toArray();

            foreach ($period as $date) {

                $leave = Leave::where([
                    ['user_id', $userId],
                    ['leave_date', $date]
                ])
                    ->whereIn('duration', ['half day', 'single', 'multiple'])
                    ->whereIn('status', ['approved', 'pending'])
                    ->first();

                if (
                    isset($leave) &&
                    (
                        ($leave->duration == 'half day' && ($request->half_day_duration == $leave->half_day_type)) ||
                        ($leave->duration == 'single' || $leave->duration == 'multiple')
                    )
                ) {
                    // if (isset($leave)) {

                    $leave->update(['status' => 'rejected']);

                    // if ($date->format('Y-m-d') == $leave->leave_date->format('Y-m-d') && is_null($leave->half_day_type)) {
                    //     continue;
                    // }
                }

                $formattedDate = $date->format('Y-m-d');
                $holiday = in_array($formattedDate, $holidaysForUser);
                if ($holiday) {
                    if ($singleDatePeriod) {
                        $holidayError = true;
                        break;
                    }
                    continue;
                }

                $this->attendanceSettings = $this->attendanceShift($showClockIn, $userId, $date, $request->clock_in_time);

                $attendance = Attendance::where('user_id', $userId)
                    ->where(DB::raw('DATE(`clock_in_time`)'), $date->format('Y-m-d'))
                    ->first();

                if (
                    is_null($attendance)
                    && $date->greaterThanOrEqualTo($userData->employeeDetail->joining_date)
                    && $date->lessThanOrEqualTo($currentDate)
                    && !$holiday
                    && $this->attendanceSettings->shift_name != 'Day Off'
                ) { // Attendance should not exist for the user for the same date

                    $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date->format('Y-m-d') . ' ' . $request->clock_in_time, $this->company->timezone);

                    $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date->format('Y-m-d') . ' ' . $request->clock_out_time, $this->company->timezone);

                    if ($clockIn->gt($clockOut) && !is_null($clockOut)) {

                        $clockOut = $clockOut->addDay();
                    }

                    $insertData[] = [
                        'user_id' => $userId,
                        'company_id' => company()->id,
                        'clock_in_time' => $clockIn->copy()->timezone(config('app.timezone')),
                        'clock_in_ip' => request()->ip(),
                        'clock_out_time' => $clockOut?->copy()->timezone(config('app.timezone')),
                        'clock_out_ip' => request()->ip(),
                        'working_from' => $request->working_from,
                        'work_from_type' => $request->work_from_type,
                        'location_id' => $request->location,
                        'clock_out_time_working_from' => $request->clock_out_time_working_from,
                        'clock_out_time_work_from_type' => $request->clock_out_time_work_from_type,
                        'clock_out_time_location_id' => $request->clock_out_time_location_id,
                        'late' => $request->late,
                        'half_day' => $request->half_day,
                        'half_day_type' => ($request->has('half_day_duration') && $request->half_day === 'yes') ? $request->half_day_duration : null,
                        'added_by' => user()->id,
                        'employee_shift_id' => $this->attendanceSettings->id,
                        'overwrite_attendance' => request()->has('overwrite_attendance') ? $request->overwrite_attendance : 'no',
                        'last_updated_by' => user()->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }
        if ($holidayError) {
            return Reply::error(__('messages.holidayError')); // Display error message
        }

        Attendance::insertOrIgnore($insertData);

        $redirectUrl = urldecode($request->redirect_url);

        if ($redirectUrl == '') {
            $redirectUrl = route('attendances.index');
        }

        return Reply::redirect($redirectUrl, __('messages.attendanceSaveSuccess'));
    }

    public function checkHalfDay(Request $request)
    {
        $halfDayDurationEnded = null; // Yes  ? not on half day &  No ? on half day

        if ($request->type == 'bulkMark') {

            if ($request->mark_attendance_by == 'date') {
                // multi_date: 16-04-2025,16-04-2025
                $dates = explode(',', $request->multi_date);

                if (count($dates) === 4) {
                    $startDateRaw = trim($dates[0]) . ', ' . trim($dates[1]); // "03 June, 2025"
                    $endDateRaw = trim($dates[2]) . ', ' . trim($dates[3]);   // "06 June, 2025"

                    $startDate = Carbon::createFromFormat($this->company->date_format, $startDateRaw, $this->company->timezone);
                    $endDate = Carbon::createFromFormat($this->company->date_format, $endDateRaw, $this->company->timezone);
                }else{

                    $startDate = Carbon::createFromFormat($this->company->date_format, trim($dates[0]), $this->company->timezone);
                    $endDate = Carbon::createFromFormat($this->company->date_format, trim($dates[1]), $this->company->timezone);
                }

                $dates = CarbonPeriod::create($startDate, $endDate);
                $dates = $dates->toArray();

            } else {

                $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $request->month . '-' . $request->year)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $period = CarbonPeriod::create($startDate, $endDate);

                $dates = [];

                foreach ($period as $date) {
                    $dates[] = $date;
                }
            }

            if ($request->has('user_id') && $request->user_id !== null) {
                $leaves = Leave::whereIn('user_id', $request->user_id)
                    ->whereIn('leave_date', $dates)
                    ->where('status', '!=', 'rejected')
                    ->whereIn('duration', ['half day', 'single', 'multiple'])
                    ->get();
            }

            if (isset($leaves) && $leaves->isNotEmpty()) {
                foreach ($leaves as $leave) {

                    if ($leave->duration === 'half day') {
                        $userShifts = User::findOrFail($leave->user_id)->shifts()->whereDate('date', $leave->leave_date)->get();
                        if ($userShifts->isNotEmpty()) {
                            $halfDayDurationEnded = $this->halfDayShiftCheck($leave, true, $userShifts);
                        } else {
                            $halfDayDurationEnded = $this->halfDayShiftCheck($leave, false);
                        }
                    }

                    $halfDay = $leave->duration == 'half day' ? 'no' : 'yes';
                    $this->halfDayExist = $leave->duration == 'half day';
                    $this->requestedHalfDay = $halfDay;
                    $this->halfDayDurEnd = $halfDayDurationEnded;

                    $fullDay = ($leave->duration == 'single' || $leave->duration == 'multiple') ? 'no' : 'yes';
                    $this->fullDayExist = $leave->duration == 'single' || $leave->duration == 'multiple';
                    $this->requestedFullDay = $fullDay;

                    $this->attendanceDuration = $request->half_day_duration;
                    $this->leaveDuration = $leave->half_day_type;

                    $this->user = $leave->user->name;
                }
            }

            return reply::dataOnly($this->data);
        } else {
            $leave = Leave::where([
                ['user_id', $request->user_id],
                ['leave_date', $request->attendance_date]
            ])->where('status', '!=', 'rejected')
                ->whereIn('duration', ['half day', 'single', 'multiple'])->first();

            if (isset($leave)) {

                if ($leave->duration === 'half day') {
                    $userShifts = User::findOrFail($leave->user_id)->shifts()->whereDate('date', $leave->leave_date)->get();
                    if ($userShifts->isNotEmpty()) {
                        $halfDayDurationEnded = $this->halfDayShiftCheck($leave, true, $userShifts);
                    } else {
                        $halfDayDurationEnded = $this->halfDayShiftCheck($leave, false);
                    }
                }

                $halfDay = $leave->duration == 'half day' ? 'no' : 'yes';
                $this->halfDayExist = $leave->duration == 'half day' ? true : false;
                $this->requestedHalfDay = $halfDay;
                $this->halfDayDurEnd = $halfDayDurationEnded;

                $fullDay = ($leave->duration == 'single' || $leave->duration == 'multiple') ? 'no' : 'yes';
                $this->fullDayExist = ($leave->duration == 'single' || $leave->duration == 'multiple') ? true : false;
                $this->requestedFullDay = $fullDay;

                $this->attendanceDuration = $request->half_day_duration;
                $this->leaveDuration = $leave->half_day_type;

                $this->user = $leave->user->name;
            }

            return reply::dataOnly($this->data);
        }
    }

    public function halfDayShiftCheck($leave, $shiftFound, $userShifts = null)
    {

        if ($shiftFound) {
            foreach ($userShifts as $userShift) {

                $halfMarkTime = Carbon::createFromFormat('H:i:s', $userShift->shift->halfday_mark_time, $this->company->timezone);
                $currentTime = Carbon::now($this->company->timezone)->format('H:i:s');
                $currentTimeCarbon = Carbon::createFromFormat('H:i:s', $currentTime, $this->company->timezone);

                if ($leave->half_day_type == 'first_half') {
                    $halfDayDurationEnded = ($currentTimeCarbon->greaterThan($halfMarkTime)) ? 'yes' : 'no';
                } elseif ($leave->half_day_type == 'second_half') {
                    $halfDayDurationEnded = ($currentTimeCarbon->lessThan($halfMarkTime)) ? 'yes' : 'no';
                }
            }
        } else {
            $attendanceSetting = AttendanceSetting::first();
            $defaultShiftId = $attendanceSetting->default_employee_shift;
            $defaultShift = EmployeeShift::findOrFail($defaultShiftId);

            $halfMarkTime = Carbon::createFromFormat('H:i:s', $defaultShift->halfday_mark_time, $this->company->timezone);
            $currentTime = Carbon::now($this->company->timezone)->format('H:i:s');
            $currentTimeCarbon = Carbon::createFromFormat('H:i:s', $currentTime, $this->company->timezone);

            if ($leave->half_day_type == 'first_half') {
                $halfDayDurationEnded = ($currentTimeCarbon->greaterThan($halfMarkTime)) ? 'yes' : 'no';
            } elseif ($leave->half_day_type == 'second_half' && $currentTimeCarbon->lessThan($halfMarkTime)) {
                $halfDayDurationEnded = ($currentTimeCarbon->lessThan($halfMarkTime)) ? 'yes' : 'no';
            }
        }

        return $halfDayDurationEnded;
    }

    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);
        $deleteAttendancePermission = user()->permission('delete_attendance');

        abort_403(!($deleteAttendancePermission == 'all' || ($deleteAttendancePermission == 'added' && $attendance->added_by == user()->id)));
        Attendance::destroy($id);

        return Reply::success(__('messages.deleteSuccess'));
    }

    /**
     * Bulk-deletes attendance rows created by the Import Attendance feature, scoped to the
     * employee/month/year currently selected on the summary page. Imported rows are identified
     * by added_by being null — ImportAttendanceJob runs on the queue with no authenticated user,
     * so AttendanceObserver never sets it, unlike every other way an attendance row gets created
     * (self clock-in, Mark Attendance, manual edit), which always has a real user() to stamp it.
     */
    public function deleteImportedAttendance(Request $request)
    {
        $deleteAttendancePermission = user()->permission('delete_attendance');

        abort_403($deleteAttendancePermission != 'all');

        $query = Attendance::where('company_id', company()->id)->whereNull('added_by');

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('clock_in_time', $request->month)->whereYear('clock_in_time', $request->year);
        }

        if ($request->filled('userId') && $request->userId != 'all') {
            $query->where('user_id', $request->userId);
        }

        $count = $query->count();
        $query->delete();

        return Reply::successWithData(__('messages.deleteSuccess'), ['count' => $count]);
    }

    /**
     * Deletes ALL attendance rows for one employee in one month — unlike
     * deleteImportedAttendance() above, this isn't limited to added_by IS NULL rows,
     * since manually-marked/edited attendance (which does set added_by) is exactly
     * what this per-employee "..." action on the Attendance grid is meant to clear.
     */
    public function deleteUserMonthAttendance(Request $request)
    {
        $deleteAttendancePermission = user()->permission('delete_attendance');

        abort_403($deleteAttendancePermission != 'all');

        $request->validate([
            'userId' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        $query = Attendance::where('company_id', company()->id)
            ->where('user_id', $request->userId)
            ->whereMonth('clock_in_time', $request->month)
            ->whereYear('clock_in_time', $request->year);

        $count = $query->count();
        $query->delete();

        return Reply::successWithData(__('messages.deleteSuccess'), ['count' => $count]);
    }

    public function importAttendance()
    {
        $this->pageTitle = __('app.importExcel') . ' ' . __('app.menu.attendance');

        $addPermission = user()->permission('add_attendance');

        abort_403(!($addPermission == 'all' || $addPermission == 'added'));


        if (request()->ajax()) {
            $html = view('attendances.ajax.import', $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'attendances.ajax.import';

        return view('attendances.create', $this->data);
    }

    public function importStore(ImportRequest $request)
    {
        // Biometric device exports for this feature routinely run into the
        // tens of thousands of punch rows — the default 512M limit isn't
        // enough to parse a file that size, let alone the batch of one
        // queued job per row created in importProcess() below.
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $rvalue = $this->importFileProcess($request, AttendanceImport::class);

        if ($rvalue == 'abort') {
            return Reply::error(__('messages.abortAction'));
        }

        $view = view('attendances.ajax.import_progress', $this->data)->render();

        return Reply::successWithData(__('messages.importUploadSuccess'), ['view' => $view]);
    }

    public function importProcess(ImportProcessRequest $request)
    {
        // The queue is now drained synchronously within this same request (see
        // importAttendancePunchesJobProcess() below) — with tens of thousands of
        // rows this request can legitimately run for several minutes.
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $batch = $this->importAttendancePunchesJobProcess($request, AttendanceImport::class, ImportAttendanceJob::class);

        return Reply::successWithData(__('messages.importProcessStart'), ['batch' => $batch]);
    }

    /**
     * Same as ImportExcel::importJobProcess(), except rows are sorted chronologically by
     * punch_time before jobs are dispatched. Clock-Out punches are paired to the most recent
     * open Clock-In session for the employee, so punches must be processed oldest-first —
     * raw biometric device exports are typically newest-first.
     */
    private function importAttendancePunchesJobProcess($request, $importClass, $importJobClass)
    {
        $importClassName = (new ReflectionClass($importClass))->getShortName();

        Artisan::call('queue:clear database --queue=' . $importClassName . ' --force');
        Artisan::call('queue:flush --no-interaction');

        $columns = array_filter($request->columns, function ($value) {
            return $value !== null;
        });

        $importInstance = new $importClass;
        Excel::import($importInstance, public_path(Files::UPLOAD_FOLDER . '/' . Files::IMPORT_FOLDER . '/' . $request->file));
        $excelData = $importInstance->getProcessedData();

        if ($request->has_heading) {
            array_shift($excelData);
        }

        $punchTimeIndex = array_search('punch_time', $columns);

        if ($punchTimeIndex !== false) {
            usort($excelData, function ($rowA, $rowB) use ($punchTimeIndex) {
                $timeA = $this->parsePunchTimeForSort($rowA[$punchTimeIndex] ?? null);
                $timeB = $this->parsePunchTimeForSort($rowB[$punchTimeIndex] ?? null);

                return $timeA <=> $timeB;
            });
        }

        Session::put('leads_count', count($excelData));

        // Punch pairing legitimately rejects some individual rows (orphan Clock-Out, name mismatch, etc.) —
        // allowFailures() keeps the batch running so those failures don't cancel every remaining pending job.
        $batch = Bus::batch([])->onConnection('database')->onQueue($importClassName)->name($importClassName)->allowFailures()->dispatch();

        // One job per chunk of rows, not one job per row — for a 39k-row file that's ~80 queue
        // reserve/execute/delete cycles instead of ~39,618 of them, which was the single biggest
        // cost in how long this import used to take. Row-level failures are still reported
        // individually (see ImportAttendanceJob::recordRowFailure()), just no longer 1:1 with jobs.
        $rowChunks = array_chunk($excelData, 500);

        // Jobs are still added to the batch in groups rather than one giant INSERT — safe
        // headroom against MySQL's max_allowed_packet regardless of how large a future import
        // file gets, even though a 39k-row file now only produces ~80 jobs total.
        foreach (array_chunk($rowChunks, 50) as $jobGroup) {
            $jobs = array_map(fn ($rows) => new $importJobClass($rows, $columns, company()), $jobGroup);
            $batch = $batch->add($jobs);
        }

        // Drain the queue synchronously, right here, instead of requiring a separately-running
        // `php artisan queue:work` process — no worker needed, the whole import completes within
        // this one request. (Same mechanism ImportController::getImportProgress() already uses on
        // each progress poll, just run once to full completion instead of in small increments.)
        // --memory must be raised to match the ini_set() memory_limit above, or the worker silently
        // stops itself early — well short of a 39k-row queue — once past its own default 128MB budget.
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => $importClassName,
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--memory' => 2048,
        ]);

        Files::deleteFile($request->file, Files::IMPORT_FOLDER);

        return $batch->fresh();
    }

    private function parsePunchTimeForSort($value)
    {
        $parsed = AttendanceImport::parsePunchTime($value);

        return $parsed ? $parsed->timestamp : 0;
    }

    public function exportAttendanceByMember($year, $month, $id)
    {
        abort_403(!canDataTableExport());

        $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $month . '-' . $year)->startOfMonth()->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $obj = User::findOrFail($id);
        $date = $endDate->lessThan(now()) ? $endDate : now();

        return Excel::download(new AttendanceByMemberExport($year, $month, $id, $obj->name, $startDate, $endDate), $obj->name . '_' . $startDate->format('d-m-Y') . '_To_' . $date->format('d-m-Y') . '.xlsx');
    }

    public function exportAllAttendance($year, $month, $id, $department, $designation)
    {
        abort_403(!canDataTableExport());

        $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $month . '-' . $year)->startOfMonth()->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $date = $endDate->lessThan(now()) ? $endDate : now();

        return Excel::download(new AttendanceExport($year, $month, $id, $department, $designation, $startDate, $endDate), 'Attendance_From_' . $startDate->format('d-m-Y') . '_To_' . $date->format('d-m-Y') . '.xlsx');
    }

    public function exportAllAttendanceClock($year, $month, $id, $department, $designation)
    {
        abort_403(!canDataTableExport());

        $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $month . '-' . $year)->startOfMonth()->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $date = $endDate->lessThan(now()) ? $endDate : now();

        return Excel::download(
            new AttendanceClockExport($year, $month, $id, $department, $designation, $startDate, $date),
            'Attendance_Clock_From_' . $startDate->format('d-m-Y') . '_To_' . $date->format('d-m-Y') . '.xlsx'
        );
    }

    public function byHour(Request $request)
    {
        $this->pageTitle = 'modules.attendance.attendanceByHour';

        abort_403(!(in_array($this->viewAttendancePermission, ['all', 'added', 'owned', 'both'])));

        if (request()->ajax()) {
            return $this->hourSummaryData($request);
        }

        $users = User::with('employeeDetails')->join('employee_details', 'users.id', '=', 'employee_details.user_id');

        if ($this->viewAttendancePermission == 'owned') {
            $this->employees = $users->where(function ($query) {
                $query->where('employee_details.user_id', user()->id);
            })->get();
        } elseif ($this->viewAttendancePermission == 'added') {
            $this->employees = $users->where(function ($query) {
                $query->where('employee_details.added_by', user()->id);
            })->get();
        } elseif ($this->viewAttendancePermission == 'both') {
            $this->employees = $users->where(function ($query) {
                $query->where('employee_details.user_id', user()->id)
                    ->orWhere('employee_details.added_by', user()->id);
            })->get();
        } elseif ($this->viewAttendancePermission == 'all') {
            $this->employees = User::allEmployees(null, false, ($this->viewAttendancePermission == 'all' ? 'all' : null));
        }

        $this->attendanceRegularisation = AttendanceSetting::first();
        $regulariseRoles = $this->attendanceRegularisation->attendance_regularize_roles;
        $regulariseRolesArray = json_decode($regulariseRoles, true) ?? [];
        $userRoles = user_roles();

        $lastRole = end($userRoles);
        $condition = in_array($lastRole, $regulariseRolesArray) || $lastRole === 'admin';

        if(!in_array('admin', user_roles()) && !$condition)
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('user_id', user()->id)->where('status', 'pending')->count();
        }
        else
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('status', 'pending')->count();
        }

        $now = now(company()->timezone);
        $this->year = $now->format('Y');
        $this->month = $now->format('m');
        $this->departments = Team::all();
        $this->designations = Designation::all();

        return view('attendances.by_hour', $this->data);
    }

    public function hourSummaryData($request)
    {
        $employees = User::with(
            [
                'employeeDetail.designation:id,name',
                'attendance' => function ($query) use ($request) {
                    $query->whereRaw('MONTH(attendances.clock_in_time) = ?', [$request->month])
                        ->whereRaw('YEAR(attendances.clock_in_time) = ?', [$request->year]);

                    if ($this->viewAttendancePermission == 'added') {
                        $query->where('attendances.added_by', user()->id);
                    } elseif ($this->viewAttendancePermission == 'owned') {
                        $query->where('attendances.user_id', user()->id);
                    }
                },
                'leaves' => function ($query) use ($request) {
                    $query->whereRaw('MONTH(leaves.leave_date) = ?', [$request->month])
                        ->whereRaw('YEAR(leaves.leave_date) = ?', [$request->year])
                        ->where('status', 'approved');
                },
                'shifts' => function ($query) use ($request) {
                    $query->whereRaw('MONTH(employee_shift_schedules.date) = ?', [$request->month])
                        ->whereRaw('YEAR(employee_shift_schedules.date) = ?', [$request->year]);
                },
                'leaves.type',
                'shifts.shift'
            ]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.inactive_date', 'users.email', 'users.created_at', 'employee_details.department_id', 'users.image')
            ->onlyEmployee()->withoutGlobalScope(ActiveScope::class)
            ->where(function ($query) use ($request) {
                $query->whereNull('users.inactive_date')
                    ->orWhere(function ($subQuery) use ($request) {
                        $subQuery->whereRaw('YEAR(users.inactive_date) >= ?', [$request->year])
                            ->whereRaw('MONTH(users.inactive_date) >= ?', [$request->month]);
                    });
            })
            ->groupBy('users.id');

        if ($request->department != 'all') {
            $employees = $employees->where('employee_details.department_id', $request->department);
        }

        if ($request->designation != 'all') {
            $employees = $employees->where('employee_details.designation_id', $request->designation);
        }

        if ($request->userId != 'all') {
            $employees = $employees->where('users.id', $request->userId);
        }

        if ($this->viewAttendancePermission == 'owned') {
            $employees = $employees->where('users.id', user()->id);
        } elseif ($this->viewAttendancePermission == 'both') {
            $employees = $employees->where('employee_details.user_id', user()->id)
                ->orWhere('employee_details.added_by', user()->id);
        } elseif ($this->viewAttendancePermission == 'added') {
            $employees = $employees->where('employee_details.added_by', user()->id);
        }

        $employees = $employees->get();

        $this->holidays = Holiday::whereRaw('MONTH(holidays.date) = ?', [$request->month])->whereRaw('YEAR(holidays.date) = ?', [$request->year])->get();

        $final = [];
        $holidayOccasions = [];
        $total = [];

        $leaveReasons = [];
        $this->daysInMonth = Carbon::parse('01-' . $request->month . '-' . $request->year)->daysInMonth;
        $now = now()->timezone($this->company->timezone);
        $requestedDate = Carbon::parse(Carbon::parse('01-' . $request->month . '-' . $request->year))->endOfMonth();

        foreach ($employees as $count => $employee) {

            $dataBeforeJoin = null;

            $dataTillToday = array_fill(1, $now->copy()->format('d'), 'Absent');
            $dataTillRequestedDate = array_fill(1, (int)$this->daysInMonth, 'Absent');
            $daysTofill = ((int)$this->daysInMonth - (int)$now->copy()->format('d'));

            if (($now->copy()->format('d') != $this->daysInMonth) && !$requestedDate->isPast()) {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), (($daysTofill >= 0 ? $daysTofill : 0)), '-');
            } else {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), (($daysTofill >= 0 ? $daysTofill : 0)), 'Absent');
            }

            if (!$requestedDate->isPast()) {
                $final[$employee->id . '#' . $employee->name] = array_replace($dataTillToday, $dataFromTomorrow);
            } else {
                $final[$employee->id . '#' . $employee->name] = array_replace($dataTillRequestedDate, $dataFromTomorrow);
            }

            $totalMinutes = 0;

            $previousdate = null;

            foreach ($employee->shifts as $shifts) {
                if ($shifts->shift->shift_name == 'Day Off') {
                    $final[$employee->id . '#' . $employee->name][$shifts->date->day] = 'Day Off';
                }
            }

            foreach ($employee->attendance as $index => $attendance) {

                $from = $attendance->clock_in_time?->timezone(company()->timezone);

                $defaultEndDateAndTime = Carbon::createFromFormat('Y-m-d H:i:s', $from?->format('Y-m-d') . ' ' . attendance_setting()->shift->office_end_time, company()->timezone);

                $to = $attendance->clock_out_time ?: $defaultEndDateAndTime;

                // totalTime() function is used to calculate the total time of an employee on particular date
                $diffInMins = ($to && $from) ? $attendance->totalTime($from, $to, $employee->id, 'm') : 0;

                // previous date is used to store the previous date of an attendance, so that we can calculate the total time of an employee on particular date
                if ($index == 0) {
                    $previousdate = $from->format('Y-m-d');
                    $totalMinutes += $diffInMins;
                } elseif ($previousdate != $from->format('Y-m-d')) {
                    $totalMinutes += $diffInMins;
                    $previousdate = $from->format('Y-m-d');
                }

                if ($diffInMins === 0) {
                    $mins = $totalMinutes;
                } else {
                    $mins = $diffInMins;
                }
                $final[$employee->id . '#' . $employee->name][Carbon::parse($attendance->clock_in_time)->timezone($this->company->timezone)->day] = '<a href="javascript:;" class="view-attendance" data-attendance-id="' . $attendance->id . '">' . intdiv($mins, 60) . ':' . ($mins % 60) . '</a>';
            }

            // Convert minutes to hours
            /** @phpstan-ignore-next-line */
            $resultTotalTime = CarbonInterval::formatHuman($totalMinutes);

            $total[$count] = $resultTotalTime;

            $emplolyeeName = view('components.employee', [
                'user' => $employee
            ]);

            $final[$employee->id . '#' . $employee->name][] = $emplolyeeName;

            // Joining date is informational only here — real attendance/import data before an
            // employee's joining_date must still display (e.g. a bulk import of pre-joining
            // punch history), so pre-join dates are no longer blanked out with a dash.

            if (Carbon::parse('01-' . $request->month . '-' . $request->year)->isFuture()) {
                $dataBeforeJoin = array_fill(1, $this->daysInMonth, '-');
            }

            if (!is_null($dataBeforeJoin)) {
                $final[$employee->id . '#' . $employee->name] = array_replace($final[$employee->id . '#' . $employee->name], $dataBeforeJoin);
            }

            foreach ($employee->leaves as $leave) {
                if ($leave->duration == 'half day') {
                    if ($final[$employee->id . '#' . $employee->name][$leave->leave_date->day] == '-' || $final[$employee->id . '#' . $employee->name][$leave->leave_date->day] == 'Absent') {
                        $final[$employee->id . '#' . $employee->name][$leave->leave_date->day] = 'Half Day';
                    }
                } else {
                    $final[$employee->id . '#' . $employee->name][$leave->leave_date->day] = 'Leave';
                    $leaveReasons[$employee->id][$leave->leave_date->day] = $leave->type->type_name . ': ' . $leave->reason;
                }
            }

            foreach ($employee->shifts as $shifts) {
                if ($shifts->shift->shift_name == 'Day Off') {
                    $final[$employee->id . '#' . $employee->name][$shifts->date->day] = 'Day Off';
                }
            }

            foreach ($this->holidays as $holiday) {
                $departmentId = $employee->employeeDetail->department_id;
                $designationId = $employee->employeeDetail->designation_id;
                $employmentType = $employee->employeeDetail->employment_type;


                $holidayDepartment = (!is_null($holiday->department_id_json)) ? json_decode($holiday->department_id_json) : [];
                $holidayDesignation = (!is_null($holiday->designation_id_json)) ? json_decode($holiday->designation_id_json) : [];
                $holidayEmploymentType = (!is_null($holiday->employment_type_json)) ? json_decode($holiday->employment_type_json) : [];

                if (((in_array($departmentId, $holidayDepartment) || $holiday->department_id_json == null) &&
                    (in_array($designationId, $holidayDesignation) || $holiday->designation_id_json == null) &&
                    (in_array($employmentType, $holidayEmploymentType) || $holiday->employment_type_json == null))) {


                    if ($final[$employee->id . '#' . $employee->name][$holiday->date->day] == 'Absent' || $final[$employee->id . '#' . $employee->name][$holiday->date->day] == '-') {
                        $final[$employee->id . '#' . $employee->name][$holiday->date->day] = 'Holiday';
                        $holidayOccasions[$holiday->date->day] = $holiday->occassion;
                    }
                }
            }
        }

        $this->employeeAttendence = $final;
        $this->holidayOccasions = $holidayOccasions;
        $this->total = $total;
        $this->month = $request->month;
        $this->year = $request->year;
        $this->leaveReasons = $leaveReasons;

        $view = view('attendances.ajax.hour_summary_data', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    public function byMapLocation(Request $request)
    {
        abort_403(!(in_array($this->viewAttendancePermission, ['all', 'added', 'owned', 'both'])));

        if (request()->ajax()) {
            return $this->byMapLocationData($request);
        }

        $this->employees = User::allEmployees(null, true, ($this->viewAttendancePermission == 'all' ? 'all' : null));
        $this->departments = Team::all();

        return view('attendances.by_map_location', $this->data);
    }

    protected function byMapLocationData($request)
    {
        $this->attendances = Attendance::with('user')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->select('attendances.*')
            ->whereDate('clock_in_time', companyToDateString($request->attendance_date))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->department != 'all') {
            $this->attendances = $this->attendances->where('employee_details.department_id', $request->department);
        }

        if ($request->userId != 'all') {
            $this->attendances = $this->attendances->where('users.id', $request->userId);
        }

        if ($this->viewAttendancePermission == 'owned') {
            $this->attendances = $this->attendances->where('users.id', user()->id);
        }

        if ($request->late != 'all') {
            $this->attendances = $this->attendances->where('attendances.late', $request->late);
        }

        $this->attendances = $this->attendances->get();

        $view = view('attendances.ajax.map_location_data', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    public function attendanceShift($defaultAttendanceSettings, $userId, $date, $clockInTime)
    {
        $checkPreviousDayShift = EmployeeShiftSchedule::without('shift')->where('user_id', $userId)
            ->where('date', $date->copy()->subDay()->toDateString())
            ->first();

        $checkTodayShift = EmployeeShiftSchedule::without('shift')->where('user_id', $userId)
            ->where('date', $date->copy()->toDateString())
            ->first();

        $backDayFromDefault = Carbon::parse($date->copy()->subDay()->format('Y-m-d') . ' ' . $defaultAttendanceSettings->office_start_time);

        $backDayToDefault = Carbon::parse($date->copy()->subDay()->format('Y-m-d') . ' ' . $defaultAttendanceSettings->office_end_time);

        if ($backDayFromDefault->gt($backDayToDefault)) {
            $backDayToDefault->addDay();
        }

        $nowTime = Carbon::createFromFormat('Y-m-d' . ' ' . company()->time_format, $date->copy()->toDateString() . ' ' . $clockInTime, 'UTC');

        if ($checkPreviousDayShift && $nowTime->betweenIncluded($checkPreviousDayShift->shift_start_time, $checkPreviousDayShift->shift_end_time)) {
            $attendanceSettings = $checkPreviousDayShift;
        } else if ($nowTime->betweenIncluded($backDayFromDefault, $backDayToDefault)) {
            $attendanceSettings = $defaultAttendanceSettings;
        } else if (
            $checkTodayShift &&
            ($nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time) || $nowTime->gt($checkTodayShift->shift_end_time))
        ) {
            $attendanceSettings = $checkTodayShift;
        } else if ($checkTodayShift && $checkTodayShift->shift->shift_type == 'flexible') {
            $attendanceSettings = $checkTodayShift;
        } else {
            $attendanceSettings = $defaultAttendanceSettings;
        }

        return $attendanceSettings->shift;
    }

    public function addAttendance($userID, $day, $month, $year)
    {
        $this->date = Carbon::createFromFormat('d-m-Y', $day . '-' . $month . '-' . $year)->format('Y-m-d');
        $this->attendance = Attendance::whereUserId($userID)->first();

        $attendanceSettings = EmployeeShiftSchedule::where('user_id', $userID)->where('date', $this->date)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;
        } else {
            $this->attendanceSettings = attendance_setting(); // Do not get this from session here

        }

        $this->location = CompanyAddress::all();
        $this->attendanceUser = User::findOrFail($userID);

        return view('attendances.ajax.add_user_attendance', $this->data);
    }

    public function qrCodeStatus(Request $request)
    {

        $attendanceSetting = AttendanceSetting::first();

        if (request()->qr_status == 1 && $attendanceSetting->auto_clock_in == 'yes') {
            return Reply::error(__('messages.qrCodeAndFirstSignError'));
        }

        $attendanceSetting->qr_enable = $request->qr_status;
        $attendanceSetting->save();

        return Reply::success('Success');
    }

    public function qrClockInOut(Request $request, $hash)
    {
        // Check if the user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('info', 'Please log in to clock in.');
        }

        // Retrieve the authenticated user
        $user = user();

        $leaveApplied = Leave::where('user_id', $user->id)
            ->whereDate('leave_date', now()->format('Y-m-d'))
            ->where('status', 'approved')
            ->first();

        $checkTodayHoliday = Holiday::where('date', now()->format('Y-m-d'))
            ->where(function ($query) use ($user) {
                $query->orWhere('department_id_json', 'like', '%"' . $user->employeeDetail->department_id . '"%')
                    ->orWhereNull('department_id_json');
            })
            ->where(function ($query) use ($user) {
                $query->orWhere('designation_id_json', 'like', '%"' . $user->employeeDetail->designation_id . '"%')
                    ->orWhereNull('designation_id_json');
            })
            ->where(function ($query) use ($user) {
                if (!is_Null($user->employeeDetail->employment_type)) {
                    $query->orWhere('employment_type_json', 'like', '%"' . $user->employeeDetail->employment_type . '"%')
                        ->orWhereNull('employment_type_json');
                }
            })
            ->first();

        $company = company();

        $showClockIn = attendance_setting();
        $attendanceSettings = $this->attendanceShiftFromTrait($showClockIn);

        $startTime = \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->office_start_time, company()->timezone);
        $midTime = \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->halfday_mark_time, company()->timezone);
        $endTime = \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->office_end_time, company()->timezone);

        $currentTime = now()->timezone($company->timezone);

        if (!is_null($attendanceSettings->early_clock_in)) {
            $startTime->subMinutes($attendanceSettings->early_clock_in);
        }

        if ($startTime->gt($endTime)) { // check if shift end time is less then current time then shift not ended yet
            if (
                now(company()->timezone)->lessThan($endTime)
                || (now(company()->timezone)->greaterThan($endTime) && now(company()->timezone)->lessThan($startTime))
            ) {
                $startTime->subDay();
                $midTime->subDay();
            } else {
                $midTime->addDay();
                $endTime->addDay();
            }
        }

        $cannotLogin = false;
        $date = now()->format('Y-m-d');

        $attendance = Attendance::where('user_id', $this->user->id)
            ->whereDate('clock_in_time', $date)
            ->get();

        foreach ($attendance as $item) {
            if ($item->clock_out_time !== null && now()->between($item->clock_in_time, $item->clock_out_time)) { // if ($item->clock_out_time !== null && added this condition
                $cannotLogin = true;
                break;
            }
        }

        if ($showClockIn->employee_clock_in_out == 'no' || $attendanceSettings->shift_name == 'Day Off') {

            $cannotLogin = true;
        } elseif (is_null($attendanceSettings->early_clock_in) && !now($this->company->timezone)->between($startTime, $endTime) && $showClockIn->show_clock_in_button == 'no' && $attendanceSettings->shift_type == 'strict') {
            $cannotLogin = true;
        } elseif ($attendanceSettings->shift_type == 'strict') {
            $earlyClockIn = now($this->company->timezone)->addMinutes((int)$attendanceSettings->early_clock_in ?? 0)->setTimezone('UTC');

            if (!$earlyClockIn->gte($startTime) && $showClockIn->show_clock_in_button == 'no') {

                $cannotLogin = true;
            } elseif ($cannotLogin && now()->betweenIncluded($startTime->copy()->subDay(), $endTime->copy()->subDay())) {
                $cannotLogin = false;
            }
        }
        $isAttendanceApplied = true;

        if ($leaveApplied != null  && ($leaveApplied->duration === 'single' || $leaveApplied->duration === 'multiple') && $currentTime->between($startTime, $endTime)) {

            $message = __('messages.leaveApplied');
            $clockInTime = null;
            $clockOutTime = null;
            $totalWorkingTime = null;
            $leave = true;
            $isAttendanceApplied = false;
            return view('attendance-settings.ajax.qrview', compact('message', 'leave'));
        } elseif ($leaveApplied != null  && ($leaveApplied->duration === 'half day' && $leaveApplied->half_day_type === 'first_half' && $currentTime->lt($midTime) && $currentTime->between($startTime, $midTime))) {

            $message = __('messages.leaveForFirstHalf');
            $clockInTime = null;
            $clockOutTime = null;
            $totalWorkingTime = null;
            $leave = true;
            $isAttendanceApplied = false;
            return view('attendance-settings.ajax.qrview', compact('message', 'leave'));
        } elseif ($leaveApplied != null  && ($leaveApplied->duration === 'half day' && $leaveApplied->half_day_type === 'second_half' && $currentTime->gt($midTime) && $currentTime->between($midTime, $endTime))) {

            $message = __('messages.leaveForSecondHalf');
            $clockInTime = null;
            $clockOutTime = null;
            $totalWorkingTime = null;
            $leave = true;
            $isAttendanceApplied = false;
            return view('attendance-settings.ajax.qrview', compact('message', 'leave'));
        } elseif (!is_null($checkTodayHoliday) && $checkTodayHoliday->exists) {

            $message = __('messages.todayHoliday');
            $clockInTime = null;
            $clockOutTime = null;
            $totalWorkingTime = null;
            $holiday = true;
            $isAttendanceApplied = false;
            return view('attendance-settings.ajax.qrview', compact('message', 'holiday'));
        } elseif ($cannotLogin == true) {

            $message = __('messages.notInOfficeHours');
            $clockInTime = null;
            $clockOutTime = null;
            $totalWorkingTime = null;
            $outOfShiftHours = true;
            $isAttendanceApplied = false;
            return view('attendance-settings.ajax.qrview', compact('message', 'outOfShiftHours'));
        }

        $userCompany = Company::where('hash', $hash)->first();

        // Check if the user is already clocked in for today
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_time', now()->format('Y-m-d'))
            ->where('company_id', $userCompany->id)
            ->whereNull('clock_out_time')
            ->first();

        $outtimeDate = null;
        $intimeDate = null;

        $trackerReminder = LogTimeFor::where('company_id', $company->id)->first();

        $time = $currentTime->format('h:i A');

        if ($todayAttendance && !session('qr_clock_in')) {
            // User is already clocked in, so clock them out
            $this->clockOutUser($todayAttendance);
            $message = __('messages.attendanceClockOutSuccess');
            $clockInTime = $todayAttendance->clock_in_time;
            $intime = Carbon::parse($clockInTime)->timezone($company->timezone);
            $intimeDate = $intime->format('h:i A');

            $clockOutTime = $todayAttendance->clock_out_time;
            $outtime = Carbon::parse($clockOutTime)->timezone($company->timezone);
            $outtimeDate = $outtime->format('h:i A');

            $workingintime = Carbon::parse($clockInTime); // Assuming $clockInTime is already a Carbon instance
            $workingouttime = Carbon::parse($todayAttendance->clock_out_time); // Assuming $todayAttendance->clock_out_time is already a Carbon instance

            $totalWorkingTime = $workingintime->diff($workingouttime)->format('%h hours %i minutes');
            $notAuthorize = false;
            $qrClockIn = false;
        } else {
            $attendanceSetting = AttendanceSetting::first();
            // Check if attendance setting status is enabled
            if ($attendanceSetting->qr_enable == 1) {
                // User is not clocked in for today, so clock them in
                if ($isAttendanceApplied = true) {
                    $res = $this->clockInUser($user, $request);
                    session(['qr_clock_in' => true]);
                }
                if (isset($res['status']) && $res['status'] == 'success') {
                    // Clock in successful, set success message
                    $message = __('messages.attendanceClockInSuccess');
                    $clockInTime = $todayAttendance?->clock_in_time->format('h:i A');
                    $clockOutTime = null;
                    $totalWorkingTime = null;
                    $notAuthorize = false;
                    $qrClockIn = false;
                } elseif (isset($res['type']) && $res['type'] == 'error') {
                    // Clock in successful, set success message
                    $message = $res['message'] ?? __('messages.attendanceClockInFailed');
                    $clockInTime = null;
                    $clockOutTime = null;
                    $totalWorkingTime = null;
                    $notAuthorize = true;
                    $qrClockIn = true;
                    $time = null;
                } else {
                    // Clock in failed, set error message
                    $message = $res['message'] ?? __('messages.attendanceClockInFailed');
                    $clockInTime = null;
                    $clockOutTime = null;
                    $totalWorkingTime = null;
                    $time = null;
                    $notAuthorize = false;
                    $qrClockIn = false;
                }
            } else {
                // Attendance feature is not enabled, prevent user from clocking in
                return redirect()->route('dashboard')->with('error', __('Attendance feature is currently disabled.'));
            }
        }

        // Pass the message and other details to the view and return the view
        return view('attendance-settings.ajax.qrview', compact('message', 'intimeDate', 'outtimeDate', 'totalWorkingTime', 'todayAttendance', 'time', 'notAuthorize', 'qrClockIn'));
    }

    private function clockInUser($user, $request)
    {
        $now = now();
        $showClockIn = AttendanceSetting::first();

        // Retrieve attendance settings
        $this->attendanceSettings = $this->attendanceShiftqr($showClockIn);

        // Construct start and end timestamps
        $startTimestamp = now()->format('Y-m-d') . ' ' . $this->attendanceSettings->office_start_time;
        $endTimestamp = now()->format('Y-m-d') . ' ' . $this->attendanceSettings->office_end_time;
        $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $startTimestamp, $this->company->timezone);
        $officeEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $endTimestamp, $this->company->timezone);

        // Check if the user can clock in
        if ($showClockIn->show_clock_in_button == 'yes') {
            $officeEndTime = now();
        }
        $officeStartTime = $officeStartTime->setTimezone('UTC');
        $officeEndTime = $officeEndTime->setTimezone('UTC');
        if ($officeStartTime->gt($officeEndTime)) {
            $officeEndTime->addDay();
        }

        $this->cannotLogin = false;
        $clockInCount = Attendance::getTotalUserClockInWithTime($officeStartTime, $officeEndTime, $this->user->id);

        // Adjust timestamps based on office start and end times
        // $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $startTimestamp, $this->company->timezone)
        //     ->setTimezone('UTC');
        // $officeEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $endTimestamp, $this->company->timezone)
        //     ->setTimezone('UTC');
        //     $lateTime = $officeStartTime->addMinutes($this->attendanceSettings->late_mark_duration);
        //     $checkTodayAttendance = Attendance::where('user_id', $this->user->id)
        //     ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $now->format('Y-m-d'))->first();


        // Check if the user is allowed to clock in based on settings
        if ($showClockIn->employee_clock_in_out == 'yes') {

            // Check if it's too early to clock in
            if (is_null($this->attendanceSettings->early_clock_in) && !$now->between($officeStartTime, $officeEndTime) && $showClockIn->show_clock_in_button == 'no' && $this->attendanceSettings->shift_type == 'strict') {
                $this->cannotLogin = true;
            } elseif ($this->attendanceSettings->shift_type == 'strict') {
                $earlyClockIn = now(company()->timezone)->addMinutes((int)$this->attendanceSettings->early_clock_in ?? 0);
                $earlyClockIn = $earlyClockIn->setTimezone('UTC');

                if ($earlyClockIn->gte($officeStartTime) || $showClockIn->show_clock_in_button == 'yes') {
                    $this->cannotLogin = false;
                } else {
                    $this->cannotLogin = true;
                }
            }

            // Check if the user can clock in from previous day
            if ($this->cannotLogin && now()->betweenIncluded($officeStartTime->copy()->subDay(), $officeEndTime->copy()->subDay())) {
                $this->cannotLogin = false;
                $clockInCount = Attendance::getTotalUserClockInWithTime($officeStartTime->copy()->subDay(), $officeEndTime->copy()->subDay(), $this->user->id);
            }
        } else {
            $this->cannotLogin = true;
        }

        // Abort if user cannot login
        abort_403($this->cannotLogin);
        // Check user by IP
        $notAuthorize = false;
        if (attendance_setting()->ip_check == 'yes') {
            $ips = (array)json_decode(attendance_setting()->ip_address);
            if (!in_array($request->ip(), $ips)) {
                return ['type' => 'error', 'message' => __('messages.notAnAuthorisedDevice'), 'notAuthorize' => $notAuthorize, 'time' => null];
            } else {
                $notAuthorize = true;
            }
        }
        $qrClockIn = false;
        $currentLatitude = $currentLongitude = null;

        // Check user by location
        if (attendance_setting()->radius_check == 'yes') {
            $checkRadius = $this->isWithinRadius($request, $user);
            if (!$checkRadius) {
                return ['type' => 'error', 'message' => __('messages.notAnValidLocation'), 'qrClockIn' => $qrClockIn];
            } else {
                $qrClockIn = true;
                $currentLatitude = $request->currentLatitude ?: session('current_latitude');
                $currentLongitude = $request->currentLongitude ?: session('current_longitude');
            }
        }

        // Check maximum attendance in a day
        if ($this->attendanceSettings->shift_type == 'strict') {
            $clockInCount = Attendance::getTotalUserClockInWithTime($officeStartTime, $officeEndTime, $user->id);
        } else {
            $clockInCount = Attendance::whereDate('clock_in_time', $officeStartTime->copy()->toDateString())
                ->where('user_id', $user->id)
                ->count();
        }


        if ($clockInCount >= $this->attendanceSettings->clockin_in_day) {
            return ['type' => 'error', 'message' => __('messages.maxClockin')];

            // return Reply::error(__('messages.maxClockin'));

        }
        if ($this->attendanceSettings->halfday_mark_time) {
            $halfDayTimestamp = $now->format('Y-m-d') . ' ' . $this->attendanceSettings->halfday_mark_time;
            $halfDayTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $halfDayTimestamp, $this->company->timezone);
            $halfDayTimestamp = $halfDayTimestamp->setTimezone('UTC');
            $halfDayTimestamp = $halfDayTimestamp->timestamp;
        }


        $timestamp = $now->format('Y-m-d') . ' ' . $this->attendanceSettings->office_start_time;
        $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, $this->company->timezone);
        $officeStartTime = $officeStartTime->setTimezone('UTC');

        $lateTime = $officeStartTime->addMinutes((int)$this->attendanceSettings->late_mark_duration ?? 0);

        $checkTodayAttendance = Attendance::where('user_id', $this->user->id)
            ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $now->format('Y-m-d'))->first();

        if ($user->employeeDetail && $user->employeeDetail->company_address_id) {
            $companyAddress = CompanyAddress::findOrFail($user->employeeDetail->company_address_id);
        } else {
            $companyAddress = CompanyAddress::where('is_default', 1)->where('company_id', $user->company_id)->first();
        }

        // Save the attendance record
        $attendance = new Attendance();
        $attendance->user_id = $user->id;
        $attendance->clock_in_time = $now;
        $attendance->clock_in_ip = request()->ip();
        $attendance->location_id = $companyAddress->id;
        $attendance->work_from_type = 'office';
        $attendance->latitude = $currentLatitude;
        $attendance->longitude = $currentLongitude;



        $currentTimestamp = $now->setTimezone('UTC');
        $currentTimestamp = $currentTimestamp->timestamp;;



        $currentLatitude = $request->currentLatitude;
        $currentLongitude = $request->currentLongitude;

        if ($currentLatitude != '' && $currentLongitude != '') {
            $attendance->latitude = $currentLatitude;
            $attendance->longitude = $currentLongitude;
        }

        $attendance->employee_shift_id = $this->attendanceSettings->id;

        $attendance->shift_start_time = $attendance->clock_in_time->toDateString() . ' ' . $this->attendanceSettings->office_start_time;

        if (Carbon::parse($this->attendanceSettings->office_start_time)->gt(Carbon::parse($this->attendanceSettings->office_end_time))) {
            $attendance->shift_end_time = $attendance->clock_in_time->addDay()->toDateString() . ' ' . $this->attendanceSettings->office_end_time;
        } else {
            $attendance->shift_end_time = $attendance->clock_in_time->toDateString() . ' ' . $this->attendanceSettings->office_end_time;
        }

        $attendance->save();

        return Reply::successWithData(__('messages.attendanceClockInSuccess'), ['time' => $now->format('h:i A'), 'ip' => $attendance->clock_in_ip, 'working_from' => $attendance->working_from, 'notAuthorize' => $notAuthorize, 'qrClockIn' => $qrClockIn]);


        // return ['type' => 'success', 'message' => __('messages.attendanceSaveSuccess')];

        // return Reply::successWithData(__('messages.attendanceSaveSuccess'), [
        //     'time' => $now->format('h:i A'),
        //     'ip' => $attendance->clock_in_ip,
        //     'working_from' => $attendance->working_from
        // ]);
    }

    private function clockOutUser($attendance)
    {
        $user = user();
        if ($user->employeeDetail && $user->employeeDetail->company_address_id) {
            $companyAddress = CompanyAddress::findOrFail($user->employeeDetail->company_address_id);
        } else {
            $companyAddress = CompanyAddress::where('is_default', 1)->where('company_id', $user->company_id)->first();
        }
        // Update clock-out time
        $attendance->update(['clock_out_time' => now(), 'clock_out_time_work_from_type' => 'office', 'clock_out_time_location_id' => $companyAddress->id]);
    }

    public function attendanceShiftqr($defaultAttendanceSettings)
    {
        $checkPreviousDayShift = EmployeeShiftSchedule::with('shift')->where('user_id', user()->id)
            ->where('date', now(company()->timezone)->subDay()->toDateString())
            ->first();

        $checkTodayShift = EmployeeShiftSchedule::with('shift')->where('user_id', user()->id)
            ->where('date', now(company()->timezone)->toDateString())
            ->first();

        $backDayFromDefault = Carbon::parse(now(company()->timezone)->subDay()->format('Y-m-d') . ' ' . $defaultAttendanceSettings->office_start_time);

        $backDayToDefault = Carbon::parse(now(company()->timezone)->subDay()->format('Y-m-d') . ' ' . $defaultAttendanceSettings->office_end_time);

        if ($backDayFromDefault->gt($backDayToDefault)) {
            $backDayToDefault->addDay();
        }

        $nowTime = Carbon::createFromFormat('Y-m-d H:i:s', now(company()->timezone)->toDateTimeString(), 'UTC');

        if ($checkPreviousDayShift && $nowTime->betweenIncluded($checkPreviousDayShift->shift_start_time, $checkPreviousDayShift->shift_end_time)) {
            $attendanceSettings = $checkPreviousDayShift;
        } else if ($nowTime->betweenIncluded($backDayFromDefault, $backDayToDefault)) {
            $attendanceSettings = $defaultAttendanceSettings;
        } else if (
            $checkTodayShift &&
            ($nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time)
                || $nowTime->gt($checkTodayShift->shift_end_time)
                || (!$nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time) && $defaultAttendanceSettings->show_clock_in_button == 'no'))
        ) {
            $attendanceSettings = $checkTodayShift;
        } else if ($checkTodayShift && !is_null($checkTodayShift->shift->early_clock_in)) {
            $attendanceSettings = $checkTodayShift;
        } else {
            $attendanceSettings = $defaultAttendanceSettings;
        }

        return $attendanceSettings->shift;
    }

    public function byRequest(AttendanceRegularisationDataTable $dataTable, Request $request)
    {
        $attendance = Attendance::find($request->employee_id);
        $this->pageTitle = 'clan.attendance.attendanceRegularisation';

        if ($request->employee_id) {
            abort_403(!(
                $this->viewAttendancePermission == 'all'
                || ($this->viewAttendancePermission == 'added' && $attendance->added_by == user()->id)
                || ($this->viewAttendancePermission == 'owned' && $attendance->user_id == user()->id)
                || ($this->viewAttendancePermission == 'both' && ($attendance->added_by == user()->id || $attendance->user_id == user()->id))));
        }
        else {
            abort_403(!in_array($this->viewAttendancePermission, ['all', 'added', 'owned', 'both']));
        }

        $now = now();
        $this->year = $now->format('Y');
        $this->month = $now->format('m');
        $this->status = request()->status ?? null;

        if ($this->viewAttendancePermission == 'owned') {
            $this->employees = User::where('id', user()->id)->get();

        }
        else {
            $this->employees = User::allEmployees(null, true, ($this->viewAttendancePermission == 'all' ? 'all' : null));
        }

        $attendanceRegularisation = AttendanceSetting::first();
        $regulariseRoles = $attendanceRegularisation->attendance_regularize_roles;
        $regulariseRolesArray = json_decode($regulariseRoles, true) ?? [];
        $userRoles = user_roles();

        $lastRole = end($userRoles);
        $condition = in_array($lastRole, $regulariseRolesArray) || $lastRole === 'admin';

        if(!in_array('admin', user_roles()) && !$condition)
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('user_id', user()->id)->where('status', 'pending')->count();
        }
        else
        {
            $this->pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('status', 'pending')->count();
        }

        return $dataTable->render('attendances.by_request', $this->data);
    }

    public function regularisationStore(StoreAttendanceRegularisation $request)
    {
        $date = $request->attendance_date;
        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_in_time, $this->company->timezone);

        $attendanceSettings = EmployeeShiftSchedule::with('shift')->where('user_id', $request->user_id)->where('date', $clockIn)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;

        }
        else {
            $this->attendanceSettings = AttendanceSetting::first()->shift; // Do not get this from session here
        }

        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_out_time, $this->company->timezone);

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                $clockOut = $clockOut->addDay();
            }
        }
        else {
            $clockOut = null;
        }

        $employeeShiftId = $this->attendanceSettings->id;

        $requestRegulisation = new AttendanceRegularisation();
        $requestRegulisation->user_id = $request->user_id;
        $requestRegulisation->date = $date;
        $requestRegulisation->clock_in_time = $clockIn->copy()->timezone(config('app.timezone'));
        $requestRegulisation->clock_out_time = $clockOut?->copy()->timezone(config('app.timezone'));
        $requestRegulisation->working_from = $request->work_from_type;
        $requestRegulisation->other_location = $request->other_location;
        $requestRegulisation->reason = $request->reason;
        $requestRegulisation->employee_shift_id = $employeeShiftId;
        $requestRegulisation->save();

        $attendanceSetting = AttendanceSetting::where('company_id', company()->id)->first();
        $attendanceRoles = $attendanceSetting->attendance_regularize_roles;

        $regulariseRolesArray = json_decode($attendanceRoles, true) ?? [];

        $rolesId = Role::with(['roleuser.user'])->whereIn('name', $regulariseRolesArray)->pluck('id')->toArray();

        $users = User::whereHas('role', function($q) use ($rolesId){
            return $q->whereIn('role_id', $rolesId);
        })->get();

        $admins = User::allAdmins(company()->id);
        $allUsers = $users->merge($admins);

        if(!in_array('admin', user_roles()))
        {
            $pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('user_id', user()->id)->where('status', 'pending')->count();
        }
        else
        {
            $pendingRequestRegularisation = AttendanceRegularisation::where('company_id', company()->id)->where('status', 'pending')->count();
        }

        event(new RequestRegulariseEvent($requestRegulisation, $allUsers));

        return Reply::successWithData(__('clan.attendance.requestRegulariseSuccessfully'), ['pendingRequest' => $pendingRequestRegularisation]);

    }

    public function acceptStatus(Request $request)
    {
        $attendanceRegularisation = AttendanceRegularisation::findOrFail($request->acceptId);
        $attendanceRegularisation->status = 'accept';
        $attendanceRegularisation->update();

        $presentAttendance = Attendance::where('user_id', $attendanceRegularisation->user_id)
                                      ->whereDate('clock_in_time', $attendanceRegularisation->date)
                                      ->first();

        if (is_null($presentAttendance)) {
            $presentAttendance = new Attendance();
            $presentAttendance->user_id = $attendanceRegularisation->user_id;
        }

        $presentAttendance->clock_in_time = $attendanceRegularisation->clock_in_time;
        $presentAttendance->clock_out_time = $attendanceRegularisation->clock_out_time;
        $presentAttendance->working_from = $attendanceRegularisation->working_from;
        $presentAttendance->work_from_type = $attendanceRegularisation->working_from;
        $presentAttendance->employee_shift_id = $attendanceRegularisation->employee_shift_id;
        $presentAttendance->save();

        event(new RequestRegularisationEvent($attendanceRegularisation));

        return Reply::success('messages.updateSuccess');
    }

    public function rejectAttendance()
    {
        return view('attendances.ajax.reject_reason');
    }

    public function rejectStatus(RejectReason $request)
    {
        $attendanceRegularisation = AttendanceRegularisation::findOrFail($request->id);
        $attendanceRegularisation->status = 'reject';
        $attendanceRegularisation->reject_reason = $request->reason;
        $attendanceRegularisation->update();

        event(new RequestRegularisationEvent($attendanceRegularisation));

        return Reply::success('messages.updateSuccess');
    }

    public function editAttendanceRegularisation($id)
    {
        $this->attendanceRegularisation = AttendanceRegularisation::findOrFail($id);

        $userID = $this->attendanceRegularisation->user_id;
        $this->row = $this->attendanceRegularisation;
        $this->attendanceUser = User::findOrFail($userID);

        $this->date =  $this->attendanceRegularisation->date;

        $this->clock_in_time = Carbon::parse($this->attendanceRegularisation->clock_in_time);

        $this->clock_out_time = $this->attendanceRegularisation->clock_out_time;

        $this->working_from = $this->attendanceRegularisation->working_from;

        $this->status = $this->attendanceRegularisation->status;

        $attendanceSettings = EmployeeShiftSchedule::where('user_id', $userID)->where('date', $this->date)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;

        }
        else {
            $this->attendanceSettings = attendance_setting()->shift; // Do not get this from session here

        }

        return view('attendances.ajax.edit_attendance_regularisation', $this->data);
    }

    public function viewAttendanceRegularisation($id)
    {
        $this->attendanceRegularisation = AttendanceRegularisation::findOrFail($id);

        $userID = $this->attendanceRegularisation->user_id;
        $this->row = $this->attendanceRegularisation;
        $this->attendanceUser = User::findOrFail($userID);

        $this->date =  $this->attendanceRegularisation->date;

        $this->clock_in_time = Carbon::parse($this->attendanceRegularisation->clock_in_time);

        $this->clock_out_time = $this->attendanceRegularisation->clock_out_time;

        $this->working_from = $this->attendanceRegularisation->working_from;

        $this->status = $this->attendanceRegularisation->status;

        $attendanceSettings = EmployeeShiftSchedule::where('user_id', $userID)->where('date', $this->date)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;

        }
        else {
            $this->attendanceSettings = attendance_setting()->shift; // Do not get this from session here

        }

        return view('attendances.ajax.view_attendance_regularisation', $this->data);
    }

    public function updateAttendanceRegularisation(UpdateAttendanceRegularisation $request, $id)
    {
        $date = $request->attendance_date;
        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_in_time, $this->company->timezone);

        $attendanceSettings = EmployeeShiftSchedule::with('shift')->where('user_id', $request->user_id)->where('date', $clockIn)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;

        }
        else {
            $this->attendanceSettings = AttendanceSetting::first()->shift; // Do not get this from session here
        }

        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->company->time_format, $date . ' ' . $request->clock_out_time, $this->company->timezone);

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                $clockOut = $clockOut->addDay();
            }
        }
        else {
            $clockOut = null;
        }

        $employeeShiftId = $this->attendanceSettings->id;

        $attendanceRegularisation = AttendanceRegularisation::findOrFail($id);
        $attendanceRegularisation->date = $date;
        $attendanceRegularisation->clock_in_time = $clockIn->copy()->timezone(config('app.timezone'));
        $attendanceRegularisation->clock_out_time = $clockOut?->copy()->timezone(config('app.timezone'));
        $attendanceRegularisation->working_from = $request->working_from;
        $attendanceRegularisation->other_location = $request->other_location;
        $attendanceRegularisation->reason = $request->reason;
        $attendanceRegularisation->employee_shift_id = $employeeShiftId;
        $attendanceRegularisation->update();

        return Reply::success(__('messages.attendanceSaveSuccess'));

    }

    public function deleteRegularisationRequest($id)
    {
        AttendanceRegularisation::destroy($id);

        return Reply::success(__('messages.deleteSuccess'));
    }

    public function requestRegulisation($userID, $day, $month, $year)
    {
        $this->date = Carbon::createFromFormat('d-m-Y', $day . '-' . $month . '-' . $year)->format('Y-m-d');

        $this->attendance = Attendance::whereUserId($userID)->first();

        $this->row = Attendance::where('user_id', $userID)
                                      ->whereDate('clock_in_time', $this->date)
                                      ->first();

        $attendanceSettings = EmployeeShiftSchedule::where('user_id', $userID)->where('date', $this->date)->first();

        if ($attendanceSettings && $attendanceSettings->shift) {
            $this->attendanceSettings = $attendanceSettings->shift;

        }
        else {
            $this->attendanceSettings = attendance_setting()->shift; // Do not get this from session here
        }

        $this->location = CompanyAddress::all();
        $this->attendanceUser = User::findOrFail($userID);

        return view('attendances.ajax.request_regularise', $this->data);
    }

    public function pendingRequest($userID, $day, $month, $year)
    {
        $this->date = Carbon::createFromFormat('d-m-Y', $day . '-' . $month . '-' . $year)->format('Y-m-d');

        $this->attendance = Attendance::whereUserId($userID)->first();

        $this->row = Attendance::where('user_id', $userID)
                                      ->whereDate('clock_in_time', $this->date)
                                      ->first();

        $this->rows = AttendanceRegularisation::where('user_id', $userID)
                                        ->whereDate('clock_in_time', $this->date)
                                        ->first();

        $this->attendanceSetting = AttendanceSetting::first();

        $attendanceSettings = EmployeeShiftSchedule::where('user_id', $userID)->where('date', $this->date)->first();

        if ($attendanceSettings) {
            $this->attendanceSettings = $attendanceSettings->shift;

        }
        else {
            $this->attendanceSettings = attendance_setting()->shift; // Do not get this from session here
        }

        $this->location = CompanyAddress::all();
        $this->attendanceUser = User::findOrFail($userID);

        return view('attendances.ajax.request_pending', $this->data);
    }
}
