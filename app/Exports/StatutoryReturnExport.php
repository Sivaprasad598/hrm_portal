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
 * A month's statutory returns as a workbook — one sheet per filing, or just
 * the one that was asked for.
 */
class StatutoryReturnExport implements WithMultipleSheets
{

    use Exportable;

    public function __construct(
        protected string $which,
        protected array $pf,
        protected array $esi,
        protected array $pt,
        protected PayPeriod $cycle,
        protected array $tds = ['lines' => [], 'problems' => []],
    ) {
    }

    public function sheets(): array
    {
        $sheets = [];

        if (in_array($this->which, ['all', 'pf'], true)) {
            $sheets[] = new StatutorySheet('Provident Fund', $this->cycle,
                ['UAN', 'Member Name', 'Gross Wages', 'EPF Wages', 'EPS Wages', 'EDLI Wages',
                    'EPF Contribution', 'EPS Contribution', 'EPF-EPS Difference', 'NCP Days', 'Refund'],
                $this->pf['lines'], $this->pf['problems'] ?? [], [3, 4, 5, 6, 7, 8, 9]);
        }

        if (in_array($this->which, ['all', 'esi'], true)) {
            // The first sheet is the ESIC portal's monthly upload layout, column for column
            $sheets[] = new StatutorySheet('ESI Upload', $this->cycle,
                ['IP Number', 'IP Name', 'No of Days for which wages paid/payable during the month', 'Total Monthly Wages',
                    'Reason Code for Zero workings days', 'Last Working Day'],
                $this->esi['lines'], $this->esi['problems'] ?? [], [4]);
            $sheets[] = new StatutorySheet('ESI Contributions', $this->cycle,
                ['IP Number', 'IP Name', 'Gross Wages', 'Employee 0.75%', 'Employer 3.25%', 'Total'],
                $this->esi['contributions'] ?? [], []);
        }

        if (in_array($this->which, ['all', 'pt'], true)) {
            $sheets[] = new StatutorySheet('Professional Tax', $this->cycle,
                ['Employee', 'Code', 'Gross', 'Professional Tax'],
                $this->pt['lines'], [], [3, 4]);
        }

        if (in_array($this->which, ['all', 'tds'], true)) {
            $sheets[] = new StatutorySheet('Income Tax (TDS)', $this->cycle,
                ['Employee Code', 'PAN', 'Employee Name', 'Section', 'Amount Paid / Credited', 'TDS Deducted', 'Date of Credit'],
                $this->tds['lines'], $this->tds['problems'] ?? [], [5, 6]);
        }

        return $sheets;
    }

}

/**
 * One filing: what it contains, and anything that would stop it being accepted.
 */
class StatutorySheet implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(
        protected string $name,
        protected PayPeriod $cycle,
        protected array $columns,
        protected array $lines,
        protected array $problems,
        // The columns (1-based) that are totalled; null = every column from the third
        protected ?array $sumColumns = null,
    ) {
    }

    public function title(): string
    {
        return $this->name;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function array(): array
    {
        return $this->lines;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = count($this->lines) + 1;
                $width = count($this->columns);
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($width);

                $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);

                if ($this->lines === []) {
                    $sheet->setCellValue('A2', 'Nothing to file for ' . $this->cycle->describe() . '.');

                    return;
                }

                // Totals under every column that holds money.
                $total = $last + 1;
                $sheet->setCellValue('A' . $total, 'Total — ' . $this->cycle->describe());

                foreach ($this->sumColumns ?? range(3, $width) as $column) {
                    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
                    $sheet->setCellValue($letter . $total, '=SUM(' . $letter . '2:' . $letter . $last . ')');
                }

                $sheet->getStyle('A' . $total . ':' . $lastColumn . $total)->getFont()->setBold(true);

                if ($this->problems !== []) {
                    $row = $total + 2;
                    $sheet->setCellValue('A' . $row, 'Check before filing:');
                    $sheet->getStyle('A' . $row)->getFont()->setBold(true);

                    foreach ($this->problems as $problem) {
                        $sheet->setCellValue('A' . (++$row), $problem);
                    }
                }
            },
        ];
    }

}
