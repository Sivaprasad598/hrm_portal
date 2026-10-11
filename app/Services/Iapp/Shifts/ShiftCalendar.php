<?php

namespace App\Services\Iapp\Shifts;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which ERP shift applied to a person on a day, and whether they were
 * expected to work.
 *
 * Follows the resolution order the rest of the ERP uses
 * (LeaveHelper::resolveWorkingDay, ImportAttendanceJob): the employee's
 * schedule for that date, otherwise the company default shift.
 *
 * Shift data cannot come from the devices — iApp exposes no shift, roster or
 * week-off endpoint — so the ERP is the only source. Everything is loaded once
 * for a set of employees and a date range, so evaluating hundreds of days costs
 * a handful of queries rather than several per day.
 *
 * Clock values are compared as stored, with no timezone conversion: punches
 * carry the device's local clock time and shift times are local too.
 *
 * Read-only.
 */
class ShiftCalendar
{

    public const SOURCE_SCHEDULE = 'schedule';
    public const SOURCE_DEFAULT = 'default';
    /** No linked ERP employee: the company default is assumed, not known. */
    public const SOURCE_ASSUMED = 'assumed';

    public const REASON_DAY_OFF = 'day_off';
    public const REASON_WEEK_OFF = 'week_off';
    public const REASON_HOLIDAY = 'holiday';
    public const REASON_LEAVE = 'leave';
    public const REASON_HALF_LEAVE = 'half_leave';

    protected Collection $shifts;

    protected ?object $defaultShift = null;

    protected ?object $settings = null;

    /** [user_id][Y-m-d] => schedule row */
    protected array $schedules = [];

    /** [Y-m-d][] => holiday row */
    protected array $holidays = [];

    /** [user_id][Y-m-d] => approved leave row */
    protected array $leaves = [];

    /** [user_id] => designation_id */
    protected array $designations = [];

    /** [user_id] => employment_type */
    protected array $employmentTypes = [];

    protected array $companyDesignations = [];

    /** [user_id] => department_id */
    protected array $departments = [];

    /** Every department id in the company, as strings. */
    protected array $companyDepartments = [];

    public function __construct(
        protected ?int $companyId,
        protected Carbon $from,
        protected Carbon $to
    ) {
        $this->shifts = collect();
    }

