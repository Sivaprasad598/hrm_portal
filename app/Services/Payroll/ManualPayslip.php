<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payslips typed into the Letter Generator form: for an old employee, or any
 * month outside the system. One page per month of a range, in the payslip
 * design chosen in Payslip Settings (the same template issued payslips use),
 * with the standard salary worked out from the CTC as payroll does
 * (SalaryCalculator) and any extra pay elements added. Nothing is saved: it is
 * drawn, shown and downloaded.
 */
class ManualPayslip
{

    public const MAX_MONTHS = 12;

    /** The months of the range, oldest first: [[month, year], ...] (at most MAX_MONTHS). */
    public static function months(?string $from, ?string $to): array
    {
        if (!$from || !preg_match('/^\d{4}-\d{2}$/', $from)) {
            return [];
        }

        $start = Carbon::createFromFormat('Y-m-d', $from . '-01')->startOfMonth();
        $end = $to && preg_match('/^\d{4}-\d{2}$/', $to) ? Carbon::createFromFormat('Y-m-d', $to . '-01')->startOfMonth() : $start->copy();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $months = [];

        for ($m = $start->copy(); $m->lte($end) && count($months) < self::MAX_MONTHS; $m->addMonth()) {
            $months[] = [(int) $m->month, (int) $m->year];
        }

        return $months;
    }

    /** What is missing on the form, one line each. */
    public static function problems(array $d): array
    {
        $problems = [];

        if (trim((string) ($d['name'] ?? '')) === '') {
            $problems[] = 'Name is empty.';
        }

        if ((float) ($d['ctc'] ?? 0) <= 0) {
            $problems[] = 'Annual CTC is empty.';
        }

        if (!self::months($d['from'] ?? null, $d['to'] ?? null)) {
            $problems[] = 'Choose the month (or the first month of the range).';
        }

        return $problems;
    }

    /** An employee's details, as the form holds them (the generator's prefill). */
    public static function detailsFor(int $userId): array
    {
        $e = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.id', $userId)
            ->first(['u.name', 'u.pan_number', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds', 'ed.employee_card_id', 'ed.employee_id',
                'ed.pf_account_number', 'ed.esi_number', 'ed.personal_account_number', 'ed.ifsc_code', 'ed.branch_id', 'ed.joining_date',
                'd.name as designation', 't.team_name as department']);

        return [
            'name' => (string) $e->name,
            'code' => (string) ($e->employee_card_id ?: $e->employee_id),
            'designation' => (string) $e->designation,
            'department' => (string) $e->department,
            'pan' => (string) $e->pan_number,
            'uan' => (string) $e->pf_account_number,
            'esi_no' => (string) $e->esi_number,
            'bank' => (string) $e->personal_account_number,
            'ifsc' => (string) $e->ifsc_code,
            'branch_id' => $e->branch_id ? (string) $e->branch_id : '',
            'ctc' => (float) $e->employee_ctc ?: '',
            'basic_pct' => $e->basic_for_payroll !== null ? (float) $e->basic_for_payroll : '',
            'tds' => (float) $e->monthly_tds ?: '',
        ];
    }

    /** The page for every month of the range, as one document (one A4 page each). */
    public static function html(array $d, bool $forPdf = false): string
    {
        $months = self::months($d['from'] ?? null, $d['to'] ?? null) ?: [[(int) now()->month, (int) now()->year]];
        $templates = new PayslipTemplates();
        $template = $templates->selected();
        $pages = array_map(fn ($my) => $templates->renderWith($template, self::vars($d, $my[0], $my[1])), $months);

        if (count($pages) === 1) {
            return $pages[0];
        }

        // One document: the first page's head (the same template throughout) and every page's body.
        preg_match('#^(.*?<body[^>]*>)#is', $pages[0], $head);
        $bodies = array_map(fn ($p) => preg_match('#<body[^>]*>(.*)</body>#is', $p, $m) ? $m[1] : $p, $pages);
        $break = $forPdf ? '<div style="page-break-after:always"></div>'
            : '<div style="height:18px;background:#e9ecef"></div>';

        return ($head[1] ?? '<html><body>') . implode($break, $bodies) . '</body></html>';
    }

