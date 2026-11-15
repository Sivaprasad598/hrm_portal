@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/full-calendar/main.min.css') }}">
@endpush

@section('filter-section')

    @php
        $calendarHolidays = \App\Models\Holiday::getHolidayByDates(
            now()->subYear()->startOfYear()->toDateString(), now()->addYear()->endOfYear()->toDateString(),
            \App\Services\Access\AssignedModules::selfService() ? user()->id : null
        )->flatMap(fn ($h) => [
            ['start' => $h->holiday_date, 'allDay' => true, 'display' => 'background', 'classNames' => ['epic-holiday-day'], 'holiday' => true],
            ['title' => $h->occassion, 'start' => $h->holiday_date, 'allDay' => true, 'classNames' => ['epic-holiday'], 'holiday' => true],
        ])->values();
    @endphp

    <x-filters.filter-box>

        <!-- EMPLOYEE FILTER START -->
        {{-- Only themselves to pick (an employee): no drop-down, the one choice stays selected --}}
        <div class="select-box {{ !empty($managerView) ? 'tl-grow' : '' }} {{ !\App\Http\Controllers\LeaveController::mine() && ($employees->count() > 1 || in_array('admin', user_roles()) || !empty($managerView)) ? 'd-flex' : 'd-none' }} py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.employee')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="employee_id" id="employee_id" data-live-search="true"
                    data-size="8">
                    @if (!empty($managerView))
                        <option value="all">All team members</option>
                    @elseif ($employees->count() > 1 || in_array('admin', user_roles()))
                        <option value="all">@lang('app.all')</option>
                    @endif
                    @foreach ($employees as $employee)
                            {!! \App\View\Components\UserOption::plain($employee) !!}
                    @endforeach
                </select>
            </div>
        </div>
        <!-- EMPLOYEE FILTER END -->

        <!-- LEAVE TYPE FILTER START -->
        @if (empty($managerView))
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('modules.leaves.leaveType')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="leave_type" id="leave_type" data-live-search="true"
                    data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}">{{ $leaveType->type_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif
        <!-- LEAVE TYPE FILTER END -->

        <!-- LEAVE STATUS FILTER END -->
        <div class="select-box {{ !empty($managerView) ? 'tl-grow' : '' }} d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.status')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="status" id="status" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    <option value="approved">@lang('app.approved')</option>
                    <option value="pending">@lang('app.pending')</option>
                    <option value="rejected">@lang('app.rejected')</option>
                </select>
            </div>
        </div>
        <!-- LEAVE STATUS FILTER END -->


        <!-- SEARCH BY LEAVE START -->
        {{-- Searches people by name: only for whoever sees other people's leave.
             Styled as the standard search (task-search) like every other list. --}}
        @if (\App\Http\Controllers\LeaveController::access()['team'] && empty($managerView))
        <div class="task-search leave-search d-flex py-1 px-lg-3 px-0 border-right-grey align-items-center">
            <form class="w-100 mr-1 mr-lg-0 mr-md-1 ml-md-1 ml-0 ml-lg-0">
                <div class="input-group bg-grey rounded">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-0 bg-additional-grey">
                            <i class="fa fa-search f-13 text-dark-grey"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="search-text-field"
                        placeholder="Search by name">
                </div>
            </form>
        </div>
        @endif
        <!-- SEARCH BY LEAVE END -->

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->

        <!-- MORE FILTERS END -->
    </x-filters.filter-box>
    @if (!empty($managerView))
        <style>
            /* Team Leave Calendar: same filter row and switch as Team Leaves */
            body.epic .filter-box .select-box.tl-grow { flex: 1 1 0; min-width: 200px; }
            body.epic .filter-box .select-box.tl-grow .select-status,
            body.epic .filter-box .select-box.tl-grow .bootstrap-select { width: 100% !important; }
            body.epic .action-bar .btn-group > .btn { height: 38px; padding: 0 16px !important; font-size: 14px; }
        </style>
    @endif

@endsection

