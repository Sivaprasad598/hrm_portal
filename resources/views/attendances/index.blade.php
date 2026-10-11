@extends('layouts.app')

@push('styles')
    <style>
        .attendance-total {
            width: 10%;
        }

        .table .thead-light th,
        .table tr td,
        .table h5 {
            font-size: 12px;
        }
        .mw-250{
            min-width: 125px;
        }
    </style>
@endpush

@section('filter-section')

    <x-filters.filter-box>
        <div class="select-box d-flex py-2 pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.employee')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="user_id" id="user_id" data-live-search="true"
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

        @if ($viewAttendancePermission == 'owned')
            <input type="hidden" name="department" id="department" value="all">
            <input type="hidden" name="designation" id="designation" value="all">
        @else
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.department')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="department" id="department" data-live-search="true"
                            data-size="8">
                        <option value="all">@lang('app.all')</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->team_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.designation')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="designation" id="designation" data-live-search="true"
                            data-size="8">
                        <option value="all">@lang('app.all')</option>
                        @foreach ($designations as $designation)
                            <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.month')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="month" id="month" data-live-search="true"
                        data-size="8">
                    <x-forms.months :selectedMonth="$month" fieldRequired="true"/>
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.year')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="year" id="year" data-live-search="true" data-size="8">
                    @for ($i = $year; $i >= $year - 4; $i--)
                        <option @if ($i == $year) selected @endif value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <!-- ATTENDANCE DASHBOARD START -->
        <div class="select-box d-flex align-items-center py-1 px-lg-2 px-md-2 px-0">
            <x-forms.link-secondary link="javascript:;" onclick="openAttendanceDashboard()" class="btn-xs" icon="chart-line">
                @lang('modules.attendance.attendanceDashboard')
            </x-forms.link-secondary>
        </div>
        <!-- ATTENDANCE DASHBOARD END -->

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->

    </x-filters.filter-box>

@endsection

@php
    $addAttendancePermission = user()->permission('add_attendance');