    public static function pdf(array $d)
    {
        return (new PayslipDocument())->pdfFromHtml(self::html($d, true));
    }

    public static function fileName(array $d): string
    {
        $months = self::months($d['from'] ?? null, $d['to'] ?? null);
        $label = $months ? Carbon::create($months[0][1], $months[0][0], 1)->format('M Y') : '';

        if (count($months) > 1) {
            $last = end($months);
            $label .= ' - ' . Carbon::create($last[1], $last[0], 1)->format('M Y');
        }

        return 'Payslip - ' . preg_replace('/[^A-Za-z0-9 ._-]+/', '', trim((string) ($d['name'] ?? 'Employee'))) . ' - ' . $label . '.pdf';
    }

    /** The month's figures, for the summary beside the form. */
    public static function summary(array $d): array
    {
        return array_map(function ($my) use ($d) {
            [$payslip, $totals] = self::figures($d, $my[0], $my[1]);

            return [
                'month' => Carbon::create($my[1], $my[0], 1)->format('M Y'),
                'days' => $payslip['attendance']['Working Days'] . ' / ' . $payslip['attendance']['Days Present'],
                'gross' => SalaryRevisionLetter::inr($totals['grossEarnings']),
                'net' => SalaryRevisionLetter::inr($totals['netSalary']),
            ];
        }, self::months($d['from'] ?? null, $d['to'] ?? null));
    }

    /** The variables a payslip template reads, for one month: the shape payslips.php builds. */
    protected static function vars(array $d, int $month, int $year): array
    {
        [$payslip, $totals] = self::figures($d, $month, $year);

        return [
            'userRole' => 'employee',
            'calculatorUrl' => 'javascript:;',
            'payslipBlockedMessage' => '',
            'attendanceUrl' => null,
            'payslip' => $payslip,
            'employerAllBlank' => false,
            'statutoryTotal' => total_of_labels([$payslip['deductions'], $payslip['employer']], $payslip['statutory_rows']),
            'netInWords' => amount_in_words($totals['netSalary']),
            'componentRows' => max(count($payslip['earnings']), count($payslip['deductions'])),
        ] + $totals;
    }

