<?php

namespace App\Services\Iapp\Sync;

use App\Imports\AttendanceImport;
use App\Jobs\ImportAttendanceJob;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\IappAttendanceLink;
use App\Models\IappAttendanceSyncRun;
use App\Models\User;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Timeline\DayTimeline;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pushes device punches into the ERP's own `attendances` table.
 *
 * The Excel importer you already use is itself a punch importer: it reads
 * employee, time and in/out, and builds sessions. The device feed carries the
 * same three things, so this does not re-implement any of that — it replays
 * punches through `ImportAttendanceJob::applyPunch()`, the public entry point
 * that exists precisely so reconstructed punches can go through the same tested
 * pairing logic without a source file.
 *
 * That means device-sourced attendance is identical in shape to imported
 * attendance: the same session pairing, the same late and half-day rules
 * computed from the real punch time, the same stale-session auto-close, the
 * same per-day check-in cap, the same orphan-out handling. Nothing in the
 * existing import path is modified.
 *
 * This is the only place the biometric module writes outside its own tables, so
 * it is deliberate only — never scheduled, never triggered by a punch sync.
 * A day that already holds attendance from somewhere else is left alone unless
 * an override is asked for, and an override keeps a copy of everything it
 * displaces.
 */
class AttendanceSync
{

    public function __construct(
        protected IappConfig $config
    ) {
    }

    protected ?Company $company = null;

    protected function company(): ?Company
    {
        return $this->company ??= Company::find($this->config->companyId());
    }

    // ------------------------------------------------------------------
    // Mapping
    // ------------------------------------------------------------------

    /**
     * Which biometric codes reach an ERP employee, and which employees none of
     * them reach.
     *
     * Three routes to a user, most explicit first: the code written on the
     * employee record, the mapping made on the Employee Mapping screen, and the
     * card-ID match the punch sync resolves on its own.
     *
     * @return array{codes: array<string,int>, employees: array, unmapped: array, mapped_users: array}
     */
    /** Which shift, and which hours, belong to each employee's day. */
    protected function shifts(): \App\Services\Attendance\ShiftResolver
    {
        return \App\Services\Attendance\ShiftResolver::shared($this->config->companyId());
    }

    public function mapping(): array
    {
        $companyId = $this->config->companyId();

        $codes = [];

        foreach (DB::table('employee_details')
            ->whereNotNull('iapp_emp_code')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['user_id', 'iapp_emp_code']) as $row) {
            $codes[(string) $row->iapp_emp_code] = (int) $row->user_id;
        }

        foreach (DB::table('iapp_employees')
            ->whereNotNull('user_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['emp_code', 'user_id']) as $row) {
            $codes[(string) $row->emp_code] ??= (int) $row->user_id;
        }

        foreach (DB::table('iapp_device_transactions')
            ->whereNotNull('user_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->select('emp_code', 'user_id')->distinct()->get() as $row) {
            if ($row->emp_code !== null && $row->emp_code !== '') {
                $codes[(string) $row->emp_code] ??= (int) $row->user_id;
            }
        }

        // Only real employees can hold attendance, so the roll call is over
        // them rather than over every user.
        $employees = DB::table('users')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'employee')
            ->when($companyId, fn ($q) => $q->where('users.company_id', $companyId))
            ->select('users.id', 'users.name', 'employee_details.employee_card_id', 'employee_details.iapp_emp_code')
            ->distinct()
            ->orderBy('users.name')
            ->get();

        $mappedUsers = array_values(array_unique(array_values($codes)));

        $unmapped = $employees
            ->reject(fn ($e) => in_array((int) $e->id, $mappedUsers, true))
            ->values()
            ->all();

        return [
            'codes' => $codes,
            'employees' => $employees->all(),
            'unmapped' => $unmapped,
            'mapped_users' => $mappedUsers,
        ];
    }

    // ------------------------------------------------------------------
    // Preview
    // ------------------------------------------------------------------