    /**
     * Preload everything needed for these employees over the range.
     *
     * @param  array<int, int|null>  $userIds
     */
    public function load(array $userIds = []): self
    {
        $from = $this->from->toDateString();
        $to = $this->to->toDateString();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        $this->shifts = DB::table('employee_shifts')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->get()
            ->keyBy('id');

        $this->settings = DB::table('attendance_settings')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->first();

        $this->defaultShift = $this->settings?->default_employee_shift
            ? $this->shifts->get($this->settings->default_employee_shift)
            : null;

        $holidays = DB::table('holidays')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->whereBetween('date', [$from, $to])
            ->get(['date', 'occassion', 'department_id_json', 'designation_id_json', 'employment_type_json']);

        foreach ($holidays as $holiday) {
            $this->holidays[substr((string) $holiday->date, 0, 10)][] = $holiday;
        }

        $this->companyDepartments = DB::table('teams')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->companyDesignations = DB::table('designations')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($userIds === []) {
            return $this;
        }

        $schedules = DB::table('employee_shift_schedules')
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from, $to])
            ->get(['user_id', 'date', 'employee_shift_id', 'shift_start_time', 'shift_end_time']);

        foreach ($schedules as $row) {
            $this->schedules[$row->user_id][substr((string) $row->date, 0, 10)] = $row;
        }

        $leaves = DB::table('leaves')
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween('leave_date', [$from, $to])
            ->get(['user_id', 'leave_date', 'duration', 'half_day_type']);

        foreach ($leaves as $row) {
            $this->leaves[$row->user_id][substr((string) $row->leave_date, 0, 10)] = $row;
        }

        $details = DB::table('employee_details')
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'department_id', 'designation_id', 'employment_type']);

        foreach ($details as $row) {
            $this->departments[$row->user_id] = $row->department_id;
            $this->designations[$row->user_id] = $row->designation_id;
            $this->employmentTypes[$row->user_id] = $row->employment_type;
        }

        return $this;
    }

    /**
     * @return array{
     *     shift: ?object, source: string, working: bool, reason: ?string,
     *     holiday: ?string, start_at: ?Carbon, end_at: ?Carbon
     * }
     */
    public function resolve(?int $userId, string $date): array
    {
        $schedule = $userId ? ($this->schedules[$userId][$date] ?? null) : null;

        if ($schedule && $this->shifts->has($schedule->employee_shift_id)) {
            $shift = $this->shifts->get($schedule->employee_shift_id);
            $source = self::SOURCE_SCHEDULE;
        } else {
            $shift = $this->defaultShift;
            $source = $userId ? self::SOURCE_DEFAULT : self::SOURCE_ASSUMED;
        }

        $result = [
            'shift' => $shift,
            'source' => $source,
            'working' => true,
            'reason' => null,
            'holiday' => null,
            'start_at' => null,
            'end_at' => null,
        ];

        if (!$shift) {
            return $result;
        }

        if (self::isDayOff($shift)) {
            return ['working' => false, 'reason' => self::REASON_DAY_OFF] + $result;
        }

        // Same rule as LeaveHelper::resolveWorkingDay, including treating an
        // empty open-days list as non-working.
        $openDays = array_map('intval', json_decode((string) $shift->office_open_days, true) ?: []);

        if ($openDays === [] || !in_array((int) Carbon::parse($date)->format('N'), $openDays, true)) {
            return ['working' => false, 'reason' => self::REASON_WEEK_OFF] + $result;
        }

        foreach ($this->holidays[$date] ?? [] as $holiday) {
            if ($this->holidayApplies($holiday, $userId)) {
                return ['working' => false, 'reason' => self::REASON_HOLIDAY, 'holiday' => $holiday->occassion] + $result;
            }
        }

        // A schedule row carries the actual times for that date; otherwise the
        // shift's standard times apply.
        if ($schedule && $schedule->shift_start_time && $schedule->shift_end_time) {
            $start = Carbon::parse($schedule->shift_start_time);
            $end = Carbon::parse($schedule->shift_end_time);
        } elseif ($shift->office_start_time && $shift->office_end_time) {
            $start = Carbon::parse($date . ' ' . $shift->office_start_time);
            $end = Carbon::parse($date . ' ' . $shift->office_end_time);
        } else {
            $start = $end = null;
        }

        if ($start && $end && $end->lte($start)) {
            $end->addDay();
        }

        $result['start_at'] = $start;
        $result['end_at'] = $end;

        $leave = $userId ? ($this->leaves[$userId][$date] ?? null) : null;

        if ($leave) {
            if (str_contains(strtolower((string) $leave->duration), 'half')) {
                $result['reason'] = self::REASON_HALF_LEAVE;
            } else {
                $result['working'] = false;
                $result['reason'] = self::REASON_LEAVE;
            }
        }

        return $result;
    }

    /**
     * Whether a holiday reaches a given person.
     *
     * Mirrors LeaveHelper::resolveHolidays, which narrows a holiday by
     * department, designation *and* employment type — a holiday applies only
     * if the employee matches on all three, and an empty list on any of them
     * means "no limit". Checking department alone would hand a holiday scoped
     * to one designation to everybody.
     *
     * An unlinked biometric code has no known department or designation, so a
     * limited holiday reaches it only when the limit covers every value in the
     * company — at which point it excludes nobody. Every holiday here is
     * scoped to the company's single department and designation, so without
     * that rule unlinked staff would be marked absent on public holidays.
     */
    protected function holidayApplies(object $holiday, ?int $userId): bool
    {
        return $this->scopeAllows($holiday->department_id_json ?? null, $userId, $this->departments, $this->companyDepartments)
            && $this->scopeAllows($holiday->designation_id_json ?? null, $userId, $this->designations, $this->companyDesignations)
            // Employment type is a fixed set of strings rather than ids, so
            // there is no company-wide list to compare an unknown against;
            // an unlinked code is allowed through on this axis alone.
            && $this->scopeAllows($holiday->employment_type_json ?? null, $userId, $this->employmentTypes, null);
    }

    /**
     * One axis of a holiday's scope: an empty list means everyone.
     *
     * @param  array<int|string, mixed>  $owned  [user_id] => that person's value
     * @param  array<int, string>|null  $universe  every value in the company, or
     *                                             null when the axis has none
     */
    protected function scopeAllows(?string $json, ?int $userId, array $owned, ?array $universe): bool
    {
        $allowed = json_decode((string) $json, true);

        if (!is_array($allowed) || $allowed === []) {
            return true;
        }

        $allowed = array_map('strval', $allowed);

        if ($userId !== null && isset($owned[$userId]) && $owned[$userId] !== null) {
            return in_array((string) $owned[$userId], $allowed, true);
        }

        if ($universe === null) {
            return true;
        }

        return $universe !== [] && array_diff($universe, $allowed) === [];
    }

    public function shifts(): Collection
    {
        return $this->shifts;
    }

    public function defaultShift(): ?object
    {
        return $this->defaultShift;
    }

    /**
     * Attendance settings and the default shift can disagree on office hours.
     * Calculations follow the shift record — that is what the ERP's own
     * attendance code resolves through `AttendanceSetting::shift()` — but the
     * disagreement is reported so it can be fixed at source.
     */
    public function settingsConflict(): ?array
    {
        if (!$this->settings || !$this->defaultShift) {
            return null;
        }

        $settings = [
            substr((string) $this->settings->office_start_time, 0, 5),
            substr((string) $this->settings->office_end_time, 0, 5),
        ];

        $shift = [
            substr((string) $this->defaultShift->office_start_time, 0, 5),
            substr((string) $this->defaultShift->office_end_time, 0, 5),
        ];

        return $settings === $shift ? null : [
            'settings' => $settings,
            'shift' => $shift,
            'shift_name' => $this->defaultShift->shift_name,
        ];
    }

    /** The codebase identifies the day-off shift by name (see AssignShiftRotation). */
    public static function isDayOff(?object $shift): bool
    {
        return $shift && strcasecmp(trim((string) $shift->shift_name), 'Day Off') === 0;
    }

    public static function label(?object $shift): string
    {
        if (!$shift) {
            return 'No shift';
        }

        if (self::isDayOff($shift) || !$shift->office_start_time) {
            return (string) $shift->shift_name;
        }

        return sprintf(
            '%s %s–%s',
            $shift->shift_name,
            substr((string) $shift->office_start_time, 0, 5),
            substr((string) $shift->office_end_time, 0, 5)
        );
    }

}
