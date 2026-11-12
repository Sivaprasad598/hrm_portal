@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
    <style>
        .filter-box {
            z-index: 2;
        }

    </style>
@endpush


@php
$addLeavePermission = user()->permission('add_leave');
$approveRejectPermission = user()->permission('approve_or_reject_leaves');
// A manager's Team Leaves: only what a manager needs (LeaveController::access()).
$managerView = \App\Http\Controllers\LeaveController::access()['managerView'];
@endphp

@section('filter-section')
    <x-filters.filter-box>
        {{-- A manager's Team Leaves filters by status (the cards set it too). --}}
        @if ($managerView)
            <div class="select-box tl-grow d-flex py-2 pr-2 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.status')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="status" id="status" data-size="8">
                        <option value="all">@lang('app.all')</option>
                        <option value="pending">@lang('app.pending')</option>
                        <option value="approved">@lang('app.approved')</option>
                        <option value="rejected">@lang('app.rejected')</option>
                    </select>
                </div>
            </div>
        {{-- Leaves waiting for this person's decision (manager, department head, HR) --}}
        @elseif (\App\Services\Leaves\LeaveApprovals::isApprover() || $approveRejectPermission === 'all')
            <div class="select-box d-flex py-2 pr-2 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Show</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="waiting" id="waiting" data-size="8">
                        <option value="">All I can see</option>
                        <option value="me" @selected(request('waiting') === 'me')>Waiting for me</option>
                    </select>
                </div>
            </div>
        @endif

        <!-- DATE START -->
        <div class="select-box {{ $managerView ? 'tl-grow' : '' }} d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.duration')</p>
            <div class="select-status d-flex">
                <input type="text" class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="datatableRange" placeholder="@lang('placeholders.dateRange')"
                    value="{{ request('start') && request('end') ? request('start') . ' ' . __('app.to') . ' ' . request('end') : '' }}">
            </div>
        </div>
        <!-- DATE END -->

        @if ($managerView)
            {{-- Team Leaves: pick one person of the team (searchable) instead of a text search --}}
            <div class="select-box tl-grow d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.employee')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="employee_id" id="employee_id"
                        data-live-search="true" data-size="8" data-container="body">
                        <option value="all">All team members</option>
                        @foreach ($employees as $employee)
                            <x-user-option :user="$employee" />
                        @endforeach
                    </select>
                </div>
            </div>
        @else
        <!-- SEARCH BY TASK START -->
        <div class="task-search d-flex  py-1 px-lg-2 px-0 border-right-grey align-items-center">
            <form class="w-100 mr-1 mr-lg-0 mr-md-1 ml-md-1 ml-0 ml-lg-0">
                <div class="input-group bg-grey rounded">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-0 bg-additional-grey">
                            <i class="fa fa-search f-13 text-dark-grey"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="search-text-field"
                        placeholder="@lang('app.startTyping')">
                </div>
            </form>
        </div>
        @endif
        <!-- SEARCH BY TASK END -->

        <!-- LEAVE MANAGEMENT START: everybody's leave, not for an employee's own table -->
        @if (\App\Http\Controllers\LeaveController::access()['team'] && !$managerView)
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.link-secondary link="javascript:;" id="open-leave-management" icon="tasks">
                Leave Management
            </x-forms.link-secondary>
        </div>
        @endif
        <!-- LEAVE MANAGEMENT END -->

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->

        <!-- MORE FILTERS START -->
        @unless ($managerView)
        <x-filters.more-filter-box>

            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('app.employee')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="employee_id" id="employee_id"
                            data-live-search="true" data-container="body" data-size="8">
                            @if ($employees->count() > 1 || in_array('admin', user_roles()))
                                <option value="all">@lang('app.all')</option>
                            @endif
                            @foreach ($employees as $employee)
                                    <x-user-option :user="$employee" />
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if (!$managerView)
            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('modules.leaves.leaveType')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="leave_type" id="leave_type" data-live-search="true"
                            data-container="body" data-size="8">
                            <option value="all">@lang('app.all')</option>
                            @foreach ($leaveTypes as $leaveType)
                                <option value="{{ $leaveType->id }}">{{ $leaveType->type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if ($approveRejectPermission == 'all')
                <div class="more-filter-items">
                    <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('app.status')</label>
                    <div class="select-filter mb-4">
                        <div class="select-others">
                            <select class="form-control select-picker" name="status" id="status" data-live-search="true"
                                data-container="body" data-size="8">
                                <option value="all">@lang('app.all')</option>
                                <option {{ request('status') == 'approved' ? 'selected' : '' }} value="approved">
                                    @lang('app.approved')</option>
                                <option value="pending">@lang('app.pending')</option>
                                <option value="rejected">@lang('app.rejected')</option>
                            </select>
                        </div>
                    </div>
                </div>
            @endif
            @endif {{-- !$managerView: a manager filters by employee only --}}


        </x-filters.more-filter-box>
        @endunless
        <!-- MORE FILTERS END -->
    </x-filters.filter-box>
@endsection


@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">

        @if ($managerView)
            {{-- Team Leaves: the cards filter the table (click again for all); the
                 Table / Calendar switch sits on the same row. --}}
            <div class="tl-head mb-3">
                <div class="tl-cards">
                    <button type="button" class="tl-card" data-status="pending">
                        <span class="tl-card__icon tl-card__icon--pending"><i class="fa fa-hourglass-half"></i></span>
                        <span class="tl-card__text"><span class="tl-card__count">{{ $teamLeaveCounts['pending'] ?? 0 }}</span>
                            <span class="tl-card__label">Pending approval</span></span>
                    </button>
                    <button type="button" class="tl-card" data-status="approved">
                        <span class="tl-card__icon tl-card__icon--approved"><i class="fa fa-check-circle"></i></span>
                        <span class="tl-card__text"><span class="tl-card__count">{{ $teamLeaveCounts['approved'] ?? 0 }}</span>
                            <span class="tl-card__label">Approved leaves</span></span>
                    </button>
                    <button type="button" class="tl-card" data-status="today">
                        <span class="tl-card__icon tl-card__icon--today"><i class="fa fa-user-clock"></i></span>
                        <span class="tl-card__text"><span class="tl-card__count">{{ $teamOnLeaveToday ?? 0 }}</span>
                            <span class="tl-card__label">On leave today</span></span>
                    </button>
                </div>
                @include('leaves.partials.view-switch', ['active' => 'table', 'class' => 'ml-0'])
            </div>
            <style>
                .tl-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
                .tl-cards { display: flex; gap: 14px; flex-wrap: wrap; flex: 1 1 auto; }
                .tl-cards .tl-card { flex: 1 1 0; }
                /* the Table / Calendar switch at the size it has in its usual bar */
                .tl-head .btn-group > .btn { height: 38px; padding: 0 16px !important; font-size: 14px; }
                /* the three filters share the row */
                body.epic .filter-box .select-box.tl-grow { flex: 1 1 0; min-width: 200px; }
                body.epic .filter-box .select-box.tl-grow .select-status,
                body.epic .filter-box .select-box.tl-grow .bootstrap-select,
                body.epic .filter-box .select-box.tl-grow input.form-control { width: 100% !important; }
                .tl-card { display: flex; align-items: center; gap: 12px; min-width: 200px; padding: 12px 16px; background: #fff;
                    border: 1px solid var(--border-color, #e8eef3); border-radius: var(--card-radius, 12px);
                    box-shadow: var(--card-shadow, 0 1px 3px rgba(16,24,40,.06)); cursor: pointer; text-align: left;
                    transition: border-color .15s, box-shadow .15s, transform .15s; }
                .tl-card:hover { border-color: var(--epic-blue, #c0714f); transform: translateY(-1px); }
                .tl-card.is-active { border-color: var(--epic-blue, #c0714f); box-shadow: 0 0 0 2px color-mix(in srgb, var(--epic-blue, #c0714f) 25%, transparent); }
                .tl-card__icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex: none; }
                .tl-card__icon--pending { background: #fff4e5; color: #b26a00; }
                .tl-card__icon--approved { background: #e8f6ee; color: #1e8a4c; }
                .tl-card__icon--today { background: #eef4ff; color: #3559c7; }
                .tl-card__count { display: block; font-size: 22px; font-weight: 600; line-height: 1.1; color: #101828; }
                .tl-card__label { display: block; font-size: 12.5px; color: #667085; white-space: nowrap; }
                @media (max-width: 575px) { .tl-card { min-width: 0; flex: 1 1 140px; } }
            </style>
        @endif

        <!-- Add Task Export Buttons Start -->
        @unless ($managerView)
        <div class="d-grid d-lg-flex d-md-flex action-bar">
            <div id="table-actions" class="flex-grow-1 align-items-center">
                @if (($addLeavePermission == 'all' || $addLeavePermission == 'added') && !$managerView)
                    <x-forms.link-primary :link="route('leaves.create')" class="mr-3 openRightModal float-left" icon="plus">
                        @lang('modules.leaves.addLeave')
                    </x-forms.link-primary>
                @endif
                @if (canDataTableExport() && !$managerView)
                    <x-forms.button-secondary id="export-all" class="mr-3 mb-2 mb-lg-0" icon="file-export">
                        @lang('app.exportExcel')
                    </x-forms.button-secondary>
                @endif
            </div>


            {{-- Bulk actions only where the table has tick-boxes (LeaveDataTable::bulkActions) --}}
            @if (user()->permission('view_leave') == 'all')
            <x-datatable.actions>
                <div class="select-status mr-3 pl-3">
                    <select name="action_type" class="form-control select-picker" id="quick-action-type" disabled>
                        <option value="">@lang('app.selectAction')</option>
                        @if ($approveRejectPermission == 'all')
                            <option value="change-leave-status">@lang('app.changeLeaveStatus')</option>
                        @endif
                        {{-- Bulk delete: the Super Admin only --}}
                        @if (\App\Services\Access\SystemAccounts::is((int) user()->id))
                            <option value="delete">@lang('app.delete')</option>
                        @endif
                    </select>
                </div>
                <div class="select-status mr-3 d-none quick-action-field" id="change-status-action">
                    <select name="status" class="form-control select-picker">
                        <option value="approved">@lang('app.approved')</option>
                        <option value="pending">@lang('app.pending')</option>
                        <option value="rejected">@lang('app.rejected')</option>
                    </select>
                </div>
            </x-datatable.actions>
            @endif


            @include('leaves.partials.view-switch', ['active' => 'table'])
        </div>
        @endunless

        <!-- leave table Box Start -->
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white">

            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}

        </div>
        <!-- leave table End -->

    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')

    @include('sections.datatable_js')

    <script>
        $('#open-leave-management').click(function() {
            var url = "{{ route('legacy.page', 'leave_management') }}";

            $(RIGHT_MODAL_TITLE).text('Leave Management');
            $(RIGHT_MODAL_CONTENT).html('<iframe src="' + url + '" style="width:100%;min-height:calc(100vh - 140px);border:0;"></iframe>');

            openTaskDetail();
        });

        $('#leaves-table').on('preXhr.dt', function(e, settings, data) {

            @if (request('start') && request('end'))
                $('#datatableRange').data('daterangepicker').setStartDate("{{ request('start') }}");
                $('#datatableRange').data('daterangepicker').setEndDate("{{ request('end') }}");
            @endif

            var dateRangePicker = $('#datatableRange').data('daterangepicker');

            let startDate = $('#datatableRange').val();

            let endDate;

            if (startDate == '') {
                startDate = null;
                endDate = null;
            } else {
                startDate = dateRangePicker.startDate.format('{{ company()->moment_date_format }}');
                endDate = dateRangePicker.endDate.format('{{ company()->moment_date_format }}');
            }

            var employeeId = $('#employee_id').val();
            var leaveTypeId = $('#leave_type').val();
            var status = $('#status').val();
            var searchText = $('#search-text-field').val();

            data['startDate'] = startDate;
            data['endDate'] = endDate;
            data['searchText'] = searchText;
            data['employeeId'] = employeeId;
            data['leaveTypeId'] = leaveTypeId;
            data['status'] = status;
            data['waiting'] = $('#waiting').length ? $('#waiting').val() : '';
        });

        const showTable = () => {
            window.LaravelDataTables["leaves-table"].draw(true);
        }

        // Team Leaves cards set the Status filter: one status at a time, a second
        // click shows all again; choosing a status in the filter lights its card.
        var markTeamCard = function (status) {
            $('.tl-card').removeClass('is-active');
            $('.tl-card[data-status="' + status + '"]').addClass('is-active');
        };

        $('.tl-card').on('click', function () {
            var card = $(this).data('status');
            var picker = $('#datatableRange').data('daterangepicker');
            var wasOn = $(this).hasClass('is-active');

            // "On leave today": approved leave dated today; any other card clears that date.
            if (card === 'today') {
                if (wasOn) {
                    $('#datatableRange').val('');
                    $('#status').val('all').selectpicker('refresh');
                    markTeamCard('');
                } else {
                    picker.setStartDate(moment()); picker.setEndDate(moment());
                    $('#datatableRange').val(moment().format('{{ company()->moment_date_format }}') + ' @lang('app.to') ' + moment().format('{{ company()->moment_date_format }}'));
                    $('#status').val('approved').selectpicker('refresh');
                    markTeamCard('today');
                }
                showTable();
                return;
            }

            if ($('.tl-card[data-status=today]').hasClass('is-active')) { $('#datatableRange').val(''); }
            var next = wasOn ? 'all' : card;
            $('#status').val(next).selectpicker('refresh');
            markTeamCard(next);
            showTable();
        });

        @if ($managerView)
            $('#status').on('change', function () {
                markTeamCard($(this).val());
            });
        @endif

        @if (canDataTableExport())
            $('#export-all').click(function () {

                @if (request('start') && request('end'))
                    $('#datatableRange').data('daterangepicker').setStartDate("{{ request('start') }}");
                    $('#datatableRange').data('daterangepicker').setEndDate("{{ request('end') }}");
                @endif

                var dateRangePicker = $('#datatableRange').data('daterangepicker');

                let startDate = $('#datatableRange').val();
                let endDate;

                if (startDate == '') {
                    startDate = null;
                    endDate = null;
                } else {
                    startDate = dateRangePicker.startDate.format('{{ company()->moment_date_format }}');
                    endDate = dateRangePicker.endDate.format('{{ company()->moment_date_format }}');
                }

                startDate = encodeURIComponent(startDate);
                endDate = encodeURIComponent(endDate);

                var url = "{{ route('leaves.export_all_leave') }}";
                string = `?startDate=${startDate}&endDate=${endDate}`;
                url += string;
                window.location.href = url;

            });
        @endif

        $('#waiting').on('change', function() { showTable(); });

        $('#start-date, #end-date, #employee_id, #leave_type, #status').on('change keyup',
            function() {
                if ($('#start-date').val() != "") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#end-date').val() != "") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#employee_id').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#leave_type').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#status').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else {
                    $('#reset-filters').addClass('d-none');
                    showTable();
                }
            });

        $('#search-text-field').on('keyup', function() {
            if ($('#search-text-field').val() != "") {
                $('#reset-filters').removeClass('d-none');
            } else {
                $('#reset-filters').addClass('d-none');
            }
            // Always redraw so clearing search returns to full results
            showTable();
        });

        $('#reset-filters').click(function() {
            $('#filter-form')[0].reset();

            $('.filter-box #status').val('all');
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('#reset-filters-2').click(function() {
            $('#filter-form')[0].reset();

            $('.filter-box #status').val('all');
            $('.filter-box #leave_type').val('all');
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('#quick-action-type').change(function() {
            const actionValue = $(this).val();

            if (actionValue != '') {
                $('#quick-action-apply').removeAttr('disabled');

                if (actionValue == 'change-leave-status') {
                    $('.quick-action-field').addClass('d-none');
                    $('#change-status-action').removeClass('d-none');
                } else {
                    $('.quick-action-field').addClass('d-none');
                }
            } else {
                $('#quick-action-apply').attr('disabled', true);
                $('.quick-action-field').addClass('d-none');
            }
        });

        $('#quick-action-apply').click(function() {
            const actionValue = $('#quick-action-type').val();
            if (actionValue == 'delete') {
                Swal.fire({
                    title: "@lang('messages.sweetAlertTitle')",
                    text: "@lang('messages.recoverRecord')",
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
                        applyQuickAction();
                    }
                });

            } else {
                applyQuickAction();
            }
        });

        $('body').on('click', '.delete-table-row', function() {
            var type = $(this).data('type');
            var id = $(this).data('leave-id');
            var uniId = $(this).data('unique-id');
            var duration = $(this).data('duration');
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "@lang('messages.recoverRecord')",
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
                    var url = "{{ route('leaves.destroy', ':id') }}";
                    url = url.replace(':id', id);

                    var token = "{{ csrf_token() }}";

                    $.easyAjax({
                        type: 'POST',
                        url: url,
                        blockUI: true,
                        data: {
                            'uniId': uniId,
                            'duration': duration,
                            '_token': token,
                            '_method': 'DELETE'
                        },
                        success: function(response) {
                            if (response.status == "success") {
                                if(type == 'multiple-leave'){
                                    window.location.reload();
                                } else{
                                    showTable();
                                }
                            }
                        }
                    });
                }
            });
        });

        const applyQuickAction = () => {
            var rowdIds = $("#leaves-table input:checkbox:checked").map(function() {
                return $(this).val();
            }).get();

            var url = "{{ route('leaves.apply_quick_action') }}?row_ids=" + rowdIds;

            $.easyAjax({
                url: url,
                container: '#quick-action-form',
                type: "POST",
                disableButton: true,
                buttonSelector: "#quick-action-apply",
                data: $('#quick-action-form').serialize(),
                success: function(response) {
                    if (response.status == 'success') {
                        showTable();
                        resetActionButtons();
                        deSelectAll();
                        $('#quick-action-form').hide();
                    }
                }
            })
        };

        $('body').on('click', '.show-leave', function() {
            var leaveId = $(this).data('leave-id');

            var url = '{{ route('leaves.show', ':id') }}';
            url = url.replace(':id', leaveId);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        // HR approving before the manager / department head: the approve popup, with a required note.
        $('body').on('click', '.leave-action-override', function() {
            let url = "{{ route('leaves.show_approved_modal') }}?leave_action=approved&override=1&type=single&leave_id=" + $(this).data('leave-id');
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('body').on('click', '.leave-action-approved', function() {
            window.approveLeaveNow(this);
        });

        $('body').on('click', '.leave-action-reject', function() {
            let action = $(this).data('leave-action');
            let leaveId = $(this).data('leave-id');
            var type = $(this).data('type');
            if(type == undefined){
                var type = 'single';
            }
            let searchQuery = "?leave_action=" + action + "&leave_id=" + leaveId + "&type=" + type;
            let url = "{{ route('leaves.show_reject_modal') }}" + searchQuery;

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('body').on('click', '.leave-action-preapprove', function() {
            var action = $(this).data('leave-action');
            var leaveId = $(this).data('leave-id');
            var leaveUId = $(this).data('leave-uid');
            leaveUId = (leaveUId == null) ? null : leaveUId;
            
            var url = "{{ route('leaves.pre_approve_leave') }}";

            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "@lang('messages.changeLeaveStatusConfirmation')",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('messages.confirm')",
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
                    $.easyAjax({
                        type: 'POST',
                        url: url,
                        blockUI: true,
                        data: {
                            'action': action,
                            'leaveId': leaveId,
                            'leaveUId': leaveUId,
                            '_token': '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.status == 'success') {
                                showTable();
                                resetActionButtons();
                                deSelectAll();
                                window.location.reload(); 
                            }
                        }
                    });
                }
            });
        });

        $('body').on('click', '.view-related-leave', function() {
            var leaveId = $(this).data('leave-id');
            var uniqueId = $(this).data('unique-id');

            var url = "{{ route('leaves.view_related_leave', ':id') }}?uniqueId="+uniqueId;
            url = url.replace(':id', leaveId);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

    </script>
@endpush
