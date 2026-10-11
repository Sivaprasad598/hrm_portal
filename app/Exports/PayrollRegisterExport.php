<?php

namespace App\Exports;

use App\Services\Payroll\PayPeriod;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * A month's payroll register: one workbook, a sheet for each thing it is
 * needed for.
 *
 *   Register   — every payslip, line by line, which is the record itself
 *   PF         — what is due to the provident fund, per employee and in total
 *   ESI        — the same for the insurance contribution
 *   Summary    — the month on one page, with the department breakdown
 *
 * Everything comes from the payslips as issued. Nothing is recalculated.
 */
class PayrollRegisterExport implements WithMultipleSheets
{

    use Exportable;

    public function __construct(
        protected array $rows,
        protected array $totals,
        protected PayPeriod $cycle,
    ) {
    }

    public function sheets(): array
    {
        return [
            new PayrollRegisterSheet($this->rows, $this->totals, $this->cycle),
            new PayrollStatutorySheet($this->rows, $this->cycle, 'pf'),
            new PayrollStatutorySheet($this->rows, $this->cycle, 'esi'),
            new PayrollSummarySheet($this->rows, $this->totals, $this->cycle),
        ];
    }

}

/**
 * The register itself: one row per payslip.
 */
class PayrollRegisterSheet implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(
        protected array $rows,
        protected array $totals,
        protected PayPeriod $cycle,
    ) {
    }

    public function title(): string
    {
        return 'Register';
    }

    public function headings(): array
    {
        return ['S.No', 'Payslip No', 'Employee', 'Code', 'Department', 'PAN',
            'Gross', 'Basic', 'HRA', 'CA', 'Other',
            'Working Days', 'Days Paid', 'LOP Days',
            'PF (employee)', 'ESI (employee)', 'PT', 'TDS', 'Total Deductions',
            'Extra Paid', 'Extra Deducted', 'Leave Encashment',
            'Net Paid', 'PF (employer)', 'ESI (employer)', 'Cost to Company',
            'Status', 'Paid On', 'Reference'];
    }

    public function array(): array
    {
        $round = fn ($value) => round((float) $value, 2);
        $out = [];

        foreach ($this->rows as $row) {
            $out[] = [
                $row['serial'],
                $row['payslip_number'],
                $row['emp_name'],
                $row['emp_id'],
                $row['department'] ?: '',
                $row['emp_pan'] ?: '',
                $round($row['gross']),
                $round($row['basic']),
                $round($row['hra']),
                $round($row['ca']),
                $round($row['others']),
                $row['working_days'],
                $round($row['days_present']),
                $round($row['lop_days'] ?? 0),
                $round($row['pf']),
                $round($row['esi']),
                $round($row['pt']),
                $round($row['tds'] ?? 0),
                $round($row['total_deductions']),
                $round($row['extra_earnings']),
                $round($row['extra_deductions']),
                $round($row['leave_encashment']),
                $round($row['paid']),
                $round($row['employer_pf']),
                $round($row['employer_esi']),
                $round($row['cost']),
                ucfirst((string) $row['approval_status']),
                $row['paid_at'] ? substr((string) $row['paid_at'], 0, 10) : '',
                $row['payment_reference'] ?: '',
            ];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = count($this->rows) + 1;

                $sheet->getStyle('A1:AC1')->getFont()->setBold(true);
                $sheet->freezePane('D2');

                if ($this->rows === []) {
                    return;
                }

                $sheet->getStyle('G2:Z' . $last)->getNumberFormat()->setFormatCode('#,##0.00');

                $total = $last + 1;
                $sheet->setCellValue('C' . $total, 'Total — ' . $this->cycle->describe());

                foreach (['G', 'H', 'I', 'J', 'K', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'] as $column) {
                    $sheet->setCellValue($column . $total, '=SUM(' . $column . '2:' . $column . $last . ')');
                }

                $sheet->getStyle('A' . $total . ':AC' . $total)->getFont()->setBold(true);
                $sheet->getStyle('G' . $total . ':Z' . $total)->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }

}

/**
 * What is owed to the provident fund or the insurance scheme, per employee.
 *
 * The same shape either way, so one class covers both: the employee's share,
 * the employer's, and the two together, which is what is actually remitted.
 */
class PayrollStatutorySheet implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(
        protected array $rows,
        protected PayPeriod $cycle,
        protected string $kind,
    ) {
    }

    public function title(): string
    {
        return $this->kind === 'pf' ? 'PF' : 'ESI';
    }

    public function headings(): array
    {
        $number = $this->kind === 'pf' ? 'PF Account No' : 'ESI No';

        return ['S.No', 'Employee', 'Code', $number, 'Gross', 'PF Wages / Gross',
            'Employee Share', 'Employer Share', 'Total Remitted'];
    }

    public function array(): array
    {
        $round = fn ($value) => round((float) $value, 2);
        $out = [];
        $serial = 0;

        foreach ($this->rows as $row) {
            $employee = $this->kind === 'pf' ? (float) $row['pf'] : (float) $row['esi'];
            $employer = $this->kind === 'pf' ? (float) $row['employer_pf'] : (float) $row['employer_esi'];

            // Somebody the scheme does not apply to this month is left out: ESI
            // stops above the wage threshold, so their row would only be noise.
            if ($employee <= 0 && $employer <= 0) {
                continue;
            }

            $out[] = [
                ++$serial,
                $row['emp_name'],
                $row['emp_id'],
                ($this->kind === 'pf' ? $row['pf_account_number'] : $row['esi_number']) ?: 'NOT ON RECORD',
                $round($row['gross']),
                $round($this->kind === 'pf' ? min((float) $row['basic'], 15000) : $row['gross']),
                $round($employee),
                $round($employer),
                $round($employee + $employer),
            ];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $sheet->getHighestRow();

                $sheet->getStyle('A1:I1')->getFont()->setBold(true);

                if ($last < 2) {
                    $sheet->setCellValue('A2', 'Nobody contributed to this scheme in ' . $this->cycle->describe() . '.');

                    return;
                }

                $sheet->getStyle('E2:I' . $last)->getNumberFormat()->setFormatCode('#,##0.00');

                $total = $last + 1;
                $sheet->setCellValue('B' . $total, 'Total — ' . $this->cycle->describe());

                foreach (['G', 'H', 'I'] as $column) {
                    $sheet->setCellValue($column . $total, '=SUM(' . $column . '2:' . $column . $last . ')');
                }

                $sheet->getStyle('A' . $total . ':I' . $total)->getFont()->setBold(true);
                $sheet->getStyle('E' . $total . ':I' . $total)->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }

}

/**
 * The month on one page: the figures to file, and where the cost sat.
 */
class PayrollSummarySheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(
        protected array $rows,
        protected array $totals,
        protected PayPeriod $cycle,
    ) {
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $t = $this->totals;
        $round = fn ($value) => round((float) $value, 2);

        $out = [
            ['Payroll register — ' . $this->cycle->describe(), ''],
            ['Period', $this->cycle->start->format('d M Y') . ' to ' . $this->cycle->end->format('d M Y')],
            ['Payslips', $t['payslips']],
            ['Of those, marked paid', $t['paid_count']],
            ['', ''],
            ['EARNINGS', ''],
            ['Gross salary', $round($t['gross'])],
            ['Extra paid (overtime, bonus, arrears)', $round($t['extra_earnings'])],
            ['Leave encashment', $round($t['encashment'])],
            ['', ''],
            ['DEDUCTED FROM EMPLOYEES', ''],
            ['Provident fund (employee share)', $round($t['pf_employee'])],
            ['Employee insurance (employee share)', $round($t['esi_employee'])],
            ['Professional tax', $round($t['pt'])],
            ['Income tax withheld', $round($t['tds'])],
            ['Other deductions (advances, recoveries)', $round($t['extra_deductions'])],
            ['', ''],
            ['TO REMIT', ''],
            ['Provident fund — both shares', $round($t['pf_payable'])],
            ['Employee insurance — both shares', $round($t['esi_payable'])],
            ['Professional tax', $round($t['pt'])],
            ['Income tax', $round($t['tds'])],
            ['', ''],
            ['WHAT IT COMES TO', ''],
            ['Paid to employees', $round($t['paid'])],
            ['Employer provident fund', $round($t['pf_employer'])],
            ['Employer insurance', $round($t['esi_employer'])],
            ['Cost to company', $round($t['cost'])],
            ['', ''],
            ['Unpaid days deducted across the month', $round($t['lop_days'])],
            ['', ''],
            ['BY DEPARTMENT', ''],
            ['Department', 'People', 'Gross', 'Paid', 'Cost to company'],
        ];

        $groups = [];

        foreach ($this->rows as $row) {
            $name = $row['department'] ?: 'No department';
            $groups[$name] ??= ['people' => 0, 'gross' => 0.0, 'paid' => 0.0, 'cost' => 0.0];
            $groups[$name]['people']++;
            $groups[$name]['gross'] += (float) $row['gross'];
            $groups[$name]['paid'] += (float) $row['paid'];
            $groups[$name]['cost'] += (float) $row['cost'];
        }

        uasort($groups, fn ($a, $b) => $b['cost'] <=> $a['cost']);

        foreach ($groups as $name => $group) {
            $out[] = [$name, $group['people'], $round($group['gross']), $round($group['paid']), $round($group['cost'])];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $sheet->getHighestRow();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('B1:E' . $last)->getNumberFormat()->setFormatCode('#,##0.00');

                // The section headings and the two name columns read as text.
                foreach (range(1, $last) as $rowNumber) {
                    $label = (string) $sheet->getCell('A' . $rowNumber)->getValue();

                    if ($label !== '' && $label === mb_strtoupper($label) && !is_numeric($label)) {
                        $sheet->getStyle('A' . $rowNumber)->getFont()->setBold(true);
                    }
                }

                $sheet->getStyle('A1:A' . $last)->getAlignment()->setWrapText(false);
            },
        ];
    }

}
