<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\EmployeeDetails;
use App\Models\Holiday;
use App\Models\Team;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AttendanceClockExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    public function __construct(
        public int|string $year,
        public int|string $month,
        public string|int $userId,
        public string|int $department,
        public string|int $designation,
        public $startDate,
        public $endDate
    ) {
    }

    public function headings(): array
    {
        return [
            '#',
            'Employee Name',
            'Email Address',
            'Department',
            'Date',
            'Clock In',
            'Clock Out',
            'Duration (hrs)',
        ];
    }

    public function collection(): Collection
    {
        $companyTimezone = company()->timezone;
        $dateFormat = company()->date_format;
        $timeFormat = company()->time_format;

        // Determine which employees are allowed based on view permission (owned/added/both/all)
        $viewAttendancePermission = user()->permission('view_attendance');
        $authUserId = auth()->id();

        $employeesQuery = EmployeeDetails::query()
            ->join('users', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('teams', 'employee_details.department_id', '=', 'teams.id')
            ->select([
                'users.id as user_id',
                'users.name as employee_name',
                'users.email as email_address',
                'teams.team_name as department_name',
                'employee_details.added_by as added_by',
                'employee_details.user_id as employee_user_id',
            ]);

        if ($viewAttendancePermission === 'owned') {
            $employeesQuery->where('users.id', $authUserId);
        } elseif ($viewAttendancePermission === 'added') {
            $employeesQuery->where('employee_details.added_by', $authUserId);
        } elseif ($viewAttendancePermission === 'both') {
            $employeesQuery->where(function ($q) use ($authUserId) {
                $q->where('employee_details.user_id', $authUserId)
                    ->orWhere('employee_details.added_by', $authUserId);
            });
        }

        // If user selected a specific employee and we are not in "owned" mode, respect it.
        if ($this->userId !== 'all' && $viewAttendancePermission !== 'owned') {
            $employeesQuery->where('users.id', $this->userId);
        }

        if ($this->department !== 'all') {
            $employeesQuery->where('employee_details.department_id', $this->department);
        }

        if ($this->designation !== 'all') {
            $employeesQuery->where('employee_details.designation_id', $this->designation);
        }

        $employees = $employeesQuery->get()->keyBy('user_id');
        if ($employees->isEmpty()) {
            return collect();
        }

        $employeeIds = $employees->keys()->values()->all();

        $startDateStr = $this->startDate->format('Y-m-d');
        $endDateStr = $this->endDate->format('Y-m-d');

        $attendances = Attendance::query()
            ->whereIn('user_id', $employeeIds)
            ->whereNotNull('clock_in_time')
            ->whereNotNull('clock_out_time')
            ->whereRaw('DATE(attendances.clock_in_time) >= ?', [$startDateStr])
            ->whereRaw('DATE(attendances.clock_in_time) <= ?', [$endDateStr])
            ->get(['id', 'user_id', 'clock_in_time', 'clock_out_time']);

        // Group by user_id, then by clock-in time (so we can skip repeated employee columns)
        $attendances = $attendances
            ->sortBy(function ($attendance) {
                $timestamp = $attendance->clock_in_time?->getTimestamp() ?? 0;
                return $attendance->user_id . '|' . $timestamp;
            })
            ->values();

        $rows = [];
        $sno = 1;
        $totalDurationMinutes = 0;
        $lastUserId = null;

        foreach ($attendances as $attendance) {
            $employee = $employees->get($attendance->user_id);
            if (!$employee) {
                continue;
            }

            $isRepeatEmployee = $lastUserId !== null && $lastUserId === $attendance->user_id;
            $lastUserId = $attendance->user_id;

            $attendanceDate = $attendance->clock_in_time
                ? $attendance->clock_in_time->timezone($companyTimezone)->translatedFormat($dateFormat)
                : '';

            $clockIn = $attendance->clock_in_time
                ? $attendance->clock_in_time->timezone($companyTimezone)->translatedFormat($timeFormat)
                : '';

            $clockOut = $attendance->clock_out_time
                ? $attendance->clock_out_time->timezone($companyTimezone)->translatedFormat($timeFormat)
                : '';

            $durationHours = 0;
            if ($attendance->clock_in_time && $attendance->clock_out_time) {
                $durationMinutes = $attendance->clock_in_time->diffInMinutes($attendance->clock_out_time);
                $totalDurationMinutes += $durationMinutes;
                $durationHours = round($durationMinutes / 60, 2);
            }

            $rows[] = (object) [
                'sno' => $isRepeatEmployee ? '' : $sno++,
                'employee_name' => $isRepeatEmployee ? '' : ($employee->employee_name ?? '--'),
                'email_address' => $isRepeatEmployee ? '' : ($employee->email_address ?? '--'),
                'department_name' => $isRepeatEmployee ? '' : ($employee->department_name ?? '--'),
                'attendance_date' => $attendanceDate,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'duration_hours' => number_format($durationHours, 2, '.', '') . 'h',
            ];
        }

        // Add totals row similar to the reference report
        $totalDurationHours = round($totalDurationMinutes / 60, 2);
        $rows[] = (object) [
            'sno' => '',
            'employee_name' => 'TOTAL HOURS LOGGED',
            'email_address' => '',
            'department_name' => '',
            'attendance_date' => '',
            'clock_in' => '',
            'clock_out' => '',
            'duration_hours' => number_format($totalDurationHours, 2, '.', '') . 'h',
        ];

        return collect($rows);
    }

    public function map($row): array
    {
        return [
            $row->sno,
            $row->employee_name,
            $row->email_address,
            $row->department_name,
            $row->attendance_date,
            $row->clock_in,
            $row->clock_out,
            $row->duration_hours,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();

        // Color-code rows by employee to make it easy to differentiate.
        // Since repeated employee rows have empty "Employee Name" cell (column B),
        // we detect employee changes by the first non-empty value in that block.
        $greenFill = ['rgb' => 'DFF2E3']; // light green
        $yellowFill = ['rgb' => 'FFF4CC']; // light yellow
        $currentEmployee = null;
        $employeeIndex = 0;

        $lastRow = $sheet->getHighestRow();
        // Data rows start from row 2, headings are on row 1.
        for ($row = 2; $row <= $lastRow; $row++) {
            $employeeName = (string) $sheet->getCell('B' . $row)->getValue();

            // If this is the total row, stop styling data rows.
            if ($employeeName === 'TOTAL HOURS LOGGED') {
                break;
            }

            // When employee name is present, it's the first row of that employee block.
            if (trim($employeeName) !== '') {
                $currentEmployee = $employeeName;
                $employeeIndex++;
            }

            // If we somehow got a row without an employee yet, skip.
            if ($currentEmployee === null) {
                continue;
            }

            $fillColor = ($employeeIndex % 2 === 1) ? $greenFill : $yellowFill;

            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => $fillColor,
                ],
            ]);
        }

        // Header styling
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        // Total row styling (last row)
        if ($lastRow >= 2) {
            $sheet->getStyle('A' . $lastRow . ':H' . $lastRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6EEF9'],
                ],
            ]);
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(14);
    }
}

