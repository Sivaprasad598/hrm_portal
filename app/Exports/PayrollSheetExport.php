<?php

namespace App\Exports;

use App\Services\Payroll\PayrollImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * A pay period's payroll as a sheet to fill in and upload (Payroll Import), simple or
 * detailed, with a "How to fill" tab. The upload reads the first tab only.
 */
class PayrollSheetExport implements WithMultipleSheets
{

    public function __construct(protected array $rows, protected string $format, protected string $period, protected bool $attendance,
        protected ?array $headings = null, protected array $elements = [], protected array $kinds = [], protected array $items = [])
    {
    }

    public function sheets(): array
    {
        return [
            new PayrollSheetTab($this->rows, $this->format, $this->headings ?? PayrollImport::headings($this->format), $this->kinds),
            new PayrollItemsTab($this->items),
            new PayrollHowToTab($this->format, $this->period, $this->attendance, $this->elements),
        ];
    }

}

/**
 * The Earnings & Deductions tab: one line per item (any number per employee), its Type said on the
 * line. The upload reads it together with the Payroll tab.
 */
class PayrollItemsTab implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(protected array $rows)
    {
    }

    public function title(): string
    {
        return 'Earnings & Deductions';
    }

    public function headings(): array
    {
        return PayrollImport::ITEM_HEADINGS;
    }

    public function array(): array
    {
        return array_map(fn ($row) => array_map(fn ($h) => $row[$h] ?? null, PayrollImport::ITEM_HEADINGS), $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max(count($this->rows) + 1, 200);   // room to add lines, with the Type list on each

                $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CB6B4F');
                $sheet->freezePane('A2');
                $sheet->getStyle('A2:A' . $last)->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle('E2:E' . $last)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('B2:B' . $last)->getFont()->getColor()->setRGB('667085');
                $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(14);
                $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(26);

                $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)->setAllowBlank(true)
                    ->setShowDropDown(true)->setShowErrorMessage(true)->setErrorTitle('Type')
                    ->setError('Choose Earning or Deduction.')->setFormula1('"Earning,Deduction"');
                $sheet->setDataValidation('C2:C' . $last, $validation);

                // earnings green, deductions red, as on the Payroll tab
                foreach ($this->rows as $i => $row) {
                    $sheet->getStyle('C' . ($i + 2) . ':E' . ($i + 2))->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(($row['Type'] ?? '') === 'Deduction' ? 'FEF3F2' : 'ECFDF3');
                }

                $sheet->getComment('C1')->getText()->createTextRun(
                    "Earning or Deduction (from the list).\nOne line per item: an employee may have any number of lines.\n" .
                    "Item: a pay element's name (Bonus, Loan Recovery...) or any other name (Night Allowance, Canteen...)."
                );
            },
        ];
    }

}

