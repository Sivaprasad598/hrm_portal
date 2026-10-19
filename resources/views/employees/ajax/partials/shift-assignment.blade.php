{{-- The employee's own shift, from a date (ShiftAssignments). On Add it starts
     from the joining date unless another date is given; on Edit a change
     applies from the date chosen and earlier days keep the shift they had.
     $shiftEmployee: the employee (Edit) or null (Add). --}}
@php
    $shiftOptions = \App\Models\EmployeeShift::where('shift_name', '<>', 'Day Off')->orderBy('office_start_time')->get();
    $defaultShiftId = attendance_setting()?->default_employee_shift;
    $defaultShiftRow = $shiftOptions->firstWhere('id', $defaultShiftId);
    $assignmentService = app(\App\Services\Attendance\ShiftAssignments::class);
    $currentAssignment = $shiftEmployee ? $assignmentService->current($shiftEmployee->id) : null;
    $shiftHistory = $shiftEmployee ? $assignmentService->history($shiftEmployee->id) : collect();
    $hours = fn ($s) => substr((string) $s->office_start_time, 0, 5) . '–' . substr((string) $s->office_end_time, 0, 5)
        . (\App\Services\Attendance\ShiftResolver::isOvernight($s) ? ' · overnight' : '');
@endphp
<div class="col-lg-3 col-md-6">
    <x-forms.select fieldId="employee_shift_id" fieldLabel="Work Shift" fieldName="employee_shift_id">
        <option value="">Company default{{ $defaultShiftRow ? ' — ' . $defaultShiftRow->shift_name . ' (' . $hours($defaultShiftRow) . ')' : '' }}</option>
        @foreach ($shiftOptions as $option)
            <option value="{{ $option->id }}" @selected($currentAssignment && (int) $currentAssignment->employee_shift_id === (int) $option->id)>
                {{ $option->shift_name }} ({{ $hours($option) }})
            </option>
        @endforeach
    </x-forms.select>
</div>
<div class="col-lg-3 col-md-6">
    <x-forms.datepicker fieldId="shift_effective_from" fieldLabel="Shift Effective From" fieldName="shift_effective_from"
        :fieldValue="$shiftEmployee ? now(company()->timezone)->translatedFormat(company()->date_format) : ''"
        :fieldPlaceholder="$shiftEmployee ? __('placeholders.date') : 'Joining date'"
        :fieldHelp="$shiftEmployee ? 'A change applies from this date; earlier days keep their shift.' : 'Empty = from the joining date.'" />
</div>
@if ($shiftHistory->isNotEmpty())
    <div class="col-12">
        <p class="f-12 text-dark-grey mb-1 mt-2"><i class="fa fa-history mr-1"></i>Shift history</p>
        <div class="d-flex flex-wrap">
            @foreach ($shiftHistory as $row)
                <span class="badge badge-light border f-12 mr-2 mb-1 p-2 text-left">
                    <strong>{{ $row->shift_name }}</strong>
                    {{ substr((string) $row->office_start_time, 0, 5) }}–{{ substr((string) $row->office_end_time, 0, 5) }}
                    · from {{ \Carbon\Carbon::parse($row->effective_from)->translatedFormat(company()->date_format) }}
                    {{ $row->effective_to ? 'to ' . \Carbon\Carbon::parse($row->effective_to)->translatedFormat(company()->date_format) : '(current)' }}
                </span>
            @endforeach
        </div>
    </div>
@endif
