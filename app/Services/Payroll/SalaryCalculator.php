<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * The company's salary formula, in one place.
 *
 * Taken from the payroll calculation sheet, column for column:
 *   Basic          = Gross x basic %             (the employee's own % where one is set)
 *   HRA            = Basic x HRA %
 *   CA             = IF(Basic > threshold, high, low)   (or on Gross: ca_basis)
 *   LTA            = ROUNDUP(Basic x LTA %)
 *   CEA            = IF(Basic < threshold, low, high)
 *   Special        = Gross - Basic - HRA - CA - LTA - CEA   ("other" / "others")
 *   PF             = MIN(Basic, ceiling) x (employee % + employer %)
 *   PF after LOP   = ROUND(PF x present / days)
 *   ESI            = IF(Gross <= threshold, Gross x (employee % + employer %), 0)
 *   ESI after LOP  = ROUND(ESI x present / days)
 *   PT             = by salary band, on the full gross
 *   Net Salary     = Gross - PF after LOP - ESI after LOP - PT - TDS
 *   Per day        = Net Salary / days in the cycle
 *   After LOP      = LOP days x Per day
 *   Final          = ROUND(Net Salary - After LOP)
 *
 * Every rate is a setting (payroll_calculations); charging PF and ESI on the
 * days paid and rounding to the rupee are settings too, so a company can turn
 * either off and get the older full-month figures.
 *
 * The payroll run, the export, the calculator page, settlements and the
 * payslips all compute from here, so they cannot drift apart.
 */
class SalaryCalculator
{

    protected object $settings;

    public function __construct(?object $settings = null)
    {
        $this->settings = $settings ?? DB::table('payroll_calculations')->orderByDesc('id')->first()
            ?? (object) ['basic_pct' => 40, 'hra_pct' => 40, 'ca_threshold' => 20000, 'ca_high' => 1600,
                'ca_low' => 800, 'ca_basis' => 'basic', 'lta_pct' => 10, 'cea_threshold' => 15000, 'cea_low' => 1200,
                'cea_high' => 2400, 'pt' => 200, 'esi_threshold' => 21000, 'esi_pct' => 0.75,
                'esi_employer_pct' => 3.25, 'pf_pct' => 12, 'pf_employer_pct' => 12, 'pf_wage_ceiling' => 25000,
                'prorate_statutory' => 1, 'round_net' => 1, 'pt_slabs' => null];
    }

    public function settings(): object
    {
        return $this->settings;
    }

    /**
     * One employee's pay for a month.
     *
     * @param  float  $ctc         annual cost to company
     * @param  ?float $basicPct    the employee's own basic %, where they have one
     * @return array<string, float>
     */
    public function forEmployee(float $ctc, ?float $basicPct, int $workingDays, float $daysPresent, float $tds = 0.0): array
    {
        $s = $this->settings;
        $gross = $ctc / 12;
        $round = $this->flag('round_net', true);
        $prorate = $this->flag('prorate_statutory', true);
        $money = fn (float $v) => $round ? round($v) : round($v, 2);

        // The employee's own basic % wins; otherwise the company default.
        $pct = ($basicPct !== null && $basicPct > 0 && $basicPct <= 100) ? $basicPct : (float) $s->basic_pct;

        $basic = $gross * $pct / 100;
        $hra = $basic * (float) $s->hra_pct / 100;

        // CA by slab, judged on Basic as the sheet does (or on Gross).
        $caOn = ($s->ca_basis ?? 'basic') === 'gross' ? $gross : $basic;
        $ca = $caOn > (float) $s->ca_threshold ? (float) $s->ca_high : (float) $s->ca_low;

        // Leave Travel Allowance, rounded up to the rupee; Children Education
        // Allowance by slab on Basic.
        $lta = isset($s->lta_pct) ? ceil(round($basic * (float) $s->lta_pct / 100, 6)) : 0.0;
        $cea = isset($s->cea_threshold)
            ? ($basic < (float) $s->cea_threshold ? (float) ($s->cea_low ?? 0) : (float) ($s->cea_high ?? 0))
            : 0.0;

        // A high basic % can leave nothing for the allowances: at 80% of 25,000
        // basic and HRA alone come to 30,000, and Special went negative so the
        // slip still added up to gross. The allowances give way instead, in
        // order, so every line is a real amount.
        $room = fn () => max(0.0, $gross - $basic - $hra - $ca - $lta - $cea);
        $hra = min($hra, max(0.0, $gross - $basic));
        $ca = min($ca, max(0.0, $gross - $basic - $hra));
        $lta = min($lta, max(0.0, $gross - $basic - $hra - $ca));
        $cea = min($cea, max(0.0, $gross - $basic - $hra - $ca - $lta));
        $other = $room();   // the Special Allowance

        // The share of the cycle that is paid, which PF and ESI follow.
        $days = max(0, $workingDays);
        $present = min((float) $days, max(0.0, $daysPresent));
        $paidShare = $prorate && $days > 0 ? $present / $days : 1.0;

        // PF, both sides, on Basic up to the ceiling; then on the days paid.
        $pfEmployeePct = (float) $s->pf_pct;
        $pfEmployerPct = isset($s->pf_employer_pct) && $s->pf_employer_pct !== null && $s->pf_employer_pct !== ''
            ? (float) $s->pf_employer_pct : $pfEmployeePct;
        $ceiling = (float) ($s->pf_wage_ceiling ?? 0);
        $pfWages = $ceiling > 0 ? min($basic, $ceiling) : $basic;
        $pfFull = $pfWages * ($pfEmployeePct + $pfEmployerPct) / 100;
        $pf = $prorate ? $money($pfFull * $paidShare) : $pfFull;
        [$pfEmployee, $pfEmployer] = $this->split($pf, $pfEmployeePct, $pfEmployerPct);

        // ESI, both sides, while gross is within the limit; then on the days paid.
        $esiEmployeePct = (float) $s->esi_pct;
        $esiEmployerPct = (float) ($s->esi_employer_pct ?? $s->esi_pct);
        $esiFull = $gross <= (float) $s->esi_threshold ? $gross * ($esiEmployeePct + $esiEmployerPct) / 100 : 0.0;
        $esi = $prorate ? $money($esiFull * $paidShare) : $esiFull;
        [$esiEmployee, $esiEmployer] = $this->split($esi, $esiEmployeePct, $esiEmployerPct);

        $pt = $this->professionalTax($gross);
        // Income tax is a figure per employee, set by the accountant.
        $tds = max(0.0, $tds);

        $net = $gross - $pf - $esi - $pt - $tds;
        $unpaidDays = max(0.0, $days - $present);
        $perDay = $days > 0 ? $net / $days : 0.0;
        $afterLop = $unpaidDays * $perDay;

        // The final figure is rounded to the rupee, as on the sheet. The
        // attendance deduction printed on the slip is whatever takes Net down
        // to it, so the slip adds up from its own lines. With no unpaid day
        // there is nothing to round against, so Net stands as it is.
        $finalNet = $unpaidDays > 0 ? max(0.0, $money($net - $afterLop)) : $net;
        $lopAmount = $net - $finalNet;

        return [
            'gross' => $gross, 'basic' => $basic, 'basic_pct' => $pct, 'hra' => $hra, 'ca' => $ca,
            'lta' => $lta, 'cea' => $cea, 'other' => $other, 'special' => $other,
            'pf_wages' => $pfWages, 'pf_full' => $pfFull,
            'pf' => $pf, 'pf_employee' => $pfEmployee, 'pf_employer' => $pfEmployer,
            'esi_full' => $esiFull,
            'esi' => $esi, 'esi_employee' => $esiEmployee, 'esi_employer' => $esiEmployer,
            'pt' => $pt, 'tds' => $tds, 'net' => $net,
            'working_days' => $days, 'days_present' => $present, 'lop_days' => $unpaidDays,
            'per_day' => $perDay, 'after_lop' => $afterLop, 'lop_amount' => $lopAmount, 'final_net' => $finalNet,
            'paid_share' => $paidShare, 'prorated' => $prorate, 'pf_employer_pct' => $pfEmployerPct,
        ];
    }

    /**
     * A contribution split between employee and employer by their rates. The
     * employee's share is taken to the paisa and the employer has the rest, so
     * the two always add back to the figure on the sheet.
     *
     * @return array{0: float, 1: float}
     */
    protected function split(float $total, float $employeePct, float $employerPct): array
    {
        $rates = $employeePct + $employerPct;
        $employee = $rates > 0 ? round($total * $employeePct / $rates, 2) : 0.0;

        return [$employee, round($total - $employee, 2)];
    }

    /** A yes/no setting, with its default where the column is not there yet. */
    protected function flag(string $key, bool $default): bool
    {
        return isset($this->settings->{$key}) ? (bool) $this->settings->{$key} : $default;
    }

    /**
     * Professional tax for a salary, from the configured bands.
     *
     * The first band the salary falls within decides it; a band with no upper
     * limit covers everything above. With no bands the flat figure is used.
     */
    public function professionalTax(float $gross): float
    {
        $slabs = $this->settings->pt_slabs ?? null;
        $slabs = is_string($slabs) ? json_decode($slabs, true) : $slabs;

        if (!is_array($slabs) || $slabs === []) {
            return (float) $this->settings->pt;
        }

        foreach ($slabs as $slab) {
            $upto = $slab['upto'] ?? null;

            if ($upto === null || $upto === '' || $gross <= (float) $upto) {
                return (float) ($slab['amount'] ?? $this->settings->pt);
            }
        }

        return (float) $this->settings->pt;
    }

}
