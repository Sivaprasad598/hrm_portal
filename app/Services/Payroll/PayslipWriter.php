<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issues a payslip for one employee and month.
 *
 * The same rules the single-employee generator applies: a month that has not
 * started cannot be paid, a month that already holds an active payslip is
 * refused until that one is revoked, the figures must add up, the payslip is
 * numbered per financial year, and any leave encashment waiting for the
 * employee is paid on it.
 */
class PayslipWriter
{

    public function __construct(
        protected SalaryCalculator $calculator,
        protected AttendanceDays $days,
        protected PayComponents $components,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, payslip_id: ?int}
     */
    public function issue(int $userId, int $month, int $year, ?int $issuedBy = null): array
    {
        $cycle = PayPeriod::forMonth($month, $year);
        $period = $cycle->label;
        // Shift assignments may have changed since this process last looked.
        \App\Services\Attendance\ShiftResolver::reset();

        if ($cycle->start->gt(now())) {
            return $this->refuse($cycle->describe() . ' has not started yet');
        }

        $employee = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->where('u.id', $userId)
            ->first(['u.id', 'u.name', 'u.pan_number', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds',
                'ed.employee_card_id', 'ed.company_id', 'd.name as designation']);

        if (!$employee) {
            return $this->refuse('That employee no longer exists');
        }

        // The Super Admin login is the system account, not staff: never paid.
        if (\App\Services\Access\SystemAccounts::is($userId)) {
            return $this->refuse($employee->name . ' is the Super Admin login, not an employee');
        }

        if (!$employee->employee_card_id) {
            return $this->refuse($employee->name . ' has no employee code');
        }

        if ((float) $employee->employee_ctc <= 0) {
            return $this->refuse($employee->name . ' has no CTC on record');
        }

        // The cycle must be over, and for anyone on the device the feed must
        // reach its last day - see PipelineStatus::payslipBlocker().
        $pipeline = new \App\Services\Iapp\Pipeline\PipelineStatus();

        if ($blocker = $pipeline->payslipBlocker($userId, $cycle)) {
            return $this->refuse($employee->name . ': ' . $blocker);
        }

        $standing = DB::table('payslips')
            ->where('emp_id', (string) $employee->employee_card_id)->where('period', $period)
            ->where('status', 'active')->whereNull('deleted_at')->first(['id']);

        if ($standing) {
            return $this->refuse($employee->name . ' already has a payslip for ' . $period . '; revoke it first');
        }

        // Device days that have not been written into ERP attendance yet would
        // count as absence, so write them first. Skip mode: a day already holding
        // attendance from anywhere else is left as it is.
        if ($pipeline->deviceInUse() && $pipeline->deviceCodeFor($userId)
            && $pipeline->pendingPushDays($cycle->start, $cycle->end, [$userId]) > 0) {
            (new \App\Services\Iapp\Sync\AttendanceSync($pipeline->config()))->run(
                $cycle->start->copy()->startOfDay(),
                $cycle->end->copy()->endOfDay(),
                \App\Models\IappAttendanceSyncRun::MODE_SKIP,
                [$userId],
                $issuedBy
            );
        }

        $count = $this->days->forRange($userId, $cycle->start, $cycle->end);

        // The pay in force for this month (salary revisions): an earlier month is paid at the old rate.
        (new SalaryRevisions())->applyDue();
        [$ctcForMonth, $basicPct] = SalaryRevisions::payFor($userId, $cycle, (float) $employee->employee_ctc,
            $employee->basic_for_payroll !== null ? (float) $employee->basic_for_payroll : null);
        $employee->employee_ctc = $ctcForMonth;
        $pay = $this->calculator->forEmployee((float) $employee->employee_ctc, $basicPct,
            $count['working_days'], $count['days_present'], (float) ($employee->monthly_tds ?? 0));

        // OT bank: the LOP days it covers this period are paid days - decided in
        // the payroll run, or automatically if so set. The overtime itself is
        // banked once the payslip is written (below).
        $bank = new OtBank();
        $otBank = null;

        if ($bank->enabled((int) $employee->company_id) && ($count['basis'] ?? '') === 'rules') {
            $ot = (new OvertimeCalculator())->forEmployee((int) $employee->company_id, $cycle, $count, $pay);
            $otBank = $bank->summary($userId, (int) $employee->company_id, $cycle, $count, $pay, $ot);
            $otBank = $bank->autoCompensate($userId, (int) $employee->company_id, $cycle, $otBank);

            if ($otBank['comp_days'] > 0) {
                $count['days_present'] += min($otBank['comp_days'], $otBank['lop_days']);
                $pay = $this->calculator->forEmployee((float) $employee->employee_ctc, $basicPct,
                    $count['working_days'], $count['days_present'], (float) ($employee->monthly_tds ?? 0));
            }
        }

        // Somebody whose settlement has been paid has had their final money and
        // their advances closed; another payslip would pay them twice over.
        $settled = DB::table('settlements')
            ->where('user_id', $userId)
            ->where('status', 'paid')
            ->orderByDesc('last_working_day')
            ->value('last_working_day');

        // Someone who rejoined after the settlement is paid again from the
        // rejoining date; only the break itself is refused.
        $rejoinedOn = $settled ? DB::table('employment_periods')->where('user_id', $userId)
            ->where('joined_on', '>', substr((string) $settled, 0, 10))->min('joined_on') : null;

        if ($settled && $cycle->start->gt(\Carbon\Carbon::parse($settled))
            && (!$rejoinedOn || $cycle->end->lt(\Carbon\Carbon::parse($rejoinedOn)))) {
            return $this->refuse($employee->name . ' was settled on '
                . \Carbon\Carbon::parse($settled)->format('d M Y')
                . ', so this period is after they left');
        }

        if ($pay['working_days'] < 1) {
            return $this->refuse('No working days in ' . $period . ' for ' . $employee->name);
        }

        $settings = $this->calculator->settings();

        // Stored the way the payslip screens store it: net salary here is the
        // employee's side only (gross less their PF, ESI, PT and TDS); the
        // printed slip takes the employer's PF and ESI off as well, since CTC
        // includes them. The attendance deduction is the one the calculation
        // sheet arrives at — LOP days x per day, taken to the rounded final
        // figure — so the slip prints exactly the sheet's Net Salary.
        $netStored = $pay['gross'] - ($pay['pf_employee'] + $pay['esi_employee'] + $pay['pt'] + $pay['tds']);
        $perDay = round($pay['per_day'], 2);
        $attendanceNet = $netStored - $pay['lop_amount'];

        // The design this slip is printed in, fixed now. A payslip is a document
        // somebody was given: changing the setting later must not restyle what
        // has already been issued, e-mailed and filed.
        $template = DB::table('payslip_template_settings')
            ->when($employee->company_id, fn ($q) => $q->where('company_id', $employee->company_id))
            ->orderBy('id')
            ->value('template') ?: 'classic';

        $values = [
            'template' => $template,
            'gross' => $pay['gross'], 'basic_pct' => $pay['basic_pct'], 'hra_pct' => $settings->hra_pct,
            'ca_threshold' => $settings->ca_threshold, 'ca_high' => $settings->ca_high, 'ca_low' => $settings->ca_low,
            'pt' => $pay['pt'], 'tds' => $pay['tds'], 'esi_threshold' => $settings->esi_threshold, 'esi_pct' => $settings->esi_pct,
            'pf_pct' => $settings->pf_pct, 'pf_employer_pct' => $pay['pf_employer_pct'],
            'pf_wage_ceiling' => $settings->pf_wage_ceiling, 'prorate_statutory' => $pay['prorated'],
            'esi_employer_pct' => $settings->esi_employer_pct ?? null,
            'working_days' => $pay['working_days'], 'days_present' => $pay['days_present'],
            'lop_days' => $pay['lop_days'],
            'basic' => $pay['basic'], 'hra' => $pay['hra'], 'ca' => $pay['ca'],
            'lta' => $pay['lta'], 'cea' => $pay['cea'], 'others' => $pay['other'],   // others = Special Allowance
            'esi' => $pay['esi_employee'], 'pf' => $pay['pf_employee'],
            'employer_pf' => $pay['pf_employer'], 'employer_esi' => $pay['esi_employer'],
            'total_deductions' => $pay['pf_employee'] + $pay['esi_employee'] + $pay['pt'] + $pay['tds'],
            'net_salary' => $netStored, 'per_day' => $perDay, 'attendance_net' => $attendanceNet,
            'emp_name' => $employee->name, 'emp_id' => $employee->employee_card_id,
            'emp_designation' => $employee->designation, 'emp_pan' => $employee->pan_number,
            'period' => $period, 'period_start' => $cycle->start->toDateString(),
            'period_end' => $cycle->end->toDateString(),
            'user_id' => $employee->id, 'company_id' => $employee->company_id,
            'generated_by' => $issuedBy, 'generated_at' => now(), 'created_at' => now(),
        ];

        // The number is the highest so far plus one, so two runs at the same
        // moment can pick the same one; the unique index catches that and the
        // loser simply takes the next.
        for ($attempt = 1; ; $attempt++) {
            try {
                $values['payslip_number'] = $this->nextNumber($period);
                $id = DB::table('payslips')->insertGetId($values);
                break;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $clash) {
                if ($attempt >= 5) {
                    throw $clash;
                }
            }
        }

        $this->claimEncashment((int) $id, (string) $employee->employee_card_id);

        // Overtime, bonuses and recoveries for this period belong to this payslip
        // now, so the next one does not pay or recover them a second time.
        $this->components->attachToPayslip($userId, $cycle->start, (int) $id);

        if ($otBank) {
            $bank->onIssue($userId, (int) $employee->company_id, $cycle, (int) $id, $otBank);
        }

        return ['ok' => true, 'message' => $employee->name . ' — ' . $period, 'payslip_id' => (int) $id];
    }

