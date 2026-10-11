<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class ProjectImport implements ToArray
{
    use \Illuminate\Support\Traits\Macroable; // Optional

    protected array $processedData = [];

    public static function fields(): array
    {
        return array(
            array('id' => 'project_name', 'name' => __('modules.projects.projectName'), 'required' => 'Yes'),
            array('id' => 'project_summary', 'name' => __('modules.projects.projectSummary'), 'required' => 'No'),
            array('id' => 'start_date', 'name' => __('modules.projects.startDate'), 'required' => 'Yes'),
            array('id' => 'deadline', 'name' => __('modules.projects.deadline'), 'required' => 'No'),
            array('id' => 'client_email', 'name' => __('app.client') . ' ' . __('app.email'), 'required' => 'No'),
            array('id' => 'project_budget', 'name' => __('modules.projects.projectBudget'), 'required' => 'No'),
            array('id' => 'status', 'name' => __('app.status'), 'required' => 'No'),
            array('id' => 'notes', 'name' => __('modules.projects.note'), 'required' => 'No'),
            array('id' => 'project_members', 'name' => __('modules.projects.projectMembers'), 'required' => 'No'),
        );
    }

    public function array(array $array): array
    {
        $header = $array[0];
        $dataRows = array_slice($array, 1);

        $startDateIndex = array_search('Start Date', $header);
        $deadlineIndex = array_search('Deadline', $header);

        foreach ($dataRows as &$row) {
            if ($startDateIndex !== false && isset($row[$startDateIndex])) {
                $row[$startDateIndex] = $this->convertExcelDateToString($row[$startDateIndex]);
            }

            if ($deadlineIndex !== false && isset($row[$deadlineIndex])) {
                $row[$deadlineIndex] = $this->convertExcelDateToString($row[$deadlineIndex]);
            }
        }

        $this->processedData = [$header, ...$dataRows];
        return $array;
    }

    public function getProcessedData(): array
    {
        return $this->processedData;
    }

    private function convertExcelDateToString($value)
    {
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

}
