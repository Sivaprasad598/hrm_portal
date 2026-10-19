<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * What a payslip pays, worked out the one way the printed slip does.
 *
 * The slip lists earnings (basic, HRA, conveyance, others and any extra
 * earning lines), takes off the employee's deductions (PF, ESI, PT, TDS, the
 * attendance deduction and any extra deduction lines) and then the employer's
 * own PF and ESI, because CTC already includes them. Every screen that shows
 * a take-home figure — the payslip list, the register, My Payslips, the
 * e-mail — used to work it out for itself, and they disagreed with the slip
 * by the employer's contributions, the TDS or the extra lines. They all ask
 * here now.
 */
class NetPay
{
    /** The attendance deduction: net salary less what attendance earned. */
    public static function lop(object $p): float
    {
        return max(0.0, (float) $p->net_salary - (float) $p->attendance_net);
    }

    /** Employer PF and ESI, where the payslip recorded them; the employee's share otherwise, as the slip prints. */
    public static function employer(object $p): float
    {
        return (float) ($p->employer_pf ?? $p->pf) + (float) ($p->employer_esi ?? $p->esi);
    }

    /** Net salary as printed on the slip, before any leave encashment. */
    public static function takeHome(object $p, float $extraEarnings = 0.0, float $extraDeductions = 0.0): float
    {
        // Built from the components, which is what the slip totals — a handful
        // of early payslips stored a net of zero while their components add up
        // to the full salary, and the slip prints the components.
        $earnings = (float) $p->basic + (float) $p->hra + (float) $p->ca
            + (float) ($p->lta ?? 0) + (float) ($p->cea ?? 0)   // Leave Travel and Children Education Allowance
            + (float) $p->others + $extraEarnings;               // others: the Special Allowance
        $deductions = (float) $p->pf + (float) $p->esi + (float) $p->pt + (float) ($p->tds ?? 0)
            + static::lop($p) + $extraDeductions;

        return $earnings - $deductions - static::employer($p);
    }

    /**
     * What the company parts with: the take-home, the encashment, and what it
     * pays over on the employee's behalf (their PF, ESI, PT and TDS) and on its
     * own (employer PF and ESI).
     */
    public static function cost(object $p, float $extraEarnings = 0.0, float $extraDeductions = 0.0): float
    {
        return static::takeHome($p, $extraEarnings, $extraDeductions) + (float) ($p->leave_encashment ?? 0)
            + (float) $p->pf + (float) $p->esi + (float) $p->pt + (float) ($p->tds ?? 0)
            + static::employer($p);
    }

    /**
     * Extra earning and deduction totals for a set of payslips.
     *
     * @return array<int, array{0: float, 1: float}>  payslip id => [earnings, deductions]
     */
    public static function extras(iterable $payslipIds): array
    {
        $out = [];

        foreach (DB::table('payslip_components')->whereIn('payslip_id', collect($payslipIds)->all())
            ->selectRaw('payslip_id, kind, SUM(amount) as total')->groupBy('payslip_id', 'kind')->get() as $line) {
            $out[(int) $line->payslip_id] ??= [0.0, 0.0];
            $out[(int) $line->payslip_id][$line->kind === 'earning' ? 0 : 1] += (float) $line->total;
        }

        return $out;
    }
}