class PayrollSheetTab implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(protected array $rows, protected string $format, protected array $columns, protected array $kinds = [])
    {
    }

    public function title(): string
    {
        return 'Payroll';
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function array(): array
    {
        $headings = $this->headings();

        return array_map(fn ($row) => array_map(fn ($h) => $row[$h] ?? null, $headings), $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headings = $this->headings();
                $last = max(count($this->rows) + 1, 2);
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headings));
                $col = fn (string $h) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(array_search($h, $headings) + 1);

                $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:' . $lastCol . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CB6B4F');
                $sheet->freezePane('C2');
                $sheet->getStyle('A2:A' . $last)->getNumberFormat()->setFormatCode('@');

                // only there to read
                $sheet->getStyle('B2:B' . $last)->getFont()->getColor()->setRGB('667085');

                // money as money
                foreach ($headings as $h) {
                    if (!in_array($h, ['Employee Code', 'Name', 'Remarks', 'Working Days', 'Days Paid'], true)) {
                        $sheet->getStyle($col($h) . '2:' . $col($h) . $last)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                // the extra earning / deduction columns: green earns, red deducts
                foreach ($headings as $h) {
                    $kind = $this->kinds[$h][1] ?? (preg_match('/^\s*(earnings?|additions?)\s*[:\-–]|^\s*\+/iu', $h) ? 'earning'
                        : (preg_match('/^\s*(deductions?|recovery)\s*[:\-–]|^\s*[\-–−]/iu', $h) ? 'deduction' : null));

                    if ($kind) {
                        $sheet->getStyle($col($h) . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($kind === 'earning' ? '12B76A' : 'D92D20');
                        $sheet->getStyle($col($h) . '2:' . $col($h) . $last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($kind === 'earning' ? 'ECFDF3' : 'FEF3F2');
                    }
                }

                // the figures that are checked, not stored
                if ($this->format === 'detailed') {
                    foreach (['Gross', 'Net Pay'] as $h) {
                        $sheet->getStyle($col($h) . '1:' . $col($h) . $last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7ED');
                        $sheet->getStyle($col($h) . '1')->getFont()->getColor()->setRGB('B54708');
                    }
                }
            },
        ];
    }

}

class PayrollHowToTab implements FromArray, WithTitle, WithEvents
{

    public function __construct(protected string $format, protected string $period, protected bool $attendance, protected array $elements = [])
    {
    }

    public function title(): string
    {
        return 'How to fill';
    }

    public function array(): array
    {
        $attendance = $this->attendance
            ? 'ON: days paid come from ERP attendance (biometric and uploaded); loss of pay is deducted.'
            : 'OFF: no attendance deductions; the full month is paid.';

        $rows = [
            ['Payroll upload: ' . $this->period . ' (' . ($this->format === 'simple' ? 'simple format' : 'detailed format') . ')'],
            ['Attendance for payroll (Payroll Settings): ' . $attendance],
            [''],
            ['Column', 'What to put in it'],
            ['Employee Code', 'Needed. The line is matched on it.'],
            ['Name', 'Optional, only to read.'],
        ];

        if ($this->format === 'simple') {
            $rows = array_merge($rows, [
                ['Annual CTC', 'Optional. Blank = the salary on record. (A "Monthly Gross" column may be used instead.)'],
                ['TDS', 'Optional. Blank = the monthly TDS on record.'],
                ['Other Earnings', 'Optional. A bonus, arrears... added to the payslip.'],
                ['Other Deductions', 'Optional. A recovery... taken off the payslip.'],
                ['Remarks', 'Optional. Kept with the extra lines.'],
                [''],
                ['How it is worked out', 'Exactly as Payroll Run: Basic, HRA, allowances, PF, ESI, PT from the salary formula; '
                    . 'days paid from attendance when it is on; pay elements already entered for the period are included.'],
            ]);
        } else {
            $rows = array_merge($rows, [
                ['Working Days / Days Paid', 'Optional. Blank = every day of the period, all paid.'],
                ['Basic, HRA, Conveyance, LTA, CEA, Special Allowance', 'The earnings. Together they are the Gross.'],
                ['Other Earnings', 'Optional. A bonus, arrears...'],
                ['Gross', 'Optional. Only checked: it must equal the earnings above.'],
                ['PF, ESI, PT, TDS', "The employee's deductions."],
                ['Attendance Deduction', 'The loss of pay. Must be 0 when attendance for payroll is off.'],
                ['Other Deductions', 'Optional. A recovery...'],
                ['Employer PF, Employer ESI', "The employer's share, taken off as on the company's payslips (CTC includes it)."],
                ['Net Pay', 'Optional. Only checked: Gross + Other Earnings - every deduction above (within a rupee).'],
                [''],
                ['How it is used', 'The figures are the payslip as you give them. Pay elements already entered for the period '
                    . '(Payroll -> Pay Elements) are added too, as on Payroll Run, and Net Pay must include them.'],
            ]);
        }

        $names = $this->elements ? implode(', ', array_map(fn ($e) => $e['name'] . ' (' . $e['kind'] . ')', $this->elements)) : 'none set up';

        return array_merge($rows, [
            [''],
            ['More earnings and deductions', 'Two ways, which can be used together. Each item becomes its own line on the payslip.'],
            ['  1. Earnings & Deductions tab (best)', 'One line per item: Employee Code, Type (Earning / Deduction), Item, Amount. '
                . 'Any number of lines per employee; nobody needs a column for items they do not have.'],
            ['  2. A column on the Payroll tab', 'Head it "Earning: <name>" or "Deduction: <name>", e.g. "Earning: Bonus", '
                . '"Deduction: Canteen". Any number, in any order: the heading decides, not the position. (A bare pay element name, '
                . 'e.g. "Bonus", also works: its type in Pay Elements decides.)'],
            ['  Item names', 'A pay element\'s name is that element, linked to it (its type must agree): ' . $names
                . '. Any other name is fine too (Night Allowance, Canteen...).'],
            ['  Checked', 'The same item twice for one employee, a Type that disagrees with Pay Elements, an item for somebody not '
                . 'on the Payroll tab: refused, and that employee\'s payslip is left out until it is fixed. Columns not understood are named on the page.'],
            ['  Already entered', 'An element already entered for the period under the same name would be paid twice, so it is refused.'],
            [''],
            ['Nothing is saved when you upload: the page shows every payslip and the totals first, and you confirm.'],
            ['Anyone who already has a payslip for the period is left out (revoke it first to issue again).'],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getColumnDimension('A')->setWidth(50);
                $sheet->getColumnDimension('B')->setWidth(110);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setRGB($this->attendance ? '067647' : 'B54708');
                $sheet->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CB6B4F');
            },
        ];
    }

}
