<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Carbon;

class AttendanceImport implements ToArray
{

    protected array $processedData = [];

    public static function fields(): array
    {
        return array(
            array('id' => 'employee_card_id', 'name' => __('modules.employees.employeeCardId'), 'required' => 'Yes'),
            array('id' => 'name', 'name' => __('modules.employees.employeeName'), 'required' => 'Yes'),
            array('id' => 'type', 'name' => __('modules.attendance.punchType'), 'required' => 'Yes'),
            array('id' => 'punch_time', 'name' => __('modules.attendance.punchTime'), 'required' => 'Yes'),
        );
    }

    public function array(array $array): array
    {
        $this->processedData = $array;
        return $array;
    }

    public function getProcessedData(): array
    {
        return $this->processedData;
    }

    /**
     * Parses a Punch Time cell value into a Carbon instance treated as being in $timezone.
     * Handles both genuine Excel date cells (returned as numeric serial values) and
     * plain text cells (e.g. "17/07/2026 16:45:34"). Returns null if unparseable.
     */
    public static function parsePunchTime($value, $timezone = null): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $naive = ExcelDate::excelToDateTimeObject($value);

                return Carbon::parse($naive->format('Y-m-d H:i:s'), $timezone);
            } catch (\Exception $e) {
                return null;
            }
        }

        $value = trim((string) $value);

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $value, $timezone);
        } catch (\Exception $e) {
            try {
                return Carbon::parse($value, $timezone);
            } catch (\Exception $e) {
                return null;
            }
        }
    }

}

