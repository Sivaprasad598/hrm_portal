<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * The company's salary formula, in one place.
 *
 * Taken from the payroll calculation sheet:
 *   Basic  = Gross x basic %            (the employee's own % where one is set)
 *   HRA    = Basic x HRA %
 *   CA     = IF(Gross > threshold, high, low)
 *   Other  = Gross - Basic - HRA - CA
 *   PF     = IF(Basic < ceiling, Basic, ceiling) x 24%   (12% employee + 12% employer)
 *   ESI    = IF(Gross <= threshold, Gross x 4%, 0)       (0.75% employee + 3.25% employer)
 *   PT     = by salary band
 *   Net    = Gross - PF - ESI - PT
 *   Perday = Net / days in the month
 *   Final  = Net - (unpaid days x per day)
 *
 * The estimate screen, the export and the payslips all compute from here, so
 * they cannot drift apart.
 */
class SalaryCalculator
{

    protected object $settings;

    public function __construct(?object $settings = null)
    {
        $this->settings = $settings ?? DB::table('payroll_calculations')->orderByDesc('id')->first()
            ?? (object) ['basic_pct' => 30, 'hra_pct' => 50, 'ca_threshold' => 20000, 'ca_high' => 1600,
                'ca_low' => 800, 'pt' => 200, 'esi_threshold' => 21000, 'esi_pct' => 0.75,
                'esi_employer_pct' => 3.25, 'pf_pct' => 12, 'pf_wage_ceiling' => 15000, 'pt_slabs' => null];
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

        // The employee's own basic % wins; otherwise the company default.
        $pct = ($basicPct !== null && $basicPct > 0 && $basicPct <= 100) ? $basicPct : (float) $s->basic_pct;

        $basic = $gross * $pct / 100;
        $hra = $basic * (float) $s->hra_pct / 100;
        $ca = $gross > (float) $s->ca_threshold ? (float) $s->ca_high : (float) $s->ca_low;
        $other = $gross - $basic - $hra - $ca;

        $ceiling = (float) ($s->pf_wage_ceiling ?? 0);
        $pfWages = $ceiling > 0 ? min($basic, $ceiling) : $basic;
        $pfEmployee = $pfWages * (float) $s->pf_pct / 100;
        $pfEmployer = $pfEmployee;

        $esiApplies = $gross <= (float) $s->esi_threshold;
        $esiEmployee = $esiApplies ? $gross * (float) $s->esi_pct / 100 : 0.0;
        $esiEmployer = $esiApplies ? $gross * (float) ($s->esi_employer_pct ?? $s->esi_pct) / 100 : 0.0;

        $pt = $this->professionalTax($gross);

        // The sheet's PF and ESI columns are both sides together, and Net is
        // gross less all three deductions.
        $pf = $pfEmployee + $pfEmployer;
        $esi = $esiEmployee + $esiEmployer;
        // Income tax is a figure per employee, set by the accountant.
        $tds = max(0.0, $tds);
        $net = $gross - $pf - $esi - $pt - $tds;

        $perDay = $workingDays > 0 ? $net / $workingDays : 0.0;
        $unpaidDays = max(0.0, $workingDays - $daysPresent);
        $afterLop = $unpaidDays * $perDay;

        return [
            'gross' => $gross, 'basic' => $basic, 'basic_pct' => $pct, 'hra' => $hra, 'ca' => $ca, 'other' => $other,
            'pf' => $pf, 'pf_employee' => $pfEmployee, 'pf_employer' => $pfEmployer,
            'esi' => $esi, 'esi_employee' => $esiEmployee, 'esi_employer' => $esiEmployer,
            'pt' => $pt, 'tds' => $tds, 'net' => $net,
            'working_days' => $workingDays, 'days_present' => $daysPresent, 'lop_days' => $unpaidDays,
            'per_day' => $perDay, 'after_lop' => $afterLop, 'final_net' => $net - $afterLop,
        ];
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
