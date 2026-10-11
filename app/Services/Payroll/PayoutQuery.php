<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What payroll actually paid, read from the payslips themselves.
 *
 * Both the register (one period, for filing) and the dashboard (a date range,
 * for looking) are built from here, so the two screens cannot drift apart. A
 * figure is never recalculated: if it looks wrong the payslip is wrong, and the
 * answer is to revoke and reissue.
 *
 * A date range selects a payslip when its period *started* inside the range.
 * Each payslip therefore falls in exactly one range, and two adjoining ranges
 * add up to the whole.
 */
class PayoutQuery
{

    /**
     * Every payslip issued for a single period, by its label.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCycle(PayPeriod $cycle, bool $includeDrafts = false): array
    {
        return $this->build(
            fn ($q) => $q->where('p.period', $cycle->label),
            $includeDrafts
        );
    }

    /**
     * Every payslip whose period began between two dates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forRange(Carbon $from, Carbon $to, bool $includeDrafts = false): array
    {
        return $this->build(
            fn ($q) => $q->whereBetween('p.period_start', [$from->toDateString(), $to->toDateString()]),
            $includeDrafts
        );
    }

    /**
     * @param  callable  $scope  narrows the query to the periods wanted
     * @return array<int, array<string, mixed>>
     */
    protected function build(callable $scope, bool $includeDrafts): array
    {
        $payslips = DB::table('payslips as p')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'p.user_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            // A draft has had nothing paid against it, so it is not part of the
            // record unless it is asked for.
            ->when(!$includeDrafts, fn ($q) => $q->whereIn('p.approval_status', ['approved', 'paid']))
            ->tap($scope)
            ->orderBy('p.emp_name')
            ->get([
                'p.id', 'p.payslip_number', 'p.emp_name', 'p.emp_id', 'p.emp_pan', 'p.user_id',
                'p.gross', 'p.basic', 'p.hra', 'p.ca', 'p.others',
                'p.pf', 'p.employer_pf', 'p.esi', 'p.employer_esi', 'p.pt', 'p.tds',
                'p.total_deductions', 'p.net_salary', 'p.attendance_net', 'p.leave_encashment',
                'p.working_days', 'p.days_present', 'p.lop_days',
                'p.period', 'p.period_start', 'p.period_end',
                'p.approval_status', 'p.paid_at', 'p.payment_reference',
                'ed.pf_account_number', 'ed.esi_number', 'ed.personal_account_number', 'ed.ifsc_code',
                't.team_name as department',
            ]);

        // Extra pay elements are their own lines on the slip, so they stay their
        // own figures here rather than being folded into the salary.
        $extras = DB::table('payslip_components as c')
            ->leftJoin('pay_element_types as t', 't.id', '=', 'c.element_type_id')
            ->whereIn('c.payslip_id', $payslips->pluck('id'))
            ->get(['c.payslip_id', 'c.kind', 'c.label', 'c.amount', 'c.element_type_id', 't.name as type_name']);

        $rows = [];
        $serial = 0;

        foreach ($payslips as $payslip) {
            $lines = $extras->where('payslip_id', $payslip->id);
            $earnings = (float) $lines->where('kind', 'earning')->sum('amount');
            $deductions = (float) $lines->where('kind', 'deduction')->sum('amount');

            $rows[] = array_merge((array) $payslip, [
                'serial' => ++$serial,
                'extra_earnings' => $earnings,
                'extra_deductions' => $deductions,
                'extras' => $lines->values()->all(),
                // What the employee is actually paid: the attendance-adjusted
                // figure, plus anything encashed and any extra line.
                'paid' => (float) $payslip->attendance_net + (float) $payslip->leave_encashment
                    + $earnings - $deductions,
                // What the company parts with, its own contributions included.
                'cost' => (float) $payslip->attendance_net + (float) $payslip->leave_encashment
                    + $earnings - $deductions
                    + (float) $payslip->employer_pf + (float) $payslip->employer_esi,
            ]);
        }

        return $rows;
    }

    /**
     * The headline figures.
     *
     * @return array<string, float|int>
     */
    public function totals(array $rows): array
    {
        $sum = fn (string $key) => array_sum(array_map(fn ($r) => (float) ($r[$key] ?? 0), $rows));

        $pfEmployee = $sum('pf');
        $pfEmployer = $sum('employer_pf');
        $esiEmployee = $sum('esi');
        $esiEmployer = $sum('employer_esi');

        return [
            'payslips' => count($rows),
            'employees' => count(array_unique(array_map(fn ($r) => $r['user_id'], $rows))),
            'paid_count' => count(array_filter($rows, fn ($r) => $r['approval_status'] === 'paid')),
            'gross' => $sum('gross'),
            'basic' => $sum('basic'),
            'hra' => $sum('hra'),
            'ca' => $sum('ca'),
            'others' => $sum('others'),
            'pf_employee' => $pfEmployee,
            'pf_employer' => $pfEmployer,
            // What goes to the authority for the period: both sides together.
            'pf_payable' => $pfEmployee + $pfEmployer,
            'esi_employee' => $esiEmployee,
            'esi_employer' => $esiEmployer,
            'esi_payable' => $esiEmployee + $esiEmployer,
            'pt' => $sum('pt'),
            'tds' => $sum('tds'),
            'deductions' => $sum('total_deductions'),
            'extra_earnings' => $sum('extra_earnings'),
            'extra_deductions' => $sum('extra_deductions'),
            'encashment' => $sum('leave_encashment'),
            'lop_days' => $sum('lop_days'),
            'paid' => $sum('paid'),
            'cost' => $sum('cost'),
        ];
    }

