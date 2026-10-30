<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\AttendanceDays;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One employee's attendance for one salary period (the 21st to the 20th, as
 * payroll pays it), day by day.
 *
 * Built from the payroll day count itself (AttendanceDays), so every line says
 * what payroll took the day as and what share of it was paid, and the totals at
 * the foot are the Working Days, Days Present and LOP on the payroll row and the
 * payslip.
 */
class PayrollAttendanceController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Salary Period Attendance';

        $this->middleware(function ($request, $next) {
            // Payroll people, and whoever may see everybody's attendance.
            abort_403(!\App\Services\Access\PayrollAccess::any()
                && user()->permission('view_attendance') !== 'all'
                && !in_array('admin', user_roles()));

            return $next($request);
        });
    }

    public function show(Request $request, int $userId)
    {
        $month = (int) ($request->month ?: now()->month);
        $year = (int) ($request->year ?: now()->year);
        $tz = company()->timezone ?: 'Asia/Kolkata';

        $this->employee = DB::table('users as u')->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.id', $userId)
            ->first(['u.id', 'u.name', 'ed.employee_id', 'ed.joining_date', 'ed.last_date', 'd.name as designation', 't.team_name as department']);

        abort_unless($this->employee, 404);

        $this->cycle = PayPeriod::forMonth($month, $year);
        $this->month = $month;
        $this->year = $year;

        $count = (new AttendanceDays($tz))->forRange($userId, $this->cycle->start, $this->cycle->end);
        $this->count = $count;
        $this->daysInCycle = (int) $this->cycle->start->copy()->startOfDay()->diffInDays($this->cycle->end->copy()->startOfDay()) + 1;
        $this->lopDays = max(0, $count['working_days'] - $count['days_present']);

        // The payslip for this period, if one has been issued.
        $this->payslip = DB::table('payslips')->where('user_id', $userId)->where('status', 'active')->whereNull('deleted_at')
            ->whereDate('period_start', $this->cycle->start->toDateString())
            ->first(['id', 'payslip_number', 'working_days', 'days_present', 'lop_days', 'approval_status', 'generated_at']);

        // Neighbouring periods, to step through.
        $this->previousPeriod = $this->cycle->previous();
        $after = Carbon::create($year, $month, 1)->addMonthNoOverflow();
        $this->nextPeriod = PayPeriod::forMonth((int) $after->month, (int) $after->year);

        $this->statusMeta = self::STATUSES;

        $view = 'payroll-attendance.show';

        if ($request->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view($view, $this->data)->render(),
                'title' => $this->employee->name . ' — ' . $this->cycle->describe()]);
        }

        $this->view = $view;

        return view('payroll-attendance.page', $this->data);
    }

    /** status => [label, colour, icon] */
    public const STATUSES = [
        'present' => ['Present', 'success', 'fa-check'],
        'half_day' => ['Half day', 'warning', 'fa-star-half-alt'],
        'half_day_leave' => ['Half day + leave', 'warning', 'fa-star-half-alt'],
        'week_off' => ['Week off (paid)', 'secondary', 'fa-calendar'],
        'holiday' => ['Holiday (paid)', 'info', 'fa-star'],
        'paid_leave' => ['Paid leave', 'primary', 'fa-plane-departure'],
        'half_leave' => ['Half-day leave', 'primary', 'fa-plane-departure'],
        'lop_leave' => ['Unpaid leave (LOP)', 'danger', 'fa-plane-slash'],
        'absent' => ['Absent (LOP)', 'danger', 'fa-times'],
        'not_employed' => ['Not employed', 'light', 'fa-minus'],
        'upcoming' => ['Not yet', 'light', 'fa-hourglass-half'],
    ];

}