    /**
     * What a sync over this range would do, without writing anything.
     *
     * @param  array<int,int>|null  $userIds  limit to these employees
     */
    public function preview(Carbon $from, Carbon $to, ?array $userIds = null): array
    {
        $mapping = $this->mapping();
        $codes = $mapping['codes'];

        if ($codes === []) {
            return [
                'rows' => [], 'totals' => $this->emptyTotals(), 'mapping' => $mapping,
            ];
        }

        // Company first: it leads the (company_id, emp_code, punch_time) index,
        // and this table carries no company scope of its own.
        $punches = DB::table('iapp_device_transactions')
            ->when($this->config->companyId(), fn ($q, $id) => $q->where('company_id', $id))
            ->whereIn('emp_code', array_keys($codes))
            // A day past the end: an overnight shift on the last day runs into it.
            ->whereBetween('punch_time', [$from->copy()->startOfDay(), $to->copy()->addDay()->endOfDay()])
            ->orderBy('punch_time')
            ->get(['emp_code', 'punch_time', 'punch_direction']);

        $tz = $this->company()?->timezone ?? 'UTC';
        $resolver = $this->shifts();
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        // Group by the employee the code points at, so two enrolments for one
        // person land on the same attendance day rather than fighting over it.
        $byUserDate = [];

        foreach ($punches as $punch) {
            $userId = $codes[(string) $punch->emp_code] ?? null;

            if (!$userId || ($userIds !== null && !in_array($userId, $userIds, true))) {
                continue;
            }

            // The shift's day: the morning tail of an overnight shift belongs to
            // the day it started. Everyone else, the calendar day.
            $date = $resolver->shiftDayOf($userId, Carbon::parse((string) $punch->punch_time, $tz), $tz);

            if ($date < $fromKey || $date > $toKey) {
                continue;
            }

            $byUserDate[$userId][$date]['punches'][] = $punch;
            $byUserDate[$userId][$date]['codes'][(string) $punch->emp_code] = true;
        }

        $names = DB::table('users')->whereIn('id', array_keys($byUserDate))->pluck('name', 'id')->all();
        $existing = $this->existingIndex(array_keys($byUserDate), $from, $to);
        $ours = $this->linkedIndex(array_keys($byUserDate), $from, $to);

        $rows = [];
        $totals = $this->emptyTotals();

        foreach ($byUserDate as $userId => $days) {
            ksort($days);

            foreach ($days as $date => $day) {
                $rowIds = $existing[$userId][$date] ?? [];
                $ourIds = $ours[$userId][$date] ?? [];
                $foreign = array_values(array_diff($rowIds, $ourIds));

                $times = array_map(fn ($p) => substr((string) $p->punch_time, 11, 8), $day['punches']);

                $rows[] = [
                    'user_id' => $userId,
                    'name' => $names[$userId] ?? ('User #' . $userId),
                    'date' => $date,
                    'codes' => array_keys($day['codes']),
                    'punches' => count($day['punches']),
                    'first' => $times ? min($times) : null,
                    'last' => $times ? max($times) : null,
                    'existing_rows' => count($rowIds),
                    'our_rows' => count($ourIds),
                    'conflict' => $foreign !== [],
                    'conflict_rows' => count($foreign),
                ];

                $totals['days']++;
                $totals['punches'] += count($day['punches']);
                $totals['conflicts'] += $foreign !== [] ? 1 : 0;
                $totals['resync'] += ($foreign === [] && $ourIds !== []) ? 1 : 0;
                $totals['fresh'] += ($rowIds === []) ? 1 : 0;
            }
        }

        $totals['employees'] = count($byUserDate);

        usort($rows, fn ($a, $b) => [$a['name'], $a['date']] <=> [$b['name'], $b['date']]);

        return ['rows' => $rows, 'totals' => $totals, 'mapping' => $mapping];
    }

    protected function emptyTotals(): array
    {
        return ['employees' => 0, 'days' => 0, 'punches' => 0, 'conflicts' => 0, 'resync' => 0, 'fresh' => 0];
    }

    /** [user_id][date] => attendance ids that exist now. */
    protected function existingIndex(array $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $index = [];

        // Compared on the company's local date, because clock_in_time is UTC
        // and a 09:30 IST arrival is 04:00 UTC the same day — but a late
        // evening one would otherwise fall on the wrong side of midnight.
        $tz = $this->company()?->timezone ?? 'UTC';

        $rows = Attendance::withoutGlobalScopes()
            ->whereIn('user_id', $userIds)
            ->whereBetween('clock_in_time', [
                $from->copy()->startOfDay()->shiftTimezone($tz)->setTimezone('UTC'),
                $to->copy()->addDay()->endOfDay()->shiftTimezone($tz)->setTimezone('UTC'),
            ])
            ->get(['id', 'user_id', 'clock_in_time']);

        $resolver = $this->shifts();
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        foreach ($rows as $row) {
            // The day the session belongs to - an overnight shift's after-midnight
            // session counts toward the day the shift started.
            $date = $resolver->shiftDayOf((int) $row->user_id, Carbon::parse($row->clock_in_time, 'UTC'), $tz);

            if ($date >= $fromKey && $date <= $toKey) {
                $index[$row->user_id][$date][] = (int) $row->id;
            }
        }

        return $index;
    }

