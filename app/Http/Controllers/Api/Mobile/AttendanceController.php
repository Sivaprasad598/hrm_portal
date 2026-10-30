<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\PayrollAttendanceController;
use App\Models\Attendance;
use App\Models\AttendanceBreakLog;
use App\Services\Attendance\ClockInCapture;
use App\Services\Attendance\ShiftResolver;
use App\Services\Payroll\AttendanceDays;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in employee's attendance from the app: clock in and out with the
 * photograph and location the Attendance Settings ask for, breaks (recorded
 * only), and the month as a calendar - counted the way payroll counts it
 * (AttendanceDays), so the app, the attendance screens and the payslip agree.
 *
 * The app follows the website's clock-in rules from Attendance Settings: the
 * "employees may clock in" switch, the IP list and the office radius, the
 * last two skipped for work from home as on the website. The shift's clock-in
 * window is not applied: the app may clock in at any hour, as on the website
 * when "show the clock-in button" is on.
 */
class AttendanceController extends MobileController
{
    /** A session left open longer than this is treated as forgotten, not as "clocked in". */
    const OPEN_SESSION_HOURS = 20;

    /** The longest period the attendance list answers in one call. */
    const MAX_RANGE_DAYS = 92;

    public function today()
    {
        $user = $this->me();
        $today = now($this->tz());
        $shift = ShiftResolver::shared($user->company_id)->shiftOn($user->id, $today->toDateString());
        $sessions = $this->sessionsOn($today->toDateString());
        $open = $this->openSession();
        $break = AttendanceBreakLog::running($user->id);
        $requirements = (new ClockInCapture())->requirements();

        return $this->ok([
            'date' => $today->toDateString(),
            'shift' => $this->shiftInfo($shift, $today->toDateString()),
            'clocked_in' => $open !== null,
            'open_session' => $open ? $this->session($open) : null,
            'on_break' => $break !== null,
            'running_break' => $break ? $this->breakInfo($break) : null,
            'sessions' => $sessions->map(fn ($a) => $this->session($a))->values(),
            'breaks' => $this->breaksOn($today->toDateString())->map(fn ($b) => $this->breakInfo($b))->values(),
            'worked_minutes' => $this->workedMinutes($sessions),
            'requirements' => [
                'photo' => $requirements['photo'],       // off | optional | mandatory
                'location' => $requirements['location'], // off | optional | mandatory
            ],
            'can_clock_in' => $this->settings()->employee_clock_in_out === 'yes',
            'office_radius_m' => $this->settings()->radius_check === 'yes' ? (int) $this->settings()->radius : null,
            'offices' => $this->offices(),
        ]);
    }

    public function clockIn(Request $request)
    {
        $this->validatePunch($request);
        $user = $this->me();
        $now = now($this->tz());

        if ($open = $this->openSession()) {
            return $this->fail('You are already clocked in since ' . $this->time($open->clock_in_time) . '.', 409);
        }

        $shift = ShiftResolver::shared($user->company_id)->shiftOn($user->id, $now->toDateString());
        $limit = (int) ($shift->clockin_in_day ?? 0);
        $sessionsToday = $this->sessionsOn($now->toDateString());

        if ($limit > 0 && $sessionsToday->count() >= $limit) {
            return $this->fail('You have reached the clock-ins allowed in a day (' . $limit . ').', 409);
        }

        if ($refusal = $this->settingsRefusal($request, (new ClockInCapture())->coordinates($request->latitude, $request->longitude))) {
            return $this->fail($refusal, 403);
        }

        [$photo, $where, $error] = $this->capture($request, 'clock in');

        if ($error) {
            return $this->fail($error, 422);
        }

        $capture = new ClockInCapture();
        $attendance = new Attendance();
        $attendance->company_id = $user->company_id;
        $attendance->user_id = $user->id;
        $attendance->clock_in_time = $now->copy()->timezone(config('app.timezone'));
        $attendance->clock_in_ip = $request->ip();
        $attendance->clock_in_type = 'mobile_app';
        $attendance->work_from_type = $request->input('work_from_type', 'office');
        $attendance->working_from = $request->working_from;
        $attendance->location_id = $this->officeId($request);
        $attendance->employee_shift_id = is_numeric($shift->id ?? null) ? $shift->id : null;
        $attendance->late = $sessionsToday->isEmpty() && $this->isLate($shift, $now) ? 'yes' : 'no';
        $attendance->half_day = 'no';
        $attendance->latitude = $where['latitude'];
        $attendance->longitude = $where['longitude'];
        $attendance->clock_in_photo = $photo;
        $attendance->photo_requirement = $capture->requirements()['photo'];
        $attendance->clock_in_place = $this->placeText($request, $capture, $request->input('work_from_type', 'office'), $request->working_from);
        $attendance->added_by = $user->id;
        $attendance->save();

        return $this->ok($this->session($attendance->fresh()), 'Clocked in at ' . $now->format('h:i A') . '.', 201);
    }