@endphp

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper px-4">

        <div class="d-grid d-lg-flex d-md-flex action-bar">
            <div id="table-actions" class="flex-grow-1 align-items-center">
                @if ($addAttendancePermission == 'all' || $addAttendancePermission == 'added')
                    <x-forms.link-primary :link="route('attendances.create')" class="mr-3 openRightModal float-left"
                                          icon="plus">
                        @lang('modules.attendance.markAttendance')
                    </x-forms.link-primary>
                @endif
                @if (canDataTableExport())
                    <x-forms.button-secondary id="export-all" class="mr-3 mb-2 mb-lg-0" icon="file-export">
                        @lang('app.exportExcel')
                    </x-forms.button-secondary>
                @endif

                @if ($addAttendancePermission == 'all' || $addAttendancePermission == 'added')
                    <x-forms.link-secondary :link="route('attendances.import')" class="mr-3 openRightModal float-left d-none d-lg-block"
                                            icon="file-upload">
                        @lang('app.importExcel')
                    </x-forms.link-secondary>
                @endif

                @if (user()->permission('delete_attendance') == 'all')
                    <x-forms.button-secondary id="delete-imported-attendance" class="mr-3 mb-2 mb-lg-0" icon="trash">
                        @lang('modules.attendance.deleteImportedData')
                    </x-forms.button-secondary>
                @endif
            </div>

            <div class="btn-group mt-2 mt-lg-0 mt-md-0 ml-0 ml-lg-3 ml-md-3" role="group">
                <a href="{{ route('attendances.index') }}" class="btn btn-secondary f-14 btn-active"
                   data-toggle="tooltip"
                   data-original-title="@lang('app.summary')"><i class="side-icon bi bi-list-ul"></i></a>

                <a href="{{ route('attendances.by_member') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                   data-original-title="@lang('modules.attendance.attendanceByMember')"><i
                        class="side-icon bi bi-person"></i></a>

                <a href="{{ route('attendances.by_hour') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                   data-original-title="@lang('modules.attendance.attendanceByHour')"><i class="fa fa-clock"></i></a>

                @if (isset($attendanceRegularisation) && $attendanceRegularisation->adjust_attendance_logs != 'not_allowed')
                    <a href="{{ route('attendances.by_request') }}" class="btn btn-secondary f-14" data-toggle="tooltip"
                    data-original-title="@lang('clan.attendance.attendanceRegularisation') ({{$pendingRequestRegularisation ?? 0}})"><i
                            class="side-icon bi bi-arrow-counterclockwise"></i></a>
                @endif

                @if (attendance_setting()->save_current_location)
                    <a href="{{ route('attendances.by_map_location') }}" class="btn btn-secondary f-14"
                       data-toggle="tooltip" data-original-title="@lang('modules.attendance.attendanceByLocation')"><i
                            class="fa fa-map-marked-alt"></i></a>
                @endif

            </div>
        </div>

        <!-- Task Box Start -->
        <x-cards.data class="mt-3">
            <div class="row">
                <div class="col-md-12">
                    <span class="f-w-500 mr-1">@lang('app.note'):</span> <i class="fa fa-star text-warning"></i> <i
                        class="fa fa-arrow-right text-lightest f-11 mx-1"></i> @lang('app.menu.holiday') &nbsp;|&nbsp;<i
                        class="fa fa-calendar-week text-red"></i> <i class="fa fa-arrow-right text-lightest f-11 mx-1"></i>
                    @lang('modules.attendance.dayOff') &nbsp;|&nbsp;
                    <i class="fa fa-check text-success"></i> <i class="fa fa-arrow-right text-lightest f-11 mx-1"></i>
                    @lang('modules.attendance.present') &nbsp;|&nbsp;
                    <i class="fa fa-home text-primary"></i> <i class="fa fa-arrow-right text-lightest f-11 mx-1"></i>
                    @lang('modules.attendance.workFromHomeLegend') &nbsp;|&nbsp;
                    <i class="fa fa-star-half-alt text-red"></i> <i
                        class="fa fa-arrow-right text-lightest f-11 mx-1"></i>
                    @lang('modules.attendance.halfDay') &nbsp;|&nbsp; <i class="fa fa-times text-lightest"></i> <i
                        class="fa fa-arrow-right text-lightest f-11 mx-1"></i>
                    @lang('modules.attendance.absent') &nbsp;|&nbsp; <i class="fa fa-plane-departure text-danger"></i> <i
                        class="fa fa-arrow-right text-lightest f-11 mx-1"></i>
                    @lang('modules.attendance.leave')

                </div>
            </div>

            <div class="row">
                <div class="col-md-12" id="attendance-data"></div>
            </div>
        </x-cards.data>
        <!-- Task Box End -->
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@if (canDataTableExport())
    <div class="modal fade" id="attendanceExportModal" tabindex="-1" role="dialog" aria-labelledby="attendanceExportModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceExportModalLabel">@lang('app.exportExcel')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <button type="button" class="btn btn-primary w-100 mb-2" id="attendance-export-btn">
                        Attendance export
                    </button>
                    <button type="button" class="btn btn-secondary w-100" id="attendance-export-clock-btn">
                        Attendance Time Export
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')

    <script>

        $('#user_id, #department, #designation, #month, #year').on('change', function () {
            if ($('#user_id').val() != "all") {
                $('#reset-filters').removeClass('d-none');
                showTable();
            } else if ($('#department').val() != "all") {
                $('#reset-filters').removeClass('d-none');
                showTable();
            } else if ($('#designation').val() != "all") {
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

        $('#reset-filters').click(function () {
            $('#filter-form')[0].reset();
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        function showTable(loading = true) {

            var year = $('#year').val();
            var month = $('#month').val();

            var userId = $('#user_id').val();
            var department = $('#department').val();
            var designation = $('#designation').val();

            //refresh counts
            var url = "{{ route('attendances.index') }}";

            var token = "{{ csrf_token() }}";

            $.easyAjax({
                data: {
                    '_token': token,
                    year: year,
                    month: month,
                    department: department,
                    designation: designation,
                    userId: userId
                },
                url: url,
                cache: false,
                blockUI: loading,
                container: '.content-wrapper',
                success: function (response) {
                    $('#attendance-data').html(response.data);
                }
            });

        }


        function openAttendanceCalendar(userID, userName, month, year) {
            // admin_leave.php is a standalone script accessed outside normal
            // Laravel routing (bypasses public/index.php), so route() calls
            // made from inside it resolve to the wrong base URL (missing
            // /hrm/public). route() works correctly here, so the real URLs
            // it needs are generated on this side and passed straight in.
            var url = "{{ route('legacy.page', 'admin_leave') }}?" + $.param({
                company_id: {{ (int) company()->id }},
                user_id: userID,
                month: month,
                year: year,
                attendance_create_url: "{{ route('attendances.create') }}",
                attendance_edit_url_template: "{{ route('attendances.edit', ':id') }}",
                attendances_index_url: "{{ route('attendances.index') }}",
                leave_create_url: "{{ route('leaves.create') }}",
                leave_edit_url_template: "{{ route('leaves.edit', ':id') }}",
                shift_mark_url_template: "{{ route('shifts.mark', [':userid', ':day', ':month', ':year']) }}"
            });

            $(RIGHT_MODAL_TITLE).text(userName || '');
            $(RIGHT_MODAL_CONTENT).html('<iframe src="' + url + '" style="width:100%;min-height:calc(100vh - 140px);border:0;"></iframe>');

            // closeTaskDetail() (main.js) always does window.history.back() on
            // close, assuming the opener pushed a history entry first — push
            // one here (same URL) so closing lands back on this page instead
            // of wherever the browser's actual previous history entry was.
            window.history.pushState({}, '', window.location.href);
            openTaskDetail();
        }

        function openAttendanceDashboard() {
            // Attendance_Dashboard.php is a standalone script accessed outside
            // normal Laravel routing (same reasoning as admin_leave.php above),
            // so its base URL is built here rather than inside that file.
            var url = "{{ route('legacy.page', 'attendance_dashboard') }}?" + $.param({
                company_id: {{ (int) company()->id }},
                month: $('#month').val(),
                year: $('#year').val()
            });

            $(RIGHT_MODAL_TITLE).text("@lang('modules.attendance.attendanceDashboard')");
            $(RIGHT_MODAL_CONTENT).html('<iframe src="' + url + '" style="width:100%;min-height:calc(100vh - 140px);border:0;"></iframe>');

            window.history.pushState({}, '', window.location.href);
            openTaskDetail();
        }

        $('#attendance-data').on('click', '.view-attendance-calendar', function () {
            var userID = $(this).data('user-id');
            var userName = $(this).data('user-name');
            var year = $('#year').val();
            var month = $('#month').val();

            openAttendanceCalendar(userID, userName, month, year);
        });

        $('#attendance-data').on('click', '.apply-late-half-day', function () {
            var userID = $(this).data('user-id');
            var year = $('#year').val();
            var month = $('#month').val();

            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "Apply a half-day deduction for this employee's oldest un-deducted late day this month?",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('app.ok')",
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
                if (!result.isConfirmed) {
                    return;
                }

                $.easyAjax({
                    url: "{{ route('attendances.apply_late_half_day') }}",
                    type: 'POST',
                    blockUI: true,
                    data: {
                        _token: "{{ csrf_token() }}",
                        user_id: userID,
                        month: month,
                        year: year
                    },
                    success: function (response) {
                        if (response.status == 'success') {
                            showTable();
                        }
                    }
                });
            });
        });

        $('#delete-imported-attendance').on('click', function () {
            var year = $('#year').val();
            var month = $('#month').val();
            var userId = $('#user_id').val();
            var employeeLabel = (userId == 'all') ? "@lang('app.all')" : $('#user_id option:selected').text().trim();

            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "This permanently deletes attendance records that came from Import Attendance (not manually marked or self clocked-in records) for " + employeeLabel + " in " + $('#month option:selected').text() + " " + year + ". This cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('app.ok')",
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
                if (!result.isConfirmed) {
                    return;
                }

                $.easyAjax({
                    url: "{{ route('attendances.import.delete') }}",
                    type: 'DELETE',
                    blockUI: true,
                    data: {
                        _token: "{{ csrf_token() }}",
                        month: month,
                        year: year,
                        userId: userId
                    },
                    success: function (response) {
                        if (response.status == 'success') {
                            showTable();
                        }
                    }
                });
            });
        });

        $('#attendance-data').on('click', '.delete-user-month-attendance', function () {
            var userID = $(this).data('user-id');
            var userName = $(this).data('user-name');
            var year = $('#year').val();
            var month = $('#month').val();
            var monthLabel = $('#month option:selected').text();

            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "Are you sure you want to delete " + userName + "'s " + monthLabel + " " + year + " attendance data? This permanently removes every attendance record for this employee in this month, including manually marked and self clocked-in records. This cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('app.ok')",
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
                if (!result.isConfirmed) {
                    return;
                }

                $.easyAjax({
                    url: "{{ route('attendances.user.delete') }}",
                    type: 'DELETE',
                    blockUI: true,
                    data: {
                        _token: "{{ csrf_token() }}",
                        userId: userID,
                        month: month,
                        year: year
                    },
                    success: function (response) {
                        if (response.status == 'success') {
                            showTable();
                        }
                    }
                });
            });
        });

        // The calendar's "Assign Status" action opens the real Mark
        // Attendance / Create Leave forms (see admin_leave.php), which save
        // by navigating back to this page — this flag (set right before
        // opening those forms) says to reopen the calendar automatically
        // once we're back, instead of landing on the plain list.
        (function () {
            var pending = sessionStorage.getItem('reopenCalendar');
            if (!pending) {
                return;
            }

            sessionStorage.removeItem('reopenCalendar');

            try {
                var state = JSON.parse(pending);
                openAttendanceCalendar(state.userId, state.userName, state.month, state.year);
            } catch (e) {
                // Malformed flag — ignore and show the plain list.
            }
        })();

        // The calendar's Day Off action opens the existing Update Shift
        // modal (shift-rosters/ajax/edit.blade.php), whose own save handler
        // calls loadData() if defined instead of navigating anywhere — hook
        // it here to refresh the open calendar iframe in place.
        window.loadData = function () {
            var iframe = document.querySelector(RIGHT_MODAL_CONTENT + ' iframe');
            if (iframe) {
                iframe.src = iframe.src;
            }
        };

        $('#attendance-data').on('click', '.view-attendance', function () {
            var attendanceID = $(this).data('attendance-id');
            var url = "{{ route('attendances.show', ':attendanceID') }}";
            url = url.replace(':attendanceID', attendanceID);

            $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_XL, url);
        });

        $('#attendance-data').on('click', '.edit-attendance', function (event) {
            var attendanceDate = $(this).data('attendance-date');
            var userData = $(this).closest('tr').children('td:first');
            var userID = $(this).data('user-id');
            var year = $('#year').val();
            var month = $('#month').val();

            var url = "{{ route('attendances.mark', [':userid', ':day', ':month', ':year']) }}";
            url = url.replace(':userid', userID);
            url = url.replace(':day', attendanceDate);
            url = url.replace(':month', month);
            url = url.replace(':year', year);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_XL, url);
        });

        function editAttendance(id) {
            var url = "{{ route('attendances.edit', [':id']) }}";
            url = url.replace(':id', id);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        }

        function addAttendance(userID) {
            var date = $('#date').val();
            const attendanceDate = date.split("-");
            let dayTime = attendanceDate[2];
            dayTime = dayTime.split(' ');
            let day = dayTime[0];
            let month = attendanceDate[1];
            let year = attendanceDate[0];

            var url = "{{ route('attendances.add-user-attendance', [':userid', ':day', ':month', ':year']) }}";
            url = url.replace(':userid', userID);
            url = url.replace(':day', day);
            url = url.replace(':month', month);
            url = url.replace(':year', year);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        }

        showTable(false);
        @if (canDataTableExport())
            $('#export-all').click(function () {
                $('#attendanceExportModal').modal('show');
            });
            $('#attendance-export-btn').click(function () {
                var year = $('#year').val();
                var month = $('#month').val();
                var department = $('#department').val();
                var designation = $('#designation').val();
                var userId = $('#user_id').val();

                var url =
                    "{{ route('attendances.export_all_attendance', [':year', ':month', ':userId', ':department', ':designation']) }}";
                url = url.replace(':year', year).replace(':month', month).replace(':userId', userId).replace(':department', department).replace(':designation', designation);
                window.location.href = url;
            });

            $('#attendance-export-clock-btn').click(function () {
                var year = $('#year').val();
                var month = $('#month').val();
                var department = $('#department').val();
                var designation = $('#designation').val();
                var userId = $('#user_id').val();

                var url =
                    "{{ route('attendances.export_all_attendance_clock', [':year', ':month', ':userId', ':department', ':designation']) }}";
                url = url.replace(':year', year).replace(':month', month).replace(':userId', userId).replace(':department', department).replace(':designation', designation);
                window.location.href = url;
            });
        @endif
    </script>

@endpush
