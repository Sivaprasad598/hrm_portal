<?php

namespace App\Exports;

use App\Services\Attendance\AttendanceImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * A pay period's attendance as a sheet to fill in and send back (Attendance Import):
 * one line per employee per day, what is on record filled in, P / HD1 / HD2 / A from a list.
 * A second tab says how to fill it. The upload reads the first tab only.
 */
class AttendanceSheetExport implements WithMultipleSheets
{

    public function __construct(protected array $rows, protected string $period, protected ?array $shift = null)
    {
    }

    public function sheets(): array
    {
        return [
            new AttendanceSheetTab($this->rows, $this->period),
            new AttendanceHowToTab($this->period, $this->shift),
        ];
    }

}

class AttendanceSheetTab implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{

    public function __construct(protected array $rows, protected string $period)
    {
    }

    public function title(): string
    {
        return 'Attendance';
    }

    public function headings(): array
    {
        return AttendanceImport::HEADINGS;
    }

    public function array(): array
    {
        $headings = $this->headings();

        return array_map(fn ($row) => array_map(fn ($heading) => $row[$heading] ?? null, $headings), $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max(count($this->rows) + 1, 2);

                $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CB6B4F');
                $sheet->freezePane('D2');
                $sheet->setAutoFilter('A1:G' . $last);

                // only there to read: the name and the note
                foreach (['B2:B' . $last, 'G2:G' . $last] as $range) {
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F4F7');
                    $sheet->getStyle($range)->getFont()->getColor()->setRGB('667085');
                }

                // the column to fill in
                $sheet->getStyle('D2:D' . $last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7ED');
                $sheet->getStyle('D1:D' . $last)->getAlignment()->setHorizontal('center');

                // text, so Excel keeps 09:30 as typed and the code's leading zeros
                foreach (['A', 'C', 'E', 'F'] as $column) {
                    $sheet->getStyle($column . '2:' . $column . $last)->getNumberFormat()->setFormatCode('@');
                }

                // P / HD1 / HD2 / A from a list
                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)->setShowInputMessage(true)
                    ->setErrorTitle('Attendance')->setError('Choose P (present), HD1 (half day, morning), HD2 (half day, afternoon) or A (absent), or leave it blank.')
                    ->setPromptTitle('Attendance')->setPrompt('P = present, HD1 = half day morning, HD2 = half day afternoon, A = absent. Without In / Out the shift\'s timings are used.')
                    ->setFormula1('"P,HD1,HD2,A"');
                $sheet->setDataValidation('D2:D' . $last, $validation);

                // locked days stand out
                foreach ($this->rows as $i => $row) {
                    if (str_starts_with((string) ($row['Note'] ?? ''), 'Locked')) {
                        $sheet->getStyle('A' . ($i + 2) . ':G' . ($i + 2))->getFont()->getColor()->setRGB('98A2B3');
                    }
                }

                $sheet->getComment('D1')->getText()->createTextRun(
                    "P = present, HD1 = half day (morning), HD2 = half day (afternoon), A = absent.\n" .
                    "Without In / Out the shift's timings are used.\n" .
                    "See the \"How to fill\" tab."
                );
            },
        ];
    }

}

class AttendanceHowToTab implements FromArray, WithTitle, WithEvents
{

    public function __construct(protected string $period, protected ?array $shift)
    {
    }

    public function title(): string
    {
        return 'How to fill';
    }

    public function array(): array
    {
        $start = $this->shift['start'] ?? '09:30';
        $end = $this->shift['end'] ?? '18:30';
        $half = $this->shift['half'] ?? '14:00';
        $name = $this->shift['name'] ?? 'General Shift';

        return [
            ['Attendance upload: ' . $this->period],
            [''],
            ['Column', 'What to put in it'],
            ['Employee Code', 'Needed. The line is matched on it.'],
            ['Name', 'Optional, only to read. It is never checked.'],
            ['Date', 'DD-MM-YYYY, a day of this period that has happened.'],
            ['Attendance', 'P = present, HD1 = half day morning, HD2 = half day afternoon, A = absent (pick from the list).'],
            ['In / Out', 'Optional. HH:MM, 24-hour (09:30, 18:45). An Out earlier than In is the next morning.'],
            ['Note', 'Only to read: week off, holiday, leave, locked.'],
            [''],
            ['You write', 'What is saved'],
            ['P', 'Present, ' . $start . ' to ' . $end . ' (' . $name . '; the employee\'s own shift where they have one)'],
            ['HD1', 'Half day, morning: ' . $start . ' to ' . $half . ' (the shift\'s half-day mark)'],
            ['HD2', 'Half day, afternoon: ' . $half . ' to ' . $end],
            ['A', 'Absent: the day\'s attendance is taken off'],
            ['P with In 10:05, Out 19:10', 'Present, 10:05 to 19:10 (the times you give are used)'],
            ['P on a day with a clock-in but no clock-out', 'The clock-out is set to ' . $end],
            ['In and Out with Attendance blank', 'Those times'],
            ['Everything blank', 'The day is left as it is'],
            [''],
            ['Days marked "Locked" have payroll generated for this period and cannot be changed.'],
            ['Nothing is saved when you upload: the page shows every change first, and you apply it.'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getColumnDimension('A')->setWidth(42);
                $sheet->getColumnDimension('B')->setWidth(95);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                foreach (['A3:B3', 'A11:B11'] as $range) {
                    $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CB6B4F');
                }
                $sheet->getStyle('A21:A22')->getFont()->setItalic(true);
            },
        ];
    }

}
