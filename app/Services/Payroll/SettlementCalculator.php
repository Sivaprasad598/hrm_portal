<?php

namespace App\Services\Payroll;

use App\Services\Leaves\EarnedLeavePolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What is owed to somebody who is leaving.
 *
 * Six things to settle, in the order anybody arguing about them would take:
 *
 *   salary for the days worked in the final month
 *   leave not taken, paid out
 *   gratuity, where the years served qualify
 *   less notice not served
 *   less advances still outstanding
 *   less amounts to recover for assets lost or damaged
 *   less the statutory deductions on the final pay
 *
 * Every figure is produced with its working, because a leaver reads this line
 * by line and will ask about each one.
 */
class SettlementCalculator
{

    public function __construct(
        protected PayComponents $components,
    ) {
    }

    /**
     * Draft a settlement. Nothing is saved; this is what the screen shows.
     *
     * @return array<string, mixed>
     */
    public function draft(int $userId, Carbon $lastWorkingDay, array $overrides = []): array
    {
        $employee = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.id', $userId)
            ->first(['u.id', 'u.name', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds',
                'ed.employee_card_id', 'ed.joining_date']);

        if (!$employee) {
            return $this->refuse('That employee is not on record.');
        }

        if ((float) $employee->employee_ctc <= 0) {
            return $this->refuse($employee->name . ' has no salary on record, so nothing can be worked out.');
        }

        $settings = (new SalaryCalculator())->settings();
        $workings = [];

        // ---- the final month's salary -----------------------------------
        $cycle = PayPeriod::forMonth((int) $lastWorkingDay->month, (int) $lastWorkingDay->year);
        $days = (new AttendanceDays())->forRange($userId, $cycle->start, min($lastWorkingDay, $cycle->end));

        // As on every payslip: the day's pay is the month's net over all the
        // days of the salary period (30 / 31), and only the days up to the last
        // working day are paid. Dividing by the days up to the last day instead
        // paid a whole month for a part of one.
        $periodDays = $cycle->days();
        $pay = (new SalaryCalculator())->forEmployee(
            (float) $employee->employee_ctc,
            $employee->basic_for_payroll !== null ? (float) $employee->basic_for_payroll : null,
            $periodDays,
            $days['days_present'],
            (float) ($employee->monthly_tds ?? 0)
        );

        // The month's pay before PF, ESI and tax: gross less the attendance
        // deduction. Those come off once, below. (This used to start from Net,
        // which is after them, and then take the employee's share off again.)
        $statutory = round($pay['pf'] + $pay['esi'] + $pay['pt'] + $pay['tds'], 2);
        $salaryDue = round($pay['final_net'] + $statutory, 2);
        $workings[] = ['Salary for ' . $cycle->describe(), $salaryDue,
            sprintf('%s day(s) paid of the %s in the period, up to %s',
                rtrim(rtrim(number_format($days['days_present'], 2), '0'), '.'),
                $periodDays, $lastWorkingDay->format('d M Y'))];

        // ---- leave not taken --------------------------------------------
        $leave = $this->leaveBalance($userId);
        $perDay = $periodDays > 0 ? $pay['net'] / $periodDays : 0.0;
        $leaveDays = (float) ($overrides['leave_days'] ?? $leave['days']);
        $leaveRate = $this->leaveDayRate($employee);
        $encashment = round($leaveDays * ($leaveRate['exact_rate'] ?? 0), 2);

        if ($encashment > 0) {
            $workings[] = ['Leave not taken', $encashment,
                sprintf('%s day(s) of %s at %s a day (%s)', rtrim(rtrim(number_format($leaveDays, 2), '0'), '.'),
                    $leave['types'] ?: 'leave', number_format((float) $leaveRate['rate'], 2), $leaveRate['source'])];
        } elseif ($leave['note'] !== '') {
            $workings[] = ['Leave not taken', 0.0, $leave['note']];
        }

        // ---- gratuity ----------------------------------------------------
        $gratuity = $this->gratuity($employee, $pay, $lastWorkingDay, $settings);

        if ($gratuity['amount'] > 0) {
            $workings[] = ['Gratuity', $gratuity['amount'], $gratuity['note']];
        } elseif ($gratuity['note'] !== '') {
            $workings[] = ['Gratuity', 0.0, $gratuity['note']];
        }

        $other = (float) ($overrides['bonus_and_other'] ?? 0);

        if ($other > 0) {
            $workings[] = ['Bonus and anything else agreed', $other, $overrides['other_note'] ?? ''];
        }

        $payable = round($salaryDue + $encashment + $gratuity['amount'] + $other, 2);

        // ---- notice not served -------------------------------------------
        $notice = $this->notice($userId, $lastWorkingDay, $perDay, $overrides);

        if ($notice['recovery'] > 0) {
            $workings[] = ['Notice not served', -$notice['recovery'], $notice['note']];
        }

        // ---- advances still owed -----------------------------------------
        $advances = $this->advancesOutstanding($userId);

        if ($advances['amount'] > 0) {
            $workings[] = ['Advances still owed', -$advances['amount'], $advances['note']];
        }

        // ---- assets lost or damaged, and assets still out -----------------
        $assets = $this->assetRecovery($userId, $employee->joining_date);

        if ($assets['amount'] > 0) {
            $workings[] = ['Assets lost or damaged', -$assets['amount'], $assets['note']];
        }

        if ($assets['unreturned'] !== '') {
            $workings[] = ['Assets not yet returned', 0.0, $assets['unreturned']];
        }

        $otherRecovery = (float) ($overrides['other_recovery'] ?? 0);

        if ($otherRecovery > 0) {
            $workings[] = ['Anything else to recover', -$otherRecovery, $overrides['recovery_note'] ?? ''];
        }

        // ---- what the law takes off the final pay -------------------------
        if ($statutory > 0) {
            $workings[] = ['Provident fund, insurance, professional and income tax', -$statutory,
                'on the final month\'s salary; PF and ESI both shares, as on the payslip'];
        }

        $deductions = round($notice['recovery'] + $advances['amount'] + $assets['amount'] + $otherRecovery + $statutory, 2);
        $net = round($payable - $deductions, 2);

        $workings[] = ['Net payable', $net, $net < 0 ? 'owed by the employee to the company' : '', 'total'];

        return [
            'ok' => true,
            'user_id' => $userId,
            'name' => $employee->name,
            'code' => $employee->employee_card_id,
            'joined_on' => $employee->joining_date ? Carbon::parse($employee->joining_date)->toDateString() : null,
            'last_working_day' => $lastWorkingDay->toDateString(),
            'years_served' => $gratuity['years'],
            'salary_days' => $days['days_present'],
            'salary_due' => $salaryDue,
            'leave_days' => $leaveDays,
            'leave_encashment' => $encashment,
            'gratuity' => $gratuity['amount'],
            'bonus_and_other' => $other,
            'total_payable' => $payable,
            'notice_shortfall_days' => $notice['shortfall'],
            'notice_recovery' => $notice['recovery'],
            'advances_outstanding' => $advances['amount'],
            'asset_recovery' => $assets['amount'],
            'assets_unreturned' => $assets['unreturned'],
            'other_recovery' => $otherRecovery,
            'statutory_deductions' => $statutory,
            'total_deductions' => $deductions,
            'net_payable' => $net,
            'per_day' => round($perDay, 2),
            'workings' => $workings,
            'message' => null,
        ];
    }

