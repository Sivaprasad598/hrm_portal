@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
    <x-filters.filter-box>
        <div class="select-box py-2 d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Status</p>
            <div class="select-status">
                <select class="form-control select-picker" name="resignation_status" id="resignation_status">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="notice_period">Notice Period</option>
                    <option value="rejected">Rejected</option>
                    <option value="completed">Off Boarded</option>
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Department</p>
            <div class="select-status">
                <select class="form-control select-picker" name="resignation_department" id="resignation_department">
                    <option value="all">All</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->team_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Designation</p>
            <div class="select-status">
                <select class="form-control select-picker" name="resignation_designation" id="resignation_designation">
                    <option value="all">All</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="task-search d-flex py-1 px-lg-3 px-0 border-right-grey align-items-center">
            <form class="w-100 mr-1 mr-lg-0 mr-md-1 ml-md-1 ml-0 ml-lg-0">
                <div class="input-group bg-grey rounded">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-0 bg-additional-grey">
                            <i class="fa fa-search f-13 text-dark-grey"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="resignation-search-text-field"
                           placeholder="Search employee...">
                </div>
            </form>
        </div>

        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="resignation-reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
    </x-filters.filter-box>
@endsection

@section('content')
<div class="content-wrapper">

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6c757d !important; cursor:pointer;"
                 onclick="filterResignationStatus('all')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Total Resignations</p>
                            <h3 class="font-weight-700 mb-0 text-dark">{{ $totalCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#e9ecef;">
                            <i class="fa fa-users text-secondary f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ffc107 !important; cursor:pointer;"
                 onclick="filterResignationStatus('pending')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Pending</p>
                            <h3 class="font-weight-700 mb-0 text-warning">{{ $pendingCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#fff3cd;">
                            <i class="fa fa-hourglass-half text-warning f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #28a745 !important; cursor:pointer;"
                 onclick="filterResignationStatus('approved')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Approved</p>
                            <h3 class="font-weight-700 mb-0 text-success">{{ $approvedCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#d4edda;">
                            <i class="fa fa-check-circle text-success f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #17a2b8 !important; cursor:pointer;"
                 onclick="filterResignationStatus('notice_period')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Notice Period</p>
                            <h3 class="font-weight-700 mb-0 text-info">{{ $noticeCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#d1ecf1;">
                            <i class="fa fa-sign-out-alt text-info f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #dc3545 !important; cursor:pointer;"
                 onclick="filterResignationStatus('rejected')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Rejected</p>
                            <h3 class="font-weight-700 mb-0 text-danger">{{ $rejectedCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#f8d7da;">
                            <i class="fa fa-times-circle text-danger f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6c757d !important; cursor:pointer;"
                 onclick="filterResignationStatus('completed')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Off Boarded</p>
                            <h3 class="font-weight-700 mb-0 text-secondary">{{ $completedCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#e9ecef;">
                            <i class="fa fa-door-open text-secondary f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="d-flex justify-content-between action-bar mb-3">
        <div id="table-actions">
            <span class="f-15 font-weight-600 text-dark">Employee Resignation Requests</span>
        </div>
    </div>

    <div class="d-flex flex-column w-tables rounded mt-3 bg-white">
        {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}
    </div>

</div>
@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        let resignationTable;

        $(function () {
            resignationTable = window.LaravelDataTables['resignation-management-table'];

            $('#resignation_status, #resignation_department, #resignation_designation').on('change', function () {
                resignationTable.draw();

                if ($('#resignation_status').val() !== 'all' || $('#resignation_department').val() !== 'all' || $('#resignation_designation').val() !== 'all') {
                    $('#resignation-reset-filters').removeClass('d-none');
                } else {
                    $('#resignation-reset-filters').addClass('d-none');
                }
            });

            $('#resignation-search-text-field').on('keyup', function () {
                resignationTable.search($(this).val()).draw();
            });

            $('#resignation-reset-filters').on('click', function () {
                $('#resignation_status').val('all').selectpicker('refresh');
                $('#resignation_department').val('all').selectpicker('refresh');
                $('#resignation_designation').val('all').selectpicker('refresh');
                $('#resignation-search-text-field').val('');
                resignationTable.search('').draw();
                $(this).addClass('d-none');
            });

            $('#resignation-management-table').on('preXhr.dt', function (e, settings, data) {
                data.status      = $('#resignation_status').val();
                data.department  = $('#resignation_department').val();
                data.designation = $('#resignation_designation').val();
                data.searchText  = $('#resignation-search-text-field').val();
            });
        });

        function filterResignationStatus(status) {
            $('#resignation_status').val(status).selectpicker('refresh');
            resignationTable.draw();
            if (status !== 'all') {
                $('#resignation-reset-filters').removeClass('d-none');
            } else {
                $('#resignation-reset-filters').addClass('d-none');
            }
        }

        function approveResignation(id) {
            // Opened from the detail panel: the Notice Period dropdown is already
            // rendered there, so just read its value.
            var $select = $('#approveNoticePeriod');

            if ($select.length) {
                var selectedId = $select.val();

                if (!selectedId) {
                    toastr.error('Please select a notice period before approving.');
                    return;
                }

                confirmApproveResignation(id, selectedId);
                return;
            }

            // Opened from the list's quick "Approve" action: no dropdown on screen
            // yet, so fetch the options and ask for one first.
            $.get('{{ url('account/resignation-management/notice-periods') }}', function (res) {
                if (res.status !== 'success' || !res.data.length) {
                    toastr.error('No notice periods configured yet. Please add one in Notice Period Settings first.');
                    return;
                }

                var inputOptions = {};
                res.data.forEach(function (option) {
                    inputOptions[option.id] = option.label;
                });

                Swal.fire({
                    title: 'Select Notice Period',
                    input: 'select',
                    inputOptions: inputOptions,
                    inputPlaceholder: 'Select notice period',
                    showCancelButton: true,
                    confirmButtonText: 'Continue',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Please select a notice period.';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        confirmApproveResignation(id, result.value);
                    }
                });
            });
        }

        function confirmApproveResignation(id, noticePeriodId) {
            Swal.fire({
                title: 'Approve Resignation?',
                text: 'This will move the employee to notice period and start the offboarding checklist.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Approve',
                confirmButtonColor: '#28a745',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ url('account/resignation-management') }}/' + id + '/approve', {
                        _token: '{{ csrf_token() }}',
                        notice_period_id: noticePeriodId,
                    }).done(function (res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            resignationTable.draw();
                        } else {
                            toastr.error(res.message);
                        }
                    });
                }
            });
        }

        function undoResignation(id) {
            Swal.fire({
                title: 'Undo Resignation Decision?',
                text: 'This puts the request back to Pending and reverses the employee status change made when it was reviewed. The employee will be notified.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Undo',
                confirmButtonColor: '#ffc107',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ url('account/resignation-management') }}/' + id + '/undo', {
                        _token: '{{ csrf_token() }}',
                    }).done(function (res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            resignationTable.draw();
                        } else {
                            toastr.error(res.message);
                        }
                    });
                }
            });
        }

        function rejectResignation(id) {
            Swal.fire({
                title: 'Reject Resignation?',
                input: 'textarea',
                inputLabel: 'Reason for rejection',
                inputPlaceholder: 'Enter the reason for rejecting this resignation...',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                confirmButtonColor: '#dc3545',
                inputValidator: (value) => {
                    if (!value) {
                        return 'A reason is required.';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ url('account/resignation-management') }}/' + id + '/reject', {
                        _token: '{{ csrf_token() }}',
                        reason: result.value,
                    }).done(function (res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            resignationTable.draw();
                        } else {
                            toastr.error(res.message);
                        }
                    });
                }
            });
        }
    </script>
@endpush
