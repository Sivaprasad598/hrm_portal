<?php

namespace App\Services\Payroll;

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
        $pay = (new SalaryCalculator())->forEmployee(
            (float) $employee->employee_ctc,
            $employee->basic_for_payroll !== null ? (float) $employee->basic_for_payroll : null,
            $days['working_days'],
            $days['days_present'],
            (float) ($employee->monthly_tds ?? 0)
        );

        $salaryDue = round($pay['net'] - $pay['after_lop'], 2);
        $workings[] = ['Salary for ' . $cycle->describe(), $salaryDue,
            sprintf('%s day(s) of %s worked up to %s',
                rtrim(rtrim(number_format($days['days_present'], 2), '0'), '.'),
                $days['working_days'], $lastWorkingDay->format('d M Y'))];

        // ---- leave not taken --------------------------------------------
        $leave = $this->leaveBalance($userId);
        $perDay = $pay['working_days'] > 0 ? $pay['net'] / $pay['working_days'] : 0.0;
        $leaveDays = (float) ($overrides['leave_days'] ?? $leave['days']);
        $encashment = round($leaveDays * $perDay, 2);

        if ($encashment > 0) {
            $workings[] = ['Leave not taken', $encashment,
                sprintf('%s day(s) at %s a day', rtrim(rtrim(number_format($leaveDays, 2), '0'), '.'),
                    number_format($perDay, 2))];
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

        $otherRecovery = (float) ($overrides['other_recovery'] ?? 0);

        if ($otherRecovery > 0) {
            $workings[] = ['Anything else to recover', -$otherRecovery, $overrides['recovery_note'] ?? ''];
        }

        // ---- what the law takes off the final pay -------------------------
        $statutory = round($pay['pf_employee'] + $pay['esi_employee'] + $pay['pt'] + $pay['tds'], 2);

        if ($statutory > 0) {
            $workings[] = ['Provident fund, insurance, professional and income tax', -$statutory,
                'on the final month\'s salary'];
        }

        $deductions = round($notice['recovery'] + $advances['amount'] + $otherRecovery + $statutory, 2);
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
    protected function leaveBalance(int $userId): array
    {
        try {
            // The leave module keeps the balance itself, so it is read rather
            // than worked out again here.
            $quotas = DB::table('employee_leave_quotas')
                ->where('user_id', $userId)
                ->get(['leave_type_id', 'no_of_leaves', 'leaves_used', 'leaves_remaining']);

            $days = 0.0;

            foreach ($quotas as $quota) {
                $days += max(0.0, $quota->leaves_remaining !== null
                    ? (float) $quota->leaves_remaining
                    : (float) $quota->no_of_leaves - (float) $quota->leaves_used);
            }

            return ['days' => round($days, 2), 'note' => 'from the leave balances'];
        } catch (\Throwable $noQuotas) {
            return ['days' => 0.0, 'note' => ''];
        }
    }

    /** @return array<string, mixed> */
    protected function refuse(string $why): array
    {
        return ['ok' => false, 'message' => $why, 'workings' => [], 'net_payable' => 0.0];
    }

}
