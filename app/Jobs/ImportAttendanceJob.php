<?php

namespace App\Jobs;

use App\Imports\AttendanceImport;
use App\Models\Attendance;
use App\Models\EmployeeShiftSchedule;
use App\Models\User;
use App\Traits\ExcelImportable;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAttendanceJob implements ShouldQueue
{

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ExcelImportable;

    private $rows;
    private $row;
    private $columns;
    private $company;

    /**
     * $rows is a chunk of many punch rows, not a single one — one job per row meant
     * tens of thousands of queue reserve/delete/serialize round trips for a large
     * import; processing a chunk per job removes nearly all of that overhead while
     * each row still gets its own transaction and failure handling below.
     *
     * @return void
     */
    public function __construct(array $rows, $columns, $company = null)
    {
        $this->rows = $rows;
        $this->columns = $columns;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->rows as $row) {
            $this->row = $row;
            $this->processRow($row);
        }
    }

    private function processRow($row)
    {
        if (!($this->isColumnExists('employee_card_id') && $this->isColumnExists('name') && $this->isColumnExists('type') && $this->isColumnExists('punch_time'))) {
            $this->recordRowFailure(__('messages.invalidData') . $this->getRowValuesAsString($row));

            return;
        }

        $employeeCardId = trim((string) $this->getColumnValue('employee_card_id'));
        $importedName = trim((string) $this->getColumnValue('name'));
        $punchType = strtolower(trim((string) $this->getColumnValue('type')));

        // user that have employee role, matched by employee card ID and verified by name
        $user = User::with('employeeDetail')
            ->whereHas('employeeDetail', function ($q) use ($employeeCardId) {
                $q->where('employee_card_id', $employeeCardId)
                    ->where('company_id', $this->company?->id);
            })
            ->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })
            ->first();

        if (!$user || strcasecmp(trim($user->name), $importedName) !== 0) {
            $this->recordRowFailure(__('messages.employeeNotFound') . ' ' . $this->getRowValuesAsString($row));

            return;
        }

        if (!in_array($punchType, ['in', 'out'])) {
            $this->recordRowFailure(__('messages.invalidData') . ' ' . $this->getRowValuesAsString($row));

            return;
        }

        $punchTime = AttendanceImport::parsePunchTime($this->getColumnValue('punch_time'), $this->company?->timezone);

        if (!$punchTime) {
            $this->recordRowFailure(__('messages.invalidDate') . ' ' . $this->getRowValuesAsString($row));

            return;
        }

        $punchTime = $punchTime->timezone('UTC');

        DB::beginTransaction();

        try {
            $this->applyPunch($user, $punchType, $punchTime);

            DB::commit();
        } catch (InvalidFormatException $e) {
            DB::rollBack();
            $this->recordRowFailure(__('messages.invalidDate') . ' ' . $this->getRowValuesAsString($row));
        } catch (\Throwable $e) {
            // Catches fatals (e.g. a transient DB lock timeout), not just Exception — without
            // this, a mid-row failure could leave this row's transaction dangling and hold
            // locks that break unrelated requests (e.g. every page load's last_activity update).
            DB::rollBack();
            $this->recordRowFailure($e->getMessage());
        }
    }

    /**
     * The actual in/out pairing logic, kept separate from processRow() (which deals with parsing
     * a raw Excel row) so a data-repair script can replay reconstructed punch events through the
     * exact same tested logic without needing a source file — see the reprocessing done to fix
     * sessions created before the auto-clock-out cutoff existed.
     */
    public function applyPunch(User $user, string $punchType, Carbon $punchTime): void
    {
        // A session left open past its own shift's Auto Clock Out Time (e.g. no matching 'out'
        // punch the same evening) must not stay open indefinitely waiting for whatever punch
        // shows up next — otherwise an unrelated punch a day (or more) later gets paired with
        // it, producing sessions that span into "1 day 4 hours" with tens of hours of bogus
        // overtime. Force-close it at its real cutoff before handling the current punch.
        $this->autoCloseStaleOpenSession($user, $punchTime);

        if ($punchType == 'in') {
            // Multiple door swipes on the way in (e.g. Entrance Door, then a floor entrance
            // seconds later) arrive as separate 'in' events for the same arrival — treating
            // every one as a new session fragments a single day's attendance into several
            // bogus partial sessions. If there's already an open session from earlier the
            // same day, this is a redundant swipe, not a new clock-in.
            $alreadyClockedInToday = Attendance::where('user_id', $user->id)
                ->where('company_id', $this->company?->id)
                ->whereNull('clock_out_time')
                ->whereDate('clock_in_time', $punchTime->toDateString())
                ->exists();

            if ($alreadyClockedInToday) {
                return;
            }

            $timezone = $this->company?->timezone ?? 'UTC';
            $localDate = $punchTime->copy()->timezone($timezone)->toDateString();
            $clockInLocal = $punchTime->copy()->timezone($timezone);
            $shift = $this->getShiftForDate($user, $localDate);

            $todaysRows = Attendance::where('user_id', $user->id)
                ->where('company_id', $this->company?->id)
                ->whereDate('clock_in_time', $punchTime->toDateString())
                ->get(['clock_in_time', 'clock_out_time']);

            // Real sessions only — the zero-duration orphan-out rows below (clock_in_time ==
            // clock_out_time) aren't check-ins and shouldn't count against the day's cap.
            $realSessionsToday = $todaysRows->filter(function ($row) {
                return is_null($row->clock_out_time) || !$row->clock_out_time->eq($row->clock_in_time);
            })->count();

            if ($shift && !is_null($shift->clockin_in_day) && $realSessionsToday >= (int) $shift->clockin_in_day) {
                // Maximum check-in allowed in a day already reached — this 'in' is extra device
                // noise (a re-swipe, a misread), not a genuine new session. Drop it, same as the
                // "already clocked in" case above; a later 'out' with nothing open to pair with
                // still gets recorded as an orphan, so the punch itself is never silently lost.
                return;
            }

            // late/half_day are computed here from the real historical clock-in time and set
            // explicitly (even when 'no') so AttendanceObserver::creating()'s own auto-calculation
            // — which uses the wall-clock time *when the row is created*, not this clock-in time —
            // never overrides them. Only meaningful for the day's first session; a lunch-break
            // second session isn't a fresh "arrival" in the late/half-day sense.
            $isFirstSessionToday = $todaysRows->isEmpty();

            $lateFlag = ($isFirstSessionToday && $this->isLateArrival($shift, $clockInLocal, $localDate, $timezone)) ? 'yes' : 'no';
            $halfDayFlag = ($isFirstSessionToday && $this->isHalfDayArrival($shift, $clockInLocal, $localDate, $timezone)) ? 'yes' : 'no';

            // Re-running the same import file (e.g. retrying after a earlier failed attempt)
            // must not create a second row for a punch that was already imported.
            Attendance::firstOrCreate(
                [
                    'company_id' => $this->company?->id,
                    'user_id' => $user->id,
                    'clock_in_time' => $punchTime->format('Y-m-d H:i:s'),
                ],
                [
                    'clock_in_ip' => '127.0.0.1',
                    'late' => $lateFlag,
                    'half_day' => $halfDayFlag,
                ]
            );
        }
        else {
            // Close the most recent open (not yet clocked-out) session for this employee
            $openSession = Attendance::where('user_id', $user->id)
                ->where('company_id', $this->company?->id)
                ->whereNull('clock_out_time')
                ->where('clock_in_time', '<=', $punchTime)
                ->orderByDesc('clock_in_time')
                ->first();

            if ($openSession) {
                $openSession->clock_out_time = $punchTime->format('Y-m-d H:i:s');
                $openSession->clock_out_ip = '127.0.0.1';
                $openSession->save();
            }
            else {
                // Orphan Clock-Out with no matching Clock-In (e.g. a duplicate/misread device
                // punch): still record it as a zero-duration session rather than dropping it,
                // so no punch from the source file is silently lost. firstOrCreate() so
                // re-running the same import file doesn't duplicate this row either.
                Attendance::firstOrCreate(
                    [
                        'company_id' => $this->company?->id,
                        'user_id' => $user->id,
                        'clock_in_time' => $punchTime->format('Y-m-d H:i:s'),
                        'clock_out_time' => $punchTime->format('Y-m-d H:i:s'),
                    ],
                    [
                        'clock_in_ip' => '127.0.0.1',
                        'clock_out_ip' => '127.0.0.1',
                        // Set explicitly for the same reason as the branch above:
                        // left out, AttendanceObserver::creating() computes them
                        // from the wall-clock time the row is written, not from
                        // the punch. A backfill run after the late cutoff was
                        // stamping every one of these zero-duration rows late —
                        // and half-day too, when it ran after the half-day mark.
                        // They are not arrivals at all; they are an unpaired
                        // exit punch.
                        'late' => 'no',
                        'half_day' => 'no',
                    ]
                );
            }
        }
    }

    /**
     * Force-closes the employee's open session if the punch about to be processed falls after
     * that session's own shift's Auto Clock Out Time cutoff — mirrors what a live clock-in session
     * would have had happen to it, instead of leaving raw imported punches to pair across
     * unrelated hours/days.
     */
    private function autoCloseStaleOpenSession(User $user, Carbon $beforePunchTime): void
    {
        $openSession = Attendance::where('user_id', $user->id)
            ->where('company_id', $this->company?->id)
            ->whereNull('clock_out_time')
            ->where('clock_in_time', '<', $beforePunchTime)
            ->orderByDesc('clock_in_time')
            ->first();

        if (!$openSession) {
            return;
        }

        $cutoff = $this->autoClockOutCutoff($user, $openSession->clock_in_time);

        if (!$cutoff || $beforePunchTime->lessThanOrEqualTo($cutoff)) {
            // Still within this session's own auto-clock-out window (or its shift couldn't be
            // determined) — a later punch is still allowed to close it normally.
            return;
        }

        $openSession->clock_out_time = $cutoff->format('Y-m-d H:i:s');
        $openSession->clock_out_ip = null;
        $openSession->save();
    }

    /**
     * The timestamp (UTC) after which an open session starting at $clockInTimeUTC should have
     * been auto-clocked-out, based on the shift assigned to the employee on that calendar day —
     * null if no shift assignment exists for that day, since a cutoff can't be determined.
     */
    private function autoClockOutCutoff(User $user, Carbon $clockInTimeUTC): ?Carbon
    {
        $timezone = $this->company?->timezone ?? 'UTC';
        $localDate = $clockInTimeUTC->copy()->timezone($timezone)->toDateString();

        $shift = $this->getShiftForDate($user, $localDate);

        if (!$shift || !$shift->office_start_time || !$shift->office_end_time || is_null($shift->auto_clock_out_time)) {
            return null;
        }

        $shiftStart = Carbon::parse($localDate . ' ' . $shift->office_start_time, $timezone);
        $shiftEnd = Carbon::parse($localDate . ' ' . $shift->office_end_time, $timezone);

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        return $shiftEnd->addHours((float) $shift->auto_clock_out_time)->timezone('UTC');
    }

    /**
     * The shift assigned to this employee on the given calendar day (via the shift roster) —
     * null if no assignment exists, since none of Auto Clock Out / late / half-day / max
     * check-ins can be safely enforced without knowing which shift's config applies.
     */
    private function getShiftForDate(User $user, string $localDate)
    {
        return EmployeeShiftSchedule::where('user_id', $user->id)->where('date', $localDate)->first()?->shift;
    }

    /**
     * Whether $clockInLocal counts as a late arrival under the shift's own Late Mark After
     * (minutes) setting — only meaningful for Strict Timings shifts, matching how
     * AttendanceObserver gates its own (wall-clock-based) late calculation the same way.
     */
    private function isLateArrival($shift, Carbon $clockInLocal, string $localDate, string $timezone): bool
    {
        if (!$shift || $shift->shift_type !== 'strict' || !$shift->office_start_time || is_null($shift->late_mark_duration)) {
            return false;
        }

        $lateAfter = Carbon::parse($localDate . ' ' . $shift->office_start_time, $timezone)
            ->addMinutes((int) $shift->late_mark_duration);

        return $clockInLocal->greaterThan($lateAfter);
    }

    /**
     * Whether $clockInLocal arrived after the shift's Half-day Mark Time — the arrival-based
     * half-day rule, computed against the real historical clock-in instead of the wall-clock
     * "now" AttendanceObserver would otherwise use for a bulk-imported row.
     */
    private function isHalfDayArrival($shift, Carbon $clockInLocal, string $localDate, string $timezone): bool
    {
        if (!$shift || !$shift->halfday_mark_time) {
            return false;
        }

        $halfDayMark = Carbon::parse($localDate . ' ' . $shift->halfday_mark_time, $timezone);

        return $clockInLocal->greaterThan($halfDayMark);
    }

    /**
     * One job now covers many rows, so a single bad row can't be handled by failing the whole
     * job (that would abort every other row still left in this chunk) — instead the failure is
     * written directly to failed_jobs, in the same shape import.import_exception already reads,
     * so the existing "failed rows" display keeps working per-row instead of per-chunk.
     */
    private function recordRowFailure(string $message)
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => $this->job?->getConnectionName() ?? 'database',
            'queue' => $this->job?->getQueue() ?? '',
            'payload' => '',
            'exception' => $message,
            'failed_at' => now(),
        ]);
    }

}