    public function clockOut(Request $request)
    {
        $this->validatePunch($request);
        $now = now($this->tz());
        $attendance = $this->openSession();

        if (!$attendance) {
            return $this->fail('You are not clocked in.', 409);
        }

        if ($refusal = $this->settingsRefusal($request, (new ClockInCapture())->coordinates($request->latitude, $request->longitude), false)) {
            return $this->fail($refusal, 403);
        }

        [$photo, $where, $error] = $this->capture($request, 'clock out');

        if ($error) {
            return $this->fail($error, 422);
        }

        $capture = new ClockInCapture();

        DB::transaction(function () use ($attendance, $request, $now, $photo, $where, $capture) {
            $attendance->clock_out_time = $now->copy()->timezone(config('app.timezone'));
            $attendance->clock_out_ip = $request->ip();
            $attendance->clock_out_type = 'mobile_app';
            $attendance->clock_out_time_location_id = $this->officeId($request);
            $attendance->clock_out_time_work_from_type = $request->input('work_from_type', 'office');
            $attendance->clock_out_time_working_from = $request->working_from;
            $attendance->clock_out_latitude = $where['latitude'];
            $attendance->clock_out_longitude = $where['longitude'];
            $attendance->clock_out_photo = $photo;
            $attendance->clock_out_photo_requirement = $capture->requirements()['photo'];
            $attendance->clock_out_place = $this->placeText($request, $capture, $request->input('work_from_type', 'office'), $request->working_from);
            $attendance->save();

            // A break still running ends with the day.
            if ($break = AttendanceBreakLog::running($attendance->user_id)) {
                $break->update(['ended_at' => now(), 'end_latitude' => $where['latitude'], 'end_longitude' => $where['longitude']]);
            }
        });

        return $this->ok($this->session($attendance->fresh()), 'Clocked out at ' . $now->format('h:i A') . '.');
    }

    public function startBreak(Request $request)
    {
        $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(AttendanceBreakLog::TYPES)),
            'note' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $session = $this->openSession();

        if (!$session) {
            return $this->fail('Clock in before starting a break.', 409);
        }

        if ($running = AttendanceBreakLog::running($this->me()->id)) {
            return $this->fail('A ' . strtolower(AttendanceBreakLog::TYPES[$running->type] ?? 'break') . ' is already running since ' . $this->time($running->started_at) . '.', 409);
        }

        $where = (new ClockInCapture())->coordinates($request->latitude, $request->longitude);
        $break = AttendanceBreakLog::create([
            'company_id' => $this->me()->company_id,
            'user_id' => $this->me()->id,
            'attendance_id' => $session->id,
            'type' => $request->type,
            'note' => $request->note,
            'started_at' => now(),
            'start_latitude' => $where['latitude'],
            'start_longitude' => $where['longitude'],
        ]);