    /** @return array{0: array, 1: array} the $payslip array and its totals */
    protected static function figures(array $d, int $month, int $year): array
    {
        require_once base_path('payslip-templates/helpers.php');

        $cycle = PayPeriod::forMonth($month, $year);
        $working = $cycle->days();
        $lop = min($working, max(0.0, (float) ($d['lop'][sprintf('%04d-%02d', $year, $month)] ?? 0)));
        $present = $working - $lop;
        $tds = max(0.0, (float) ($d['tds'] ?? 0));
        $basicPct = ($d['basic_pct'] ?? '') !== '' && $d['basic_pct'] !== null ? (float) $d['basic_pct'] : null;
        $c = (new SalaryCalculator())->forEmployee((float) ($d['ctc'] ?? 0), $basicPct, $working, $present, $tds);

        $earnings = [
            ['Basic', round((float) $c['basic'], 2)],
            ['HRA', round((float) $c['hra'], 2)],
            ['Conveyance Allowance', round((float) $c['ca'], 2)],
        ];
        if ((float) $c['lta'] > 0) {
            $earnings[] = ['Leave Travel Allowance', round((float) $c['lta'], 2)];
        }
        if ((float) $c['cea'] > 0) {
            $earnings[] = ['Children Education Allowance', round((float) $c['cea'], 2)];
        }
        $earnings[] = ['Special Allowance', round((float) $c['other'], 2)];

        // The extra pay elements typed in: each with its own label, every month of the range.
        $extraEarnings = [];
        $extraDeductions = [];
        foreach ((array) ($d['extras'] ?? []) as $x) {
            $label = trim((string) ($x['label'] ?? ''));
            $amount = round((float) ($x['amount'] ?? 0), 2);

            if ($label === '' || $amount == 0.0) {
                continue;
            }
            ($x['kind'] ?? 'earning') === 'deduction' ? $extraDeductions[] = [$label, $amount] : $extraEarnings[] = [$label, $amount];
        }
        $earnings = array_merge($earnings, $extraEarnings);

        $deductions = array_merge([
            ['Employee PF', round((float) $c['pf_employee'], 2)],
            ['Employee ESI', round((float) $c['esi_employee'], 2)],
            ['Professional Tax', round((float) $c['pt'], 2)],
            ['Income Tax (TDS)', round($tds, 2)],
            ['Lop', round((float) $c['lop_amount'], 2)],
        ], $extraDeductions);

        $employer = [
            ['Employer PF', round((float) $c['pf_employer'], 2)],
            ['Employer ESI', round((float) $c['esi_employer'], 2)],
        ];

        // The footer: the branch's address and contact emails, as on issued payslips.
        $branch = !empty($d['branch_id']) ? DB::table('branches')->where('id', $d['branch_id'])->first(['branch_name', 'address', 'emails']) : null;
        $address = '#30-10-12 & 13, 1ST & 2ND FLOOR, BESIDE UNION BANK OF INDIA<br>BANK STREET, MURALI NAGAR, VISAKHAPATNAM - 530007';
        $note = '<b>Note:</b> If you have any queries, please contact hr2@arowansoft.com Or hr3@arowansoft.com';
        if ($branch?->address) {
            $address = nl2br(e($branch->address));
        }
        $emails = $branch?->emails ? json_decode($branch->emails, true) : null;
        if (is_array($emails) && $emails) {
            $note = '<b>Note:</b> If you have any queries, please contact ' . implode(' Or ', array_map('e', $emails));
        }

        $company = company();
        $payslip = [
            'address' => trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $address)))),
            'company' => [
                'name' => $company?->company_name ?: 'AROWANA',
                'mark' => '△',
                'tagline' => 'PRECISION ▲ INNOVATION ▲ SUCCESS',
                'address' => $address,
                'stamp' => "AROWANA\nCORPORATIONS\nAUTHORIZED",
            ],
            'period' => Carbon::create($year, $month, 1)->format('F - Y'),
            'employee' => [
                'Name' => trim((string) ($d['name'] ?? '')) ?: '—',
                'Emp.ID' => trim((string) ($d['code'] ?? '')) ?: '—',
                'Designation' => trim((string) ($d['designation'] ?? '')) ?: '—',
                'PAN No' => trim((string) ($d['pan'] ?? '')) ?: '—',
            ] + array_filter([
                'Department' => trim((string) ($d['department'] ?? '')),
                'UAN / PF No' => trim((string) ($d['uan'] ?? '')),
                'ESI No' => trim((string) ($d['esi_no'] ?? '')),
                'Bank A/c' => trim((string) ($d['bank'] ?? '')),
                'IFSC' => trim((string) ($d['ifsc'] ?? '')),
            ], fn ($v) => $v !== ''),
            'earnings' => $earnings,
            'extra_earnings' => $extraEarnings,
            'deductions' => $deductions,
            'employer' => $employer,
            'statutory_rows' => ['Employee PF', 'Employee ESI', 'Professional Tax', 'Employer PF', 'Employer ESI'],
            'months_per_year' => 12,
            'note' => $note,
            'attendance' => [
                'Working Days' => (string) $working,
                'Days Present' => rtrim(rtrim(number_format($present, 1), '0'), '.'),
                'LOP Days' => rtrim(rtrim(number_format($lop, 1), '0'), '.'),
                'Per-day' => 'Rs. ' . indian_number($c['per_day']),
            ],
            'year_to_date' => null,
            'approval_status' => 'approved',
            'branch_name' => $branch?->branch_name,
            'encashment' => ['amount' => 0.0, 'days' => 0.0],
        ];

        $grossEarnings = total_of($earnings);
        $grossDeductions = total_of($deductions);
        $employerTotal = total_of($employer);
        $netSalary = $grossEarnings - $grossDeductions - $employerTotal;
        $ctcMonthly = $grossEarnings - total_of($extraEarnings);

        return [$payslip, [
            'grossEarnings' => $grossEarnings,
            'grossDeductions' => $grossDeductions,
            'employerTotal' => $employerTotal,
            'netSalary' => $netSalary,
            'ctcMonthly' => $ctcMonthly,
            'ctcAnnual' => $ctcMonthly * 12,
        ]];
    }

}
