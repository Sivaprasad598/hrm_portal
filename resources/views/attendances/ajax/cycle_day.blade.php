{{-- A day without punches, from the salary-month grid (AttendanceController::cycleDay). --}}
@php
    [$label, $colour, $icon] = $day ? ($statusMeta[$day['status']] ?? [$day['status'], 'light', 'fa-circle']) : ['—', 'light', 'fa-circle'];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp

<div class="modal-header">
    <h5 class="modal-title">Attendance details</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <div class="d-flex align-items-center mb-3">
        <img src="{{ $employee->image_url }}" class="rounded-circle mr-2" width="36" height="36" alt="">
        <div>
            <div class="f-14 font-weight-500 text-dark">{{ $employee->name }}</div>
            <div class="f-12 text-lightest">{{ $employee->employeeDetail->employee_id ?? '' }}{{ optional(optional($employee->employeeDetail)->designation)->name ? ' · ' . $employee->employeeDetail->designation->name : '' }}</div>
        </div>
        <div class="ml-auto text-right">
            <div class="f-15 font-weight-500">{{ $date->format('l, d F Y') }}</div>
            @if ($day && !empty($day['shift']))<span class="badge badge-light border f-11">{{ $day['shift'] }} {{ $day['shift_hours'] ?? '' }}</span>@endif
        </div>
    </div>

    <table class="table table-sm table-bordered f-13 mb-3">
        <tr><td class="text-dark-grey w-30">Counted as</td>
            <td><span class="badge badge-{{ $colour }}"><i class="fa {{ $icon }} mr-1"></i>{{ $label }}</span>
                @if (!empty($day['note']))<span class="f-12 text-dark-grey ml-1">{{ $day['note'] }}</span>@endif</td></tr>
        @if ($day && $day['status'] !== 'upcoming')
            <tr><td class="text-dark-grey">Paid for the day</td><td class="{{ $day['paid'] < 1 ? 'text-red font-weight-bold' : '' }}">{{ $fmt($day['paid']) }} day</td></tr>
        @endif
        @if ($holiday)
            <tr><td class="text-dark-grey">Holiday</td><td>{{ $holiday }}</td></tr>
        @endif
        <tr><td class="text-dark-grey">Clock in / out</td><td class="text-lightest">No punches recorded for this day.</td></tr>
    </table>

    @if ($leaves->isNotEmpty())
        <h6 class="f-13 font-weight-600 mb-2">Leave</h6>
        <table class="table table-sm table-bordered f-13 mb-0">
            <thead class="bg-light"><tr><th>Type</th><th>Duration</th><th>Status</th><th>Paid</th><th>Reason</th></tr></thead>
            <tbody>
            @foreach ($leaves as $l)
                <tr>
                    <td>{{ $l->type_name ?: '—' }}</td>
                    <td>{{ $l->duration === 'half day' ? 'Half day' . ($l->half_day_type ? ' (' . str_replace('_', ' ', $l->half_day_type) . ')' : '') : 'Full day' }}</td>
                    <td><span class="badge badge-{{ $l->status === 'approved' ? 'success' : ($l->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($l->status) }}</span></td>
                    <td>{{ $l->paid ? 'Paid' : 'Unpaid (LOP)' }}</td>
                    <td>{{ $l->reason ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
    @if ($canEditAttendance)
        <x-forms.button-primary class="cycle-mark" icon="plus" data-user-id="{{ $employee->id }}" data-date="{{ $date->toDateString() }}">Mark attendance</x-forms.button-primary>
    @endif
</div>
