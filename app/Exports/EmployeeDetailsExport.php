<?php

namespace App\Exports;

use App\Services\Payroll\EmployeeImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Everyone on record, as a sheet to fill in and send back.
 *
 * What is already known is filled in, so the blanks are the work. The employee
 * code is what the import matches on, which is why it is locked-looking and
 * said so at the top.
 */
class EmployeeDetailsExport implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(protected array $rows)
    {
    }

    public function title(): string
    {
        return 'Employees';
    }

    public function headings(): array
    {
        return array_keys(EmployeeImport::COLUMNS);
    }

    public function array(): array
    {
        $headings = $this->headings();

        return array_map(
            fn ($row) => array_map(fn ($heading) => $row[$heading] ?? null, $headings),
            $this->rows
        );
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = count($this->rows) + 1;
                $columns = count($this->headings());
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns);

                $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
                $sheet->freezePane('C2');

                // The two columns the import reads but never writes.
                $sheet->getStyle('A1:B' . max($last, 1))->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDF2F7');

                // Text, or Excel eats the leading zero on an account number.
                foreach (['A', 'G', 'H', 'I', 'J', 'K', 'L', 'M'] as $column) {
                    $sheet->getStyle($column . '2:' . $column . max($last, 2))
                        ->getNumberFormat()->setFormatCode('@');
                }

                $sheet->getComment('A1')->getText()->createTextRun(
                    "This is what the import matches on. Do not change it.\n" .
                    "The employee code may be filled in where it is blank, but not changed once set.\n" .
                    "A blank cell anywhere else means \"leave as it is\" — it never erases what is on record."
                );
            },
        ];
    }

}
