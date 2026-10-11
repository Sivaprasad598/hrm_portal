<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * A month's payroll as a spreadsheet, in the company's own column order —
 * the same layout as the calculation sheet payroll is checked against, so the
 * two can be compared side by side.
 */
class PayrollRunExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(protected array $rows, protected string $monthName)
    {
    }

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string
    {
        return 'Calculation Sheet Format';
    }

    public function headings(): array
    {
        return ['S.No', 'Employee Name', 'Gross', 'Basic', 'HRA', 'CA', 'Other', 'PF', 'ESI', 'PT', 'TDS',
            'Net Salary', 'LOP', 'No.of. Days', 'Present Days', 'Per day', 'After LOP', 'Net Salary'];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        $round = fn ($value) => round((float) $value, 2);

        return [
            $row['serial'],
            $row['name'],
            $round($row['gross']),
            $round($row['basic']),
            $round($row['hra']),
            $round($row['ca']),
            $round($row['other']),
            $round($row['pf']),
            $round($row['esi']),
            $round($row['pt']),
            $round($row['tds'] ?? 0),
            $round($row['net']),
            $round($row['lop_days']),
            $row['working_days'],
            $round($row['days_present']),
            $round($row['per_day']),
            $round($row['after_lop']),
            $round($row['final_net']),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = count($this->rows) + 1;

                $sheet->getStyle('A1:R1')->getFont()->setBold(true);
                $sheet->getStyle('A1:R' . $lastRow)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('C2:R' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

                // A totals line, which is the point of the whole screen.
                if ($this->rows !== []) {
                    $total = $lastRow + 1;
                    $sheet->setCellValue('B' . $total, 'Total (' . $this->monthName . ')');

                    foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'Q', 'R'] as $column) {
                        $sheet->setCellValue($column . $total, '=SUM(' . $column . '2:' . $column . $lastRow . ')');
                    }

                    $sheet->getStyle('A' . $total . ':R' . $total)->getFont()->setBold(true);
                    $sheet->getStyle('C' . $total . ':R' . $total)->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }

}
