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

        // The dates asked for (?from=2026-10-21&to=2026-11-12), else this salary
        // period. Never past today: days not yet lived have no attendance.
        $cycle = PayPeriod::containing($today);
        $from = $this->parseDay($request->from, $tz) ?? $cycle->start->copy()->setTimezone($tz)->startOfDay();
        $to = $this->parseDay($request->to, $tz) ?? $cycle->end->copy()->setTimezone($tz)->startOfDay();

        if ($to->gt($today)) {
            $to = $today->copy();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $this->from = $from;
        $this->to = $to;
        $this->defaultRange = $from->isSameDay($cycle->start) && ($to->isSameDay($cycle->end) || $to->isSameDay($today));
        $this->isCurrent = $today->betweenIncluded($from, $to);
        $this->today = $today;

        $counter = new AttendanceDays($tz);
        $rows = [];

        foreach (User::with('employeeDetail.designation')->whereIn('id', $team)->orderBy('name')->get() as $member) {
            try {
                $count = $counter->forRange((int) $member->id, $from->copy(), $to->copy()->endOfDay());
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

        $this->pendingLeaveByUser = DB::table('leaves')->whereIn('user_id', $team)->where('status', 'pending')
            ->selectRaw('user_id, count(distinct ifnull(unique_id, id)) n')->groupBy('user_id')->pluck('n', 'user_id');

        return view('my-team.attendance', $this->data);
    }

    /** A Y-m-d date from the filter, or null when absent or not a date. */
    private function parseDay(?string $value, string $tz): ?Carbon
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, $tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** [month, year] of the payroll month after this period's. */
    private function monthAfter(PayPeriod $cycle): array
    {
        $next = Carbon::create($cycle->year, $cycle->month, 1)->addMonthNoOverflow();

        return [(int) $next->month, (int) $next->year];
    }

}