    /** AROW/2026-27/000123, one run of numbers per financial year. */
    protected function nextNumber(string $period): string
    {
        $date = strtotime('first day of ' . str_replace(' - ', ' ', $period)) ?: time();
        $start = (int) date('n', $date) >= 4 ? (int) date('Y', $date) : (int) date('Y', $date) - 1;
        $label = $start . '-' . substr((string) ($start + 1), -2);

        $highest = (int) DB::table('payslips')
            ->where('payslip_number', 'like', 'AROW/' . $label . '/%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(payslip_number, '/', -1) AS UNSIGNED)), 0) AS n")
            ->value('n');

        return sprintf('AROW/%s/%06d', $label, $highest + 1);
    }

    /**
     * Leave encashed at a year end is paid on the employee's next payslip, and
     * each ledger row records the payslip it went out on so it is never paid twice.
     */
    protected function claimEncashment(int $payslipId, string $cardId): void
    {
        try {
            $userId = DB::table('employee_details')->where('employee_card_id', $cardId)->value('user_id');

            if (!$userId) {
                return;
            }

            $pending = DB::table('leave_encashments')->where('user_id', $userId)->where('status', 'pending')->get();
            $days = 0.0;
            $amount = 0.0;

            foreach ($pending as $row) {
                $claimed = DB::table('leave_encashments')->where('id', $row->id)->where('status', 'pending')
                    ->update(['status' => 'paid', 'payslip_id' => $payslipId, 'paid_at' => now(), 'updated_at' => now()]);

                if ($claimed === 1) {
                    $days += (float) $row->days;
                    $amount += (float) $row->amount;
                }
            }

            if ($amount > 0) {
                DB::table('payslips')->where('id', $payslipId)
                    ->update(['leave_encashment' => round($amount, 2), 'encashed_days' => $days]);
            }
        } catch (\Throwable) {
            // The payslip stands on its own; the encashment waits for the next one.
        }
    }

    /** @return array{ok: bool, message: string, payslip_id: null} */
    protected function refuse(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'payslip_id' => null];
    }

}
