<?php

namespace App\Services\Attendance;

use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * HR uploading a pay period's attendance from a spreadsheet.
 *
 * The sheet, one line per employee per day:
 *   Employee Code (needed) | Name (optional, only to read) | Date | Attendance | In | Out | Note (only to read)
 *
 * Attendance is P (present), HD1 / HD2 (half day: morning / afternoon) or A (absent); In / Out are HH:MM.
 *  - P with no times: the shift's start to its end (the General Shift: 09:30 to 18:30).
 *  - HD1 with no times: the morning, the shift's start to its half-day mark (09:30 to 14:00).
 *  - HD2 with no times: the afternoon, the half-day mark to the shift's end (14:00 to 18:30).
 *    Both are marked a half day (first_half / second_half, as HR's Add Attendance records it).
 *    A plain "HD" is the morning.
 *  - A: the day's attendance is taken off (kept in the upload's log).
 *  - Times given are used as they are; an Out earlier than In is the next morning.
 *  - P on a day that has a clock-in but no clock-out: the clock-out is the shift's end.
 *  - Attendance left blank with times: those times. Everything blank: the day is left as it is.
 * The shift is the employee's own for that day (roster, assignment, default); on a day off,
 * the company's default shift (the General Shift).
 *
 * The rules around it:
 *  - Only the pay cycle running now and the one before it can be uploaded.
 *  - Once an employee's payslip for the period is generated (draft or later), their
 *    attendance in it cannot be changed here. Revoking the payslip opens it again.
 *  - A day that has not happened yet cannot be uploaded.
 *
 * What is written is an ordinary attendance row, as HR's Add Attendance writes one (shift,
 * shift times, half day, location), so the register, the dashboards and payroll read it
 * like any other. Nothing is written until the preview has been read and applied, and the
 * locks are checked again then: payroll may have been generated in between.
 */
class AttendanceImport
{

    public const HEADINGS = ['Employee Code', 'Name', 'Date', 'Attendance', 'In', 'Out', 'Note'];

    /** What the Attendance column takes. */
    public const MARKS = [
        'p' => 'P', 'present' => 'P', 'pr' => 'P', 'full' => 'P', 'fullday' => 'P',
        // half day, morning session (the first half): shift start to the half-day mark
        'hd1' => 'HD1', 'hd' => 'HD1', 'halfday' => 'HD1', 'half' => 'HD1', 'h' => 'HD1', 'hdm' => 'HD1', 'morning' => 'HD1',
        'firsthalf' => 'HD1', 'fh' => 'HD1', 'halfdaymorning' => 'HD1', 'hdmorning' => 'HD1', 'halfday1' => 'HD1',
        // half day, afternoon session (the second half): the half-day mark to shift end
        'hd2' => 'HD2', 'hda' => 'HD2', 'afternoon' => 'HD2', 'secondhalf' => 'HD2', 'sh' => 'HD2',
        'halfdayafternoon' => 'HD2', 'hdafternoon' => 'HD2', 'halfday2' => 'HD2',
        'a' => 'A', 'absent' => 'A', 'ab' => 'A',
    ];

    /** How the half-day sessions read on screen. */
    public const SESSIONS = ['first' => 'morning', 'second' => 'afternoon'];

    /** Longest session an upload may create, in hours. */
    protected const MAX_HOURS = 20;

    protected string $tz;

    /** shift times per company / shift id, worked out once */
    protected array $shiftTimes = [];

    public function __construct()
    {
        $this->tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
    }

    public function timezone(): string
    {
        return $this->tz;
    }

    // ---------------------------------------------------------------- periods

    /**
     * The periods that can be uploaded: the one before the running cycle, then the running one.
     *
     * @return array<string, PayPeriod>  key ("2026-11") => cycle
     */
    public function periods(): array
    {
        $current = PayPeriod::containing(Carbon::now($this->tz));
        $previous = $current->previous();

        return [$this->key($previous) => $previous, $this->key($current) => $current];
    }

    public function key(PayPeriod $cycle): string
    {
        return sprintf('%04d-%02d', $cycle->year, $cycle->month);
    }

    /** The period asked for, or the one before the running cycle (the one payroll is next run for). */
    public function period(?string $key): PayPeriod
    {
        $periods = $this->periods();

        return $periods[$key] ?? reset($periods);
    }

    /** The last day of the period that can be uploaded: its end, or today while it runs. */
    public function lastDay(PayPeriod $cycle): string
    {
        $today = Carbon::now($this->tz)->toDateString();
        $end = $cycle->end->toDateString();

        return $end < $today ? $end : $today;
    }

    // -------------------------------------------------------------- employees

