<?php

namespace App\Services\Iapp\Reconcile;

use App\Models\BiometricException;
use App\Models\IappAttendanceLink;
use App\Services\Attendance\ShiftResolver;
use App\Services\Iapp\Sync\AttendanceSync;
use App\Services\Payroll\AttendanceDays;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The 3 AM cross-check: does ERP attendance hold what the device recorded, and
 * do the punches make a complete day?
 *
 * For each employee linked to a device code it compares, in the shift's own
 * window, the device's punches with the ERP attendance rows, and records every
 * disagreement as a BiometricException. A problem found again updates its row;
 * one that has gone away (the day re-synced, the code linked) is closed as
 * "auto". Days whose shift was still running when the data was pulled are left
 * for the next night instead of being reported as a missing exit.
 */
class BiometricReconciler
{

    /** How far ERP may sit from the device before it counts as a mismatch. */
    public const TOLERANCE_MINUTES = 5;

    /** Day-level types this check owns: closed automatically when not found again. */
    protected const DAY_TYPES = ['pull_failed', 'not_written', 'erp_other_source', 'time_mismatch', 'orphan_erp',
        'payslip_locked', 'single_punch', 'missing_exit', 'missing_entry'];

    protected string $tz;

    protected array $seen = [];

    protected array $counts = [BiometricException::SYNC => 0, BiometricException::PUNCH => 0];

    public function __construct(protected ?int $companyId = null)
    {
        $this->tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
        $this->companyId ??= DB::table('companies')->value('id');
    }

    /**
     * @param  Carbon  $through  how far the device data reaches (the pull's end)
     * @return array{sync:int, punch:int, closed:int}
     */
    public function run(Carbon $date, bool $deviceComplete, Carbon $through, ?string $pullError = null): array
    {
        $day = Carbon::parse($date->toDateString(), $this->tz)->startOfDay();
        $key = $day->toDateString();
        $this->seen = [];
        $this->counts = [BiometricException::SYNC => 0, BiometricException::PUNCH => 0];
        ShiftResolver::reset();

        if (!$deviceComplete) {
            $this->record($key, 'pull_failed', 'pull_failed|' . $key, null, null,
                'Device data for ' . $day->format('d M Y') . ' is incomplete' . ($pullError ? ': ' . $pullError : ''),
                ['error' => $pullError]);
        }

        $this->unlinkedCodes($key);
        $this->employees($key, $through);
        $this->payslips();

        $closed = $this->closeGoneAway($key);

        return $this->counts + ['closed' => $closed];
    }

    /** Punches from codes nobody is linked to (ignored codes excepted): one row per code. */
    protected function unlinkedCodes(string $key): void
    {
        $ignored = DB::table('iapp_employees')->where('map_status', 'ignored')->pluck('emp_code')->map(fn ($c) => (string) $c)->all();

        $codes = DB::table('iapp_device_transactions')
            ->whereNull('user_id')->whereNotNull('emp_code')->where('emp_code', '<>', '')
            ->whereBetween('punch_time', [$key . ' 00:00:00', $key . ' 23:59:59'])
            ->selectRaw('emp_code, MAX(employee_name) as name, COUNT(*) as punches')
            ->groupBy('emp_code')
            ->get();

        foreach ($codes as $code) {
            if (in_array((string) $code->emp_code, $ignored, true)) {
                continue;
            }

            $this->record($key, 'unlinked_code', 'unlinked_code|' . $code->emp_code, null, (string) $code->emp_code,
                sprintf('%s (%s) punched %d time(s) on %s but is not linked to an employee',
                    $code->name ?: 'Unknown', $code->emp_code, $code->punches, Carbon::parse($key)->format('d M')),
                ['name' => $code->name, 'punches' => (int) $code->punches]);
        }

        // Linked or ignored since: those are dealt with.
        BiometricException::withoutGlobalScopes()->where('type', 'unlinked_code')->where('status', BiometricException::OPEN)
            ->get()
            ->each(function (BiometricException $e) use ($ignored) {
                $linked = DB::table('employee_details')->where('iapp_emp_code', $e->emp_code)->exists()
                    || DB::table('iapp_employees')->where('emp_code', $e->emp_code)->where('map_status', 'mapped')->exists();

                if ($linked || in_array((string) $e->emp_code, $ignored, true)) {
                    $this->close($e);
                }
            });
    }

