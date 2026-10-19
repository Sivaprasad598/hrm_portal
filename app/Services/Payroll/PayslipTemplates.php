<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * The payslip designs, and a preview of each.
 *
 * A preview renders the real template file from sample figures. Drawing a
 * picture of each design instead would have been easier and would have started
 * lying the first time a template changed.
 */
class PayslipTemplates
{

    /**
     * @return array<string, array{name: string, blurb: string}>
     */
    public function all(): array
    {
        return [
            'classic' => [
                'name' => 'Classic',
                'blurb' => 'The design this company has always issued. Wide, on a tinted page, '
                    . 'with the earnings and deductions side by side.',
            ],
            'modern' => [
                'name' => 'Modern (A4)',
                'blurb' => 'Built for the page rather than the screen: one A4 sheet, a headed '
                    . 'band, the net pay in words, and the days, employer share and '
                    . 'year-to-date figures in three panels.',
            ],
        ];
    }

    public function exists(string $template): bool
    {
        return array_key_exists($template, $this->all())
            && is_file($this->fileFor($template));
    }

    public function name(string $template): string
    {
        return $this->all()[$template]['name'] ?? $template;
    }

    /** What new payslips will be printed in. */
    public function selected(?int $companyId = null): string
    {
        $companyId = $companyId ?: $this->companyId();

        $template = DB::table('payslip_template_settings')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id')
            ->value('template');

        return $this->exists((string) $template) ? (string) $template : 'classic';
    }

    /**
     * The signed-in company, where there is one.
     *
     * company() answers false outside a request — a console run, a queued job —
     * and reading ->id off a bool is fatal, so it is never read directly.
     */
    protected function companyId(): ?int
    {
        $company = company();

        return is_object($company) ? (int) $company->id : null;
    }

    protected function company(): ?object
    {
        $company = company();

        return is_object($company) ? $company : null;
    }

    protected function fileFor(string $template): string
    {
        return base_path('payslip-templates/' . $template . '.php');
    }

    /**
     * One template, drawn from figures that exercise every part of it: two
     * earnings and two deductions so the paired table has rows on both sides,
     * an employer share, attendance and a year-to-date block.
     */
    public function render(string $template): string
    {
        // The template reads these out of the enclosing scope, exactly as it
        // does when payslips.php includes it.
        // The formatting the templates and the sample both call, shared with
        // payslips.php so there is one definition rather than two that drift.
        require_once base_path('payslip-templates/helpers.php');

        extract($this->sample(), EXTR_SKIP);

        ob_start();
        include $this->fileFor($template);
        $html = (string) ob_get_clean();

        // A preview is the document, not the page around it. Classic carries a
        // toolbar — "back to calculator", a print button — which belongs to the
        // screen it is normally served on and only confused the card it is
        // shown in. The same rule the print stylesheet already applies.
        return str_replace('</head>',
            "<style>.actions{display:none !important}"
            . "html,body{background:#ffffff !important}"
            . ".desk{background:#ffffff !important;padding:0 !important}"
            . "</style></head>",
            $html);
    }

    /**
     * The shape payslips.php hands a template: the $payslip array and the
     * figures derived from it.
     *
     * @return array<string, mixed>
     */
    protected function sample(): array
    {
        $company = $this->company();

        // A month by the calculation sheet (gross 32,000), plus an extra line each way.
        $earnings = [
            ['Basic', 12800.00],
            ['House Rent Allowance', 5120.00],
            ['Conveyance Allowance', 800.00],
            ['Leave Travel Allowance', 1280.00],
            ['Children Education Allowance', 1200.00],
            ['Special Allowance', 10800.00],
            ['Overtime', 4500.00],
        ];

        $deductions = [
            ['Employee PF', 1536.00],
            ['Employee ESI', 0.00],
            ['Professional Tax', 200.00],
            ['Salary Advance', 2000.00],
        ];

        $employer = [
            ['Employer PF', 1800.00],
            ['Employer ESI', 975.00],
        ];

        $payslip = [
            // The same keys payslips.php builds, so a template that reads one
            // of them in the real thing finds it here too.
            'company' => [
                'name' => $company?->company_name ?: 'AROWANA',
                'mark' => '',
                'tagline' => 'PRECISION / INNOVATION / SUCCESS',
                'address' => $company?->address ?: 'Company address, City',
                'stamp' => "SAMPLE
PREVIEW
ONLY",
                'logo' => null,
            ],
            'address' => $company?->address ?: 'Company address, City',
            'branch_name' => 'Head Office',
            'tagline' => '',
            'period' => 'Sample — ' . now()->format('F Y'),
            'approval_status' => 'approved',
            'amount' => ['symbol' => '₹'],
            'employee' => [
                'Employee Name' => 'A Sample Employee',
                'Employee ID' => 'EMP000',
                'Designation' => 'Designation',
                'Department' => 'Department',
                'Date of Joining' => now()->subYear()->format('d-m-Y'),
                'PAN' => 'ABCDE0000F',
                'UAN / PF No' => '100000000000',
                'Bank A/c' => 'XXXXXXXX0000',
            ],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'employer' => $employer,
            'statutory_rows' => ['Employee PF', 'Employee ESI', 'Professional Tax', 'Employer PF', 'Employer ESI'],
            'months_per_year' => 12,
            'note' => 'This is a preview drawn from sample figures. It is the template itself, '
                . 'so a payslip issued in this design will look exactly like this.',
            'attendance' => [
                'Working days' => '19',
                'Days paid' => '18.5',
                'Loss of pay' => '0.5',
            ],
            'year_to_date' => [
                'label' => '2026-27',
                'months' => 6,
                'gross' => 210000.00,
                'pf' => 10800.00,
                'esi' => 1350.00,
                'pt' => 1200.00,
                'tds' => 0.00,
                'lop' => 1480.00,
                'net' => 195170.00,
            ],
            'encashment' => ['amount' => 0.0, 'days' => 0.0],
            'days' => [],
        ];

        $sum = fn (array $rows) => array_sum(array_map(fn ($r) => (float) ($r[1] ?? 0), $rows));

        $grossEarnings = $sum($earnings);
        $grossDeductions = $sum($deductions);
        $employerTotal = $sum($employer);
        $netSalary = $grossEarnings - $grossDeductions - $employerTotal;
        $ctcMonthly = $grossEarnings;

        return [
            // The classic template was written as part of a page and reads a
            // few things from around it: who is looking, where "back to the
            // calculator" goes, and any warning banner. A preview has none of
            // those, so they are given as an employee would see them — without
            // the calculator link, which a preview has no business offering.
            'userRole' => 'employee',
            'calculatorUrl' => 'javascript:;',
            'payslipBlockedMessage' => '',
            'payslip' => $payslip,
            'grossEarnings' => $grossEarnings,
            'grossDeductions' => $grossDeductions,
            'employerTotal' => $employerTotal,
            'employerAllBlank' => false,
            'statutoryTotal' => 5000.00,
            'netSalary' => $netSalary,
            'ctcMonthly' => $ctcMonthly,
            'ctcAnnual' => $ctcMonthly * 12,
            // Worked out, not written down: a sample that says one figure in
            // digits and another in words is the first thing anyone notices.
            'netInWords' => function_exists('amount_in_words')
                ? amount_in_words($netSalary)
                : '',
            'componentRows' => max(count($earnings), count($deductions)),
        ];
    }

}
