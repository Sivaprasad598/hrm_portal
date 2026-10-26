

<!-- LEAVE SETTING START -->
@php
    // Who a leave type applies to, in a line: "All departments", or the first
    // two and "+N more" (the full list on hover) - not a list of every one.
    $appliesTo = function ($json, $all, $nameField, string $noun) {
        $ids = array_map('intval', (array) (json_decode((string) $json, true) ?: []));
        $names = $all->whereIn('id', $ids)->pluck($nameField)->values();

        if ($names->isEmpty()) {
            return '<span class="text-lightest">None</span>';
        }

        if ($names->count() >= $all->count()) {
            return '<span class="badge badge-light border f-12 font-weight-normal">All ' . e($noun) . '</span>';
        }

        $shown = $names->take(2)->map(fn ($n) => e($n))->implode(', ');
        $rest = $names->count() - 2;

        return $shown . ($rest > 0 ? ' <span class="badge badge-light border f-11 font-weight-normal" data-toggle="tooltip" title="'
            . e($names->slice(2)->implode(', ')) . '">+' . $rest . ' more</span>' : '');
    };
    $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">

    <div class="table-responsive">
        <x-table class="table-bordered">
            <x-slot name="thead">
                <th style="min-width: 240px">@lang('modules.leaves.leaveType')</th>
                <th>@lang('modules.leaves.leaveAllotmentType')</th>
                <th>@lang('modules.leaves.noOfLeaves')</th>
                <th>@lang('modules.leaves.monthLimit')</th>
                <th>@lang('modules.leaves.leavePaidStatus')</th>
                <th>@lang('app.department')</th>
                <th>@lang('app.designation')</th>
                <th class="text-right">@lang('app.action')</th>
            </x-slot>

            @forelse($leaveTypes as $key=>$leaveType)
                <tr id="type-{{ $leaveType->id }}">
                    <td>
                        <p class="f-w-500 mb-0"><i class="fa fa-circle mr-1 text-yellow"
                                style="color: {{ $leaveType->color }}"></i>{{ $leaveType->type_name }}
                        </p>
                        @foreach (\App\Services\Leaves\LeaveRules::describe($leaveType) as $rule)
                            <div class="f-11 text-dark-grey">{{ $rule }}</div>
                        @endforeach
                    </td>
                    <td>
                        @if ($leaveType->isUnlimited())
                            @lang('app.unlimitedLeaveType')
                        @else
                            {{ ucfirst($leaveType->leavetype) }}
                        @endif
                    </td>
                    <td> {{ $leaveType->isUnlimited() ? '--' : $days($leaveType->no_of_leaves) }}</td>
                    <td> {{ ($leaveType->isUnlimited() || $leaveType->monthly_limit <= 0) ? '--' : $days($leaveType->monthly_limit) }}</td>
                    <td>
                        @if ($leaveType->paid == 1)
                            @lang('modules.credit-notes.paid')
                        @else
                            @lang('modules.credit-notes.unpaid')
                        @endif
                    </td>
                    <td class="f-13">{!! $appliesTo($leaveType->department, $departments, 'team_name', 'departments') !!}</td>
                    <td class="f-13">{!! $appliesTo($leaveType->designation, $designations, 'name', 'designations') !!}</td>
                    <td class="text-right">
                        <div class="task_view">
                            <a href="javascript:;" data-leave-id="{{ $leaveType->id }}"
                                class="editNewLeaveType task_view_more d-flex align-items-center justify-content-center">
                                <i class="fa fa-edit icons mr-2"></i> @lang('app.edit')
                            </a>
                        </div>
                        @if($leaveType->leaves_count > 0)
                        <div class="task_view">
                            <a href="javascript:;" data-leave-id="{{ $leaveType->id }}" data-leave-status="archive"
                                data-toggle="tooltip" data-placement="top" title="@lang('messages.whyArchive')"
                                class="archive-leave task_view_more d-flex align-items-center justify-content-center">
                                <i class="fa fa-archive icons mr-2"></i> @lang('app.archive')
                            </a>
                        </div>
                        @else
                        <div class="task_view mt-1 mt-lg-0 mt-md-0">
                            <a href="javascript:;" data-leave-id="{{ $leaveType->id }}" data-leave-status="force_delete"
                                class="delete-category task_view_more d-flex align-items-center justify-content-center">
                                <i class="fa fa-trash icons mr-2"></i> @lang('app.delete')
                            </a>
                        </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-cards.no-record icon="list" :message="__('messages.noLeaveTypeAdded')" />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>

</div>
<!-- LEAVE SETTING END -->

<script>
    $('body').on('click', '.delete-category', function() {

    var id = $(this).data('leave-id');
    var force_delete = $(this).data('leave-status');

    Swal.fire({
        title: "@lang('messages.sweetAlertTitle')",
        text: "@lang('messages.deleteLeaveType')",
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

            var url = "{{ route('leaveType.destroy', ':id') }}";
            url = url.replace(':id', id);

            var token = "{{ csrf_token() }}";

            $.easyAjax({
                type: 'POST',
                url: url,
                blockUI: true,
                data: {
                    '_token': token,
                    '_method': 'DELETE',
                    'force_delete': force_delete,
                },
                success: function(response) {
                    if (response.status == "success") {
                        $('#type-' + id).fadeOut();
                    }
                }
            });
        }
    });
    });

    // ---

    $('body').on('click', '.archive-leave', function() {

        var id = $(this).data('leave-id');
        var archive = $(this).data('leave-status');

        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.archiveMessageLeave')",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('messages.confirmArchive')",
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

                var url = "{{ route('leaveType.destroy', ':id') }}";
                url = url.replace(':id', id);

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    blockUI: true,
                    data: {
                        '_token': token,
                        '_method': 'DELETE',
                        'archive' : archive,
                    },
                    success: function(response) {
                        if (response.status == "success") {
                            $('#type-' + id).fadeOut();
                        }
                    }
                });
            }
        });
    });


    // add new leave type
    $('#addNewLeaveType').click(function() {
    var url = "{{ route('leaveType.create') }}";
    $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
    $.ajaxModal(MODAL_XL, url);
    });


    $('.editNewLeaveType').click(function() {

        var id = $(this).data('leave-id');

        var url = "{{ route('leaveType.edit', ':id ') }}";
        url = url.replace(':id', id);

        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

</script>
