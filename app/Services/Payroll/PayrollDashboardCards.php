<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * The Payroll Dashboard's cards: each one a headline figure with the lines
 * that make it up, and - when opened - a table of every employee behind it.
 *
 * Built from PayoutQuery rows, the same rows the Salary Statement and the
 * statutory returns read, so the three cannot disagree.
 */
class PayrollDashboardCards
{

    /**
     * The cards, in sections.
     *
     * @return array<string, array{title: string, note: string, cards: array}>
     */
    public function sections(array $rows, array $totals, array $elements): array
    {
        $lop = $this->sum($rows, fn ($r) => $this->lop($r));
        $ot = $this->overtime($rows);
        $employeeStatutory = $totals['pf_employee'] + $totals['esi_employee'] + $totals['pt'] + $totals['tds'];
        $deductions = $employeeStatutory + $lop + $totals['extra_deductions'];
        $employer = $totals['pf_employer'] + $totals['esi_employer'];
        $earned = $totals['gross'] + $totals['extra_earnings'] + $totals['encashment'];
        $affected = count(array_filter($rows, fn ($r) => (float) $r['lop_days'] > 0));
        $returns = new StatutoryReturns(new PayoutQuery());
        $pf = $returns->providentFund($rows);
        $esi = $returns->employeeInsurance($rows);
        $pt = $returns->professionalTax($rows);
        $tds = $returns->incomeTax($rows);

        $salary = [
            $this->card('gross', 'Gross Salary', $totals['gross'], $totals['payslips'] . ' payslip(s) · ' . $totals['employees'] . ' employee(s)', 'blue', 'fa-wallet', [
                ['Basic', $totals['basic']],
                ['HRA', $totals['hra']],
                ['Conveyance', $totals['ca']],
                ['Leave Travel', $totals['lta']],
                ['Children Education', $totals['cea']],
                ['Special Allowance', $totals['others']],
            ]),
            $this->card('earnings', 'Extra Earnings', $totals['extra_earnings'] + $totals['encashment'], 'pay elements credited', 'green', 'fa-plus-circle',
                array_merge(
                    array_map(fn ($e) => [$e['name'], $e['total']], array_values(array_filter($elements, fn ($e) => $e['kind'] === 'earning'))),
                    [['Leave encashment', $totals['encashment']]]
                )),
            $this->card('overtime', 'Overtime', $ot['amount'], 'OT bank: encashed and covered', 'teal', 'fa-clock', [
                ['Encashed', $ot['amount']],
                ['Hours encashed', $ot['encashed_hours'], 'hours'],
                ['Days covered from bank', $ot['covered_days'], 'days'],
                ['Hours left in banks', $ot['balance'], 'hours'],
            ]),
            $this->card('deductions', 'Total Deductions', $deductions, 'everything taken from pay', 'red', 'fa-minus-circle', array_merge([
                ['PF (employee)', $totals['pf_employee']],
                ['ESI (employee)', $totals['esi_employee']],
                ['Professional Tax', $totals['pt']],
                ['TDS', $totals['tds']],
                ['Loss of Pay', $lop],
            ], array_map(fn ($e) => [$e['name'], $e['total']], array_values(array_filter($elements, fn ($e) => $e['kind'] === 'deduction'))))),
            $this->card('lop', 'Loss of Pay', $lop, $affected . ' employee(s) with unpaid days', 'orange', 'fa-user-times', [
                ['Unpaid days', $totals['lop_days'], 'days'],
                ['Employees affected', $affected, 'count'],
                ['Average per employee', $affected ? $lop / $affected : 0],
            ]),
            $this->card('net', 'Net Pay', $totals['paid'], $totals['paid_count'] . ' of ' . $totals['payslips'] . ' marked paid', 'indigo', 'fa-hand-holding-usd', array_merge([
                ['Earnings', $earned],
                ['− Deductions', $deductions],
            ], $employer > 0 ? [['− Employer PF / ESI (in CTC)', $employer]] : [])),
            $this->card('cost', 'Cost to Company', $totals['cost'], 'salary + employer contributions', 'dark', 'fa-building', [
                ['Net pay', $totals['paid']],
                ['Employee statutory + TDS', $employeeStatutory],
                ['Employer PF', $totals['pf_employer']],
                ['Employer ESI', $totals['esi_employer']],
            ]),
        ];

        $statutory = [
            $this->card('pf', 'Provident Fund (PF)', $totals['pf_payable'], $pf['totals']['members'] . ' member(s)', 'purple', 'fa-shield-alt', [
                ['Employee share', $totals['pf_employee']],
                ['Employer share', $totals['pf_employer']],
                ['of which pension (EPS)', $pf['totals']['pension']],
                ['Missing UAN', count($pf['problems']), 'count'],
            ]),
            $this->card('esi', 'Employee Insurance (ESI)', $totals['esi_payable'], $esi['totals']['members'] . ' insured', 'teal', 'fa-heartbeat', [
                ['Employee share', $totals['esi_employee']],
                ['Employer share', $totals['esi_employer']],
                ['Wages covered', $esi['totals']['wages']],
                ['Missing ESI number', count($esi['problems']), 'count'],
            ]),
            $this->card('pt', 'Professional Tax (PT)', $totals['pt'], $pt['totals']['people'] . ' employee(s)', 'amber', 'fa-landmark',
                array_map(fn ($b) => ['₹' . number_format($b['amount'], 0) . ' slab · ' . $b['people'] . ' people', $b['total']], $pt['bands'])),
            $this->card('tds', 'Income Tax (TDS)', $totals['tds'], $tds['totals']['people'] . ' employee(s)', 'red', 'fa-file-invoice', [
                ['Withheld', $tds['totals']['total']],
                ['On gross of', $tds['totals']['gross']],
                ['Missing PAN', count($tds['problems']), 'count'],
            ]),
        ];

        return [
            'salary' => ['title' => 'Salary Summary', 'note' => 'what was earned, taken and paid', 'cards' => $salary],
            'statutory' => ['title' => 'Statutory Contributions', 'note' => 'employee vs employer', 'cards' => $statutory],
        ];
    }

