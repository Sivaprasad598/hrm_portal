<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * The files the provident fund, the insurance scheme and the professional tax
 * office each want for a month.
 *
 * All three are built from payslips already issued, so what is filed and what
 * was paid are the same thing by construction. Nothing is recomputed.
 *
 * **The layouts here follow the published formats, but a portal will reject a
 * file over a detail no document mentions.** Every return is therefore produced
 * with a companion list of what is missing or doubtful, and the first upload of
 * each should be treated as a test.
 */
class StatutoryReturns
{

    public function __construct(protected PayoutQuery $payouts)
    {
    }

    /**
     * The provident fund's electronic challan-cum-return.
     *
     * One line per member, fields separated by #~#, in the order the ECR 2.0
     * format lists them:
     *   UAN, name, gross wages, EPF wages, EPS wages, EDLI wages,
     *   EPF contribution, EPS contribution, EPF-EPS difference,
     *   non-contributing days, refund of advances
     *
     * @return array{lines: array, text: string, totals: array, problems: array}
     */
    public function providentFund(array $rows, ?object $settings = null): array
    {
        $settings = $settings ?? (new SalaryCalculator())->settings();
        $ceiling = (float) ($settings->pf_wage_ceiling ?: 15000);
        // The pension share is a fixed slice of the employer's contribution,
        // taken on wages up to the same ceiling.
        $pensionRate = 8.33;

        $lines = [];
        $upload = [];
        $problems = [];
        $totals = ['members' => 0, 'gross' => 0.0, 'epf_wages' => 0.0, 'employee' => 0.0,
            'employer_epf' => 0.0, 'pension' => 0.0, 'total' => 0.0];

        foreach ($rows as $row) {
            $employee = (float) $row['pf'];
            $employer = (float) $row['employer_pf'];

            if ($employee <= 0 && $employer <= 0) {
                continue;
            }

            $uan = preg_replace('/\D/', '', (string) ($row['pf_account_number'] ?? ''));
            $missingUan = strlen($uan) !== 12;

            if ($missingUan) {
                $problems[] = $row['emp_name'] . ' has no valid 12-digit UAN, so they are left out of the ECR text file (EPFO refuses the whole file otherwise); add the UAN and download again.';
            }

            // EPF wages are the wages the 12% was actually taken on (the full basic when
            // PF runs on it, the ceiling when capped); EPS and EDLI wages stop at the ceiling.
            $epfWages = $employee > 0 ? round(min((float) $row['basic'] ?: INF, $employee / 0.12), 0) : round(min((float) $row['basic'], $ceiling), 0);
            $epsWages = min($epfWages, round($ceiling, 0));
            $pension = round($epsWages * $pensionRate / 100, 0);
            // Whatever the employer puts in beyond the pension share stays in
            // the provident fund itself.
            $employerEpf = max(0, round($employer, 0) - $pension);
            $unpaidDays = (float) ($row['lop_days'] ?? 0);

            $line = [
                $missingUan ? 'NO UAN' : $uan,
                $this->plain($row['emp_name']),
                round((float) $row['gross'], 0),
                $epfWages,
                $epsWages,
                $epsWages,
                round($employee, 0),
                $pension,
                $employerEpf,
                round($unpaidDays, 0),
                0,
            ];

            $lines[] = $line;
            if (!$missingUan) {
                $upload[] = $line;
            }
            $totals['members']++;
            $totals['gross'] += (float) $row['gross'];
            $totals['epf_wages'] += $epfWages;
            $totals['employee'] += round($employee, 0);
            $totals['employer_epf'] += $employerEpf;
            $totals['pension'] += $pension;
        }

        $totals['total'] = $totals['employee'] + $totals['employer_epf'] + $totals['pension'];

        return [
            'lines' => $lines,
            // The portal takes the members with a UAN only, no heading line, CRLF ends
            'text' => implode("\r\n", array_map(fn ($l) => implode('#~#', $l), $upload)),
            'totals' => $totals,
            'problems' => $problems,
        ];
    }