    /**
     * Everyone who can have attendance in the period: on the books for some of it,
     * with an employee code (what the sheet matches on). Not the Super Admin login.
     *
     * @return \Illuminate\Support\Collection<string, object>  keyed by employee code (lower case)
     */
    public function employees(PayPeriod $cycle)
    {
        $start = $cycle->start->toDateString();
        $end = $cycle->end->toDateString();

        return DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::hidden())
            ->whereNotNull('ed.employee_card_id')->where('ed.employee_card_id', '!=', '')
            ->where(fn ($q) => $q->whereNull('ed.joining_date')->orWhereDate('ed.joining_date', '<=', $end))
            ->where(fn ($q) => $q->where('u.status', 'active')->orWhereDate('ed.last_date', '>=', $start))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.company_id', 'ed.employee_card_id as code', 'ed.joining_date', 'ed.last_date'])
            ->keyBy(fn ($e) => mb_strtolower(trim((string) $e->code)));
    }

    /**
     * Who already has a payslip for the period - their days in it are locked.
     * Any payslip that stands (draft, approved or paid) counts; a revoked one does not.
     *
     * @param  int[] $userIds
     * @return array<int, array<int, array{0: string, 1: string}>>  user => [[from, to], ...]
     */
    public function locks(array $userIds, PayPeriod $cycle): array
    {
        if ($userIds === []) {
            return [];
        }

        $locks = [];

        foreach (array_chunk($userIds, 1000) as $chunk) {
            $rows = DB::table('payslips')
                ->whereIn('user_id', $chunk)
                ->where('status', 'active')->whereNull('deleted_at')
                ->where(function ($q) use ($cycle) {
                    // by its dates; a payslip without them, by its period label
                    $q->where(fn ($d) => $d->whereNotNull('period_start')->whereNotNull('period_end')
                            ->whereDate('period_start', '<=', $cycle->end->toDateString())
                            ->whereDate('period_end', '>=', $cycle->start->toDateString()))
                        ->orWhere(fn ($d) => $d->whereNull('period_start')->where('period', $cycle->label));
                })
                ->get(['user_id', 'period_start', 'period_end']);

            foreach ($rows as $p) {
                $locks[(int) $p->user_id][] = [
                    $p->period_start ? substr((string) $p->period_start, 0, 10) : $cycle->start->toDateString(),
                    $p->period_end ? substr((string) $p->period_end, 0, 10) : $cycle->end->toDateString(),
                ];
            }
        }

        return $locks;
    }

    public static function isLocked(array $locks, int $userId, string $date): bool
    {
        foreach ($locks[$userId] ?? [] as [$from, $to]) {
            if ($date >= $from && $date <= $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attendance on record, by employee and day (the local date of the clock-in):
     * the first In, the last Out, whether it is marked a half day, and its rows.
     *
     * @param  int[] $userIds
     * @return array<int, array<string, array{in: string, out: ?string, open: bool, half: bool, ids: int[]}>>
     */
    public function onRecord(array $userIds, string $from, string $to): array
    {
        $out = [];

        if ($userIds === []) {
            return $out;
        }

        [$fromUtc] = $this->dayBoundsUtc($from);
        [, $toUtc] = $this->dayBoundsUtc($to);

        foreach (array_chunk($userIds, 500) as $chunk) {
            $rows = DB::table('attendances')->whereIn('user_id', $chunk)
                ->whereBetween('clock_in_time', [$fromUtc, $toUtc])
                ->orderBy('clock_in_time')
                ->get(['id', 'user_id', 'clock_in_time', 'clock_out_time', 'half_day', 'half_day_type']);

            foreach ($rows as $row) {
                $in = Carbon::parse($row->clock_in_time, 'UTC')->setTimezone($this->tz);
                $date = $in->toDateString();
                $day = &$out[(int) $row->user_id][$date];
                $day ??= ['in' => $in->format('H:i'), 'out' => null, 'out_at' => null, 'open' => false, 'half' => false, 'session' => null, 'ids' => []];
                $day['ids'][] = (int) $row->id;

                if ($row->half_day === 'yes') {
                    $day['half'] = true;
                    $day['session'] = $row->half_day_type === 'second_half' ? 'second' : 'first';
                }

                if ($row->clock_out_time) {
                    $leftAt = Carbon::parse($row->clock_out_time, 'UTC')->setTimezone($this->tz);

                    if ($day['out_at'] === null || $leftAt->gt($day['out_at'])) {
                        $day['out_at'] = $leftAt;
                        $day['out'] = $leftAt->format('H:i');
                    }
                } else {
                    $day['open'] = true;
                }

                unset($day);
            }
        }

        return $out;
    }

    /**
     * The shift a P / HD without times follows on a day: the employee's own; on a day
     * off (or a shift with no hours) the company's default shift.
     *
     * @return array{id: ?int, name: string, start: string, end: string, half: string, full_minutes: int}|null
     */
    public function shiftFor(int $userId, ?int $companyId, string $date): ?array
    {
        $shift = ShiftResolver::shared($companyId)->shiftOn($userId, $date);

        if (!$this->hasHours($shift) || !ShiftResolver::worksOn($shift, $date)) {
            $shift = $this->defaultShift($companyId);
        }

        if (!$this->hasHours($shift)) {
            return null;
        }

        return $this->times($shift, $companyId);
    }

    /** The company's default shift (the General Shift), as P / HD use it. */
    public function generalShift(?int $companyId): ?array
    {
        $shift = $this->defaultShift($companyId);

        return $this->hasHours($shift) ? $this->times($shift, $companyId) : null;
    }

    protected function times(object $shift, ?int $companyId): array
    {
        $key = (int) $companyId . '|' . ($shift->id ?? 'x') . '|' . $shift->office_start_time . '|' . $shift->office_end_time;

        return $this->shiftTimes[$key] ??= (function () use ($shift) {
            $start = substr((string) $shift->office_start_time, 0, 5);
            $end = substr((string) $shift->office_end_time, 0, 5);
            $length = $this->minutesBetween($start, $end);
            $mark = $shift->halfday_mark_time ?? null;
            $half = $mark && $mark !== '00:00:00' ? substr((string) $mark, 0, 5) : null;

            // the half-day mark must sit inside the shift; otherwise half way through it
            if (!$half || $this->minutesBetween($start, $half) <= 0 || $this->minutesBetween($start, $half) >= $length) {
                $half = $this->addMinutes($start, intdiv($length, 2));
            }

            $full = $shift->min_working_hours_full_day !== null && (float) $shift->min_working_hours_full_day > 0
                ? (int) round((float) $shift->min_working_hours_full_day * 60) : $length;

            return ['id' => is_numeric($shift->id ?? null) ? (int) $shift->id : null, 'name' => (string) ($shift->shift_name ?? 'Shift'),
                'start' => $start, 'end' => $end, 'half' => $half, 'full_minutes' => min($full, $length)];
        })();
    }

    protected function hasHours(?object $shift): bool
    {
        return $shift && $shift->office_start_time && $shift->office_end_time
            && substr((string) $shift->office_start_time, 0, 5) !== substr((string) $shift->office_end_time, 0, 5);
    }

    protected function defaultShift(?int $companyId): ?object
    {
        $id = DB::table('attendance_settings')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->value('default_employee_shift');
        $shift = $id ? DB::table('employee_shifts')->find($id) : null;

        return $shift ?: DB::table('employee_shifts')->where('shift_name', 'General Shift')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->first();
    }

    // ------------------------------------------------------------------ sheet

    /**
     * The sheet to download: every employee, every day of the period so far, with what is
     * on record filled in (P or HD and the times) and a note on the day.
     *
     * @return array<int, array<string, ?string>>
     */
    public function sheet(PayPeriod $cycle): array
    {
        $employees = $this->employees($cycle);
        $ids = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $from = $cycle->start->toDateString();
        $to = $this->lastDay($cycle);
        $record = $this->onRecord($ids, $from, $to);
        $locks = $this->locks($ids, $cycle);
        $holidays = DB::table('holidays')->whereBetween('date', [$from, $to])->pluck('occassion', 'date')
            ->mapWithKeys(fn ($name, $date) => [substr((string) $date, 0, 10) => $name])->all();
        $leaves = [];

        foreach (DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->whereIn('l.user_id', $ids)->where('l.status', 'approved')->whereBetween('l.leave_date', [$from, $to])
            ->get(['l.user_id', 'l.leave_date', 'l.duration', 't.type_name']) as $l) {
            $leaves[(int) $l->user_id][substr((string) $l->leave_date, 0, 10)] = trim(($l->type_name ?: 'Leave') . ($l->duration === 'half day' ? ' (half day)' : ' leave'));
        }

        $rows = [];

        foreach ($employees as $e) {
            $resolver = ShiftResolver::shared((int) $e->company_id ?: null);

            for ($d = Carbon::parse($from); $d->toDateString() <= $to; $d->addDay()) {
                $date = $d->toDateString();
                $day = $record[(int) $e->id][$date] ?? null;
                $note = match (true) {
                    self::isLocked($locks, (int) $e->id, $date) => 'Locked: payroll generated',
                    $e->joining_date && $date < substr((string) $e->joining_date, 0, 10) => 'Before joining',
                    $e->last_date && $date > substr((string) $e->last_date, 0, 10) => 'After leaving',
                    isset($leaves[(int) $e->id][$date]) => $leaves[(int) $e->id][$date],
                    isset($holidays[$date]) => 'Holiday: ' . $holidays[$date],
                    !ShiftResolver::worksOn($resolver->shiftOn((int) $e->id, $date), $date) => 'Week off',
                    $day !== null && $day['out'] === null => 'No clock-out',
                    default => null,
                };

                $rows[] = [
                    'Employee Code' => (string) $e->code,
                    'Name' => $e->name,
                    'Date' => $d->format('d-m-Y'),
                    // a day with no clock-out stays blank: P would close it at the shift's end
                    'Attendance' => $day && $day['out'] !== null ? $this->markOf($day) : null,
                    'In' => $day['in'] ?? null,
                    'Out' => $day['out'] ?? null,
                    'Note' => $note,
                ];
            }
        }

        return $rows;
    }

    /**
     * A small sample of the sheet, each way a day can be filled, on real employee codes and
     * working days of the period (so it can be uploaded as it is to try the page out).
     *
     * @return array<int, array<string, ?string>>
     */
    public function sample(PayPeriod $cycle): array
    {
        $employees = $this->employees($cycle);
        $locks = $this->locks($employees->pluck('id')->map(fn ($id) => (int) $id)->all(), $cycle);
        $to = $this->lastDay($cycle);
        $picked = [];

        foreach ($employees as $e) {
            if (isset($locks[(int) $e->id]) || ($e->joining_date && substr((string) $e->joining_date, 0, 10) > $cycle->start->toDateString())) {
                continue;
            }

            $resolver = ShiftResolver::shared((int) $e->company_id ?: null);
            $days = [];

            for ($d = $cycle->start->copy(); $d->toDateString() <= $to && count($days) < 4; $d->addDay()) {
                if (ShiftResolver::worksOn($resolver->shiftOn((int) $e->id, $d->toDateString()), $d->toDateString())) {
                    $days[] = $d->format('d-m-Y');
                }
            }

            if (count($days) === 4) {
                $picked[] = [$e, $days];
            }

            if (count($picked) === 2) {
                break;
            }
        }

        $shift = $this->generalShift($picked[0][0]->company_id ?? null) ?? ['start' => '09:30', 'end' => '18:30', 'half' => '14:00', 'name' => 'General Shift'];
        $examples = [
            ['P', null, null, 'Example: P without times = ' . $shift['start'] . ' to ' . $shift['end'] . ' (' . $shift['name'] . ')'],
            ['P', '10:05', '19:10', 'Example: P with your own times = those times'],
            ['HD1', null, null, 'Example: HD1 = half day, morning: ' . $shift['start'] . ' to ' . $shift['half']],
            ['A', null, null, 'Example: A = absent, the day\'s attendance is taken off'],
            ['HD2', null, null, 'Example: HD2 = half day, afternoon: ' . $shift['half'] . ' to ' . $shift['end']],
            [null, '21:00', '06:00', 'Example: only In / Out = those times (Out earlier than In is the next morning)'],
            ['P', '09:30', null, 'Example: P with only In = Out at the shift\'s end'],
            [null, null, null, 'Example: everything blank = the day is left as it is'],
        ];
        $rows = [];

        foreach ($picked as $i => [$e, $days]) {
            foreach ($days as $j => $date) {
                [$mark, $in, $out, $note] = $examples[$i * 4 + $j];
                $rows[] = ['Employee Code' => (string) $e->code, 'Name' => $i === 0 ? $e->name : null, 'Date' => $date,
                    'Attendance' => $mark, 'In' => $in, 'Out' => $out, 'Note' => $note];
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------- preview

    /**
     * Read an uploaded sheet against the period and what is on record. Nothing is written.
     *
     * @param  array<int, array<int, mixed>> $rows  the sheet's rows, headings first
     */
    public function preview(array $rows, PayPeriod $cycle): array
    {
        $result = ['period' => $this->key($cycle), 'label' => $cycle->describe(), 'changes' => [], 'problems' => [],
            'summary' => ['new' => 0, 'changed' => 0, 'absent' => 0, 'unchanged' => 0, 'locked' => 0, 'problems' => 0, 'blank' => 0]];

        $columns = $this->columns($rows[0] ?? []);

        if (!$columns) {
            $result['error'] = 'The first row of the sheet must have the headings: Employee Code, Date, and Attendance or In / Out.'
                . ' Download the sheet on this page and fill that in.';

            return $result;
        }

        $employees = $this->employees($cycle);
        $ids = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $from = $cycle->start->toDateString();
        $to = $this->lastDay($cycle);
        $record = $this->onRecord($ids, $from, $to);
        $locks = $this->locks($ids, $cycle);
        $seen = [];

        $problem = function (int $line, ?string $code, ?string $date, string $message) use (&$result) {
            $result['problems'][] = ['line' => $line, 'code' => $code, 'date' => $date, 'message' => $message];
            $result['summary']['problems']++;
        };

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $line = $index + 1;
            $cell = fn (string $key) => $columns[$key] !== null ? ($row[$columns[$key]] ?? null) : null;
            $codeCell = trim((string) $cell('code'));
            $dateCell = $cell('date');
            $markCell = $cell('mark');
            $inCell = $cell('in');
            $outCell = $cell('out');

            if ($codeCell === '' && $this->blank($dateCell) && $this->blank($markCell) && $this->blank($inCell) && $this->blank($outCell)) {
                continue;   // an empty line
            }

            if ($codeCell === '') {
                $problem($line, null, null, 'no employee code');
                continue;
            }

            $employee = $employees[mb_strtolower($codeCell)] ?? null;

            if (!$employee) {
                $problem($line, $codeCell, null, 'no employee with code ' . $codeCell . ' for this period');
                continue;
            }

            $date = $this->date($dateCell);

            if ($date === null) {
                $problem($line, $codeCell, null, 'the date "' . $this->shown($dateCell) . '" could not be read (use DD-MM-YYYY)');
                continue;
            }

            $shownDate = Carbon::parse($date)->format('d-m-Y');

            if ($date < $from || $date > $cycle->end->toDateString()) {
                $problem($line, $codeCell, $shownDate, 'not in the period ' . $cycle->describe());
                continue;
            }

            if ($date > $to) {
                $problem($line, $codeCell, $shownDate, 'this day has not happened yet');
                continue;
            }

            $userId = (int) $employee->id;

            if (isset($seen[$userId][$date])) {
                $problem($line, $codeCell, $shownDate, 'this employee and day are already on line ' . $seen[$userId][$date]);
                continue;
            }

            $seen[$userId][$date] = $line;

            $mark = $this->mark($markCell);
            $in = $this->time($inCell);
            $out = $this->time($outCell);

            if ($mark === false) {
                $problem($line, $codeCell, $shownDate, 'Attendance "' . $this->shown($markCell) . '" is not P, HD1, HD2 or A');
                continue;
            }

            if ($in === false || $out === false) {
                $problem($line, $codeCell, $shownDate, 'the time "' . $this->shown($in === false ? $inCell : $outCell)
                    . '" could not be read (use HH:MM, e.g. 09:30 or 18:45)');
                continue;
            }

            $current = $record[$userId][$date] ?? null;
            $plan = $this->plan($mark, $in, $out, $current, $userId, (int) $employee->company_id ?: null, $date);

            if ($plan['state'] === 'unchanged' || $plan['state'] === 'blank') {
                $result['summary'][$plan['state']]++;
                continue;
            }

            if (self::isLocked($locks, $userId, $date)) {
                $result['summary']['locked']++;
                $problem($line, $codeCell, $shownDate, 'payroll for ' . $cycle->describe() . ' is already generated for '
                    . $employee->name . ', so this day cannot be changed');
                continue;
            }

            if ($plan['state'] === 'problem') {
                $problem($line, $codeCell, $shownDate, $plan['message']);
                continue;
            }

            $result['summary'][$plan['state']]++;
            $result['changes'][] = $plan + [
                'line' => $line, 'user_id' => $userId, 'company_id' => (int) $employee->company_id,
                'code' => (string) $employee->code, 'name' => $employee->name, 'date' => $date,
                'from' => $current ? $this->markOf($current) . ' ' . $current['in'] . '–' . ($current['out'] ?? 'no clock-out')
                    . (count($current['ids']) > 1 ? ' (' . count($current['ids']) . ' sessions)' : '') : null,
            ];
        }

        $result['grid'] = $this->grid($result['changes'], $cycle, $record, $employees);

        return $result;
    }

    /**
     * The upload as the Attendance Register will show it, for the people it changes:
     * every day of the period after saving, the changed days marked.
     *
     * Day codes: P present, HD half day, A absent (taken off), O clocked in with no
     * clock-out, W week off, H holiday, L leave, "" nothing on record.
     *
     * @return array{dates: string[], rows: array<int, array{name: string, code: string, days: array<string, array{0: string, 1: bool, 2: ?string}>, totals: array<string, float>}>}
     */
    protected function grid(array $changes, PayPeriod $cycle, array $record, $employees): array
    {
        $from = $cycle->start->toDateString();
        $to = $this->lastDay($cycle);
        $dates = [];

        for ($d = Carbon::parse($from); $d->toDateString() <= $to; $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $byUser = [];

        foreach ($changes as $c) {
            $byUser[(int) $c['user_id']][$c['date']] = $c;
        }

        if ($byUser === []) {
            return ['dates' => $dates, 'rows' => []];
        }

        $holidays = DB::table('holidays')->whereBetween('date', [$from, $to])->pluck('occassion', 'date')
            ->mapWithKeys(fn ($name, $date) => [substr((string) $date, 0, 10) => $name])->all();
        $leaves = [];

        foreach (DB::table('leaves')->whereIn('user_id', array_keys($byUser))->where('status', 'approved')
            ->whereBetween('leave_date', [$from, $to])->get(['user_id', 'leave_date']) as $l) {
            $leaves[(int) $l->user_id][substr((string) $l->leave_date, 0, 10)] = true;
        }

        $byId = $employees->keyBy('id');
        $rows = [];

        foreach ($byUser as $userId => $changed) {
            $employee = $byId[$userId] ?? null;
            $resolver = ShiftResolver::shared((int) ($employee->company_id ?? 0) ?: null);
            $days = [];
            $totals = ['P' => 0, 'HD' => 0, 'absent' => 0, 'changed' => count($changed)];

            foreach ($dates as $date) {
                if (isset($changed[$date])) {
                    $c = $changed[$date];
                    $code = $c['state'] === 'absent' ? 'A' : (!empty($c['half']) ? 'HD' : 'P');
                    $tip = $c['state'] === 'absent' ? 'absent: ' . $c['from'] . ' taken off'
                        : $this->markLabel($c['mark']) . ' ' . $c['in'] . '–' . $c['out'] . ($c['from'] ? ' (was ' . $c['from'] . ')' : ' (new)');
                    $days[$date] = [$code, true, $tip];
                } else {
                    $day = $record[$userId][$date] ?? null;
                    $code = match (true) {
                        $day !== null && $day['out'] === null => 'O',
                        $day !== null => $day['half'] ? 'HD' : 'P',
                        isset($leaves[$userId][$date]) => 'L',
                        isset($holidays[$date]) => 'H',
                        !ShiftResolver::worksOn($resolver->shiftOn($userId, $date), $date) => 'W',
                        default => '',
                    };
                    $days[$date] = [$code, false, $day ? $day['in'] . '–' . ($day['out'] ?? 'no clock-out') . ' (as on record)' : null];
                }

                match ($days[$date][0]) {
                    'P', 'O' => $totals['P']++,
                    'HD' => $totals['HD']++,
                    'A', '' => $totals['absent']++,
                    default => null,
                };
            }

            $rows[] = ['name' => $employee->name ?? ('#' . $userId), 'code' => (string) ($employee->code ?? ''), 'days' => $days, 'totals' => $totals];
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['dates' => $dates, 'rows' => $rows];
    }

    /**
     * What one line means for its day.
     *
     * @return array{state: string, message?: string, in?: string, out?: string, half?: bool, overnight?: bool, minutes?: int, shift?: ?array, filled?: string}
     */
    protected function plan(?string $mark, ?string $in, ?string $out, ?array $current, int $userId, ?int $companyId, string $date): array
    {
        // Absent: whatever is on record goes.
        if ($mark === 'A') {
            return $current ? ['state' => 'absent', 'half' => false] : ['state' => 'unchanged'];
        }

        $shift = null;
        $filled = [];

        if ($mark === null) {
            // no mark: the times, as before; a blank cell keeps what is on record
            if ($in === null && $out === null) {
                return ['state' => $current ? 'unchanged' : 'blank'];
            }

            $newIn = $in ?? ($current['in'] ?? null);
            $newOut = $out ?? ($current['out'] ?? null);
            $half = (bool) ($current['half'] ?? false);
            $session = $current['session'] ?? null;
        } else {
            $half = $mark === 'HD1' || $mark === 'HD2';
            $session = $half ? ($mark === 'HD2' ? 'second' : 'first') : null;
            $shift = $this->shiftFor($userId, $companyId, $date);
            // the session's own times: morning = start to the half-day mark, afternoon = the mark to the end
            $baseIn = $session === 'second' ? ($shift['half'] ?? null) : ($shift['start'] ?? null);
            $baseOut = $session === 'first' ? ($shift['half'] ?? null) : ($shift['end'] ?? null);

            if ($in === null && $out === null) {
                if ($current && !$half && !$current['half'] && $current['out'] !== null) {
                    return ['state' => 'unchanged'];   // present on record already, with its own times
                }

                if (!$shift && !$current) {
                    return ['state' => 'problem', 'message' => 'no shift with timings for this employee and day: give In and Out'];
                }

                if ($current && !$half && !$current['half']) {
                    // present, clocked in but never out: out at the shift's end
                    $newIn = $current['in'];
                    $newOut = $shift['end'] ?? null;
                    $filled[] = 'Out';
                } else {
                    $newIn = $baseIn;
                    $newOut = $baseOut;
                    $filled = ['In', 'Out'];
                }
            } else {
                $newIn = $in ?? ($current && !$half ? $current['in'] : $baseIn);
                $newOut = $out ?? ($current && $current['out'] !== null && !$half ? $current['out'] : $baseOut);

                if ($in === null) { $filled[] = 'In'; }
                if ($out === null) { $filled[] = 'Out'; }
            }
        }

        if ($current && $newIn === $current['in'] && $newOut === $current['out'] && $half === $current['half']
            && (!$half || $session === $current['session'])) {
            return ['state' => 'unchanged'];
        }

        if ($newIn === null || $newOut === null) {
            return ['state' => 'problem', 'message' => $current
                ? 'the day on record has no clock-out, so an Out time (or P) is needed'
                : 'give P, HD1, HD2 or A, or both In and Out, for a day with no attendance on record'];
        }

        if ($newIn === $newOut) {
            return ['state' => 'problem', 'message' => 'In and Out are the same time'];
        }

        $overnight = $newOut < $newIn;
        $minutes = $this->minutesBetween($newIn, $newOut);

        if ($minutes > self::MAX_HOURS * 60) {
            return ['state' => 'problem', 'message' => 'the session would be longer than ' . self::MAX_HOURS . ' hours'];
        }

        // A half day given a full day's hours would be paid as a full day.
        $shift ??= $half ? $this->shiftFor($userId, $companyId, $date) : null;
        if ($half && $shift && $minutes >= $shift['full_minutes']) {
            return ['state' => 'problem', 'message' => 'marked a half day but ' . $newIn . '–' . $newOut . ' is a full day\'s hours: clear In and Out for '
                . ($session === 'second' ? $shift['half'] . '–' . $shift['end'] . ' (afternoon)' : $shift['start'] . '–' . $shift['half'] . ' (morning)')
                . ', or give the half day\'s times'];
        }

        $markCode = $half ? ($session === 'second' ? 'HD2' : 'HD1') : 'P';

        return ['state' => $current ? 'changed' : 'new', 'in' => $newIn, 'out' => $newOut, 'half' => $half, 'session' => $session,
            'overnight' => $overnight, 'minutes' => $minutes, 'mark' => $markCode,
            'shift' => $shift, 'filled' => $filled ? implode(' and ', $filled) . ' from ' . ($shift['name'] ?? 'the shift') : null];
    }

    // ------------------------------------------------------------------ apply

    /**
     * Write what the preview showed, checking the locks again first.
     *
     * @return array{created: int, replaced: int, removed: int, skipped: array<int, string>, import_id: ?int}
     */
    public function apply(array $preview, PayPeriod $cycle, int $by, ?string $file = null): array
    {
        $changes = $preview['changes'] ?? [];
        $ids = array_values(array_unique(array_map(fn ($c) => (int) $c['user_id'], $changes)));
        $locks = $this->locks($ids, $cycle);   // payroll may have been generated since the preview
        $to = $this->lastDay($cycle);
        $counts = ['created' => 0, 'replaced' => 0, 'removed' => 0];
        $saved = [];   // what was written, kept with the upload so it can be read later
        $skipped = [];
        $displaced = [];
        $touched = [];
        $locations = [];
        $rules = [];

        foreach ($changes as $c) {
            $userId = (int) $c['user_id'];
            $date = (string) $c['date'];
            $who = $c['name'] . ' ' . Carbon::parse($date)->format('d-m-Y');

            if (self::isLocked($locks, $userId, $date)) {
                $skipped[] = $who . ': payroll generated meanwhile';
                continue;
            }

            if ($date < $cycle->start->toDateString() || $date > $to) {
                $skipped[] = $who . ': outside the period';
                continue;
            }

            [$dayFrom, $dayTo] = $this->dayBoundsUtc($date);
            $companyId = (int) $c['company_id'] ?: null;
            $row = null;

            if ($c['state'] !== 'absent') {
                $inLocal = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $c['in'], $this->tz);
                $outLocal = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $c['out'], $this->tz);

                if (!empty($c['overnight'])) {
                    $outLocal->addDay();
                }

                $shift = ShiftResolver::shared($companyId)->shiftOn($userId, $date);
                $shiftId = $c['shift']['id'] ?? (is_numeric($shift->id ?? null) ? (int) $shift->id : null);
                $shiftStart = $c['shift']['start'] ?? ($shift->office_start_time ?? null);
                $shiftEnd = $c['shift']['end'] ?? ($shift->office_end_time ?? null);
                $late = 'no';

                try {
                    $dayRules = $rules[(int) $companyId] ??= (WorkRules::forCompany($companyId, $this->tz) ?: false);

                    // an afternoon half day starts at the half-day mark on purpose: not late
                    $afternoon = !empty($c['half']) && ($c['session'] ?? 'first') === 'second';

                    if (!$afternoon && $dayRules && $shift && $dayRules->appliesOn($date) && $dayRules->isLate($inLocal->getTimestamp(), $shift, $date)) {
                        $late = 'yes';
                    }
                } catch (\Throwable) {
                    // the late mark is information only; payroll works lateness out itself
                }

                $location = $locations[(int) $companyId] ??= DB::table('company_addresses')
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->orderByDesc('is_default')->value('id');

                // As HR's Add Attendance writes it (AttendanceController::store), in UTC like every clock-in.
                $row = [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'location_id' => $location,
                    'clock_in_time' => $inLocal->copy()->utc()->format('Y-m-d H:i:s'),
                    'clock_out_time' => $outLocal->copy()->utc()->format('Y-m-d H:i:s'),
                    'clock_in_type' => 'import',
                    'clock_out_type' => 'import',
                    'auto_clock_out' => 0,
                    'work_from_type' => 'office',
                    'working_from' => 'office',
                    'clock_out_time_work_from_type' => 'office',
                    'clock_out_time_location_id' => $location,
                    'employee_shift_id' => $shiftId,
                    'shift_start_time' => $shiftStart ? $date . ' ' . substr((string) $shiftStart, 0, 5) . ':00' : null,
                    'shift_end_time' => $shiftEnd ? Carbon::parse($date . ' ' . substr((string) $shiftEnd, 0, 5), $this->tz)
                        ->addDays(substr((string) $shiftEnd, 0, 5) <= substr((string) $shiftStart, 0, 5) ? 1 : 0)->format('Y-m-d H:i:s') : null,
                    'late' => $late,
                    'half_day' => !empty($c['half']) ? 'yes' : 'no',
                    'half_day_type' => !empty($c['half']) ? (($c['session'] ?? 'first') === 'second' ? 'second_half' : 'first_half') : null,
                    'overwrite_attendance' => 'no',
                    'attendance_remark' => mb_substr('Uploaded by HR' . ($c['state'] === 'changed' ? ' (was ' . $c['from'] . ')' : ''), 0, 191),
                    'added_by' => $by,
                    'last_updated_by' => $by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::transaction(function () use ($userId, $dayFrom, $dayTo, $row, $c, &$counts, &$displaced) {
                $old = DB::table('attendances')->where('user_id', $userId)
                    ->whereBetween('clock_in_time', [$dayFrom, $dayTo])->lockForUpdate()->get();

                foreach ($old as $o) {
                    $displaced[] = (array) $o;
                }

                if ($old->isNotEmpty()) {
                    DB::table('attendances')->whereIn('id', $old->pluck('id'))->delete();
                }

                if ($row) {
                    DB::table('attendances')->insert($row);
                    $counts[$old->isNotEmpty() ? 'replaced' : 'created']++;
                } elseif ($old->isNotEmpty()) {
                    $counts['removed']++;
                }
            });

            $touched[] = [$userId, $date];
            $saved[] = ['user_id' => $userId, 'code' => $c['code'], 'name' => $c['name'], 'date' => $date, 'state' => $c['state'],
                'mark' => $c['state'] === 'absent' ? 'A' : ($c['mark'] ?? 'P'), 'in' => $c['in'] ?? null, 'out' => $c['out'] ?? null,
                'overnight' => !empty($c['overnight']), 'from' => $c['from'] ?? null, 'line' => $c['line'] ?? null];
        }

        $importId = null;

        if (array_sum($counts) > 0) {
            $importId = DB::table('attendance_imports')->insertGetId([
                'company_id' => DB::table('companies')->value('id'),
                'period' => $this->key($cycle),
                'period_label' => $cycle->describe(),
                'file_name' => $file ? mb_substr($file, 0, 191) : null,
                'days_created' => $counts['created'],
                'days_replaced' => $counts['replaced'] + $counts['removed'],
                'days_skipped' => count($skipped),
                'replaced_rows' => json_encode($displaced),
                'changes' => json_encode($saved),
                'created_by' => $by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The Daily Review judges these days again, as it does after any attendance change.
        $review = new DailyAttendanceReview();
        $today = Carbon::now($this->tz)->toDateString();

        foreach ($touched as [$userId, $date]) {
            if ($date >= $today) {
                continue;
            }

            try {
                $review->refresh($userId, Carbon::parse($date, $this->tz)->startOfDay());
            } catch (\Throwable $e) {
                Log::warning('Daily Review refresh after attendance upload failed', ['user' => $userId, 'date' => $date, 'error' => $e->getMessage()]);
            }
        }

        ShiftResolver::reset();

        return $counts + ['skipped' => $skipped, 'import_id' => $importId];
    }

    /**
     * One upload as it was saved: the log, the days it wrote (P / HD1 / HD2 / A with their times and
     * what they were before), and a register of them (employees x the period's days).
     *
     * An upload saved before the days were kept with it is rebuilt from attendance: its Excel-upload
     * rows written in the upload's minutes, and the rows it took off with nothing put back (absent).
     *
     * @return array{import: object, days: array, rebuilt: bool, grid: array}|null
     */
    public function upload(int $id): ?array
    {
        $import = DB::table('attendance_imports as i')->leftJoin('users as u', 'u.id', '=', 'i.created_by')
            ->where('i.id', $id)->first(['i.*', 'u.name as by_name']);

        if (!$import) {
            return null;
        }

        $days = isset($import->changes) && $import->changes ? (json_decode($import->changes, true) ?: []) : null;
        $rebuilt = $days === null;

        if ($rebuilt) {
            $days = $this->rebuild($import);
        }

        $periods = $this->allPeriods($import->period);
        usort($days, fn ($a, $b) => [$a['name'], $a['date']] <=> [$b['name'], $b['date']]);

        // the register: one row per employee, the period's days across
        $dates = [];
        if ($periods) {
            for ($d = $periods->start->copy(); $d->lte($periods->end); $d->addDay()) {
                $dates[] = $d->toDateString();
            }
        }

        $rows = [];
        foreach ($days as $day) {
            $key = $day['user_id'];
            $rows[$key] ??= ['name' => $day['name'], 'code' => $day['code'], 'days' => [], 'totals' => ['P' => 0, 'HD' => 0, 'A' => 0]];
            $rows[$key]['days'][$day['date']] = $day;
            $mark = $day['mark'] === 'A' ? 'A' : ($day['mark'] === 'P' ? 'P' : 'HD');
            $rows[$key]['totals'][$mark]++;
        }

        return ['import' => $import, 'days' => $days, 'rebuilt' => $rebuilt, 'grid' => ['dates' => $dates, 'rows' => array_values($rows)]];
    }

    /** The pay cycle a stored period key ("2026-11") stands for. */
    protected function allPeriods(?string $key): ?PayPeriod
    {
        if (!$key || !preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            return null;
        }

        return PayPeriod::forMonth((int) $m[2], (int) $m[1]);
    }

    /** An older upload's days, from attendance and the rows it replaced. */
    protected function rebuild(object $import): array
    {
        $tz = $this->tz;
        $at = Carbon::parse($import->created_at);
        $written = DB::table('attendances as a')->join('employee_details as ed', 'ed.user_id', '=', 'a.user_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.clock_in_type', 'import')->where('a.added_by', $import->created_by)
            ->whereBetween('a.created_at', [$at->copy()->subMinutes(30), $at->copy()->addMinute()])
            ->get(['a.user_id', 'a.clock_in_time', 'a.clock_out_time', 'a.half_day', 'a.half_day_type', 'a.attendance_remark', 'ed.employee_card_id', 'u.name']);
        $days = [];

        foreach ($written as $a) {
            $in = Carbon::parse($a->clock_in_time, 'UTC')->setTimezone($tz);
            $out = $a->clock_out_time ? Carbon::parse($a->clock_out_time, 'UTC')->setTimezone($tz) : null;
            $from = preg_match('/\(was (.+)\)$/', (string) $a->attendance_remark, $m) ? $m[1] : null;
            $days[$a->user_id . '|' . $in->toDateString()] = [
                'user_id' => (int) $a->user_id, 'code' => (string) $a->employee_card_id, 'name' => $a->name, 'date' => $in->toDateString(),
                'state' => $from ? 'changed' : 'new',
                'mark' => $a->half_day === 'yes' ? ($a->half_day_type === 'second_half' ? 'HD2' : 'HD1') : 'P',
                'in' => $in->format('H:i'), 'out' => $out?->format('H:i'), 'overnight' => $out && $out->toDateString() !== $in->toDateString(),
                'from' => $from, 'line' => null,
            ];
        }

        // taken off with nothing put back in its place: marked absent
        foreach (json_decode((string) $import->replaced_rows, true) ?: [] as $old) {
            $in = Carbon::parse($old['clock_in_time'], 'UTC')->setTimezone($tz);
            $key = $old['user_id'] . '|' . $in->toDateString();

            if (isset($days[$key])) {
                continue;
            }

            $who = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')->where('u.id', $old['user_id'])
                ->first(['u.name', 'ed.employee_card_id']);
            $outOld = $old['clock_out_time'] ? Carbon::parse($old['clock_out_time'], 'UTC')->setTimezone($tz)->format('H:i') : 'no clock-out';
            $days[$key] = ['user_id' => (int) $old['user_id'], 'code' => (string) ($who->employee_card_id ?? ''), 'name' => $who->name ?? '—',
                'date' => $in->toDateString(), 'state' => 'absent', 'mark' => 'A', 'in' => null, 'out' => null, 'overnight' => false,
                'from' => $in->format('H:i') . '–' . $outOld, 'line' => null];
        }

        return array_values($days);
    }

    /** The latest uploads, for the page. */
    public function history(int $limit = 8)
    {
        return DB::table('attendance_imports as i')->leftJoin('users as u', 'u.id', '=', 'i.created_by')
            ->orderByDesc('i.id')->limit($limit)
            ->get(['i.id', 'i.period_label', 'i.file_name', 'i.days_created', 'i.days_replaced', 'i.days_skipped', 'i.created_at', 'u.name as by_name']);
    }

    // ---------------------------------------------------------------- reading

    /**
     * Where each heading is. Employee Code and Date are needed, and Attendance or In / Out;
     * Name and Note are never read. Null when the sheet cannot be read.
     *
     * @return array<string, ?int>|null
     */
    protected function columns(array $headings): ?array
    {
        $names = [
            'code' => ['employeecode', 'empcode', 'code', 'employeeid', 'empid', 'employeeno', 'empno'],
            'date' => ['date', 'day', 'attendancedate'],
            'mark' => ['attendance', 'status', 'mark', 'pahd', 'presentabsent'],
            'in' => ['in', 'intime', 'clockin', 'clockintime', 'timein', 'checkin'],
            'out' => ['out', 'outtime', 'clockout', 'clockouttime', 'timeout', 'checkout'],
        ];
        $found = array_fill_keys(array_keys($names), null);

        foreach ($headings as $index => $heading) {
            $key = preg_replace('/[^a-z]/', '', mb_strtolower((string) $heading));

            foreach ($names as $field => $options) {
                if ($found[$field] === null && in_array($key, $options, true)) {
                    $found[$field] = $index;
                }
            }
        }

        if ($found['code'] === null || $found['date'] === null || ($found['mark'] === null && ($found['in'] === null || $found['out'] === null))) {
            return null;
        }

        return $found;
    }

    protected function blank($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    protected function shown($value): string
    {
        return mb_substr(trim((string) $value), 0, 30);
    }

    /** The Attendance cell: 'P', 'HD1', 'HD2', 'A', null when blank, false when not one of them. */
    public function mark($value): string|false|null
    {
        if ($this->blank($value)) {
            return null;
        }

        $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));

        return self::MARKS[$key] ?? false;
    }

    /** A day on record as its mark: P, HD1 (morning half) or HD2 (afternoon half). */
    protected function markOf(array $day): string
    {
        return $day['half'] ? (($day['session'] ?? 'first') === 'second' ? 'HD2' : 'HD1') : 'P';
    }

    /** A mark as it reads: "HD1 (morning)". */
    public function markLabel(string $mark): string
    {
        return match ($mark) {
            'HD1' => 'HD1 (morning)',
            'HD2' => 'HD2 (afternoon)',
            default => $mark,
        };
    }

    /** A date cell as 'Y-m-d', or null. Excel dates arrive as day numbers. */
    public function date($value): ?string
    {
        if ($this->blank($value)) {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $text = trim((string) $value);

        foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd-M-Y', 'd M Y', 'd-M-y', 'd/m/y', 'd-m-y', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $text);
            } catch (\Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $text) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /**
     * A time cell as 'H:i': null when blank, false when it cannot be read.
     * Excel times arrive as a share of a day (0.395833 = 09:30).
     */
    public function time($value): string|false|null
    {
        if ($this->blank($value)) {
            return null;
        }

        // 18.45 or 9 typed into a cell Excel keeps as a number: hours (and minutes after the dot)
        if (is_numeric($value) && (float) $value >= 1 && (float) $value < 24
            && preg_match('/^\d{1,2}(?:\.(\d{1,2}))?$/', (string) $value, $hm)) {
            $minute = isset($hm[1]) ? (int) str_pad($hm[1], 2, '0') : 0;

            return $minute < 60 ? sprintf('%02d:%02d', (int) $value, $minute) : false;
        }

        // Excel's own times are a share of a day (0.395833 = 09:30); a date and time, the part after the day
        if (is_numeric($value) && ((float) $value < 1 || (float) $value > 20000)) {
            $fraction = fmod((float) $value, 1.0);
            $minutes = (int) round($fraction * 1440);

            if ($minutes >= 1440) {
                $minutes = 0;
            }

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        $text = trim((string) $value);

        // a date and a time together: the time part
        if (preg_match('/\d{1,2}[:.]\d{2}(?::\d{2})?\s*([AaPp]\.?[Mm]\.?)?$/', $text, $m)) {
            $text = $m[0];
        }

        if (!preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?\s*([AaPp])?\.?[Mm]?\.?$/', $text, $m)) {
            return false;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $half = isset($m[3]) ? strtolower($m[3]) : '';

        if ($half !== '') {
            if ($hour < 1 || $hour > 12) {
                return false;
            }
            $hour = $hour % 12 + ($half === 'p' ? 12 : 0);
        }

        if ($hour > 23 || $minute > 59) {
            return false;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /** Minutes from one HH:MM to another, past midnight when the second is earlier. */
    protected function minutesBetween(string $from, string $to): int
    {
        [$fh, $fm] = array_map('intval', explode(':', $from));
        [$th, $tm] = array_map('intval', explode(':', $to));
        $minutes = ($th * 60 + $tm) - ($fh * 60 + $fm);

        return $minutes < 0 ? $minutes + 1440 : $minutes;
    }

    protected function addMinutes(string $time, int $minutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        $total = ($h * 60 + $m + $minutes) % 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    /** A local day's start and end, in UTC, as stored. */
    protected function dayBoundsUtc(string $date): array
    {
        $start = Carbon::parse($date, $this->tz)->startOfDay();

        return [$start->copy()->utc()->format('Y-m-d H:i:s'), $start->copy()->endOfDay()->utc()->format('Y-m-d H:i:s')];
    }

}
