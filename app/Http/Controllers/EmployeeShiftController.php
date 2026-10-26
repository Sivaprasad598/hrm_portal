<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Requests\EmployeeShift\StoreEmployeeShift;
use App\Models\AttendanceSetting;
use App\Models\Company;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeShiftController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        // Both were 'ticket types', copied from another settings controller —
        // the page announced itself as Ticket Types and highlighted that entry
        // in the settings menu.
        $this->pageTitle = 'Shift Settings';
        $this->activeSettingMenu = 'shift_settings';

        // This screen had no permission check of any kind, so any signed-in
        // employee could create, edit or delete a shift — and the shift decides
        // what counts as a full day, a half day and a late mark, which is what
        // attendance and then payroll are worked out from.
        //
        // manage_employee_shifts is the permission built for it: the Shifts tab
        // already reads it to decide whether to draw the Add, Edit and Delete
        // buttons, and only the controller behind them was ungated.
        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_employee_shifts') !== 'all');

            return $next($request);
        });
    }

    public function create()
    {
        return view('employee-shifts.create', $this->data);
    }

    public function store(StoreEmployeeShift $request)
    {
        $setting = new EmployeeShift();
        $this->fill($setting, $request);
        $setting->save();
        session()->forget('attendance_setting');
        \App\Services\Attendance\ShiftResolver::reset();

        return Reply::success(__('messages.employeeShiftAdded'));
    }

    /**
     * The shift form, both ways: a fixed-timing shift (start, end, late after,
     * half day after) or a flexible one (any time in the day; a full day after
     * so many hours - WorkRules). What the simple form leaves out keeps its
     * value, or takes a sensible default from the shift's own hours.
     */
    private function fill(EmployeeShift $s, $request): void
    {
        $format = $this->company->time_format;
        $time = fn ($v, $fallback) => $v ? Carbon::createFromFormat($format, $v) : Carbon::parse($fallback);

        $s->shift_name = $request->shift_name;
        $s->shift_short_code = $request->shift_short_code;
        $s->color = $request->color;
        $s->shift_type = $request->shift_type === 'flexible' ? 'flexible' : 'strict';
        $s->office_open_days = json_encode($request->office_open_days);
        $s->clockin_in_day = $request->filled('clockin_in_day') ? (int) $request->clockin_in_day : ($s->clockin_in_day ?: 20);
        $s->early_clock_in = $request->filled('early_clock_in') ? $request->early_clock_in : $s->early_clock_in;

        if ($s->shift_type === 'flexible') {
            // Any time in the day counts; no late mark, no half-day mark.
            $hours = max(1, min(23, (float) ($request->total_shift_hours ?: 9)));
            $half = $request->filled('halfday_shift_hours') ? max(0.5, min($hours, (float) $request->halfday_shift_hours)) : $hours / 2;

            $s->office_start_time = Carbon::parse('00:00:00');
            $s->office_end_time = Carbon::parse('23:59:00');
            $s->halfday_mark_time = Carbon::parse('00:00:00');
            $s->late_mark_duration = 0;
            $s->flexible_total_hours = $hours;
            $s->flexible_half_day_hours = $half;
            $s->flexible_auto_clockout = $request->filled('auto_clockout') ? (int) $request->auto_clockout : ($s->flexible_auto_clockout ?? 1);
            $s->auto_clock_out_time = $s->auto_clock_out_time ?? 1;
            $s->min_working_hours_full_day = $hours;
            $s->min_working_hours_half_day = $half;
        }
        else {
            $start = $time($request->office_start_time, '09:30:00');
            $end = $time($request->office_end_time, '18:30:00');
            // Hours from start to end (past midnight too), to the minute.
            $length = round(fmod(($end->getTimestamp() - $start->getTimestamp()) / 3600 + 24, 24), 2) ?: 9.0;

            $s->office_start_time = $start;
            $s->office_end_time = $end;
            // Half day after: given, or halfway through the shift.
            $s->halfday_mark_time = $request->filled('halfday_mark_time')
                ? $time($request->halfday_mark_time, '14:00:00') : $start->copy()->addMinutes((int) round($length * 30));
            $s->late_mark_duration = (int) ($request->late_mark_duration ?? 0);
            $s->auto_clock_out_time = $request->filled('auto_clock_out_time') ? $request->auto_clock_out_time : ($s->auto_clock_out_time ?? 1);
            $s->min_working_hours_full_day = $request->filled('min_working_hours_full_day') ? $request->min_working_hours_full_day : ($s->min_working_hours_full_day ?: $length);
            $s->min_working_hours_half_day = $request->filled('min_working_hours_half_day') ? $request->min_working_hours_half_day : ($s->min_working_hours_half_day ?: $length / 2);
        }

        $s->min_working_hours_full_day_grace = $s->min_working_hours_full_day_grace ?? 0;
        $s->min_working_hours_half_day_grace = $s->min_working_hours_half_day_grace ?? 0;
    }

    public function edit($id)
    {
        $this->employeeShift = EmployeeShift::findOrFail($id);
        $this->openDays = json_decode($this->employeeShift->office_open_days);
        return view('employee-shifts.edit', $this->data);
    }

    public function destroy($id)
    {
        // A shift employees have (or had) as their own cannot go: their days are
        // judged by it. Move them to another shift on Edit Employee first.
        $assigned = \Illuminate\Support\Facades\DB::table('employee_shift_assignments')
            ->where('employee_shift_id', $id)->distinct()->count('user_id');

        if ($assigned > 0) {
            return Reply::error($assigned . ' employee(s) have this shift on record. Give them another shift on Edit Employee before deleting it.');
        }

        EmployeeShift::destroy($id);
        return Reply::success(__('messages.deleteSuccess'));
    }

    public function setDefaultShift()
    {
        $this->company->attendanceSetting->update([
            'default_employee_shift' => request()->shiftID
        ]);


        session()->forget('attendance_setting');
        return Reply::success(__('messages.updateSuccess'));
    }

    public function update(StoreEmployeeShift $request, $id)
    {
        $setting = EmployeeShift::findOrFail($id);
        // Fixed or flexible can change on edit too (it was never saved here).
        $this->fill($setting, $request);
        $setting->save();
        session()->forget('attendance_setting');
        \App\Services\Attendance\ShiftResolver::reset();

        // Roster days written from employees' assignments carry the shift's hours;
        // from today on they take the new ones.
        app(\App\Services\Attendance\ShiftAssignments::class)->refreshHours((int) $setting->id);

        return Reply::success(__('messages.updateSuccess'));
    }

    public function index(Request $request)
    {
        // What this returns is a modal — header, body, footer — meant to be
        // pulled into the Shifts tab on Attendance Settings. Opened as a page
        // it drew that fragment on its own, with no layout, no menu and a stray
        // close button: a shift list with nothing around it.
        //
        // The Shift Settings menu entry points here, so a request that is not
        // the tab's own goes to the page that actually manages shifts, where
        // Add Shift, edit, delete and set-default all live.
        if (!$request->ajax()) {
            return redirect()->route('attendance-settings.index', ['tab' => 'shift']);
        }

        $this->weekMap = Holiday::weekMap();
        $this->employeeShifts = EmployeeShift::where('shift_name', '<>', 'Day Off')->get();
        $generalShift = attendance_setting();
        $this->defaultShift = ($generalShift && $generalShift->attendanceSetting && $generalShift->attendanceSetting->shift) ? $generalShift->attendanceSetting->shift : null;

        return view('employee-shifts.index', $this->data);
    }

}
