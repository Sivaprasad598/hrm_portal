<?php

namespace App\Http\Controllers;

// use App\Http\Controllers\Carbon;

use App\DataTables\ShiftRotationDataTable;
use Carbon\Carbon;

use App\Models\Role;
use App\Helper\Reply;

use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Models\EmployeeShift;
// use Endroid\QrCode\QrCode;
use App\Models\AttendanceSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use App\Models\EmployeeShiftSchedule;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\ErrorCorrectionLevel;
use App\Http\Requests\AttendanceSetting\UpdateAttendanceSetting;
use App\Http\Requests\AttendanceSetting\AttendanceRegularisationSetting;

class AttendanceSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.attendanceSettings';
        $this->activeSettingMenu = 'attendance_settings';
        $this->middleware(function ($request, $next) {

            abort_403(!(user()->permission('manage_attendance_setting') == 'all' && in_array('attendance', user_modules())));

            return $next($request);
        });
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $this->ipAddresses = [];
        $this->attendanceSetting = AttendanceSetting::first();
        $this->monthlyReportRoles = json_decode($this->attendanceSetting->monthly_report_roles);
        $this->roles = Role::where('name', '<>', 'client')->get();

        $this->allRoles = Role::whereNotIn('name', ['client', 'admin'])->get();

        $selectedRolesJson = $this->attendanceSetting?->attendance_regularize_roles;

        $this->selectedRoles = json_decode($selectedRolesJson, true) ?? [];

        if (json_decode($this->attendanceSetting->ip_address)) {
            $this->ipAddresses = json_decode($this->attendanceSetting->ip_address, true);
        }

        $tab = request('tab');
        switch ($tab) {
        case 'shift':
            $this->weekMap = Holiday::weekMap();
            $this->employeeShifts = EmployeeShift::where('shift_name', '<>', 'Day Off')->get();
            $this->view = 'attendance-settings.ajax.shift';
            break;

        case 'qrcode':


            // PNG needs PHP's GD extension, which many hosts leave out - without
            // it this tab was a "Server Error". SVG needs nothing and shows the
            // same code; the page only uses the image as a data URI.
            $qrBuilder = fn ($writer) => Builder::create()
                ->writer($writer)
                ->encoding(new Encoding('UTF-8'))
                ->data((route('settings.qr-login', ['hash' => company()->hash])))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->validateResult(false)
                ->build();

            try {
                $this->qr = (extension_loaded('gd') && function_exists('imagecreatetruecolor') && function_exists('imagepng'))
                    ? $qrBuilder(new PngWriter())
                    : $qrBuilder(new \Endroid\QrCode\Writer\SvgWriter());
            } catch (\Throwable $e) {
                report($e);
                $this->qr = $qrBuilder(new \Endroid\QrCode\Writer\SvgWriter());
            }



            $this->view = 'attendance-settings.ajax.qrcode';
        break;
        case 'regularisation':
            $this->view = 'attendance-settings.ajax.regularisation';
            break;
        case 'working-hours':
            $this->shifts = \App\Models\EmployeeShift::where('shift_name', '<>', 'Day Off')->orderBy('shift_name')->get();
            $this->breaks = \Illuminate\Support\Facades\DB::table('attendance_breaks')->where('company_id', company()->id)
                ->orderBy('sort_order')->orderBy('start_time')->get();
            $this->view = 'attendance-settings.ajax.working-hours';
            break;
        case 'half-day':   // the old Half Day Settings tab is now part of Lates & Half Day
        case 'lates':
            $tab = 'lates';
            $this->shifts = \App\Models\EmployeeShift::where('shift_name', '<>', 'Day Off')->orderBy('shift_name')->get();
            $this->view = 'attendance-settings.ajax.lates';
            break;
        case 'overtime':
            $this->view = 'attendance-settings.ajax.overtime';
            break;
        case 'shift-rotation':
            return $this->shiftRotation();
            break;
        default:
            $this->view = 'attendance-settings.ajax.attendance';
            break;
        }

        $this->activeTab = $tab ?: 'attendance';

        if (request()->ajax()) {
            $html = view($this->view, $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle, 'activeTab' => $this->activeTab]);
        }

        return view('attendance-settings.index', $this->data);
    }

    public function shiftRotation()
    {
        $this->pageTitle = 'app.menu.shiftRotation';
        $this->activeTab = request('tab') ?: 'overview';
        $this->view = 'attendance-settings.ajax.shift-rotation';
        $dataTable = new ShiftRotationDataTable(true);

        return $dataTable->render('attendance-settings.index', $this->data);
    }

    /**
     * @param UpdateAttendanceSetting $request
     * @param int $id
     * @return array
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    //phpcs:ignore
    public function update(UpdateAttendanceSetting $request, $id)
    {
        $setting = company()->attendanceSetting;

        $attendanceSetting = AttendanceSetting::find(company()->id);

        if (request()->auto_clock_in == 'yes' && $attendanceSetting->qr_enable == 1) {
            return Reply::error(__('messages.fristSignAndQrCodeError'));
        }

        $setting->employee_clock_in_out = ($request->employee_clock_in_out == 'yes') ? 'yes' : 'no';
        $setting->radius_check = ($request->radius_check == 'yes') ? 'yes' : 'no';
        $setting->ip_check = ($request->ip_check == 'yes') ? 'yes' : 'no';
        $setting->radius = $request->radius;
        $setting->ip_address = json_encode($request->ip);
        $setting->alert_after = $request->alert_after;
        $setting->week_start_from = $request->week_start_from;
        $setting->alert_after_status = ($request->alert_after_status == 'on') ? 1 : 0;
        $setting->save_current_location = ($request->save_current_location) ? 1 : 0;

        // What a clock-in must carry: a photograph, a place, or neither.
        if (in_array($request->clock_in_photo, ['off', 'optional', 'mandatory'], true)) {
            $setting->clock_in_photo = $request->clock_in_photo;
        }

        if (in_array($request->clock_in_location, ['off', 'optional', 'mandatory'], true)) {
            $setting->clock_in_location = $request->clock_in_location;
        }
        $setting->allow_shift_change = ($request->allow_shift_change) ? 1 : 0;
        $setting->auto_clock_in = ($request->auto_clock_in) ? 'yes' : 'no';
        $setting->show_clock_in_button = ($request->show_clock_in_button == 'yes') ? 'yes' : 'no';
        $setting->auto_clock_in_location = $request->auto_clock_in_location;
        $setting->monthly_report = ($request->monthly_report) ? 1 : 0;
        $setting->monthly_report_roles = json_encode($request->monthly_report_roles);
        $setting->save();

        session()->forget(['attendance_setting','company']);

        return Reply::success(__('messages.updateSuccess'));
    }

    public function attendanceShift($defaultAttendanceSettings)
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

        }
        else if ($nowTime->betweenIncluded($backDayFromDefault, $backDayToDefault)) {
            $attendanceSettings = $defaultAttendanceSettings;

        }
        else if ($checkTodayShift &&
            ($nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time)
                || $nowTime->gt($checkTodayShift->shift_end_time)
                || (!$nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time) && $defaultAttendanceSettings->show_clock_in_button == 'no'))
        ) {
            $attendanceSettings = $checkTodayShift;
        }
        else if ($checkTodayShift && !is_null($checkTodayShift->shift->early_clock_in)) {
            $attendanceSettings = $checkTodayShift;
        }
        else {
            $attendanceSettings = $defaultAttendanceSettings;
        }

        return $attendanceSettings->shift;

    }

    public function attendanceRegularisation(AttendanceRegularisationSetting $request)
    {
        $setting = company()->attendanceSetting;
        $setting->adjust_attendance_logs = $request->adjust_attendance_logs;
        if ($request->adjust_attendance_logs === 'not_allowed') {
            $setting->adjustment_allowed = 0;
        } else {
            $setting->adjustment_allowed = 1;
        }
        $setting->times_adjustment_allowed = $request->times_adjustment_allowed;
        $setting->last_days = $request->last_days;
        $setting->adjustment_total_times = $request->adjustment_total_times;
        $setting->adjustment_type = $request->adjustment_type;
        $setting->before_day_of_month = $request->before_day_of_month;
        $setting->attendance_regularize_roles = json_encode($request->attendance_regularize_roles);
        $setting->update();

        return Reply::success(__('messages.updateSuccess'));

    }

    /** Working Hours tab: the working day, breaks, counting, half day, grace. See WorkRules. */
    public function updateWorkingHours(Request $request)
    {
        $data = $request->validate([
            'wh_count_mode' => 'required|in:anywhere,within_shift',
            'wh_work_hours' => 'required|numeric|min:1|max:16',
            'wh_grace_minutes' => 'required|integer|min:0|max:120',
            'wh_half_day_hours' => 'required|numeric|min:0.5|lt:wh_work_hours',
            'breaks' => 'nullable|array|max:20',
            'breaks.*.name' => 'required|string|max:60',
            'breaks.*.start' => 'required|date_format:H:i',
            'breaks.*.end' => 'required|date_format:H:i|different:breaks.*.start',
            'shift_breaks' => 'nullable|array',
            'shift_custom' => 'nullable|array',
        ], ['wh_half_day_hours.lt' => 'A half day must be shorter than a full day.',
            'breaks.*.name.required' => 'Give every break a name.',
            'breaks.*.start.required' => 'Give every break its start time.',
            'breaks.*.end.required' => 'Give every break its end time.',
            'breaks.*.end.different' => 'A break has to end after it starts.'], self::RULE_FIELDS);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data) {
            $this->saveRules(array_intersect_key($data, array_flip(['wh_count_mode', 'wh_work_hours', 'wh_grace_minutes', 'wh_half_day_hours'])));

            // Breaks: the rows on screen, by their id - or a new one ("new3") that gets an id now.
            $ids = [];
            $order = 0;
            $table = \Illuminate\Support\Facades\DB::table('attendance_breaks');

            foreach ((array) $request->breaks as $key => $b) {
                $row = ['company_id' => company()->id, 'name' => trim($b['name']), 'start_time' => $b['start'] . ':00',
                    'end_time' => $b['end'] . ':00', 'is_default' => !empty($b['default']), 'sort_order' => ++$order, 'updated_at' => now()];

                if (ctype_digit((string) $key) && (clone $table)->where('id', $key)->where('company_id', company()->id)->exists()) {
                    (clone $table)->where('id', $key)->update($row);
                    $ids[(string) $key] = (int) $key;
                } else {
                    $ids[(string) $key] = (clone $table)->insertGetId($row + ['created_at' => now()]);
                }
            }

            (clone $table)->where('company_id', company()->id)->whereNotIn('id', array_values($ids) ?: [0])->delete();

            // Each shift: the breaks it picked, or the defaults if it never chose.
            foreach (\App\Models\EmployeeShift::where('shift_name', '<>', 'Day Off')->pluck('id') as $shiftId) {
                $custom = (string) ($request->shift_custom[$shiftId] ?? '0') === '1';
                $picked = array_values(array_filter(array_map(fn ($k) => $ids[(string) $k] ?? null, (array) ($request->shift_breaks[$shiftId] ?? []))));

                \App\Models\EmployeeShift::where('id', $shiftId)->update(['break_ids' => $custom ? json_encode($picked) : null]);
            }
        });

        \App\Services\Attendance\WorkRules::reset();
        \App\Services\Attendance\ShiftResolver::reset();

        return Reply::success(__('messages.updateSuccess'));
    }

    /** Lates & Half Day tab: when somebody is late, and what lates cost. */
    public function updateLates(Request $request)
    {
        $data = $request->validate([
            'wh_late_basis' => 'required|in:shift,global',
            'wh_late_time' => 'nullable|required_if:wh_late_basis,global|date_format:H:i',
            'half_day_late_threshold' => 'required|integer|min:1|max:31',
            'half_day_deduction_type' => 'required|in:0.5,1',
        ], ['wh_late_time.required_if' => 'Give the time after which everybody is late.'], self::RULE_FIELDS);

        $this->saveRules($data);

        return Reply::success(__('messages.updateSuccess'));
    }

    /** Overtime tab. */
    public function updateOvertime(Request $request)
    {
        $data = $request->boolean('ot_enabled') ? $request->validate([
            'ot_basis' => 'required|in:gross,basic',
            'ot_rate_weekday' => 'required|numeric|min:0|max:10',
            'ot_rate_weekend' => 'required|numeric|min:0|max:10',
            'ot_rate_holiday' => 'required|numeric|min:0|max:10',
            'ot_min_minutes' => 'required|integer|min:0|max:600',
            'ot_cap_weekday_hours' => 'required|numeric|min:0|max:16',
            'ot_cap_off_hours' => 'required|numeric|min:0|max:24',
            'ot_mode' => 'required|in:bank,pay',
            'ot_compensate_choice' => 'required|in:manual,auto,off',
            'ot_encash' => 'required|boolean',
            'ot_bank_cap' => 'nullable|numeric|min:0|max:9999',
        ], [], self::RULE_FIELDS) : [];

        if ($data) {
            // One choice on screen, two settings underneath.
            $data['ot_compensate'] = $data['ot_compensate_choice'] !== 'off';
            $data['ot_compensate_mode'] = $data['ot_compensate_choice'] === 'auto' ? 'auto' : 'manual';
            $data['ot_bank_cap'] = ($data['ot_bank_cap'] ?? '') === '' || $data['ot_bank_cap'] === null ? null : $data['ot_bank_cap'];
            unset($data['ot_compensate_choice']);
        }

        $this->saveRules($data + ['ot_enabled' => $request->boolean('ot_enabled')]);

        return Reply::success(__('messages.updateSuccess'));
    }

    /** Names used in the error messages of the three rule tabs. */
    private const RULE_FIELDS = [
        'wh_count_mode' => 'count hours', 'wh_work_hours' => 'work hours', 'breaks.*.name' => 'break name',
        'breaks.*.start' => 'break start', 'breaks.*.end' => 'break end', 'wh_grace_minutes' => 'grace', 'wh_half_day_hours' => 'half day',
        'wh_late_basis' => 'late after', 'wh_late_time' => 'late time',
        'half_day_late_threshold' => 'lates before a deduction', 'half_day_deduction_type' => 'deduction',
        'ot_basis' => 'paid on', 'ot_rate_weekday' => 'weekday rate', 'ot_rate_weekend' => 'week-off rate',
        'ot_rate_holiday' => 'holiday rate', 'ot_min_minutes' => 'count from', 'ot_cap_weekday_hours' => 'weekday cap',
        'ot_cap_off_hours' => 'week-off / holiday cap', 'ot_mode' => 'overtime is', 'ot_compensate_choice' => 'cover LOP with OT',
        'ot_encash' => 'encash in payroll', 'ot_bank_cap' => 'bank limit',
    ];

    private function saveRules(array $values): void
    {
        $setting = company()->attendanceSetting;

        foreach ($values as $field => $value) {
            $setting->{$field} = $value;
        }

        $setting->save();
        \App\Services\Attendance\WorkRules::reset();
    }

    public function updateHalfDay(Request $request)
    {
        $request->validate([
            'half_day_late_threshold' => 'required|integer|min:1',
            'half_day_deduction_type' => 'required|in:0.5,1',
        ]);

        $setting = company()->attendanceSetting;
        $setting->half_day_late_threshold = $request->half_day_late_threshold;
        $setting->half_day_deduction_type = $request->half_day_deduction_type;
        $setting->update();

        return Reply::success(__('messages.updateSuccess'));
    }

}
