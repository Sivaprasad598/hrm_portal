<?php

namespace App\Http\Controllers;

use App\Services\Attendance\ShiftResolver;
use App\Services\Attendance\WorkRules;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance -> Dashboard -> "Attendance Rules": how a day is counted, in plain
 * words, built from the settings in force (Attendance Settings: shifts, working
 * hours, lates & half day, overtime; Leave Settings; payroll's pay cycle). Every
 * figure comes from WorkRules, the same code that counts attendance for payroll,
 * so the page cannot drift from what actually happens.
 */
class AttendanceRulesController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Attendance Rules';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array(user()->permission('view_attendance'), ['all', 'added', 'owned', 'both'], true));

            return $next($request);
        });
    }

    public function index()
    {
        $companyId = company()->id;
        $tz = company()->timezone ?: config('app.timezone');
        $s = DB::table('attendance_settings')->where('company_id', $companyId)->first() ?? DB::table('attendance_settings')->first();
        $rules = WorkRules::forCompany($companyId, $tz);
        $hm = fn ($seconds) => sprintf('%dh %02dm', intdiv(max(0, $seconds), 3600), intdiv(max(0, $seconds) % 3600, 60));
        $hours = fn ($h) => rtrim(rtrim(number_format((float) $h, 2), '0'), '.');
        $time = fn ($t) => $t ? Carbon::parse($t)->format('h:i A') : '—';
        $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun', 7 => 'Sun'];
        $today = now($tz)->toDateString();

        // Every shift, with what a full day, a half day and a late mean on it.
        $shifts = DB::table('employee_shifts')->when(Schema::hasColumn('employee_shifts', 'company_id'), fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id')->get()
            ->reject(fn ($shift) => ShiftResolver::isDayOff($shift))
            ->map(function ($shift) use ($rules, $hm, $time, $dayNames, $today, $s) {
                $dated = clone $shift;
                $dated->day_start = $today . ' ' . ($shift->office_start_time ?: '00:00:00');
                $dated->day_end = (ShiftResolver::isOvernight($shift) ? Carbon::parse($today)->addDay()->toDateString() : $today)
                    . ' ' . ($shift->office_end_time ?: '23:59:59');
                $flexible = WorkRules::isFlexible($shift);
                $days = collect(json_decode((string) $shift->office_open_days, true) ?: [])->map(fn ($d) => $dayNames[(int) $d] ?? $d)->implode(', ');
                $half = $rules ? ($flexible ? $rules->halfSeconds($dated) : min($rules->halfSeconds($dated), $rules->shorterHalfSeconds($dated))) : 0;

                return (object) [
                    'name' => $shift->shift_name,
                    'default' => (int) $shift->id === (int) ($s->default_employee_shift ?? 0),
                    'flexible' => $flexible,
                    'times' => $flexible ? 'Flexible hours' : $time($shift->office_start_time) . ' – ' . $time($shift->office_end_time),
                    'days' => $days ?: '—',
                    'breaks' => $rules ? $hm($rules->breakSeconds($dated)) : '—',
                    'full' => $rules ? $hm($rules->workSeconds($dated)) : '—',
                    'half' => $rules ? $hm($half) : '—',
                    'late_at' => !$flexible && $shift->late_mark_duration !== null && $shift->office_start_time
                        ? Carbon::parse($shift->office_start_time)->addMinutes((int) $shift->late_mark_duration)->format('h:i A') : null,
                    'late_minutes' => $shift->late_mark_duration,
                    'half_mark' => !$flexible && $shift->halfday_mark_time && $shift->halfday_mark_time !== '00:00:00' ? $time($shift->halfday_mark_time) : null,
                    'end' => $time($shift->office_end_time),
                ];
            })->values();

        $this->s = $s;
        $this->rulesOn = (bool) $rules;
        $this->shifts = $shifts;
        $this->main = $shifts->firstWhere('default', true) ?? $shifts->first();
        $this->breaks = Schema::hasTable('attendance_breaks')
            ? DB::table('attendance_breaks')->where('company_id', $companyId)->where('is_default', 1)->orderBy('sort_order')->get()
                ->map(fn ($b) => $b->name . ' ' . $time($b->start_time) . ' – ' . $time($b->end_time))
            : collect();
        $this->grace = (int) ($s->wh_grace_minutes ?? 0);
        $this->countAnywhere = ($s->wh_count_mode ?? 'anywhere') !== 'shift';
        $this->effectiveFrom = !empty($s->wh_effective_from) ? Carbon::parse($s->wh_effective_from)->format('d M Y') : null;
        $this->lateEvery = max(1, (int) ($s->half_day_late_threshold ?? 3));
        $this->lateCost = (float) ($s->half_day_deduction_type ?? 0.5) ?: 0.5;
        $this->hours = $hours;

        // Worked examples on the default shift, decided by the same rules payroll uses.
        $examples = [];
        $defaultShift = DB::table('employee_shifts')->where('id', $s->default_employee_shift ?? 0)->first();

        if ($rules && $defaultShift && !WorkRules::isFlexible($defaultShift) && $defaultShift->office_start_time && $defaultShift->office_end_time) {
            $d = clone $defaultShift;
            $d->day_start = $today . ' ' . $defaultShift->office_start_time;
            $d->day_end = $today . ' ' . $defaultShift->office_end_time;
            $at = fn ($base, $minutes) => Carbon::parse($today . ' ' . $base, $tz)->addMinutes($minutes);
            $start = $defaultShift->office_start_time;
            $end = $defaultShift->office_end_time;
            $late = (int) ($defaultShift->late_mark_duration ?? 0);
            $mark = $defaultShift->halfday_mark_time && $defaultShift->halfday_mark_time !== '00:00:00' ? $defaultShift->halfday_mark_time : null;

            $cases = [
                ['On time, full shift', $at($start, 0), $at($end, 0), 'weekday'],
                ['A little late, stays to the end', $at($start, max(1, $late - 5)), $at($end, 0), 'weekday'],
                ['Late, stays to the end', $at($start, $late + 15), $at($end, 0), 'weekday'],
                ['Leaves early, at the half-day mark', $at($start, 0), $mark ? $at($mark, 0) : $at($start, 270), 'weekday'],
                ['Comes in after the half-day mark', $mark ? $at($mark, 30) : $at($start, 300), $at($end, 0), 'weekday'],
                ['Leaves 2 hours after the shift', $at($start, 0), $at($end, 120), 'weekday'],
                ['In the office 5 hours on a week off (lunch taken off)', $at($start, 0), $at($start, 300), 'weekend'],
                ['Very short day', $at($start, 0), $at($start, 120), 'weekday'],
            ];

            foreach ($cases as [$label, $in, $out, $type]) {
                [$f, $l] = [$in->getTimestamp(), $out->getTimestamp()];
                $worked = $rules->worked($f, $l, $d, $today);
                $isLate = $type === 'weekday' && $rules->isLate($f, $d, $today);
                $afterMark = $type === 'weekday' && $rules->afterHalfDayMark($f, $d, $today);
                $credit = $type === 'weekday' ? $rules->dayCredit($f, $l, $worked, $d, $today) : null;
                if ($afterMark && $credit > 0.5) {
                    $credit = 0.5;
                }
                $ot = (int) $rules->overtime($f, $l, $d, $type);
                $bank = $ot / 3600 * $rules->rate($type);

                $result = $type === 'weekday'
                    ? ($credit >= 1 ? 'Full day' : ($credit >= 0.5 ? 'Half day (½ day LOP)' : 'Absent (1 day LOP)'))
                    : 'Week off (already paid)';

                $examples[] = (object) [
                    'label' => $label, 'in' => $in->format('h:i A'), 'out' => $out->format('h:i A'),
                    'worked' => $hm($worked), 'result' => $result, 'credit' => $credit,
                    'late' => $isLate ? ($afterMark ? 'Late, after the half-day mark' : 'Late mark') : '—',
                    'ot' => $ot > 0 ? $hm($ot) . ($rules->rate($type) != 1 ? ' × ' . $hours($rules->rate($type)) . ' = ' . $hours(round($bank, 2)) . 'h banked' : ' banked') : '—',
                ];
            }
        }

        $this->examples = $examples;

        // Overtime
        $this->ot = (object) [
            'on' => (bool) ($s->ot_enabled ?? false),
            'min' => (int) ($s->ot_min_minutes ?? 0),
            'capWeekday' => $hours($s->ot_cap_weekday_hours ?? 0),
            'capOff' => $hours($s->ot_cap_off_hours ?? 0),
            'rateWeekday' => $hours($s->ot_rate_weekday ?? 1),
            'rateWeekend' => $hours($s->ot_rate_weekend ?? 1.5),
            'rateHoliday' => $hours($s->ot_rate_holiday ?? 2),
            'bank' => ($s->ot_mode ?? 'bank') === 'bank',
            'cover' => (bool) ($s->ot_compensate ?? false),
            'coverAuto' => ($s->ot_compensate_mode ?? 'manual') === 'auto',
            'encash' => (bool) ($s->ot_encash ?? false),
            'cap' => $s->ot_bank_cap ?? null,
            'basis' => ($s->ot_basis ?? 'gross') === 'basic' ? 'basic salary' : 'gross salary',
        ];

        // Leave
        $leave = DB::table('leave_settings')->where('company_id', $companyId)->first() ?? DB::table('leave_settings')->first();
        $steps = json_decode((string) ($leave->approval_levels ?? ''), true) ?: ['hr'];
        $stepNames = ['manager' => 'the reporting manager', 'department_head' => 'the division head', 'hr' => 'HR'];
        $this->leaveSteps = collect($steps)->map(fn ($step) => $stepNames[$step] ?? $step)->values()->all();
        $this->escalateDays = (int) ($leave->escalate_after_days ?? 0);
        $this->sandwich = (bool) ($leave->sandwich_rule ?? false);
        $this->leaveTypes = DB::table('leave_types')->whereNull('deleted_at')->orderBy('id')->get(['type_name', 'paid'])
            ->map(fn ($t) => (object) ['name' => $t->type_name, 'paid' => (bool) $t->paid]);

        // Payroll
        $startDay = PayPeriod::configuredStartDay();
        $cycle = PayPeriod::containing(now($tz));
        $this->cycleText = $startDay <= 1 ? 'the calendar month (1st to the last day)'
            : 'the ' . $startDay . date('S', mktime(0, 0, 0, 1, $startDay)) . ' of one month to the ' . ($startDay - 1) . date('S', mktime(0, 0, 0, 1, $startDay - 1)) . ' of the next';
        $this->cycleNow = $cycle->describe() . ' (' . Carbon::create($cycle->year, $cycle->month, 1)->format('F Y') . ' payroll)';
        $this->useBiometric = \App\Services\Payroll\AttendanceGate::useBiometric($companyId);

        // Who the hours rules do not apply to
        $this->hoursOffDivisions = Schema::hasColumn('teams', 'consider_attendance_hours')
            ? DB::table('teams')->where('consider_attendance_hours', 0)->orderBy('team_name')->pluck('team_name')->all() : [];
        $this->exemptCount = DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
            ->where('u.status', 'active')->where('ed.attendance_exempt', 1)->count();

        return view('attendance-rules.index', $this->data);
    }

}