    /**
     * Every pay element in the catalogue, with what it came to - the credits
     * and the debits apart. Elements not used in the range show as nil.
     *
     * @return array{earning: array, deduction: array}
     */
    public function elementCards(array $elements): array
    {
        $used = collect($elements)->keyBy('key');
        $out = ['earning' => [], 'deduction' => []];

        foreach (DB::table('pay_element_types')->orderBy('id')->get(['id', 'name', 'kind']) as $type) {
            $key = 'element:' . $type->id;
            $hit = $used->pull($key);
            $out[$type->kind === 'deduction' ? 'deduction' : 'earning'][] = [
                'key' => $key, 'name' => $type->name,
                'total' => $hit['total'] ?? 0.0, 'people' => (int) ($hit['people'] ?? 0),
            ];
        }

        // Lines entered by name, before the catalogue existed. One with a
        // catalogue name ("Incentive"; "OT encashed (20h)" is Overtime) takes
        // that card's place while the catalogue type itself has nothing.
        foreach ($used as $hit) {
            $side = $hit['kind'] === 'deduction' ? 'deduction' : 'earning';
            $name = preg_match('/^OT\b/i', $hit['name']) ? 'Overtime' : $hit['name'];
            $merged = false;

            foreach ($out[$side] as &$card) {
                if (strcasecmp($card['name'], $name) === 0 && abs($card['total']) < 0.005 && str_starts_with($card['key'], 'element:')) {
                    $card = ['key' => $hit['key'], 'name' => $card['name'], 'total' => $hit['total'], 'people' => (int) $hit['people']];
                    $merged = true;
                    break;
                }
            }

            unset($card);

            if (!$merged) {
                $out[$side][] = ['key' => $hit['key'], 'name' => $hit['name'], 'total' => $hit['total'], 'people' => (int) $hit['people']];
            }
        }

        return $out;
    }

