@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
    <x-filters.filter-box>
        <div class="select-box py-2 d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Status</p>
            <div class="select-status">
                <select class="form-control select-picker" name="exit_status" id="exit_status">
                    <option value="all">All</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="probation">Probation</option>
                    <option value="notice_period">Notice Period</option>
                    <option value="off_boarded">Off Boarded</option>
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Department</p>
            <div class="select-status">
                <select class="form-control select-picker" name="exit_department" id="exit_department">
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
                <select class="form-control select-picker" name="exit_designation" id="exit_designation">
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
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="search-text-field"
                           placeholder="Search employee...">
                </div>
            </form>
        </div>

        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
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
                 onclick="filterByStatus('all')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Total Employees</p>
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
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #28a745 !important; cursor:pointer;"
                 onclick="filterByStatus('confirmed')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Confirmed</p>
                            <h3 class="font-weight-700 mb-0 text-success">{{ $confirmedCount }}</h3>
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
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ffc107 !important; cursor:pointer;"
                 onclick="filterByStatus('probation')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Probation</p>
                            <h3 class="font-weight-700 mb-0 text-warning">{{ $probationCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#fff3cd;">
                            <i class="fa fa-clock text-warning f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #17a2b8 !important; cursor:pointer;"
                 onclick="filterByStatus('notice_period')">
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
                 onclick="filterByStatus('off_boarded')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">Off Boarded</p>
                            <h3 class="font-weight-700 mb-0 text-danger">{{ $offBoardedCount }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;background:#f8d7da;">
                            <i class="fa fa-door-open text-danger f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="d-flex justify-content-between action-bar mb-3">
        <div id="table-actions">
            <span class="f-15 font-weight-600 text-dark">Employee Exit Management</span>
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
        let table;

        $(function () {
            // window.LaravelDataTables['employee-exit-table'] is assigned inside the
            // DataTables init script's own $(document).ready callback (see
            // sections.datatable_js above). Doing our setup inside another ready
            // callback guarantees it runs after that one (jQuery fires ready
            // callbacks in registration order), instead of racing it.
            table = window.LaravelDataTables['employee-exit-table'];

            $('#exit_status, #exit_department, #exit_designation').on('change', function () {
                table.draw();
            });

            $('#search-text-field').on('keyup', function () {
                table.search($(this).val()).draw();
            });

            $('#reset-filters').on('click', function () {
                $('#exit_status').val('all').selectpicker('refresh');
                $('#exit_department').val('all').selectpicker('refresh');
                $('#exit_designation').val('all').selectpicker('refresh');
                $('#search-text-field').val('');
                table.search('').draw();
                $(this).addClass('d-none');
            });

            $('#exit_status, #exit_department, #exit_designation').on('change', function () {
                if ($('#exit_status').val() !== 'all' || $('#exit_department').val() !== 'all' || $('#exit_designation').val() !== 'all') {
                    $('#reset-filters').removeClass('d-none');
                } else {
                    $('#reset-filters').addClass('d-none');
                }
            });

            $('#employee-exit-table').on('preXhr.dt', function (e, settings, data) {
                data.status      = $('#exit_status').val();
                data.department  = $('#exit_department').val();
                data.designation = $('#exit_designation').val();
                data.searchText  = $('#search-text-field').val();
            });
        });

        function filterByStatus(status) {
            $('#exit_status').val(status).selectpicker('refresh');
            table.draw();
        }

        function confirmEmployee(userId) {
            Swal.fire({
                title: 'Confirm Employee?',
                text: 'This will mark the employee as confirmed and end their probation period.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Confirm',
                confirmButtonColor: '#28a745',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ route('employee-exit.confirm') }}', {
                        _token: '{{ csrf_token() }}',
                        user_id: userId
                    }).done(function (res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            table.draw();
                        } else {
                            toastr.error(res.message);
                        }
                    });
                }
            });
        }

        function offboardEmployee(exitId) {
            Swal.fire({
                title: 'Off-Board Employee?',
                text: 'This will mark the employee as off-boarded and deactivate their account.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Off-Board',
                confirmButtonColor: '#dc3545',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ route('employee-exit.offboard', '__ID__') }}'.replace('__ID__', exitId), {
                        _token: '{{ csrf_token() }}',
                        _method: 'PATCH'
                    }).done(function (res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            table.draw();
                        } else {
                            toastr.error(res.message);
                        }
                    });
                }
            });
        }
    </script>
@endpush
