<?php

namespace App\Services\Payroll;

use App\Services\Attendance\WorkRules;
use Illuminate\Support\Facades\DB;

/**
 * Overtime pay from the hours AttendanceDays counted (Attendance Settings ->
 * Working Hours & OT):
 *
 *   hourly = monthly gross (or basic) / (days in the salary period x work hours)
 *   pay    = weekday hours x hourly x weekday rate
 *          + week-off hours x hourly x weekend rate
 *          + holiday hours  x hourly x holiday rate
 *
 * It goes on the payroll run as one earning line, "Overtime (calculated)",
 * that HR can change or take off before the payslip is issued.
 */
class OvertimeCalculator
{

    public const LABEL = 'Overtime (calculated)';

    /**
     * @param array $count AttendanceDays::forRange()
     * @param array $pay   SalaryCalculator::forEmployee()
     * @return array{hours: float, amount: float, hourly: float, lines: array, note: string}
     */
    public function forEmployee(int $companyId, PayPeriod $cycle, array $count, array $pay): array
    {
        $rules = WorkRules::forCompany($companyId);
        $empty = ['hours' => 0.0, 'amount' => 0.0, 'hourly' => 0.0, 'lines' => [], 'note' => ''];

        if (!$rules || !$rules->s->ot_enabled || ($count['basis'] ?? 'standard') !== 'rules') {
            return $empty;
        }

        $base = $rules->s->ot_basis === 'basic' ? (float) $pay['basic'] : (float) $pay['gross'];
        $hoursPerDay = (float) $rules->s->wh_work_hours ?: 8.0;
        $hourly = $base / max(1, $cycle->days() * $hoursPerDay);

        $lines = [];
        $amount = 0.0;
        $hours = 0.0;

        foreach (['weekday' => 'Weekday', 'weekend' => 'Week-off', 'holiday' => 'Holiday'] as $type => $label) {
            $h = (float) ($count['breakdown']['ot_' . $type . '_hours'] ?? 0);
            $rate = $rules->rate($type);

            if ($h <= 0 || $rate <= 0) {
                continue;
            }

            $value = round($h * $hourly * $rate, 2);
            $lines[] = compact('type', 'label', 'rate', 'value') + ['hours' => $h];
            $amount += $value;
            $hours += $h;
        }

        $note = implode(' · ', array_map(fn ($l) => sprintf('%s %sh x %s x %s', $l['label'],
            rtrim(rtrim(number_format($l['hours'], 2), '0'), '.'), number_format($hourly, 2), rtrim(rtrim(number_format($l['rate'], 2), '0'), '.')), $lines));

        return ['hours' => round($hours, 2), 'amount' => round($amount, 2), 'hourly' => round($hourly, 2), 'lines' => $lines,
            'note' => $note ? $note . ' (on ' . ($rules->s->ot_basis === 'basic' ? 'basic' : 'gross') . ')' : ''];
    }

    /**
     * Write (or refresh) the period's "Overtime (calculated)" line for an
     * employee. A line already on an issued payslip is left alone; none is
     * left behind when there is no overtime.
     *
     * @return string 'added' | 'updated' | 'removed' | 'none' | 'issued'
     */
    public function store(int $userId, PayPeriod $cycle, array $ot): string
    {
        $existing = DB::table('payslip_components')->where('user_id', $userId)
            ->whereDate('period_start', $cycle->start->toDateString())
            ->where('label', self::LABEL)->first(['id', 'payslip_id']);

        if ($existing && $existing->payslip_id) {
            return 'issued';
        }

        if ($ot['amount'] <= 0) {
            if ($existing) {
                DB::table('payslip_components')->where('id', $existing->id)->delete();

                return 'removed';
            }

            return 'none';
        }

        if ($existing) {
            DB::table('payslip_components')->where('id', $existing->id)
                ->update(['amount' => $ot['amount'], 'note' => $ot['note'], 'updated_at' => now()]);

            return 'updated';
        }

        (new PayComponents())->add($userId, $cycle->start, 'earning', self::LABEL, $ot['amount'], $ot['note']);

        return 'added';
    }

}