    /**
     * The table a card opens: columns and one row per employee.
     *
     * @return array{title: string, columns: array, rows: array}
     */
    public function table(array $rows, string $card): array
    {
        $who = fn ($r) => [$r['emp_name'], $r['emp_id'], $r['department'] ?: '—', $r['period']];
        $whoColumns = [['Employee', 'text'], ['Code', 'text'], ['Department', 'text'], ['Period', 'text']];

        if (str_starts_with($card, 'element:') || str_starts_with($card, 'label:')) {
            $result = (new PayoutQuery())->behind($rows, $card);

            return [
                'title' => $result['label'],
                'columns' => [['Employee', 'text'], ['Code', 'text'], ['Department', 'text'], ['Period', 'text'], ['Line', 'text'], ['Amount', 'money']],
                'rows' => array_map(fn ($p) => [$p['name'], $p['code'], $p['department'] ?: '—', $p['period'], $p['note'] ?? '', $p['amount']], $result['rows']),
            ];
        }

        $pick = fn (callable $keep, callable $line) => array_values(array_map($line, array_filter($rows, $keep)));
        $all = fn () => true;

        [$title, $columns, $lines] = match ($card) {
            'gross' => ['Gross Salary', [['Basic', 'money'], ['HRA', 'money'], ['Conveyance', 'money'], ['LTA', 'money'], ['CEA', 'money'], ['Special', 'money'], ['Gross', 'money']],
                $pick($all, fn ($r) => [...$who($r), (float) $r['basic'], (float) $r['hra'], (float) $r['ca'], (float) $r['lta'], (float) $r['cea'], (float) $r['others'], (float) $r['gross']])],
            'earnings' => ['Extra Earnings', [['Pay elements', 'text'], ['Elements total', 'money'], ['Leave encashment', 'money'], ['Total', 'money']],
                $pick(fn ($r) => $r['extra_earnings'] > 0 || (float) $r['leave_encashment'] > 0, fn ($r) => [...$who($r),
                    $this->lineNames($r, 'earning'), (float) $r['extra_earnings'], (float) $r['leave_encashment'], $r['extra_earnings'] + (float) $r['leave_encashment']])],
            'overtime' => ['Overtime', [['Hours earned', 'num'], ['Hours encashed', 'num'], ['Encashed', 'money'], ['Days covered', 'num'], ['Hours left', 'num']],
                $pick(fn ($r) => $this->otOf($r) !== null, fn ($r) => [...$who($r), ...array_values($this->otOf($r))])],
            'deductions' => ['Total Deductions', [['PF', 'money'], ['ESI', 'money'], ['PT', 'money'], ['TDS', 'money'], ['Loss of Pay', 'money'], ['Pay elements', 'money'], ['Total', 'money']],
                $pick($all, fn ($r) => [...$who($r), (float) $r['pf'], (float) $r['esi'], (float) $r['pt'], (float) $r['tds'], $this->lop($r), (float) $r['extra_deductions'],
                    (float) $r['pf'] + (float) $r['esi'] + (float) $r['pt'] + (float) $r['tds'] + $this->lop($r) + (float) $r['extra_deductions']])],
            'lop' => ['Loss of Pay', [['Working days', 'num'], ['Days present', 'num'], ['Unpaid days', 'num'], ['Per day', 'money'], ['Deducted', 'money']],
                $pick(fn ($r) => (float) $r['lop_days'] > 0, fn ($r) => [...$who($r), (float) $r['working_days'], (float) $r['days_present'], (float) $r['lop_days'],
                    (float) $r['lop_days'] > 0 ? $this->lop($r) / (float) $r['lop_days'] : 0, $this->lop($r)])],
            'net' => ['Net Pay', [['Earnings', 'money'], ['Deductions', 'money'], ['Net pay', 'money'], ['Status', 'text'], ['Payment ref', 'text']],
                $pick($all, fn ($r) => [...$who($r), (float) $r['gross'] + $r['extra_earnings'] + (float) $r['leave_encashment'],
                    (float) $r['pf'] + (float) $r['esi'] + (float) $r['pt'] + (float) $r['tds'] + $this->lop($r) + (float) $r['extra_deductions'],
                    $r['paid'], ucfirst((string) $r['approval_status']), (string) ($r['payment_reference'] ?? '')])],
            'cost' => ['Cost to Company', [['Net pay', 'money'], ['Employee statutory + TDS', 'money'], ['Employer PF', 'money'], ['Employer ESI', 'money'], ['Cost', 'money']],
                $pick($all, fn ($r) => [...$who($r), $r['paid'], (float) $r['pf'] + (float) $r['esi'] + (float) $r['pt'] + (float) $r['tds'],
                    (float) $r['employer_pf'], (float) $r['employer_esi'], $r['cost']])],
            'pf' => ['Provident Fund', [['UAN / PF no.', 'text'], ['Basic', 'money'], ['Employee', 'money'], ['Employer', 'money'], ['Total', 'money']],
                $pick(fn ($r) => (float) $r['pf'] > 0 || (float) $r['employer_pf'] > 0, fn ($r) => [...$who($r), $r['pf_account_number'] ?: 'missing',
                    (float) $r['basic'], (float) $r['pf'], (float) $r['employer_pf'], (float) $r['pf'] + (float) $r['employer_pf']])],
            'esi' => ['Employee Insurance', [['ESI no.', 'text'], ['Gross', 'money'], ['Employee', 'money'], ['Employer', 'money'], ['Total', 'money']],
                $pick(fn ($r) => (float) $r['esi'] > 0 || (float) $r['employer_esi'] > 0, fn ($r) => [...$who($r), $r['esi_number'] ?: 'missing',
                    (float) $r['gross'], (float) $r['esi'], (float) $r['employer_esi'], (float) $r['esi'] + (float) $r['employer_esi']])],
            'pt' => ['Professional Tax', [['Gross', 'money'], ['PT', 'money']],
                $pick(fn ($r) => (float) $r['pt'] > 0, fn ($r) => [...$who($r), (float) $r['gross'], (float) $r['pt']])],
            'tds' => ['Income Tax (TDS)', [['PAN', 'text'], ['Gross', 'money'], ['TDS', 'money']],
                $pick(fn ($r) => (float) $r['tds'] > 0, fn ($r) => [...$who($r), $r['emp_pan'] ?: 'missing', (float) $r['gross'], (float) $r['tds']])],
            default => ['—', [], []],
        };

        return ['title' => $title, 'columns' => array_merge($whoColumns, $columns), 'rows' => $lines];
    }

