@php
$manageShiftPermission = user()->permission('manage_employee_shifts');
@endphp
<div class="table-responsive">
    <x-table class="table-bordered">
        <x-slot name="thead">
            <th>@lang('app.name')</th>
            <th>Timing</th>
            <th>Working days</th>
            @if ($manageShiftPermission == 'all')
                <th>&nbsp;</th>
                <th class="text-right pr-20">@lang('app.action')</th>
            @else
                <th>&nbsp;</th>
            @endif
        </x-slot>

        @php $shiftUsage = \App\Services\Attendance\ShiftAssignments::usage(); @endphp
        @forelse($employeeShifts as $shift)
            <tr class="row{{ $shift->id }}">
                <td>
                    <span class="badge badge-info f-12 p-1" style="background-color: {{ $shift->color }}">
                        {{ $shift->shift_name }}</span>
                    @if (\App\Services\Attendance\ShiftResolver::isOvernight($shift))
                        <span class="badge badge-dark f-10 ml-1">overnight</span>
                    @endif
                    {{-- Employees given this shift as their own (Add / Edit Employee) --}}
                    @if (!empty($shiftUsage[$shift->id]))
                        <div class="f-11 text-dark-grey mt-1"><i class="fa fa-users mr-1"></i>{{ $shiftUsage[$shift->id] }} employee(s) assigned</div>
                    @elseif ($shift->id == attendance_setting()->default_employee_shift)
                        <div class="f-11 text-lightest mt-1">Everyone without their own shift</div>
                    @endif
                </td>
                @php
                    $t = fn ($v) => $v && $v !== '00:00:00' ? \Carbon\Carbon::createFromFormat('H:i:s', $v)->translatedFormat(company()->time_format) : null;
                    $h = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
                    $flexible = \App\Services\Attendance\WorkRules::isFlexible($shift);
                @endphp
                <td class="f-13">
                    @if ($flexible)
                        <span class="badge badge-light border f-11 mb-1">Flexible hours</span>
                        <div>Full day after {{ $h($shift->flexible_total_hours) }} h including breaks, any time of day</div>
                        <div class="text-dark-grey f-12">Half day: {{ $h($shift->flexible_half_day_hours ?: $shift->flexible_total_hours / 2) }} h &middot; no late marks</div>
                    @else
                        <span class="badge badge-light border f-11 mb-1">Fixed timing</span>
                        <div>{{ $t($shift->office_start_time) }} &ndash; {{ $t($shift->office_end_time) }}</div>
                        <div class="text-dark-grey f-12">
                            Late after {{ (int) $shift->late_mark_duration }} min{{ $t($shift->halfday_mark_time) ? ' · half day if in after ' . $t($shift->halfday_mark_time) : '' }}
                        </div>
                    @endif
                </td>
                <td class="f-13">
                    @foreach (json_decode($shift->office_open_days) ?: [] as $item)
                        {{ $weekMap[$item] ?? '' }}@if (!$loop->last), @endif
                    @endforeach
                </td>
                @if ($manageShiftPermission == 'all')
                    <td>
                        <x-forms.radio fieldId="shift_{{ $shift->id }}" class="set_default_shift"
                            data-shift-id="{{ $shift->id }}" :fieldLabel="__('app.default')" fieldName="set_default_shift"
                            fieldValue="{{ $shift->id }}" :checked="$shift->id == attendance_setting()->default_employee_shift ? 'checked' : ''">
                        </x-forms.radio>
                    </td>
                    <td class="text-right pr-20">
                        <div class="task_view mb-1">
                            <a href="javascript:;" data-shift-id="{{ $shift->id }}"
                                class="edit-shift task_view_more d-flex align-items-center justify-content-center" data-toggle="tooltip"
                                data-original-title="@lang('app.edit')"> <i
                                    class="fa fa-edit icons"></i>
                            </a>
                        </div>
                        @if ($shift->id != attendance_setting()->default_employee_shift)
                            <div class="task_view mt-1 mt-lg-0 mt-md-0">
                                <a href="javascript:;" data-shift-id="{{ $shift->id }}"
                                    class="delete-shift task_view_more d-flex align-items-center justify-content-center dropdown-toggle" data-toggle="tooltip"
                                    data-original-title="@lang('app.delete')">
                                    <i class="fa fa-trash icons"></i>
                                </a>
                            </div>
                        @endif
                    </td>
                @elseif (isset($shift) && isset($defaultShift) && $shift->shift_name == $defaultShift->shift_name)
                    <td>
                        @lang('app.defaultShift')
                    </td>
                @else
                    <td>&nbsp;</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-cards.no-record icon="user" :message="__('messages.noAgentAdded')" />
                </td>
            </tr>
        @endforelse
    </x-table>
</div>

<script>
    $('body').on('click', '#addEmployeeShift', function() {
        var url = "{{ route('employee-shifts.create') }}";
        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

    $('body').on('click', '.edit-shift', function() {
        var shiftID = $(this).data('shift-id');
        var url = "{{ route('employee-shifts.edit', ':id') }}";
        url = url.replace(':id', shiftID);

        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

    /* delete shift */
    $('body').on('click', '.delete-shift', function() {
        var id = $(this).data('shift-id');

        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.removeShiftText')",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('messages.confirmDelete')",
            cancelButtonText: "@lang('app.cancel')",
            customClass: {
                confirmButton: 'btn btn-primary mr-3',
                cancelButton: 'btn btn-secondary'
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{ route('employee-shifts.destroy', ':id') }}";
                url = url.replace(':id', id);

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    blockUI: true,
                    data: {
                        '_token': token,
                        '_method': 'DELETE'
                    },
                    success: function(response) {
                        if (response.status == "success") {
                            $('.row' + id).fadeOut(100);
                        }
                    }
                });
            }
        });
    });

    $('body').on('click', '.set_default_shift', function() {
        var shiftID = $(this).data('shift-id');
        var token = "{{ csrf_token() }}";

        $.easyAjax({
            url: "{{ route('employee-shifts.set_default') }}",
            type: "POST",
            data: {
                shiftID: shiftID,
                _token: token
            },
            blockUI: true,
            container: '#editSettings',
            success: function(response) {
                if (response.status == "success") {
                    window.location.reload();
                }
            }
        });
    });

</script>
