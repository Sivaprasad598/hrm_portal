<style>
    #status-filter {
        align-items: center;
    }
</style>

@extends('layouts.app')

@push('datatable-styles')
    <script src="{{ asset('vendor/jquery/frappe-charts.min.iife.js') }}"></script>
    <script src="{{ asset('vendor/jquery/Chart.min.js') }}"></script>
    @include('sections.datatable_css')
@endpush

@section('filter-section')

    <x-filters.filter-box>
        <div class="select-box py-2 d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.employee')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="employee" id="employee" data-live-search="true"
                        data-size="8">
                    @if ($employees->count() > 1 || in_array('admin', user_roles()))
                        <option value="all">@lang('app.all')</option>
                    @endif
                    @forelse ($employees as $item)
                        <x-user-option :user="$item" :selected="request('employee_id') == $item->id"></x-user-option>
                    @empty
                        <x-user-option :user="user()"></x-user-option>
                    @endforelse
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0" id="status-filter">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.status')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="status" id="status" data-live-search="true" data-size="8">
                    <option value="pending">@lang('app.pending')</option>
                    <option value="all">@lang('app.all')</option>
                    <option @if($status == 'accept') selected @endif value="accept">@lang('app.accept')</option>
                    <option @if($status == 'reject') selected @endif value="reject">@lang('app.reject')</option>
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-3 px-md-3 px-0 border-right-grey border-right-grey-sm-0" id="status-filter">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.month')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="month" id="month" data-live-search="true" data-size="8">
                    <x-forms.months :selectedMonth="$month" fieldRequired="true"/>
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-3 px-md-3 px-0 border-right-grey border-right-grey-sm-0" id="status-filter">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.year')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="year" id="year" data-live-search="true" data-size="8">
                    @for ($i = $year; $i >= $year - 4; $i--)
                        <option @if ($i == $year) selected @endif value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->

    </x-filters.filter-box>

@endsection

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper px-4">

        <div class="d-grid d-lg-flex d-md-flex action-bar">
            <div id="table-actions" class="flex-grow-1 align-items-center">
            </div>

            <div class="btn-group mt-2 mt-lg-0 mt-md-0 ml-0 ml-lg-3 ml-md-3" role="group">
                <a href="{{ route('attendances.index') }}" class="btn btn-secondary f-14"
                   data-toggle="tooltip"
                   data-original-title="@lang('app.summary')"><i class="side-icon bi bi-list-ul"></i></a>

                <a href="{{ route('attendances.by_member') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                   data-original-title="@lang('modules.attendance.attendanceByMember')"><i
                        class="side-icon bi bi-person"></i></a>

                <a href="{{ route('attendances.by_hour') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                   data-original-title="@lang('modules.attendance.attendanceByHour')"><i class="fa fa-clock"></i></a>

                <a href="{{ route('attendances.by_request') }}" class="btn btn-secondary f-14 btn-active" data-toggle="tooltip"
                data-original-title="@lang('clan.attendance.attendanceRegularisation') ({{$pendingRequestRegularisation}})" ><i
                        class="side-icon bi bi-arrow-counterclockwise"></i></a>

                @if (attendance_setting()->save_current_location)
                    <a href="{{ route('attendances.by_map_location') }}" class="btn btn-secondary f-14"
                       data-toggle="tooltip" data-original-title="@lang('modules.attendance.attendanceByLocation')"><i
                            class="fa fa-map-marked-alt"></i></a>
                @endif

            </div>
        </div>



        <div class="d-flex flex-column w-tables rounded mt-3 bg-white">

            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}


        </div>
        <!-- Task Box End -->
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection


@push('scripts')
    @include('sections.datatable_js')

    <script>

    $('#attendanceregularisation-table').on('preXhr.dt', function(e, settings, data) {

    var employee = $('#employee').val();
    var status = $('#status').val();
    var month = $('#month').val();
    var year = $('#year').val();
    var searchText = $('#search-text-field').val();

    data['employee'] = employee;
    data['status'] = status;
    data['month'] = month;
    data['year'] = year;
    data['searchText'] = searchText;
    });
    const showTable = () => {
    window.LaravelDataTables["attendanceregularisation-table"].draw(false);
    }

    $(' #status, #field, #employee, #month, #year')
        .on('change keyup',
            function() {
                if ($('#status').val() != "pending") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#employee').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#month').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    showTable();
                } else if ($('#year').val() != "all") {
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
                showTable();
            }
        });

        $('#reset-filters,#reset-filters-2').click(function() {
            $('#filter-form')[0].reset();

            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('body').on('click', '.accept-action', function() {
            let acceptId  = $(this).data('accept-id');
            let url = "{{ route('attendances.accept_status') }}";

            $.easyAjax({
                type: 'POST',
                url: url,
                blockUI: true,
                data: {
                    'acceptId': acceptId,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status == 'success') {
                        showTable();
                    }
                }
            });
        });

        $('body').on('click', '.reject-action', function() {
            let rejectId  = $(this).data('reject-id');
            let url = "{{ route('attendances.show_reject_modal' , ':rejectID') }}";
            url = url.replace(':rejectID', rejectId);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#attendanceregularisation-table').on('click', '.view-attendance-regularisation', function () {
            var id = $(this).data('view-id');
            var url = "{{ route('attendances.view_attendance_regularisation', ':viewID') }}";
            url = url.replace(':viewID', id);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#attendanceregularisation-table').on('click', '.edit-attendance-regularisation', function () {
            var id = $(this).data('edit-id');
            var url = "{{ route('attendances.edit_attendance_regularisation', ':editID') }}";
            url = url.replace(':editID', id);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

    </script>
@endpush
