<?php

namespace App\Exports;

use App\Models\ProjectTimeLog;
use App\Models\CustomField;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectTimeLogsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles, Responsable
{

    /**
     * Optional filters
     */
    protected ?string $startDate;
    protected ?string $endDate;
    protected ?int $userId;
    
    /**
     * Custom fields to export
     */
    protected $customFields;
    protected $customFieldsData;

    /**
     * The file name for download
     *
     * @var string
     */
    public string $fileName = 'project-time-logs.xlsx';

    public function __construct(?string $startDate = null, ?string $endDate = null, ?int $userId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
        
        // Load custom fields that should be exported
        $this->customFields = CustomField::exportCustomFields(new ProjectTimeLog());
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Determine date window: default to current month
        $companyTz = company() ? company()->timezone : config('app.timezone');
        $startCompanyTz = $this->startDate
            ? Carbon::parse($this->startDate, $companyTz)->startOfDay()
            : now($companyTz)->startOfMonth();
        $endCompanyTz = $this->endDate
            ? Carbon::parse($this->endDate, $companyTz)->endOfDay()
            : now($companyTz)->endOfMonth()->endOfDay();

        // Convert the company window to UTC for querying stored datetimes
        $start = $startCompanyTz->clone()->setTimezone('UTC');
        $end = $endCompanyTz->clone()->setTimezone('UTC');

        $query = ProjectTimeLog::query()
            ->without('breaks', 'activeBreak')
            ->with(['user', 'activeBreak', 'project', 'task'])
            ->withSum('breaks', 'total_minutes')
            ->select(['id', 'user_id', 'project_id', 'task_id', 'start_time', 'end_time', 'total_minutes'])
            ->whereBetween('start_time', [$start, $end])
            ->when($this->userId, function ($q) {
                $q->where('user_id', $this->userId);
            })
            ->orderBy('start_time', 'desc');

        $timelogs = $query->get();
        
        // Initialize customFieldsData as empty collection
        $this->customFieldsData = collect();
        
        // Load custom fields data for all timelogs
        if ($this->customFields->isNotEmpty() && $timelogs->isNotEmpty()) {
            $customFieldsId = $this->customFields->pluck('id')->toArray();
            $timelogIds = $timelogs->pluck('id')->toArray();
            
            $this->customFieldsData = DB::table('custom_fields_data')
                ->where('model', ProjectTimeLog::CUSTOM_FIELD_MODEL)
                ->whereIn('custom_field_id', $customFieldsId)
                ->whereIn('model_id', $timelogIds)
                ->select('id', 'custom_field_id', 'model_id', 'value')
                ->get()
                ->groupBy('model_id');
        }

        return $timelogs;
    }

    public function headings(): array
    {
        $headings = [
            __('app.employee'),
            __('modules.taskCode'),
            __('app.task'),
            __('app.project'),
            __('modules.timeLogs.startTime'),
            __('modules.timeLogs.endTime'),
            __('modules.timeLogs.totalHours'),
        ];
        
        // Add custom field headings
        foreach ($this->customFields as $field) {
            $headings[] = $field->label;
        }
        
        return $headings;
    }

    /**
     * @param ProjectTimeLog $log
     */
    public function map($log): array
    {
        $companyTz = company() ? company()->timezone : config('app.timezone');
        $start = $log->start_time instanceof Carbon ? $log->start_time : Carbon::parse($log->start_time);
        $end = $log->end_time ? ($log->end_time instanceof Carbon ? $log->end_time : Carbon::parse($log->end_time)) : null;

        // Stored in UTC; convert to company timezone for display
        $start = $start->clone()->tz($companyTz)->translatedFormat(company()->date_format . ' ' . company()->time_format);
        $end = $end ? $end->clone()->tz($companyTz)->translatedFormat(company()->date_format . ' ' . company()->time_format) : null;

        $row = [
            optional($log->user)->name,
            optional($log->project)->project_short_code,
            optional($log->task)->heading,
            optional($log->project)->project_name,
            $start,
            $end,
            $this->formatTotalHours($log),
        ];
        
        // Add custom field values
        foreach ($this->customFields as $field) {
            $row[] = $this->getCustomFieldValue($log, $field);
        }
        
        return $row;
    }
    
    /**
     * Get custom field value for export
     */
    protected function getCustomFieldValue($log, $customField)
    {
        // Get custom fields data for this timelog
        $timelogCustomFieldsData = $this->customFieldsData[$log->id] ?? collect();
        
        // Find the specific field value
        $fieldData = $timelogCustomFieldsData->firstWhere('custom_field_id', $customField->id);
        
        if (!$fieldData || is_null($fieldData->value) || $fieldData->value === '') {
            return '--';
        }
        
        // Format based on field type
        switch ($customField->type) {
            case 'select':
                // Select fields store the index, need to look up the value
                $values = json_decode($customField->values, true);
                return isset($values[$fieldData->value]) ? $values[$fieldData->value] : '--';
            
            case 'radio':
                // Radio fields store the actual value directly (not index)
                return $fieldData->value;
                
            case 'date':
                try {
                    return Carbon::parse($fieldData->value)->translatedFormat(company()->date_format);
                } catch (\Exception $e) {
                    return $fieldData->value;
                }
                
            case 'checkbox':
                return $fieldData->value;
                
            default:
                return $fieldData->value;
        }
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_DATE_DATETIME,
            'F' => NumberFormat::FORMAT_DATE_DATETIME,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Calculate the last column letter based on standard columns + custom fields
        $totalColumns = 7 + $this->customFields->count(); // 7 standard columns + custom fields
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);
        
        // Bold header row and center it
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        return [];
    }

    protected function formatTotalHoursDataTable(ProjectTimeLog $log): string
    {
        $breakMinutes = (int) ($log->breaks_sum_total_minutes ?? 0);

        if (is_null($log->end_time)) {
            // Open timer: compute up to now or leave as running duration
            $end = $log->activeBreak ? $log->activeBreak->start_time : now();
            // $totalMinutes = $end->diffInMinutes($log->start_time) - $breakMinutes;
            $totalMinutes = abs(
                (int) $end->diffInMinutes($log->start_time) - $breakMinutes
            );
        } else {
            $totalMinutes = ((int) $log->total_minutes) - $breakMinutes;
        }

        if ($totalMinutes < 0) {
            $totalMinutes = 0;
        }

        $hours = (int) floor($totalMinutes / 60);
        $minutes = (int) ($totalMinutes % 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
    
    protected function formatTotalHours(ProjectTimeLog $row): string
    {
        $now = \Illuminate\Support\Carbon::now();
        // Determine total minutes based on end_time
        $totalMinutes = is_null($row->end_time)
            ? (
                ($row->activeBreak)
                    ? $row->activeBreak->start_time->diffInMinutes($row->start_time)
                    : abs($now->diffInMinutes($row->start_time))
            ) - abs($row->breaks->sum('total_minutes'))
            : $row->total_minutes - $row->breaks->sum('total_minutes');
      
        $totalMinutes = abs((int) $totalMinutes);
        // Convert total minutes to hours and minutes
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        // Format output based on hours and minutes
        $formattedTime = $hours > 0
            ? $hours . 'h' . ($minutes > 0 ? ' ' . sprintf('%02dm', $minutes) : '')
            : ($minutes > 0 ? sprintf('%dm', $minutes) : '0s');
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}