    /** [user_id][date] => attendance ids this module created. */
    protected function linkedIndex(array $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $index = [];

        $links = IappAttendanceLink::whereIn('user_id', $userIds)
            ->where('link_type', IappAttendanceLink::TYPE_CREATED)
            ->whereNotNull('attendance_id')
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'attendance_date', 'attendance_id']);

        foreach ($links as $link) {
            $index[$link->user_id][$link->attendance_date->toDateString()][] = (int) $link->attendance_id;
        }

        return $index;
    }

    // ------------------------------------------------------------------
    // Review
    // ------------------------------------------------------------------

    /**
     * The attendance a day already holds, for the review popup.
     */
    public function existingAttendance(int $userId, string $date): array
    {
        $tz = $this->company()?->timezone ?? 'UTC';

        $ourIds = IappAttendanceLink::where('user_id', $userId)
            ->whereDate('attendance_date', $date)
            ->where('link_type', IappAttendanceLink::TYPE_CREATED)
            ->pluck('attendance_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = Attendance::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereBetween('clock_in_time', [
                Carbon::parse($date)->startOfDay()->shiftTimezone($tz)->setTimezone('UTC'),
                Carbon::parse($date)->endOfDay()->shiftTimezone($tz)->setTimezone('UTC'),
            ])
            ->orderBy('clock_in_time')
            ->get();

        return $rows->map(function ($row) use ($tz, $ourIds) {
            $in = Carbon::parse($row->clock_in_time)->setTimezone($tz);
            $out = $row->clock_out_time ? Carbon::parse($row->clock_out_time)->setTimezone($tz) : null;

            return [
                'id' => (int) $row->id,
                'in' => $in->format('H:i:s'),
                'out' => $out?->format('H:i:s'),
                'minutes' => $out ? max(0, $out->diffInMinutes($in, true)) : null,
                'late' => $row->late,
                'half_day' => $row->half_day,
                'working_from' => $row->working_from ?? $row->work_from_type,
                'source' => in_array((int) $row->id, $ourIds, true) ? 'Biometric sync' : 'Manual / import',
                'ours' => in_array((int) $row->id, $ourIds, true),
            ];
        })->all();
    }

    // ------------------------------------------------------------------
    // Run
    // ------------------------------------------------------------------

    /**
     * Write device punches into `attendances`.
     *
     * @param  array<int,int>|null  $userIds
     * @param  array<int,string>|null  $only  'user_id|Y-m-d' pairs to restrict to
     */
    public function run(
        Carbon $from,
        Carbon $to,
        string $mode = IappAttendanceSyncRun::MODE_SKIP,
        ?array $userIds = null,
        ?int $actorId = null,
        ?array $only = null,
        bool $collapse = true
    ): IappAttendanceSyncRun {
        // Shifts, assignments or the roster may have changed since this process
        // last looked (a queue worker lives across many runs).
        \App\Services\Attendance\ShiftResolver::reset();

        $preview = $this->preview($from, $to, $userIds);

        $run = IappAttendanceSyncRun::create([
            'company_id' => $this->config->companyId(),
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'conflict_mode' => $mode,
            'status' => IappAttendanceSyncRun::STATUS_DONE,
            'started_by' => $actorId,
            'started_at' => now(),
        ]);

        $company = $this->company();
        $tz = $company?->timezone ?? 'UTC';
        $job = new ImportAttendanceJob([], [], $company);

        $users = User::whereIn('id', array_column($preview['rows'], 'user_id'))->get()->keyBy('id');

        $counts = ['employees' => 0, 'days' => 0, 'punches' => 0, 'created' => 0, 'replaced' => 0, 'skipped' => 0, 'locked' => 0];
        $seenUsers = [];
        $skipped = [];
        $locked = [];
        $locks = self::payslipLocks(array_keys($users->all()));

        foreach ($preview['rows'] as $row) {
            if ($only !== null && !in_array($row['user_id'] . '|' . $row['date'], $only, true)) {
                continue;
            }

            $user = $users->get($row['user_id']);

            if (!$user) {
                continue;
            }

            // Already paid: a day an approved payslip counted is not rewritten
            // underneath it. The cross-check reports any difference instead.
            if (self::isLocked($locks, (int) $row['user_id'], $row['date'])) {
                $counts['locked']++;
                $locked[] = ['user' => $row['name'], 'date' => $row['date']];

                continue;
            }

            if ($row['conflict'] && $mode !== IappAttendanceSyncRun::MODE_OVERRIDE) {
                $counts['skipped']++;
                $skipped[] = ['user' => $row['name'], 'date' => $row['date'], 'rows' => $row['conflict_rows']];

                continue;
            }

            $result = $this->syncDay($run, $job, $user, $row, $tz, $mode, $collapse);

            $counts['days']++;
            $counts['punches'] += $result['punches'];
            $counts['created'] += $result['created'];
            $counts['replaced'] += $result['replaced'];
            $seenUsers[$row['user_id']] = true;
        }

        $counts['employees'] = count($seenUsers);

        $run->forceFill([
            'employees' => $counts['employees'],
            'days' => $counts['days'],
            'punches_sent' => $counts['punches'],
            'rows_created' => $counts['created'],
            'rows_replaced' => $counts['replaced'],
            'days_skipped' => $counts['skipped'],
            // Capped: a wide range with many conflicts would otherwise put
            // thousands of entries into one JSON column.
            'summary' => ['skipped' => array_slice($skipped, 0, 200), 'locked' => array_slice($locked, 0, 200)],
            'message' => sprintf(
                '%d attendance row(s) created for %d employee(s) across %d day(s). %d replaced, %d day(s) skipped%s.',
                $counts['created'],
                $counts['employees'],
                $counts['days'],
                $counts['replaced'],
                $counts['skipped'],
                $counts['locked'] ? ', ' . $counts['locked'] . ' day(s) left as paid (in an approved payslip)' : ''
            ),
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * Days each employee's approved or paid payslips have already counted.
     *
     * A payslip locks its cycle only up to the day it was approved: payslips
     * here have been approved mid-cycle, and the days after that were not yet
     * worked, so freezing them would stop real attendance being recorded.
     *
     * @param  int[]  $userIds
     * @return array<int, array<int, array{0: string, 1: string}>>  user => [[from, to], ...]
     */
    public static function payslipLocks(array $userIds): array
    {
        if ($userIds === [] || !\Illuminate\Support\Facades\Schema::hasColumn('payslips', 'approval_status')) {
            return [];
        }

        $locks = [];

        $rows = DB::table('payslips')
            ->whereIn('user_id', $userIds)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereIn('approval_status', ['approved', 'paid'])
            ->whereNotNull('period_start')->whereNotNull('period_end')
            ->get(['user_id', 'period_start', 'period_end', 'approved_at', 'paid_at']);

        foreach ($rows as $p) {
            $signed = $p->approved_at ?: $p->paid_at;
            $until = substr((string) $p->period_end, 0, 10);

            if ($signed && substr((string) $signed, 0, 10) < $until) {
                $until = substr((string) $signed, 0, 10);
            }

            $locks[(int) $p->user_id][] = [substr((string) $p->period_start, 0, 10), $until];
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
     * Replay one employee-day's punches.
     *
     * @return array{punches:int, created:int, replaced:int}
     */
    protected function syncDay(
        IappAttendanceSyncRun $run,
        ImportAttendanceJob $job,
        User $user,
        array $row,
        string $tz,
        string $mode,
        bool $collapse = true
    ): array {
        $date = $row['date'];
        $replaced = 0;

        // The hours that belong to this day: the calendar day, or for an overnight
        // shift from its start to the next morning (ShiftResolver::dayWindow).
        [$localStart, $localEnd] = $this->shifts()->dayWindow($user->id, $date, $tz);

        $punches = DB::table('iapp_device_transactions')
            ->when($this->config->companyId(), fn ($q, $id) => $q->where('company_id', $id))
            ->whereIn('emp_code', $row['codes'])
            ->whereBetween('punch_time', [$localStart->format('Y-m-d H:i:s'), $localEnd->format('Y-m-d H:i:s')])
            ->orderBy('punch_time')
            ->get(['emp_code', 'punch_time', 'punch_direction', 'controller_code']);

        if ($punches->isEmpty()) {
            return ['punches' => 0, 'created' => 0, 'replaced' => 0];
        }

        $events = $collapse ? $this->collapse($date, $punches) : $this->rawEvents($punches);

        if ($events === []) {
            return ['punches' => 0, 'created' => 0, 'replaced' => 0];
        }

        DB::transaction(function () use ($run, $user, $date, $tz, $mode, &$replaced) {
            $existing = $this->existingIndex([$user->id], Carbon::parse($date), Carbon::parse($date));
            $ids = $existing[$user->id][$date] ?? [];

            if ($ids === []) {
                return;
            }

            $ourIds = IappAttendanceLink::where('user_id', $user->id)
                ->whereDate('attendance_date', $date)
                ->where('link_type', IappAttendanceLink::TYPE_CREATED)
                ->whereNotNull('attendance_id')
                ->pluck('attendance_id')->map(fn ($id) => (int) $id)->all();

            // Rows this module wrote before are always cleared and rebuilt, so a
            // re-sync reflects the punches as they stand rather than layering a
            // second set of sessions on top. Rows from anywhere else are only
            // touched when an override says so.
            $removable = $mode === IappAttendanceSyncRun::MODE_OVERRIDE
                ? $ids
                : array_values(array_intersect($ids, $ourIds));

            if ($removable === []) {
                return;
            }

            $rows = Attendance::withoutGlobalScopes()->whereIn('id', $removable)->get();

            foreach ($rows as $attendance) {
                $wasOurs = in_array((int) $attendance->id, $ourIds, true);

                // Everything displaced is kept, so an override can be undone.
                IappAttendanceLink::create([
                    'company_id' => $run->company_id,
                    'run_id' => $run->id,
                    'attendance_id' => null,
                    'user_id' => $user->id,
                    'attendance_date' => $date,
                    'link_type' => IappAttendanceLink::TYPE_REPLACED,
                    'payload' => $attendance->getAttributes(),
                ]);

                if (!$wasOurs) {
                    $replaced++;
                }
            }

            IappAttendanceLink::where('user_id', $user->id)
                ->whereDate('attendance_date', $date)
                ->where('link_type', IappAttendanceLink::TYPE_CREATED)
                ->whereIn('attendance_id', $removable)
                ->delete();

            Attendance::withoutGlobalScopes()->whereIn('id', $removable)->delete();
        });

        // Bounded to the day being written: plucking every attendance id the
        // employee has ever had would be two whole-history scans per day synced.
        $window = [$localStart->copy()->setTimezone('UTC'), $localEnd->copy()->setTimezone('UTC')];

        $before = Attendance::withoutGlobalScopes()
            ->where('user_id', $user->id)->whereBetween('clock_in_time', $window)->pluck('id')->all();

        foreach ($events as $event) {
            $moment = AttendanceImport::parsePunchTime($this->eventMoment($date, $event['time']), $tz);

            if (!$moment) {
                continue;
            }

            // applyPunch expects UTC, exactly as processRow hands it over.
            $job->applyPunch($user, $event['type'], $moment->timezone('UTC'));
        }

        $this->closeDayEnd($user, $date, $tz, $events, $window);

        $after = Attendance::withoutGlobalScopes()
            ->where('user_id', $user->id)->whereBetween('clock_in_time', $window)->pluck('id')->all();
        $created = array_values(array_diff($after, $before));

        foreach ($created as $id) {
            IappAttendanceLink::create([
                'company_id' => $run->company_id,
                'run_id' => $run->id,
                'attendance_id' => $id,
                'user_id' => $user->id,
                'attendance_date' => $date,
                'emp_code' => $row['codes'][0] ?? null,
                'link_type' => IappAttendanceLink::TYPE_CREATED,
            ]);
        }

        return ['punches' => count($events), 'created' => count($created), 'replaced' => $replaced];
    }

    /**
     * A session still open once a finished day has been replayed is a forgotten
     * exit. Left open, the first "out" of the next morning closed it, so one
     * evening became a session running past midnight and the next day lost its
     * arrival. Close it at the last thing the device saw that day instead: the
     * person was there at least until then, and no evidence says longer.
     *
     * Today is left alone: the person may simply still be at work, and the next
     * pull rebuilds today from scratch anyway.
     */
    protected function closeDayEnd(User $user, string $date, string $tz, array $events, array $window): void
    {
        // Not while the day is still running - for an overnight shift, until its
        // window ends the next morning.
        if ($events === [] || Carbon::now('UTC')->lte($window[1])) {
            return;
        }

        $lastSeen = AttendanceImport::parsePunchTime($this->eventMoment($date, end($events)['time']), $tz)?->timezone('UTC');

        if (!$lastSeen) {
            return;
        }

        Attendance::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->whereBetween('clock_in_time', $window)
            ->get()
            ->each(function (Attendance $open) use ($lastSeen) {
                $open->clock_out_time = $lastSeen->greaterThan($open->clock_in_time) ? $lastSeen : $open->clock_in_time;
                $open->clock_out_ip = null;
                $open->saveQuietly();
            });
    }

    /**
     * One clean in/out event per real movement.
     *
     * The controllers report a single swipe once per gate, so the raw feed
     * carries roughly 1.3 events for every real one. Replayed as-is those
     * duplicates become sessions of their own: a 55-second session from an
     * entry reported twice, and zero-duration orphan rows that the attendance
     * observer then stamps late and half-day. DayTimeline is the same
     * reconstruction every other biometric screen uses, so attendance written
     * here agrees with what those screens show.
     *
     * Face-reader scans carry no usable direction, so they are not paired into
     * sessions in the middle of the day — they would pair into sessions that
     * never happened. At the edges of the day they are still evidence, though:
     * a scan before the first door punch is the arrival, and one after the last
     * is the departure. Dropping those lost real time — 30 Sep began at 09:56 on
     * the face reader, but the ERP day started at the 13:03 door punch.
     *
     * @return array<int, array{time:string, type:string}>
     */
    protected function collapse(string $date, $punches): array
    {
        $timeline = new DayTimeline(DayTimeline::directionlessControllers($this->config->companyId()));
        $day = $timeline->build($date, $punches);

        $events = [];
        $scans = [];

        foreach ($day['events'] as $event) {
            if ($event['kind'] === DayTimeline::KIND_SCAN) {
                $scans[] = $event['at']->format('Y-m-d H:i:s');

                continue;
            }

            if (!in_array($event['direction'], ['in', 'out'], true)) {
                continue;
            }

            $events[] = ['time' => $event['at']->format('Y-m-d H:i:s'), 'type' => $event['direction']];
        }

        if ($scans !== []) {
            sort($scans);
            $firstScan = $scans[0];
            $lastScan = end($scans);

            if ($events === []) {
                // Only the face reader saw them: arrival and departure, if two scans apart.
                $events[] = ['time' => $firstScan, 'type' => 'in'];

                if ($lastScan !== $firstScan) {
                    $events[] = ['time' => $lastScan, 'type' => 'out'];
                }
            }
            else {
                if ($firstScan < $events[0]['time']) {
                    array_unshift($events, ['time' => $firstScan, 'type' => 'in']);
                }

                if ($lastScan > end($events)['time']) {
                    $events[] = ['time' => $lastScan, 'type' => 'out'];
                }
            }
        }

        // A gate (or face-reader) repeat is collapsed to its first swipe, which is
        // right for an arrival but cut the day's last exit short by up to a minute
        // and a half (19:22:00 instead of 19:22:18). The day ends at the last
        // punch: the final event takes its time, or - when the whole day was one
        // burst of swipes - the burst gets its end as an exit instead of losing
        // its start.
        $rawLast = substr((string) $punches->max('punch_time'), 0, 19);

        if ($events !== [] && $rawLast > end($events)['time']
            && strtotime($rawLast) - strtotime(end($events)['time']) <= DayTimeline::COLLAPSE_SECONDS) {
            if (count($events) === 1) {
                $events[] = ['time' => $rawLast, 'type' => 'out'];
            }
            else {
                $events[array_key_last($events)]['time'] = $rawLast;
            }
        }

        return $events;
    }

    /** An event's moment: events carry the full date and time; a bare time is on the day itself. */
    protected function eventMoment(string $date, string $time): string
    {
        return strlen($time) > 8 ? $time : $date . ' ' . $time;
    }

    /**
     * Every punch as the device reported it, duplicates included — what the
     * Excel path replays today.
     *
     * @return array<int, array{time:string, type:string}>
     */
    protected function rawEvents($punches): array
    {
        $events = [];

        foreach ($punches as $punch) {
            $events[] = [
                'time' => substr((string) $punch->punch_time, 0, 19),
                'type' => $punch->punch_direction === 'out' ? 'out' : 'in',
            ];
        }

        return $events;
    }

}
