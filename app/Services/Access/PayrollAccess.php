<?php

namespace App\Services\Access;

/**
 * Who may do what in payroll: four switches per role (Settings -> Access).
 *
 *   prepare  - Run Payroll (generate payslips, OT bank, TDS), the calculator, one payslip, offer letters
 *   approve  - approve, mark paid, send payslips
 *   revoke   - revoke and delete payslips
 *   settings - pay elements, OT hours, income tax, statutory returns, settlements, salary revisions, payslip design
 *
 * Seeing the payroll records (issued payslips, salary statement, summary) takes
 * any one of them. The Super Admin has them all.
 */
class PayrollAccess
{
    const SWITCHES = ['prepare', 'approve', 'revoke', 'settings'];

    public static function can(string $switch): bool
    {
        if (!user()) {
            return false;
        }

        return in_array('admin', user_roles()) || user()->permission('payroll_' . $switch) === 'all';
    }

    /** Any payroll switch at all: enough to see the payroll records. */
    public static function any(): bool
    {
        foreach (self::SWITCHES as $switch) {
            if (self::can($switch)) {
                return true;
            }
        }

        return false;
    }
}