    /**
     * Amounts recorded against the employee for assets they lost or damaged in
     * this employment (Assets -> Take Return), and the assets still with them,
     * which have to be taken back or written off before the settlement is final.
     *
     * @return array{amount: float, note: string, unreturned: string}
     */
    public function assetRecovery(int $userId, $joinedOn): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('asset_assignments')) {
            return ['amount' => 0.0, 'note' => '', 'unreturned' => ''];
        }

        $rows = DB::table('asset_assignments as aa')->join('assets as a', 'a.id', '=', 'aa.asset_id')
            ->where('aa.user_id', $userId)
            ->when($joinedOn, fn ($q) => $q->whereDate('aa.assigned_on', '>=', Carbon::parse($joinedOn)->toDateString()))
            ->get(['a.name', 'a.asset_code', 'aa.returned_on', 'aa.condition_in', 'aa.recovery_amount']);

        $charged = $rows->filter(fn ($r) => $r->returned_on && (float) $r->recovery_amount > 0);
        $out = $rows->whereNull('returned_on');

        return [
            'amount' => round((float) $charged->sum(fn ($r) => (float) $r->recovery_amount), 2),
            'note' => $charged->map(fn ($r) => $r->name . ' (' . $r->asset_code . ', ' . ($r->condition_in === 'lost' ? 'lost' : 'damaged')
                . ') ' . number_format((float) $r->recovery_amount, 2))->implode('; '),
            'unreturned' => $out->isEmpty() ? '' : $out->count() . ' still with the employee: '
                . $out->map(fn ($r) => $r->name . ' (' . $r->asset_code . ')')->implode(', ')
                . '. Take them back, or record them as lost with an amount to recover, before agreeing the settlement.',
        ];
    }

    /**
     * Gratuity: so many days' pay for each year served, once the service
     * qualifies, capped.
     *
     * @return array{amount: float, years: float, note: string}
     */
    protected function gratuity(object $employee, array $pay, Carbon $lastDay, object $settings): array
    {
        if (!$employee->joining_date) {
            return ['amount' => 0.0, 'years' => 0.0, 'note' => 'No joining date on record, so service cannot be counted.'];
        }

        $joined = Carbon::parse($employee->joining_date);
        $years = round($joined->floatDiffInYears($lastDay), 2);
        $qualifying = (float) ($settings->gratuity_years ?? 5);

        if ($years < $qualifying) {
            return ['amount' => 0.0, 'years' => $years,
                'note' => sprintf('%s year(s) served; %s are needed, so none is payable.',
                    rtrim(rtrim(number_format($years, 2), '0'), '.'),
                    rtrim(rtrim(number_format($qualifying, 1), '0'), '.'))];
        }

        $daysPerYear = (float) ($settings->gratuity_days_per_year ?? 15);
        $ceiling = (float) ($settings->gratuity_ceiling ?? 0);

        // The convention is a month of 26 working days on the last drawn basic.
        $monthlyBasic = $pay['basic'];
        $amount = round(($monthlyBasic / 26) * $daysPerYear * floor($years), 2);
        $note = sprintf('%s day(s) of basic for each of %d year(s), on a 26-day month',
            rtrim(rtrim(number_format($daysPerYear, 1), '0'), '.'), (int) floor($years));

        if ($ceiling > 0 && $amount > $ceiling) {
            $amount = $ceiling;
            $note .= ', capped at ' . number_format($ceiling);
        }

        return ['amount' => $amount, 'years' => $years, 'note' => $note];
    }

    /**
     * Notice not served, recovered at the daily rate.
     *
     * @return array{shortfall: float, recovery: float, note: string}
     */
    protected function notice(int $userId, Carbon $lastDay, float $perDay, array $overrides): array
    {
        if (array_key_exists('notice_shortfall_days', $overrides)) {
            $shortfall = max(0.0, (float) $overrides['notice_shortfall_days']);

            return [
                'shortfall' => $shortfall,
                'recovery' => round($shortfall * $perDay, 2),
                'note' => $shortfall > 0 ? 'entered by hand' : '',
            ];
        }

        $resignation = DB::table('resignations')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('resignations', 'closed_by_rejoin_id'),
                fn ($q) => $q->whereNull('closed_by_rejoin_id'))
            ->orderByDesc('id')
            ->first(['notice_period_days', 'notice_start_date', 'last_working_date', 'notice_served']);

        if (!$resignation || !$resignation->notice_start_date || !$resignation->notice_period_days) {
            return ['shortfall' => 0.0, 'recovery' => 0.0, 'note' => ''];
        }

        $required = (int) $resignation->notice_period_days;
        $served = Carbon::parse($resignation->notice_start_date)->diffInDays($lastDay) + 1;
        $shortfall = max(0.0, $required - $served);

        return [
            'shortfall' => $shortfall,
            'recovery' => round($shortfall * $perDay, 2),
            'note' => $shortfall > 0
                ? sprintf('%d day(s) required, %d served', $required, $served)
                : sprintf('%d day(s) required and served in full', $required),
        ];
    }

    /**
     * Advances and loans with a balance still owed.
     *
     * @return array{amount: float, note: string}
     */
    protected function advancesOutstanding(int $userId): array
    {
        $arrangements = DB::table('payroll_arrangements')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('total_amount')
            ->get(['label', 'total_amount', 'recovered']);

        $amount = 0.0;
        $names = [];

        foreach ($arrangements as $arrangement) {
            $left = max(0.0, (float) $arrangement->total_amount - (float) $arrangement->recovered);

            if ($left > 0) {
                $amount += $left;
                $names[] = $arrangement->label;
            }
        }

        return [
            'amount' => round($amount, 2),
            'note' => $names === [] ? '' : implode(', ', $names),
        ];
    }

    /**
     * Leave the employee has not taken, from whatever the leave module says.
     *
     * @return array{days: float, note: string}
     */
    /**
     * Leave days to be paid out: the remaining balance of the leave types set
     * to be encashed (Leave Types -> Encashed). Casual, sick and the like lapse
     * on leaving and are not paid.
     *
     * @return array{days: float, types: string, note: string}
     */
    protected function leaveBalance(int $userId): array
    {
        try {
            // The leave module keeps the balance itself, so it is read rather
            // than worked out again here.
            $quotas = DB::table('employee_leave_quotas as q')
                ->join('leave_types as t', 't.id', '=', 'q.leave_type_id')
                ->where('q.user_id', $userId)
                ->whereNull('t.deleted_at')
                ->get(['t.id as type_id', 't.type_name', 't.encashed', 'q.no_of_leaves', 'q.leaves_used', 'q.leaves_remaining']);
            $earnedLeave = new \App\Services\Leaves\EarnedLeavePolicy();
            $notes = [];

            $days = 0.0;
            $types = [];
            $lapsing = 0.0;

            foreach ($quotas as $quota) {
                $left = max(0.0, $quota->leaves_remaining !== null
                    ? (float) $quota->leaves_remaining
                    : (float) $quota->no_of_leaves - (float) $quota->leaves_used);

                if ((int) $quota->encashed === 1) {
                    // Earned Leave: paid out only after the years of service, and
                    // only up to the most the policy pays.
                    if ($earnedLeave->isEarnedLeave((int) $quota->type_id) && $left > 0) {
                        if (!$earnedLeave->canUse($userId)) {
                            $notes[] = EarnedLeavePolicy::fmt($left) . ' day(s) of ' . $quota->type_name . ' not paid: '
                                . strtolower(rtrim($earnedLeave->refusal($userId), '.'));
                            continue;
                        }

                        $cap = $earnedLeave->encashMaxDays();

                        if ($cap !== null && $left > $cap) {
                            $notes[] = 'paid up to ' . EarnedLeavePolicy::fmt($cap) . ' of ' . EarnedLeavePolicy::fmt($left) . ' day(s) of ' . $quota->type_name;
                            $left = $cap;
                        }
                    }

                    $days += $left;
                    if ($left > 0) {
                        $types[] = $quota->type_name;
                    }
                } else {
                    $lapsing += $left;
                }
            }

            if ($days <= 0 && $lapsing > 0 && $notes === []) {
                $notes[] = 'No leave type is set to be encashed; ' . EarnedLeavePolicy::fmt($lapsing) . ' day(s) of other leave lapse';
            }

            return [
                'days' => round($days, 2),
                'types' => implode(', ', $types) . ($notes && $days > 0 ? '; ' . implode('; ', $notes) : ''),
                'note' => $days <= 0 ? implode('; ', $notes) : '',
            ];
        } catch (\Throwable $noQuotas) {
            return ['days' => 0.0, 'types' => '', 'note' => ''];
        }
    }

    /**
     * What a day of encashed leave is worth: the Leave Settings rule (basic or
     * gross, over the divisor), the same as the year-end encashment.
     *
     * @return array{rate: ?float, exact_rate: ?float, source: ?string}
     */
    protected function leaveDayRate(object $employee): array
    {
        try {
            $companyId = (int) (DB::table('employee_details')->where('user_id', $employee->id)->value('company_id') ?: 1);

            return (new \App\Services\Leaves\LeaveYearEnd($companyId, 1))->salaryBase($employee);
        } catch (\Throwable $noRule) {
            return ['rate' => null, 'exact_rate' => null, 'source' => null];
        }
    }

    /** @return array<string, mixed> */
    protected function refuse(string $why): array
    {
        return ['ok' => false, 'message' => $why, 'workings' => [], 'net_payable' => 0.0];
    }

}