        return $this->ok($this->breakInfo($break), AttendanceBreakLog::TYPES[$break->type] . ' started.', 201);
    }

    public function endBreak(Request $request)
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $break = AttendanceBreakLog::running($this->me()->id);

        if (!$break) {
            return $this->fail('No break is running.', 409);
        }

        $where = (new ClockInCapture())->coordinates($request->latitude, $request->longitude);
        $break->update(['ended_at' => now(), 'end_latitude' => $where['latitude'], 'end_longitude' => $where['longitude']]);

        return $this->ok($this->breakInfo($break->fresh()), AttendanceBreakLog::TYPES[$break->type] . ' ended.');
    }

    /** The month, day by day, as payroll counts it. */
    public function calendar(Request $request)
    {
        $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $tz = $this->tz();
        $start = Carbon::create((int) ($request->year ?: now($tz)->year), (int) ($request->month ?: now($tz)->month), 1, 0, 0, 0, $tz);
        $end = $start->copy()->endOfMonth()->startOfDay();
        [$summary, $days] = $this->period($start, $end);

        return $this->ok([
            'month' => (int) $start->month,
            'year' => (int) $start->year,
            'summary' => $summary,
            'legend' => $this->legend(),
            'days' => $days->values(),
        ]);
    }

    /** Any period up to MAX_RANGE_DAYS, day by day, newest first - the attendance list. */
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'status' => 'nullable|string|max:30',
        ]);

        $tz = $this->tz();
        $to = $request->to ? Carbon::parse($request->to, $tz)->startOfDay() : now($tz)->startOfDay();
        $from = $request->from ? Carbon::parse($request->from, $tz)->startOfDay() : $to->copy()->startOfMonth();

        if ($from->gt($to)) {
            return $this->fail('The from date must be on or before the to date.', 422, ['from' => ['The from date must be on or before the to date.']]);
        }

        if ($from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
            return $this->fail('Choose a period of at most ' . self::MAX_RANGE_DAYS . ' days.', 422, ['from' => ['Choose a period of at most ' . self::MAX_RANGE_DAYS . ' days.']]);
        }

        [$summary, $days] = $this->period($from, $to);

        if ($request->status) {
            $days = $days->where('status', $request->status);
        }

        return $this->ok([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => $summary,
            'legend' => $this->legend(),
            'days' => $days->sortByDesc('date')->values(),
        ]);
    }

    /** One day in full: how it was counted, every punch with its photo and place, and the breaks. */
    public function day(string $date)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date), 404);

        $tz = $this->tz();
        $day = Carbon::parse($date, $tz)->startOfDay();
        $count = (new AttendanceDays($tz))->forRange($this->me()->id, $day, $day);
        $entry = $count['days'][0] ?? null;
        $leaves = $this->leavesBetween($day, $day);
        $breaks = $this->breaksOn($day->toDateString());

        return $this->ok([
            'day' => $entry ? $this->calendarDay($entry, $leaves[$day->toDateString()] ?? [], $breaks) : null,
            'sessions' => $this->sessionsOn($day->toDateString())->map(fn ($a) => $this->session($a, true))->values(),
            'breaks' => $breaks->map(fn ($b) => $this->breakInfo($b))->values(),
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A period as payroll counts it: [summary, days].
     *
     * @return array{0: array, 1: \Illuminate\Support\Collection}
     */
    private function period(Carbon $start, Carbon $end): array
    {
        $count = (new AttendanceDays($this->tz()))->forRange($this->me()->id, $start, $end);
        $leaves = $this->leavesBetween($start, $end);
        $breaks = $this->breaksBetween($start, $end);
        $days = collect($count['days'])->map(fn ($day) => $this->calendarDay($day, $leaves[$day['date']] ?? [], $breaks[$day['date']] ?? collect()));
        $b = $count['breakdown'];

        return [[
            'days_counted' => $count['working_days'],   // days of the period up to today
            'days_paid' => (float) $count['days_present'],
            'lop_days' => round(max(0, $count['working_days'] - $count['days_present']), 2),
            'worked_days' => (float) ($b['worked'] ?? 0),
            'week_offs' => (int) ($b['week_off'] ?? 0),
            'holidays' => (int) ($b['holiday'] ?? 0),
            'paid_leave_days' => (float) ($b['paid_leave'] ?? 0),
            'unpaid_leave_days' => (float) ($b['lop_leave'] ?? 0),
            'absent_days' => (float) ($b['absent'] ?? 0),
            'late_marks' => (int) ($b['late_marks'] ?? 0),
            'late_half_days' => (int) ($b['late_half_days'] ?? 0),
            'worked_minutes' => (int) $days->sum('worked_minutes'),
        ], $days];
    }

    private function legend()
    {
        return collect(PayrollAttendanceController::STATUSES)->map(fn ($meta, $key) => ['status' => $key, 'label' => $meta[0]])
            ->values()->push(['status' => 'in_progress', 'label' => 'In progress (today)']);
    }

    private function settings(): object
    {
        // Read each time: a controller object can outlive one request.
        return DB::table('attendance_settings')->where('company_id', $this->me()->company_id)->first()
            ?? DB::table('attendance_settings')->first();
    }


    /** The company's offices, for the "where are you" picker, as the website's clock-in offers them. */
    private function offices()
    {
        return DB::table('company_addresses')->where('company_id', $this->me()->company_id)->orderByDesc('is_default')->orderBy('id')
            ->get(['id', 'location', 'address', 'is_default', 'latitude', 'longitude'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'name' => trim((string) $o->location) !== '' ? $o->location : $o->address,
                'is_default' => (bool) $o->is_default,
                'latitude' => $o->latitude !== null ? (float) $o->latitude : null,
                'longitude' => $o->longitude !== null ? (float) $o->longitude : null,
            ])->values();
    }

    /** The office the employee picked, if it is one of the company's. */
    private function officeId(Request $request): ?int
    {
        $id = (int) $request->location_id;

        return $id && DB::table('company_addresses')->where('company_id', $this->me()->company_id)->where('id', $id)->exists() ? $id : null;
    }

    /**
     * Why Attendance Settings refuse this punch, as the website's clock-in
     * refuses it; null when allowed. Working from home skips the IP and
     * radius checks, as on the website.
     */
    private function settingsRefusal(Request $request, array $where, bool $clockingIn = true): ?string
    {
        $settings = $this->settings();

        if ($clockingIn && ($settings->employee_clock_in_out ?? 'yes') !== 'yes') {
            return 'Clocking in yourself is turned off. Please contact HR.';
        }

        if ($request->input('work_from_type', 'office') === 'home') {
            return null;
        }

        if (($settings->ip_check ?? 'no') === 'yes' && !in_array($request->ip(), (array) json_decode((string) $settings->ip_address), true)) {
            return __('messages.notAnAuthorisedDevice');
        }

        if (($settings->radius_check ?? 'no') === 'yes' && ($clockingIn ? ($settings->auto_clock_in_location ?? 'office') !== 'home' : true)) {
            $office = $this->officeFor($request);

            if ($where['latitude'] === null) {
                return 'Your location is required to ' . ($clockingIn ? 'clock in' : 'clock out') . ' at the office. Allow location access and try again.';
            }

            if ($office && $office->latitude !== null
                && $this->metresBetween((float) $office->latitude, (float) $office->longitude, $where['latitude'], $where['longitude']) > (float) $settings->radius) {
                return __('messages.notAnValidLocation');
            }
        }

        return null;
    }

    /** The office to measure from: the one picked, else the employee's, else the default - as the website does. */
    private function officeFor(Request $request): ?object
    {
        $companyId = $this->me()->company_id;
        $id = $this->officeId($request) ?: (int) DB::table('employee_details')->where('user_id', $this->me()->id)->value('company_address_id');
        $office = $id ? DB::table('company_addresses')->where('company_id', $companyId)->where('id', $id)->first() : null;

        return $office ?: DB::table('company_addresses')->where('company_id', $companyId)->where('is_default', 1)->first();
    }

    private function metresBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = fn ($d) => deg2rad($d);
        $a = sin($rad($lat2 - $lat1) / 2) ** 2 + cos($rad($lat1)) * cos($rad($lat2)) * sin($rad($lng2 - $lng1) / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function validatePunch(Request $request): void
    {
        $request->validate([
            'location_id' => 'nullable|integer',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:255',
            'work_from_type' => 'nullable|in:office,home,other',
            'working_from' => 'nullable|required_if:work_from_type,other|string|max:150',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:' . (int) (ClockInCapture::MAX_BYTES / 1024),
            'photo_base64' => 'nullable|string',
        ]);
    }

    /**
     * The photograph and coordinates sent, checked against the Attendance
     * Settings exactly as the web clock-in checks them.
     *
     * @return array{0: ?string, 1: array, 2: ?string}  [photo path, coordinates, refusal]
     */
    private function capture(Request $request, string $action): array
    {
        $capture = new ClockInCapture();
        $rules = $capture->requirements();
        $dataUrl = $request->photo_base64;

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $dataUrl = 'data:' . $file->getMimeType() . ';base64,' . base64_encode((string) file_get_contents($file->getRealPath()));
        } elseif ($dataUrl && !str_starts_with($dataUrl, 'data:')) {
            $dataUrl = 'data:image/jpeg;base64,' . $dataUrl;   // plain base64 from the app
        }

        $where = $capture->coordinates($request->latitude, $request->longitude);
        $photo = $capture->storePhoto($dataUrl, $this->me()->id);

        if ($refuse = $capture->missing($rules, $photo['path'], $where['latitude'], $action)) {
            return [null, $where, $refuse];
        }

        if ($photo['problem'] !== null && $rules['photo'] === 'mandatory') {
            return [null, $where, $photo['problem']];
        }

        return [$photo['path'], $where, null];
    }

    private function placeText(Request $request, ClockInCapture $capture, ?string $type, ?string $workingFrom): ?string
    {
        $address = trim((string) $request->address);

        return $address !== '' ? mb_substr($address, 0, 255) : $capture->place($this->officeId($request), $type, $workingFrom);
    }

    /** The session clocked in and not yet out (a forgotten one older than a day does not count). */
    private function openSession(): ?Attendance
    {
        return Attendance::where('user_id', $this->me()->id)
            ->whereNull('clock_out_time')
            ->where('clock_in_time', '>=', now()->subHours(self::OPEN_SESSION_HOURS))
            ->orderByDesc('clock_in_time')
            ->first();
    }

    private function sessionsOn(string $date)
    {
        [$from, $to] = $this->utcBounds($date);

        return Attendance::where('user_id', $this->me()->id)
            ->whereBetween('clock_in_time', [$from, $to])
            ->orderBy('clock_in_time')
            ->get();
    }

    private function breaksOn(string $date)
    {
        [$from, $to] = $this->utcBounds($date);

        return AttendanceBreakLog::where('user_id', $this->me()->id)
            ->whereBetween('started_at', [$from, $to])->orderBy('started_at')->get();
    }

    private function breaksBetween(Carbon $start, Carbon $end)
    {
        [$from] = $this->utcBounds($start->toDateString());
        [, $to] = $this->utcBounds($end->toDateString());

        return AttendanceBreakLog::where('user_id', $this->me()->id)
            ->whereBetween('started_at', [$from, $to])->orderBy('started_at')->get()
            ->groupBy(fn ($b) => $b->started_at->copy()->timezone($this->tz())->toDateString());
    }

    /** A local day as UTC limits for the stored times. */
    private function utcBounds(string $date): array
    {
        $local = Carbon::parse($date, $this->tz())->startOfDay();

        return [$local->copy()->timezone(config('app.timezone')), $local->copy()->endOfDay()->timezone(config('app.timezone'))];
    }

    /** Leave on each day of the range: date => [{type, duration, half, status, paid}]. */
    private function leavesBetween(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->where('l.user_id', $this->me()->id)
            ->whereBetween('l.leave_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('l.status', ['approved', 'pending'])
            ->get(['l.leave_date', 'l.duration', 'l.half_day_type', 'l.status', 'l.paid', 't.type_name']);

        $out = [];

        foreach ($rows as $row) {
            $out[substr((string) $row->leave_date, 0, 10)][] = [
                'type' => $row->type_name,
                'duration' => $row->duration,              // single | multiple | half day
                'half' => $row->half_day_type,             // first_half | second_half | null
                'status' => $row->status,                  // approved | pending
                'paid' => (bool) $row->paid,
            ];
        }

        return $out;
    }

    private function calendarDay(array $day, array $leaves, $breaks): array
    {
        $status = $day['status'];
        $time = fn ($v) => $v ? substr($v, 11, 5) : null;

        // Today, with a punch but not yet a full day: still going, not absent.
        if ($day['date'] === now($this->tz())->toDateString() && $day['first_in'] && in_array($status, ['absent', 'half_day'], true)) {
            $status = 'in_progress';
        }

        return [
            'date' => $day['date'],
            'weekday' => Carbon::parse($day['date'])->format('l'),
            'status' => $status,
            'status_label' => $status === 'in_progress' ? 'In progress (today)' : (PayrollAttendanceController::STATUSES[$status][0] ?? ucfirst(str_replace('_', ' ', $status))),
            'paid_day' => (float) $day['paid'],
            'shift' => $day['shift'],
            'shift_hours' => $day['shift_hours'],
            'first_in' => $time($day['first_in']),
            'last_out' => $time($day['last_out']),
            'worked_minutes' => isset($day['worked_minutes']) ? (int) $day['worked_minutes'] : intdiv((int) $day['seconds'], 60),
            'late' => isset($day['late']),
            'late_note' => $day['late'] ?? null,
            'overtime_minutes' => (int) ($day['ot_minutes'] ?? 0),
            'holiday' => $status === 'holiday' ? ($day['note'] ?? 'Holiday') : null,
            'note' => $status === 'holiday' ? null : ($day['note'] ?? null),
            'leaves' => $leaves,
            'breaks' => [
                'count' => $breaks->count(),
                'minutes' => (int) $breaks->sum(fn ($b) => $b->ended_at ? intdiv($b->ended_at->diffInSeconds($b->started_at, true), 60) : 0),
            ],
        ];
    }

    private function session(Attendance $a, bool $full = false): array
    {
        $out = [
            'id' => $a->id,
            'clock_in' => $this->time($a->clock_in_time),
            'clock_out' => $this->time($a->clock_out_time),
            'source' => $this->source($a),
            'work_from' => $a->work_from_type,
            'minutes' => $a->clock_out_time ? intdiv(Carbon::parse($a->clock_out_time)->diffInSeconds(Carbon::parse($a->clock_in_time), true), 60) : null,
        ];

        if ($full) {
            $out['clock_in_photo_url'] = ClockInCapture::url($a->clock_in_photo);
            $out['clock_in_location'] = $this->location($a->latitude, $a->longitude, $a->clock_in_place);
            $out['clock_out_photo_url'] = ClockInCapture::url($a->clock_out_photo);
            $out['clock_out_location'] = $this->location($a->clock_out_latitude, $a->clock_out_longitude, $a->clock_out_place);
        }

        return $out;
    }

    private function source(Attendance $a): string
    {
        if ($a->clock_in_type === 'mobile_app') {
            return 'mobile_app';
        }

        // Clocked in by the employee on the website; anything else was marked by
        // HR or brought in from the biometric device.
        return (int) $a->added_by === (int) $a->user_id ? 'web' : 'hr_or_device';
    }

    private function location($lat, $lng, ?string $place): ?array
    {
        if ($lat === null && !$place) {
            return null;
        }

        return ['latitude' => $lat !== null ? (float) $lat : null, 'longitude' => $lng !== null ? (float) $lng : null, 'address' => $place];
    }

    private function breakInfo(AttendanceBreakLog $b): array
    {
        return [
            'id' => $b->id,
            'type' => $b->type,
            'label' => AttendanceBreakLog::TYPES[$b->type] ?? $b->type,
            'note' => $b->note,
            'started_at' => $this->time($b->started_at),
            'ended_at' => $this->time($b->ended_at),
            'minutes' => $b->ended_at ? intdiv($b->ended_at->diffInSeconds($b->started_at, true), 60) : null,
        ];
    }

    private function shiftInfo(?object $shift, string $date): ?array
    {
        return $shift ? [
            'name' => $shift->shift_name,
            'type' => $shift->shift_type ?? null,
            'start' => $shift->office_start_time ? substr((string) $shift->office_start_time, 0, 5) : null,
            'end' => $shift->office_end_time ? substr((string) $shift->office_end_time, 0, 5) : null,
            'working_day' => ShiftResolver::worksOn($shift, $date),
        ] : null;
    }

    private function workedMinutes($sessions): int
    {
        return (int) $sessions->sum(fn ($a) => intdiv(Carbon::parse($a->clock_out_time ?? now())->diffInSeconds(Carbon::parse($a->clock_in_time), true), 60));
    }

    /** Late on a fixed shift: after its start plus the late-mark allowance. A flexible shift is never late. */
    private function isLate(?object $shift, Carbon $now): bool
    {
        if (!$shift || ($shift->shift_type ?? '') === 'flexible' || !$shift->office_start_time) {
            return false;
        }

        $start = Carbon::parse($now->toDateString() . ' ' . $shift->office_start_time, $this->tz());

        return $now->gt($start->addMinutes((int) ($shift->late_mark_duration ?? 0)));
    }
}