    protected function card(string $key, string $title, float $value, string $sub, string $tone, string $icon, array $lines): array
    {
        return compact('key', 'title', 'value', 'sub', 'tone', 'icon', 'lines');
    }

    protected function lop(array $row): float
    {
        return NetPay::lop((object) $row);
    }

    protected function sum(array $rows, callable $of): float
    {
        return array_sum(array_map($of, $rows));
    }

    protected function lineNames(array $row, string $kind): string
    {
        return collect($row['extras'])->where('kind', $kind)->map(fn ($l) => $l->label)->implode(', ');
    }

    /** The OT bank figures on one payslip, or null when it had none. */
    protected function otOf(array $row): ?array
    {
        $ot = json_decode((string) ($row['ot_summary'] ?? ''), true);

        if (!$ot || (abs((float) ($ot['earned'] ?? 0)) < 0.005 && abs((float) ($ot['encashed_hours'] ?? 0)) < 0.005
            && abs((float) ($ot['covered_days'] ?? 0)) < 0.005)) {
            return null;
        }

        return [
            'earned' => round((float) ($ot['earned'] ?? 0), 2),
            'encashed_hours' => round((float) ($ot['encashed_hours'] ?? 0), 2),
            'amount' => round((float) ($ot['encashed_amount'] ?? 0), 2),
            'covered_days' => round((float) ($ot['covered_days'] ?? 0), 2),
            'balance' => round((float) ($ot['balance'] ?? 0), 2),
        ];
    }

    protected function overtime(array $rows): array
    {
        $sum = ['amount' => 0.0, 'encashed_hours' => 0.0, 'covered_days' => 0.0, 'balance' => 0.0];

        foreach ($rows as $row) {
            if ($ot = $this->otOf($row)) {
                foreach (array_keys($sum) as $key) {
                    $sum[$key] += $ot[$key];
                }
            }
        }

        return $sum;
    }

}
