<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class TaskImport implements ToArray
{

    protected array $processedData = [];

    public static function fields(): array
    {
        return array(
            array('id' => 'heading', 'name' => __('app.title'), 'required' => 'Yes'),
            array('id' => 'start_date', 'name' => __('modules.projects.startDate'), 'required' => 'Yes'),
            array('id' => 'priority', 'name' => __('modules.tasks.priority'), 'required' => 'Yes'),
            array('id' => 'description', 'name' => __('app.description'), 'required' => 'No'),
            array('id' => 'due_date', 'name' => __('app.dueDate'), 'required' => 'No'),
            array('id' => 'project_name', 'name' => __('app.project'), 'required' => 'No'),
            array('id' => 'category', 'name' => __('modules.tasks.taskCategory'), 'required' => 'No'),
            array('id' => 'status', 'name' => __('app.status'), 'required' => 'No'),
            array('id' => 'assignees', 'name' => __('modules.tasks.assignTo'), 'required' => 'No'),
            array('id' => 'labels', 'name' => __('app.label'), 'required' => 'No'),
            array('id' => 'milestone', 'name' => __('modules.projects.milestones'), 'required' => 'No'),
            array('id' => 'is_private', 'name' => __('modules.tasks.makePrivate'), 'required' => 'No'),
            array('id' => 'billable', 'name' => __('modules.tasks.billable'), 'required' => 'No'),
            array('id' => 'estimate_hours', 'name' => __('app.hrs'), 'required' => 'No'),
            array('id' => 'estimate_minutes', 'name' => __('app.mins'), 'required' => 'No'),
            array('id' => 'dependent_task', 'name' => __('modules.tasks.dependentTask'), 'required' => 'No'),
            array('id' => 'subtasks', 'name' => __('modules.tasks.subTask'), 'required' => 'No'),
        );
    }

    public function array(array $array): array
    {
        $header = $array[0] ?? [];
        $dataRows = array_slice($array, 1);

        $startDateIndex = array_search('Start Date', $header);
        $dueDateIndex = array_search('Due Date', $header);

        foreach ($dataRows as &$row) {
            if ($startDateIndex !== false && isset($row[$startDateIndex])) {
                $row[$startDateIndex] = $this->convertExcelDateToString($row[$startDateIndex]);
            }

            if ($dueDateIndex !== false && isset($row[$dueDateIndex])) {
                $row[$dueDateIndex] = $this->convertExcelDateToString($row[$dueDateIndex]);
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