    /** Every employee linked to a device code, compared device against ERP. */
    protected function employees(string $key, Carbon $through): void
    {
        $codes = DB::table('employee_details')->whereNotNull('iapp_emp_code')->where('iapp_emp_code', '<>', '')
            ->pluck('iapp_emp_code', 'user_id')
            ->union(DB::table('iapp_employees')->where('map_status', 'mapped')->whereNotNull('user_id')->pluck('emp_code', 'user_id'));

        $employed = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereIn('u.id', $codes->keys())
            ->where(fn ($q) => $q->whereNull('ed.joining_date')->orWhereDate('ed.joining_date', '<=', $key))
            ->where(fn ($q) => $q->whereNull('ed.last_date')->orWhereDate('ed.last_date', '>=', $key))
            ->pluck('u.name', 'u.id');

        $locks = AttendanceSync::payslipLocks($employed->keys()->all());
        $resolver = ShiftResolver::shared();
        $throughUtc = $through->copy()->setTimezone('UTC');

        foreach ($employed as $userId => $name) {
            $userId = (int) $userId;
            $code = (string) $codes[$userId];
            [$startUtc, $endUtc] = $resolver->dayWindowUtc($userId, $key, $this->tz);
            [$start, $end] = $resolver->dayWindow($userId, $key, $this->tz);
            $inProgress = $endUtc->gt($throughUtc);

            $punches = DB::table('iapp_device_transactions')->where('emp_code', $code)
                ->whereBetween('punch_time', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
                ->orderBy('punch_time')->get(['punch_time', 'punch_direction', 'controller_code']);

            $rows = DB::table('attendances')->where('user_id', $userId)
                ->whereBetween('clock_in_time', [$startUtc, $endUtc])
                ->get(['id', 'clock_in_time', 'clock_out_time']);

            $linked = $rows->isEmpty() ? collect() : DB::table('iapp_attendance_links')
                ->whereIn('attendance_id', $rows->pluck('id'))->where('link_type', IappAttendanceLink::TYPE_CREATED)
                ->pluck('attendance_id');

            // The device's own day (calendar) for entry/exit; an overnight shift
            // spans two of those, so its exit is read from the ERP session instead.
            $overnight = ShiftResolver::isOvernight($resolver->shiftOn($userId, $key));
            $record = $overnight ? null : DB::table('iapp_attendance_records')->where('emp_id', $code)
                ->whereDate('attendance_date', $key)->first(['first_in', 'last_out', 'punch_count']);

            // ERP is written from the punches as they come, so it is compared with
            // the first and last punch, not with the device summary (which leaves
            // out an exit nobody entered for).
            $deviceIn = $punches->isNotEmpty() ? Carbon::parse($punches->first()->punch_time, $this->tz) : null;
            $deviceOut = $punches->count() > 1 ? Carbon::parse($punches->last()->punch_time, $this->tz) : null;
            [$missingEntries, $missingExits, $directional] = $this->unpaired($punches);

            $erpIn = $rows->min('clock_in_time') ? Carbon::parse($rows->min('clock_in_time'), 'UTC')->setTimezone($this->tz) : null;
            $erpOut = $rows->whereNotNull('clock_out_time')->max('clock_out_time')
                ? Carbon::parse($rows->whereNotNull('clock_out_time')->max('clock_out_time'), 'UTC')->setTimezone($this->tz) : null;
            $erpOpen = $rows->contains(fn ($r) => $r->clock_out_time === null);

            $locked = AttendanceSync::isLocked($locks, $userId, $key);
            $facts = ['device_in' => $deviceIn?->format('H:i'), 'device_out' => $deviceOut?->format('H:i'),
                'erp_in' => $erpIn?->format('H:i'), 'erp_out' => $erpOut?->format('H:i'), 'punches' => $punches->count()];
            $fp = fn (string $type) => $type . '|' . $key . '|' . $userId;

            if ($punches->isNotEmpty()) {
                if ($rows->isEmpty()) {
                    $this->record($key, $locked ? 'payslip_locked' : 'not_written', $fp($locked ? 'payslip_locked' : 'not_written'), $userId, $code,
                        $locked
                            ? "{$name}: the device has {$punches->count()} punch(es) but the paid payslip counted no attendance"
                            : "{$name}: {$punches->count()} punch(es) on the device, no ERP attendance",
                        $facts);
                }
                elseif ($linked->isEmpty()) {
                    if (!$this->agrees($deviceIn, $erpIn) || (!$inProgress && !$this->agrees($deviceOut, $erpOut))) {
                        $this->record($key, 'erp_other_source', $fp('erp_other_source'), $userId, $code,
                            "{$name}: ERP {$this->span($erpIn, $erpOut)} (entered by hand or web), device {$this->span($deviceIn, $deviceOut)}",
                            $facts);
                    }
                }
                elseif (!$this->agrees($deviceIn, $erpIn) || (!$inProgress && $deviceOut && !$this->agrees($deviceOut, $erpOut))) {
                    $type = $locked ? 'payslip_locked' : 'time_mismatch';
                    $this->record($key, $type, $fp($type), $userId, $code,
                        "{$name}: ERP {$this->span($erpIn, $erpOut)}, device {$this->span($deviceIn, $deviceOut)}",
                        $facts);
                }

                // Punch problems, once the shift is over.
                if (!$inProgress) {
                    // One punch from a reader that gives a direction is an entry
                    // without its exit (or the reverse), and is named as such.
                    if ($punches->count() === 1 && !$directional) {
                        $this->record($key, 'single_punch', $fp('single_punch'), $userId, $code,
                            "{$name}: one punch only, at " . ($deviceIn?->format('H:i') ?? '?'), $facts);
                    }
                    elseif ($directional) {
                        if ($missingEntries) {
                            $this->record($key, 'missing_entry', $fp('missing_entry'), $userId, $code,
                                "{$name}: exit at " . implode(', ', $missingEntries) . ' with no entry before it', $facts + ['exits_without_entry' => $missingEntries]);
                        }

                        if ($missingExits) {
                            $this->record($key, 'missing_exit', $fp('missing_exit'), $userId, $code,
                                "{$name}: entry at " . implode(', ', $missingExits) . ' with no exit after it', $facts + ['entries_without_exit' => $missingExits]);
                        }
                    }
                    // Face readers carry no direction: only an open session says
                    // an exit is missing.
                    elseif (($record && $record->first_in && !$record->last_out) || ($overnight && $erpOpen)) {
                        $this->record($key, 'missing_exit', $fp('missing_exit'), $userId, $code,
                            "{$name}: entry at " . ($deviceIn?->format('H:i') ?? '?') . ', no exit punch', $facts);
                    }
                }
            }
            elseif ($linked->isNotEmpty()) {
                $this->record($key, 'orphan_erp', $fp('orphan_erp'), $userId, $code,
                    "{$name}: ERP holds device attendance {$this->span($erpIn, $erpOut)} but the device has no punches", $facts);
            }
        }
    }

    /**
     * Entries and exits with nothing to pair with, walked in time order over
     * the punches whose reader states a direction. A repeat swipe of the same
     * direction within three minutes (several gates, a second try) is one.
     *
     * @return array{0: string[], 1: string[], 2: bool}  exits without entry, entries without exit, any directional punch
     */
    protected function unpaired($punches): array
    {
        $fixed = $this->fixedDirectionReaders ??= \App\Services\Iapp\Timeline\DayTimeline::directionlessControllers($this->companyId);
        $inside = false;
        $lastIn = null;
        $previous = null;
        $missingEntries = [];
        $missingExits = [];
        $directional = false;

        foreach ($punches as $punch) {
            $direction = strtolower((string) $punch->punch_direction);

            // A reader set to one direction (the face readers, "In-Mode") calls
            // every swipe an entry, so it says nothing about pairing.
            if (!in_array($direction, ['in', 'out'], true) || in_array((string) $punch->controller_code, $fixed, true)) {
                continue;
            }

            $directional = true;
            $at = Carbon::parse($punch->punch_time, $this->tz);

            if ($previous && $previous[0] === $direction && $at->diffInMinutes($previous[1]) <= 3) {
                continue;
            }

            if ($direction === 'in') {
                if ($inside && $lastIn) {
                    $missingExits[] = $lastIn->format('H:i');
                }

                $inside = true;
                $lastIn = $at;
            }
            else {
                if (!$inside) {
                    $missingEntries[] = $at->format('H:i');
                }

                $inside = false;
            }

            $previous = [$direction, $at];
        }

        if ($inside && $lastIn) {
            $missingExits[] = $lastIn->format('H:i');
        }

        return [$missingEntries, $missingExits, $directional];
    }

    protected ?array $fixedDirectionReaders = null;

    /**
     * Approved payslips for cycles that have ended, against what attendance
     * gives for the same cycle now. Payslips approved mid-cycle are the usual
     * reason they part company.
     */
    protected function payslips(): void
    {
        $today = Carbon::now($this->tz)->toDateString();

        $payslips = DB::table('payslips as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->whereNull('p.deleted_at')->where('p.status', 'active')
            ->whereIn('p.approval_status', ['approved', 'paid'])
            ->whereNotNull('p.period_end')->where('p.period_end', '<', $today)
            ->where('p.period_end', '>=', Carbon::now($this->tz)->subDays(70)->toDateString())
            ->get(['p.id', 'p.user_id', 'u.name', 'p.period_start', 'p.period_end', 'p.days_present', 'p.working_days']);

        $counter = new AttendanceDays($this->tz);
        $seenIds = [];

        foreach ($payslips as $p) {
            $now = (float) $counter->forRange((int) $p->user_id, Carbon::parse($p->period_start, $this->tz), Carbon::parse($p->period_end, $this->tz))['days_present'];
            $paid = (float) $p->days_present;
            $fingerprint = 'payslip_mismatch|' . $p->id;
            $seenIds[] = $fingerprint;

            if (abs($now - $paid) >= 0.5) {
                $this->record(substr((string) $p->period_end, 0, 10), 'payslip_mismatch', $fingerprint, (int) $p->user_id, null,
                    sprintf('%s: payslip %s – %s paid for %s day(s); attendance now gives %s',
                        $p->name, Carbon::parse($p->period_start)->format('d M'), Carbon::parse($p->period_end)->format('d M Y'),
                        rtrim(rtrim(number_format($paid, 1), '0'), '.'), rtrim(rtrim(number_format($now, 1), '0'), '.')),
                    ['payslip_id' => $p->id, 'paid_days' => $paid, 'attendance_days' => $now, 'difference' => round($now - $paid, 1)]);
            }
        }

        BiometricException::withoutGlobalScopes()->where('type', 'payslip_mismatch')->where('status', BiometricException::OPEN)
            ->whereNotIn('fingerprint', array_values(array_intersect($seenIds, $this->seen)))
            ->get()->each(fn ($e) => $this->close($e));
    }

    /** Close this date's exceptions that were not found again. */
    protected function closeGoneAway(string $key): int
    {
        $gone = BiometricException::withoutGlobalScopes()
            ->whereDate('date', $key)->whereIn('type', self::DAY_TYPES)
            ->where('status', BiometricException::OPEN)
            ->whereNotIn('fingerprint', $this->seen ?: ['-'])
            ->get();

        $gone->each(fn ($e) => $this->close($e));

        return $gone->count();
    }

    protected function close(BiometricException $e): void
    {
        $e->forceFill(['status' => BiometricException::RESOLVED, 'resolution' => 'auto', 'resolved_at' => now(),
            'resolved_by' => null, 'note' => $e->note ?: 'No longer found by the nightly cross-check.'])->save();
    }

    /**
     * One row per problem. Found again: refreshed. An ignored one stays ignored;
     * one somebody resolved by hand stays resolved unless the facts changed.
     */
    protected function record(string $date, string $type, string $fingerprint, ?int $userId, ?string $code, string $title, array $details = []): void
    {
        $this->seen[] = $fingerprint;
        $this->counts[BiometricException::TYPES[$type][0]]++;

        $existing = BiometricException::withoutGlobalScopes()->where('fingerprint', $fingerprint)->first();
        $values = ['company_id' => $this->companyId, 'date' => $date, 'category' => BiometricException::TYPES[$type][0],
            'type' => $type, 'user_id' => $userId, 'emp_code' => $code, 'title' => mb_substr($title, 0, 255),
            'details' => $details, 'last_seen_at' => now()];

        if (!$existing) {
            BiometricException::create($values + ['fingerprint' => $fingerprint, 'status' => BiometricException::OPEN, 'first_seen_at' => now()]);

            return;
        }

        $reopen = $existing->status === BiometricException::RESOLVED
            && ($existing->resolution === 'auto' || $existing->details != $details);

        $existing->forceFill($values + ($reopen
            ? ['status' => BiometricException::OPEN, 'resolution' => null, 'resolved_at' => null, 'resolved_by' => null]
            : []))->save();
    }

    protected function agrees(?Carbon $a, ?Carbon $b): bool
    {
        if (!$a || !$b) {
            return $a === $b;
        }

        return abs($a->diffInMinutes($b)) <= self::TOLERANCE_MINUTES;
    }

    protected function span(?Carbon $in, ?Carbon $out): string
    {
        return ($in?->format('H:i') ?? '—') . '–' . ($out?->format('H:i') ?? '—');
    }

}