    /**
     * Every extra pay element in the rows, totalled by the type it came from.
     *
     * An element entered before the catalogue existed has no type; it is
     * grouped under the label written on the line, which is what the payslip
     * shows anyway.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byElement(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            foreach ($row['extras'] as $line) {
                $name = $line->type_name ?: $line->label;
                $key = ($line->element_type_id ?: 'label') . '|' . $name;

                $groups[$key] ??= [
                    'key' => $line->element_type_id ? 'element:' . $line->element_type_id : 'label:' . $name,
                    'name' => $name,
                    'kind' => $line->kind,
                    'total' => 0.0,
                    'people' => [],
                ];
                $groups[$key]['total'] += (float) $line->amount;
                $groups[$key]['people'][$row['user_id']] = true;
            }
        }

        foreach ($groups as &$group) {
            $group['people'] = count($group['people']);
        }

        unset($group);
        usort($groups, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_values($groups);
    }

    /**
     * The same figures grouped by department, largest cost first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byDepartment(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $name = $row['department'] ?: 'No department';
            $groups[$name] ??= ['department' => $name, 'people' => 0, 'gross' => 0.0, 'paid' => 0.0, 'cost' => 0.0];
            $groups[$name]['people']++;
            $groups[$name]['gross'] += (float) $row['gross'];
            $groups[$name]['paid'] += (float) $row['paid'];
            $groups[$name]['cost'] += (float) $row['cost'];
        }

        usort($groups, fn ($a, $b) => $b['cost'] <=> $a['cost']);

        return $groups;
    }

    /**
     * Who is behind one figure, and what each of them contributed to it.
     *
     * This is what opening a card shows: the same total, but named.
     *
     * @return array{label: string, kind: string, total: float, rows: array<int, array<string, mixed>>}
     */
    public function behind(array $rows, string $metric): array
    {
        $out = [];
        $total = 0.0;

        // An element card: "element:12" for a catalogue type, "label:Overtime"
        // for a line entered before the catalogue existed.
        if (str_starts_with($metric, 'element:') || str_starts_with($metric, 'label:')) {
            [$kind, $needle] = explode(':', $metric, 2);

            foreach ($rows as $row) {
                foreach ($row['extras'] as $line) {
                    $matches = $kind === 'element'
                        ? (string) $line->element_type_id === $needle
                        : ($line->type_name ?: $line->label) === $needle;

                    if (!$matches) {
                        continue;
                    }

                    $total += (float) $line->amount;
                    $out[] = $this->person($row, (float) $line->amount, $line->label);
                }
            }

            return [
                'label' => $kind === 'element' ? ($out ? $out[0]['note'] : 'Pay element') : $needle,
                'kind' => 'element',
                'total' => $total,
                'rows' => $out,
            ];
        }

        $columns = $this->metrics();

        if (!isset($columns[$metric])) {
            return ['label' => $metric, 'kind' => 'unknown', 'total' => 0.0, 'rows' => []];
        }

        [$label, $column] = $columns[$metric];

        foreach ($rows as $row) {
            $amount = is_callable($column) ? $column($row) : (float) ($row[$column] ?? 0);

            if (abs($amount) < 0.005) {
                continue;
            }

            $total += $amount;
            $out[] = $this->person($row, $amount, null);
        }

        usort($out, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return ['label' => $label, 'kind' => 'figure', 'total' => $total, 'rows' => $out];
    }

    /**
     * The figures a card can be opened on, and where each comes from.
     *
     * @return array<string, array{0: string, 1: string|callable}>
     */
    public function metrics(): array
    {
        return [
            'gross' => ['Gross salary', 'gross'],
            'basic' => ['Basic', 'basic'],
            'hra' => ['House rent allowance', 'hra'],
            'ca' => ['Conveyance allowance', 'ca'],
            'others' => ['Other allowances', 'others'],
            'encashment' => ['Leave encashment', 'leave_encashment'],
            'pf_employee' => ['Provident fund — employee share', 'pf'],
            'pf_employer' => ['Provident fund — employer share', 'employer_pf'],
            'pf_payable' => ['Provident fund — both shares',
                fn ($r) => (float) $r['pf'] + (float) $r['employer_pf']],
            'esi_employee' => ['Employee insurance — employee share', 'esi'],
            'esi_employer' => ['Employee insurance — employer share', 'employer_esi'],
            'esi_payable' => ['Employee insurance — both shares',
                fn ($r) => (float) $r['esi'] + (float) $r['employer_esi']],
            'pt' => ['Professional tax', 'pt'],
            'tds' => ['Income tax withheld', 'tds'],
            'deductions' => ['Total deductions', 'total_deductions'],
            'extra_earnings' => ['Extra paid', 'extra_earnings'],
            'extra_deductions' => ['Extra deducted', 'extra_deductions'],
            'paid' => ['Paid to employees', 'paid'],
            'cost' => ['Cost to company', 'cost'],
            'lop_days' => ['Unpaid days', 'lop_days'],
        ];
    }

    /** One line of a drill-down. */
    protected function person(array $row, float $amount, ?string $note): array
    {
        return [
            'user_id' => $row['user_id'],
            'name' => $row['emp_name'],
            'code' => $row['emp_id'],
            'department' => $row['department'] ?: '',
            'payslip_id' => $row['id'],
            'payslip_number' => $row['payslip_number'],
            'period' => $row['period'],
            'amount' => round($amount, 2),
            'note' => $note,
        ];
    }

}
