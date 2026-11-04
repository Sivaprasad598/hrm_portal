<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Attendance\CorrectionApprovals;
use App\Services\Payroll\AttendanceDays;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A reporting manager's team: the people whose Reporting To is them.
 *
 * Team Attendance shows each one's salary period as payroll counts it (paid
 * days, LOP, late), how today stands, and their leave - read only; a name
 * opens that person's month (Attendance by Member, which lets a manager pick
 * their team). Nobody else's attendance is reachable from here.
 */
class MyTeamController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Team Attendance';
    }

    public function attendance(Request $request)
    {
        $team = CorrectionApprovals::teamUserIds();
        abort_403(!$team);

        $tz = company()->timezone ?: 'Asia/Kolkata';
        $today = now($tz)->startOfDay();

        // This salary period, or the one asked for (?period=2026-10 is "October")
        $this->cycle = preg_match('/^(\d{4})-(\d{2})$/', (string) $request->period, $m)
            ? PayPeriod::forMonth((int) $m[2], (int) $m[1])
            : PayPeriod::containing($today);
        $this->previousPeriod = $this->cycle->previous();
        $this->nextPeriod = PayPeriod::forMonth(...$this->monthAfter($this->cycle));
        $this->isCurrent = $today->betweenIncluded($this->cycle->start, $this->cycle->end);
        $this->today = $today;

        $counter = new AttendanceDays($tz);
        $rows = [];

        foreach (User::with('employeeDetail.designation')->whereIn('id', $team)->orderBy('name')->get() as $member) {
            try {
                $count = $counter->forRange((int) $member->id, $this->cycle->start, $this->cycle->end);
            } catch (\Throwable $e) {
                report($e);
                $count = null;
            }

            $days = collect($count['days'] ?? [])->keyBy('date');
            $b = $count['breakdown'] ?? [];

            $rows[] = [
                'user' => $member,
                'today' => $this->isCurrent ? $days->get($today->toDateString()) : null,
                'paid' => $count ? (float) $count['days_present'] : null,
                'counted' => $count ? (int) $count['working_days'] : null,
                'lop' => $count ? round(max(0, $count['working_days'] - $count['days_present']), 2) : null,
                'late' => (int) ($b['late_marks'] ?? 0) + (int) ($b['late_half_days'] ?? 0),
                'leave' => $days->whereIn('status', ['paid_leave', 'half_leave', 'lop_leave', 'half_day_leave'])->count(),
            ];
        }

        $this->rows = $rows;

        // What is waiting for them, beside the table
        $this->leavesWaiting = \App\Services\Leaves\LeaveApprovals::waitingFor()->unique(fn ($l) => $l->unique_id ?: 'id' . $l->id)->count();
        $this->correctionsWaiting = CorrectionApprovals::waitingFor()->count();
        $this->pendingLeaveByUser = DB::table('leaves')->whereIn('user_id', $team)->where('status', 'pending')
            ->selectRaw('user_id, count(distinct ifnull(unique_id, id)) n')->groupBy('user_id')->pluck('n', 'user_id');

        return view('my-team.attendance', $this->data);
    }

    /** [month, year] of the payroll month after this period's. */
    private function monthAfter(PayPeriod $cycle): array
    {
        $next = Carbon::create($cycle->year, $cycle->month, 1)->addMonthNoOverflow();

        return [(int) $next->month, (int) $next->year];
    }

}
