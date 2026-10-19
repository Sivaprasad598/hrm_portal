<?php

namespace App\Services\Employment;

use Illuminate\Support\Facades\DB;

/**
 * An employee's stretches of employment: joined, left, and joined again.
 *
 * The employee record (employee_details) holds the current period - its
 * joining date, and its exit date once they leave - because everything that
 * already reads those dates (attendance, payroll, leave, probation) should
 * see the employment that is running now. This table keeps every period,
 * so the first joining date and each exit and rejoin stay on record.
 *
 * The current period follows the employee record: saving the joining or exit
 * date, designation or department updates it (EmployeeDetailsObserver).
 */
class EmploymentPeriods
{
    /** Every period, oldest first. */
    public function all(int $userId)
    {
        return DB::table('employment_periods as p')
            ->leftJoin('designations as d', 'd.id', '=', 'p.designation_id')
            ->leftJoin('teams as t', 't.id', '=', 'p.department_id')
            ->leftJoin('settlements as s', 's.id', '=', 'p.settlement_id')
            ->where('p.user_id', $userId)
            ->orderBy('p.period_no')
            ->get(['p.*', 'd.name as designation_name', 't.team_name as department_name',
                's.status as settlement_status', 's.net_payable as settlement_amount']);
    }

    public function current(int $userId): ?object
    {
        return DB::table('employment_periods')->where('user_id', $userId)->orderByDesc('period_no')->first();
    }

    public function previous(int $userId): ?object
    {
        return DB::table('employment_periods')->where('user_id', $userId)->orderByDesc('period_no')->skip(1)->first();
    }

    /** The first period, for an employee created before periods existed or just added. */
    public function ensure(int $userId): object
    {
        $current = $this->current($userId);

        if ($current) {
            return $current;
        }

        $this->syncFromRecord($userId);

        return $this->current($userId);
    }

    /** Copy the employee record onto the current period (or start the first one). */
    public function syncFromRecord(int $userId): void
    {
        $d = DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
            ->where('ed.user_id', $userId)
            ->first(['ed.company_id', 'ed.joining_date', 'ed.last_date', 'ed.employee_id', 'ed.employee_card_id',
                'ed.designation_id', 'ed.department_id', 'ed.branch_id', 'u.employee_ctc']);

        if (!$d) {
            return;
        }

        $values = [
            'joined_on' => $d->joining_date ? substr((string) $d->joining_date, 0, 10) : null,
            'left_on' => $d->last_date ? substr((string) $d->last_date, 0, 10) : null,
            'employee_code' => $d->employee_id, 'employee_card_id' => $d->employee_card_id,
            'designation_id' => $d->designation_id, 'department_id' => $d->department_id, 'branch_id' => $d->branch_id,
            'employee_ctc' => $d->employee_ctc, 'updated_at' => now(),
        ];

        $current = $this->current($userId);

        if ($current) {
            DB::table('employment_periods')->where('id', $current->id)->update($values);
        } else {
            DB::table('employment_periods')->insert($values + [
                'company_id' => $d->company_id, 'user_id' => $userId, 'period_no' => 1, 'created_at' => now(),
            ]);
        }
    }

    /**
     * Whether a day is paid through payroll for this employee.
     *
     * Days of the current period are. Days of an earlier period are too, unless
     * a paid settlement already covered them - the final salary was paid there.
     * Days between periods are not: the person was not employed.
     *
     * Returns null when the employee has a single period, so callers keep
     * their own joining / exit date rule, which says the same thing.
     *
     * @return array<string, bool>|null  date => payable, for every date in the range
     */
    public function payableDays(int $userId, string $from, string $to): ?array
    {
        $periods = DB::table('employment_periods as p')
            ->leftJoin('settlements as s', 's.id', '=', 'p.settlement_id')
            ->where('p.user_id', $userId)->orderBy('p.period_no')
            ->get(['p.joined_on', 'p.left_on', 's.status as settlement_status', 's.last_working_day']);

        if ($periods->count() < 2) {
            return null;
        }

        $days = [];

        for ($day = \Carbon\Carbon::parse($from); $day->toDateString() <= $to; $day->addDay()) {
            $key = $day->toDateString();
            $payable = false;

            foreach ($periods as $i => $period) {
                $joined = $period->joined_on ? substr((string) $period->joined_on, 0, 10) : null;
                $left = $period->left_on ? substr((string) $period->left_on, 0, 10) : null;
                $isLast = $i === $periods->count() - 1;

                if ($joined && $key < $joined) {
                    continue;
                }

                if ($left && $key > $left) {
                    continue;
                }

                $settled = !$isLast && $period->settlement_status === 'paid'
                    && $period->last_working_day && $key <= substr((string) $period->last_working_day, 0, 10);
                $payable = !$settled;
                break;
            }

            $days[$key] = $payable;
        }

        return $days;
    }
}