    /**
     * The employee insurance return: one line per insured person.
     *
     * @return array{lines: array, totals: array, problems: array}
     */
    public function employeeInsurance(array $rows): array
    {
        $lines = [];
        $problems = [];
        $contributions = [];
        $totals = ['members' => 0, 'wages' => 0.0, 'employee' => 0.0, 'employer' => 0.0, 'total' => 0.0];

        foreach ($rows as $row) {
            $employee = (float) $row['esi'];
            $employer = (float) $row['employer_esi'];

            if ($employee <= 0 && $employer <= 0) {
                continue;
            }

            $number = trim((string) ($row['esi_number'] ?? ''));

            if ($number === '') {
                $problems[] = $row['emp_name'] . ' has no ESI number.';
                $number = 'NOT ON RECORD';
            }

            $days = (int) round((float) ($row['days_present'] ?? 0));

            // ESIC monthly contribution upload: IP number, name, days paid, wages,
            // reason code (0 = worked; 1 = on leave for a zero-day month), last working day
            $lines[] = [
                $number,
                $this->plain($row['emp_name']),
                $days,
                round((float) $row['gross'], 0),
                $days > 0 ? 0 : 1,
                '',
            ];
            $contributions[] = [
                $number,
                $this->plain($row['emp_name']),
                round((float) $row['gross'], 2),
                round($employee, 2),
                round($employer, 2),
                round($employee + $employer, 2),
            ];

            $totals['members']++;
            $totals['wages'] += (float) $row['gross'];
            $totals['employee'] += $employee;
            $totals['employer'] += $employer;
        }

        $totals['total'] = $totals['employee'] + $totals['employer'];

        return ['lines' => $lines, 'contributions' => $contributions, 'totals' => $totals, 'problems' => $problems];
    }

    /**
     * Professional tax, which is paid as one challan per band.
     *
     * @return array{bands: array, lines: array, totals: array}
     */
    public function professionalTax(array $rows): array
    {
        $bands = [];
        $lines = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $amount = (float) $row['pt'];

            if ($amount <= 0) {
                continue;
            }

            $key = number_format($amount, 2);
            $bands[$key] ??= ['amount' => $amount, 'people' => 0, 'total' => 0.0];
            $bands[$key]['people']++;
            $bands[$key]['total'] += $amount;
            $total += $amount;

            $lines[] = [
                $this->plain($row['emp_name']),
                $row['emp_id'],
                round((float) $row['gross'], 2),
                round($amount, 2),
            ];
        }

        krsort($bands);

        return [
            'bands' => array_values($bands),
            'lines' => $lines,
            'totals' => ['people' => count($lines), 'total' => $total],
        ];
    }

    /**
     * Income tax withheld (TDS) for the month: who it was taken from, against
     * which PAN, for the challan and the quarterly 24Q statement.
     *
     * @return array{lines: array, totals: array, problems: array}
     */
    public function incomeTax(array $rows, ?string $creditedOn = null): array
    {
        $lines = [];
        $problems = [];
        $totals = ['people' => 0, 'gross' => 0.0, 'total' => 0.0];

        foreach ($rows as $row) {
            $amount = (float) ($row['tds'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $pan = strtoupper(trim((string) ($row['emp_pan'] ?? '')));

            if ($pan === '') {
                $problems[] = $row['emp_name'] . ' has no PAN; tax withheld without a PAN is taken at the higher rate.';
                $pan = 'PANNOTAVBL';
            }

            // 24Q Annexure II: employee code, PAN, name, section, paid/credited, TDS, date
            $lines[] = [
                $row['emp_id'],
                $pan,
                $this->plain($row['emp_name']),
                '192',
                round((float) $row['gross'], 2),
                round($amount, 2),
                $creditedOn ?? '',
            ];

            $totals['people']++;
            $totals['gross'] += (float) $row['gross'];
            $totals['total'] += $amount;
        }

        return ['lines' => $lines, 'totals' => $totals, 'problems' => $problems];
    }

    /**
     * Everything that would stop a return being accepted.
     *
     * @return array<int, string>
     */
    public function readiness(array $rows): array
    {
        $problems = [];

        $noPf = array_filter($rows, fn ($r) => (float) $r['pf'] > 0 && trim((string) ($r['pf_account_number'] ?? '')) === '');
        $noEsi = array_filter($rows, fn ($r) => (float) $r['esi'] > 0 && trim((string) ($r['esi_number'] ?? '')) === '');
        $noPan = array_filter($rows, fn ($r) => trim((string) ($r['emp_pan'] ?? '')) === '');
        $noBank = array_filter($rows, fn ($r) => trim((string) ($r['personal_account_number'] ?? '')) === '');

        if ($noPf !== []) {
            $problems[] = count($noPf) . ' contributing to the provident fund have no PF account number.';
        }

        if ($noEsi !== []) {
            $problems[] = count($noEsi) . ' contributing to the insurance scheme have no ESI number.';
        }

        if ($noPan !== []) {
            $problems[] = count($noPan) . ' have no PAN, which the tax return needs.';
        }

        if ($noBank !== []) {
            $problems[] = count($noBank) . ' have no bank account, so they cannot be paid by transfer.';
        }

        return $problems;
    }

    /** The portals take plain ASCII; anything else is refused on upload. */
    protected function plain(?string $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/[^A-Za-z0-9 .\-]/', '', $value);

        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

}