@php
$addLeavePermission = user()->permission('add_leave');
@endphp

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">
        {{-- Standard layout: Apply Leave on the left; Filters (the filter row stays
             hidden until clicked) and the Table / Calendar / My Leaves switch on the right --}}
        <div class="lc-bar">
            <div class="lc-bar__left">
                {{-- Not on a manager's team calendar: they apply from My Space → My Leaves --}}
                @if (($addLeavePermission == 'all' || $addLeavePermission == 'added') && !\App\Http\Controllers\LeaveController::access()['team'])
                    <x-forms.link-primary :link="route('leaves.create')"
                        data-redirect-url="{{ route('leaves.calendar', \App\Http\Controllers\LeaveController::mine() ? ['mine' => 1] : []) }}"
                        class="openRightModal" icon="plus">
                        @lang('modules.leaves.addLeave')
                    </x-forms.link-primary>
                @endif
            </div>
            <div class="lc-bar__right">
                @include('leaves.partials.view-switch', ['active' => 'calendar', 'class' => 'ml-0'])
                {{-- Filters in the right corner, as on the other pages --}}
                <button type="button" id="lc-filters" class="btn-secondary rounded f-14 lc-filter-btn" aria-expanded="false">
                    <i class="fa fa-filter mr-1"></i>Filters<span class="lc-filter-dot d-none"></span>
                </button>
            </div>
        </div>

        <div class="lc-card">
            <div id="calendar"></div>
        </div>

        <style>
            /* the filter row: hidden until "Filters" is clicked */
            body:not(.page-filters-open) #fullscreen > .filter-box { display: none !important; }
            .lc-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
            .lc-bar__left, .lc-bar__right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            body.epic .lc-bar .btn-primary, body.epic .lc-bar .btn-secondary, body.epic .lc-bar .btn-group > .btn {
                height: 38px; border-radius: 8px !important; display: inline-flex; align-items: center; padding: 0 14px !important; font-size: 14px; margin: 0 !important; }
            body.epic .lc-bar .btn-group > .btn:not(:first-child) { border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; }
            body.epic .lc-bar .btn-group > .btn:not(:last-child) { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; }
            .lc-filter-btn { position: relative; cursor: pointer; }
            body.page-filters-open .lc-filter-btn { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important;
                border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
            body.page-filters-open .lc-filter-btn i { color: var(--epic-blue, #cb6b4f) !important; }
            .lc-filter-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--epic-blue, #cb6b4f); border: 2px solid #fff; }

            /* the calendar: one white rounded card, its toolbar in the app's button style */
            .lc-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 20px; }
            .lc-card .fc .fc-toolbar.fc-header-toolbar { margin-bottom: 16px; gap: 10px; flex-wrap: wrap; }
            .lc-card .fc .fc-toolbar-title { font-size: 17px; font-weight: 600; color: #1f2937; }
            .lc-card .fc .fc-button { height: 38px; padding: 0 14px; font-size: 14px; text-transform: none; box-shadow: none !important;
                background: #fff; border: 1px solid #e5e7eb; color: #374151; border-radius: 8px; }
            .lc-card .fc .fc-button:hover { background: #f9fafb; color: #111827; }
            .lc-card .fc .fc-button:disabled { opacity: .55; }
            .lc-card .fc .fc-button-group > .fc-button:not(:first-child) { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -1px; }
            .lc-card .fc .fc-button-group > .fc-button:not(:last-child) { border-top-right-radius: 0; border-bottom-right-radius: 0; }
            .lc-card .fc .fc-button-primary:not(:disabled).fc-button-active { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; }
            .lc-card .fc .fc-today-button { margin-left: 8px !important; }
            .lc-card .fc .fc-col-header-cell { background: #f9fafb; padding: 8px 0; }
            .lc-card .fc .fc-col-header-cell-cushion { font-size: 12.5px; font-weight: 500; color: #667085; text-transform: uppercase; letter-spacing: .03em; }
            .lc-card .fc-theme-standard td, .lc-card .fc-theme-standard th, .lc-card .fc-theme-standard .fc-scrollgrid { border-color: #eef0f3; }
            .lc-card .fc .fc-scrollgrid { border-radius: 10px; overflow: hidden; }
            .lc-card .fc a.fc-daygrid-day-number { font-size: 13px; color: #475467 !important; padding: 6px 8px; text-decoration: none; }
            .lc-card .fc .fc-day-other a.fc-daygrid-day-number { color: #c0c5cc !important; }
            .lc-card .fc .fc-day-today { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 6%, #fff) !important; }
            .lc-card .fc .fc-day-today a.fc-daygrid-day-number { color: var(--epic-blue, #cb6b4f) !important; font-weight: 600; }
        </style>

    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')
    <script src="{{ asset('vendor/full-calendar/main.min.js') }}"></script>
    <script src="{{ asset('vendor/full-calendar/locales-all.min.js') }}"></script>

    <script>
        $('#employee_id, #leave_type, #status').on('change keyup',
            function() {
                if ($('#employee_id').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    loadData();
                } else if ($('#leave_type_id').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    loadData();
                } else if ($('#status').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                    loadData();
                } else {
                    $('#reset-filters').addClass('d-none');
                    loadData();
                }
            });

        $('#search-text-field').on('keyup', function() {
            if ($('#search-text-field').val() != "") {
                $('#reset-filters').removeClass('d-none');
            } else {
                $('#reset-filters').addClass('d-none');
            }
            // Refresh calendar events based on search term (empty term = all)
            loadData();
        });

        // "Filters": shows / hides the filter row; a dot while one is set
        // the drop-downs are sized once, the first time the filters show (after drawing)
        var lcSized = false;
        $('#lc-filters').on('click', function () {
            var open = $('body').toggleClass('page-filters-open').hasClass('page-filters-open');
            $(this).attr('aria-expanded', open);
            if (open && !lcSized) {
                lcSized = true;
                requestAnimationFrame(function () { setTimeout(function () { $('#fullscreen > .filter-box .select-picker').selectpicker('refresh'); }, 0); });
            }
        });

        function markLeaveFilters() {
            var set = ['#employee_id', '#leave_type', '#status'].some(function (s) {
                return $(s).length && $(s).closest('.d-none').length === 0 && $(s).val() && $(s).val() !== 'all';
            }) || !!($('#search-text-field').val() || '');
            $('.lc-filter-dot').toggleClass('d-none', !set);
        }
        $('#employee_id, #leave_type, #status').on('change', markLeaveFilters);
        $('#search-text-field').on('keyup', markLeaveFilters);

        $('#reset-filters').click(function() {
            setTimeout(markLeaveFilters, 0);
            $('#filter-form')[0].reset();

            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            loadData();
        });

        var initialLocaleCode = '{{ user()->locale }}';
        var calendarEl = document.getElementById('calendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            locale: initialLocaleCode,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            firstDay: parseInt("{{ attendance_setting()?->week_start_from }}"),
            navLinks: true, // can click day/week names to navigate views
            selectable: false,
            selectMirror: true,
            eventClick: function(arg) {
                // Holidays are shown, not opened
                if (arg.event.extendedProps.holiday) {
                    return;
                }

                getEventDetail(arg.event.id);
            },
            // Leave: a soft tint of its type's colour with a strong left edge,
            // instead of a solid block (see .lv-ev in epic/theme.css)
            eventDidMount: function(info) {
                if (!info.el.classList.contains('lv-ev')) { return; }
                var c = info.event.backgroundColor || '#cb6b4f';
                info.el.style.background = 'color-mix(in srgb, ' + c + ' 16%, #fff)';
                info.el.style.borderLeftColor = c;
                info.el.title = info.event.title;
            },
            editable: false,
            dayMaxEvents: true, // allow "more" link when too many events
            eventSources: [{
                // Holidays (last year to next), the ones that apply to this
                // person for an employee: a tinted day and its name on it
                events: @json($calendarHolidays),
            }, {
                url: "{{ route('leaves.calendar') }}",
                extraParams: function() {
                    // A filter not on the page (the manager's calendar has no leave
                    // type) means "all", never the text "undefined".
                    var employeeId = $('#employee_id').val() || 'all';
                    var leaveTypeId = $('#leave_type').val() || 'all';
                    var status = $('#status').val() || 'all';
                    var searchText = $('#search-text-field').val() || '';

                    return {
                        employeeId: employeeId,
                        leaveTypeId: leaveTypeId,
                        status: status,
                        searchText: searchText,
                        mine: {{ \App\Http\Controllers\LeaveController::mine() ? 1 : 0 }},
                    };
                }
            }],
            eventTimeFormat: {
                hour: company.time_format == 'H:i' ? '2-digit' : 'numeric',
                minute: '2-digit',
                meridiem: company.time_format == 'H:i' ? false : true
            }
        });

        calendar.render();

        function loadData() {
            calendar.refetchEvents();
            calendar.destroy();
            calendar.render();
        }

        // show leave detail in right modal
        var getEventDetail = function(id) {
            openTaskDetail();
            var url = "{{ route('leaves.show', ':id') }}";
            url = url.replace(':id', id);

            $.easyAjax({
                url: url,
                blockUI: true,
                container: RIGHT_MODAL,
                historyPush: true,
                success: function(response) {
                    if (response.status == "success") {
                        $(RIGHT_MODAL_CONTENT).html(response.html);
                        $(RIGHT_MODAL_TITLE).html(response.title);
                    }
                },
                error: function(request, status, error) {
                    if (request.status == 403) {
                        $(RIGHT_MODAL_CONTENT).html(
                            '<div class="align-content-between d-flex justify-content-center mt-105 f-21">403 | Permission Denied</div>'
                        );
                    } else if (request.status == 404) {
                        $(RIGHT_MODAL_CONTENT).html(
                            '<div class="align-content-between d-flex justify-content-center mt-105 f-21">404 | Not Found</div>'
                        );
                    } else if (request.status == 500) {
                        $(RIGHT_MODAL_CONTENT).html(
                            '<div class="align-content-between d-flex justify-content-center mt-105 f-21">500 | Something Went Wrong</div>'
                        );
                    }
                }
            });
        }
    </script>
@endpush
