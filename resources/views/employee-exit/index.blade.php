@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
    <x-filters.filter-box>
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

    {{-- DataTable --}}

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
                if ($('#exit_department').val() !== 'all' || $('#exit_designation').val() !== 'all') {
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
