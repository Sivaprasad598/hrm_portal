@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')

    <x-filters.filter-box>
        <!-- CLIENT START -->
        <div class="select-box py-2 d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.employee')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="employee" id="employee" data-live-search="true"
                        data-size="8">
                    @if ($employees->count() > 1 || in_array('admin', user_roles()))
                        <option value="all">@lang('app.all')</option>
                    @endif
                    @foreach ($employees as $employee)
                        <x-user-option :user="$employee"/>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- CLIENT END -->

        <!-- DESIGNATION START -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.designation')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="designation" id="designation">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($designations as $designation)
                        <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <!-- DESIGNATION END -->


        <!-- SEARCH BY TASK START -->
        <div class="task-search d-flex  py-1 px-lg-3 px-0 border-right-grey align-items-center">
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
        <!-- SEARCH BY TASK END -->

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->

        <!-- MORE FILTERS START -->
        <x-filters.more-filter-box>
            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('app.department')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="department" data-container="body"
                                id="department">
                            <option value="all">@lang('app.all')</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->team_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('modules.employees.reportingTo')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="reporting_employee" id="reporting_employee" data-live-search="true"
                                data-size="8">
                            @if ($employees->count() > 1 || in_array('admin', user_roles()))
                                <option value="all">@lang('app.all')</option>
                            @endif
                            @foreach ($employees as $employee)
                                <x-user-option :user="$employee"/>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 "
                       for="usr">@lang('modules.employees.role')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="role" id="role" data-container="body">
                            <option value="all">@lang('app.all')</option>
                            @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('app.status')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="status" id="status" data-container="body">
                            <option value="all">@lang('app.all')</option>
                            <option selected value="active">@lang('app.active')</option>
                            <option value="deactive">@lang('app.inactive')</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('modules.employees.gender')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="gender" id="gender" data-container="body">
                            <option value="all">@lang('app.all')</option>
                            <option value="male">@lang('app.male')</option>
                            <option value="female">@lang('app.female')</option>
                            <option value="others">@lang('app.others')</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="more-filter-items">
                <label class="f-14 text-dark-grey mb-12 " for="usr">@lang('modules.employees.employmentType')</label>
                <div class="select-filter mb-4">
                    <div class="select-others">
                        <select class="form-control select-picker" name="employmentType" id="employmentType" data-container="body">
                            <option value="all">@lang('app.all')</option>
                            <option value="probation">@lang('app.onProbation')</option>
                            <option value="probation_completed">@lang('app.probationCompleted')</option>
                            <option value="internship">@lang('app.onInternship')</option>
                            <option value="notice_period">@lang('app.onNoticePeriod')</option>
                            <option value="new_hires">@lang('app.newHires')</option>
                            <option value="long_standing">@lang('app.longStanding')</option>

                        </select>
                    </div>
                </div>
            </div>

        </x-filters.more-filter-box>
        <!-- MORE FILTERS END -->
    </x-filters.filter-box>

@endsection

@php
    $addEmployeePermission = user()->permission('add_employees');
    $addDesignationPermission = user()->permission('add_designation');
    $viewDesignationPermission = user()->permission('view_designation');
@endphp

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">
        <!-- Add Task Export Buttons Start -->
        <div class="d-flex justify-content-between action-bar">

            <div id="table-actions" class="d-block d-lg-flex align-items-center">
                @if ($addEmployeePermission == 'all')
                    <x-forms.link-primary :link="route('employees.create')" class="mr-3 openRightModal" icon="plus">
                        @lang('app.addEmployee')
                    </x-forms.link-primary>

                    {{-- Hidden: employees are added (or imported), not invited by email.
                    <x-forms.button-secondary class="mr-3 invite-member mb-2 mb-lg-0" icon="plus">
                        @lang('app.inviteEmployee')
                    </x-forms.button-secondary>
                    --}}
                @endif

                @if ($addEmployeePermission == 'all')
                    <x-forms.link-secondary :link="route('employees.import')" class="mr-3 openRightModal mb-2 mb-lg-0 d-none d-lg-block"
                                            icon="file-upload">
                        @lang('app.importExcel')
                    </x-forms.link-secondary>
                @endif
            </div>

            <x-datatable.actions>
                <div class="select-status mr-3 pl-3">
                    <select name="action_type" class="form-control select-picker" id="quick-action-type" disabled>
                        <option value="">@lang('app.selectAction')</option>
                        <option value="change-status">@lang('modules.tasks.changeStatus')</option>
                        @if (user()->permission('edit_employees') == 'all')
                            <option value="assign-reporting">Assign Reporting To…</option>
                        @endif
                        <option value="delete">@lang('app.delete')</option>
                    </select>
                </div>
                <div class="select-status mr-3 d-none quick-action-field" id="change-status-action">
                    <select name="status" class="form-control select-picker">
                        <option value="deactive">@lang('app.inactive')</option>
                        <option value="active">@lang('app.active')</option>
                    </select>
                </div>
            </x-datatable.actions>

        </div>
        <!-- Add Task Export Buttons End -->
        {{-- Assign Reporting To: the ticked employees report to the person picked here --}}
        @if (user()->permission('edit_employees') == 'all')
            @php
                $reportCandidates = \App\Models\User::query()->where('users.status', 'active')
                    ->join('employee_details as ed', 'ed.user_id', '=', 'users.id')
                    ->leftJoin('designations as dg', 'dg.id', '=', 'ed.designation_id')
                    ->orderBy('users.name')->get(['users.id', 'users.name', 'ed.employee_id', 'dg.name as designation']);
                $rolesOf = \Illuminate\Support\Facades\DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
                    ->whereIn('r.name', ['manager', 'team_lead', 'ceo'])->get(['ru.user_id', 'r.name'])->groupBy('user_id')
                    ->map(fn ($rows) => $rows->pluck('name')->all());
                // Who reports to whom: employee -> team lead -> manager -> CEO
                $levelOf = function ($id) use ($rolesOf) {
                    $r = $rolesOf->get($id, []);
                    return in_array('ceo', $r, true) ? 'ceo' : (in_array('manager', $r, true) ? 'manager' : (in_array('team_lead', $r, true) ? 'team_lead' : 'employee'));
                };
                $reportLevels = $reportCandidates->mapWithKeys(fn ($u) => [$u->id => $levelOf($u->id)]);
                $reportPeople = $reportCandidates->groupBy(fn ($u) => $reportLevels[$u->id]);
            @endphp
            <div class="modal fade" id="assignReportingModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Assign Reporting To</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            {{-- The hierarchy, with the step being set lit --}}
                            <div class="report-chain mb-3">
                                <span data-level="employee">Employee</span><i>›</i>
                                <span data-level="team_lead">Team Lead</span><i>›</i>
                                <span data-level="manager">Manager</span><i>›</i>
                                <span data-level="ceo">CEO</span>
                            </div>
                            <p class="f-14 text-dark mb-3" id="assign-reporting-what"></p>
                            <p class="f-13 text-danger mb-0 d-none" id="assign-reporting-mixed"></p>

                            @foreach (['team_lead' => 'a team lead', 'manager' => 'a manager', 'ceo' => 'the CEO'] as $level => $whom)
                                <div class="report-pick d-none" data-level="{{ $level }}">
                                    @if (empty($reportPeople[$level]) || $reportPeople[$level]->isEmpty())
                                        <p class="f-13 text-lightest mb-0">Nobody has the {{ ['team_lead' => 'Team Lead', 'manager' => 'Manager', 'ceo' => 'CEO'][$level] }} role yet. Give it under Employees → Edit → Role.</p>
                                    @else
                                        <div class="person-pick">
                                            <div class="person-pick__search">
                                                <i class="fa fa-search"></i>
                                                <input type="text" class="form-control" placeholder="Search {{ $whom }} by name, designation or ID" autocomplete="off">
                                            </div>
                                            <div class="person-pick__list">
                                                @foreach ($reportPeople[$level] as $u)
                                                    <label class="person-pick__row" data-find="{{ strtolower($u->name . ' ' . $u->designation . ' ' . $u->employee_id) }}">
                                                        <input type="radio" name="report_person_{{ $level }}" value="{{ $u->id }}">
                                                        <span class="person-pick__avatar">{{ strtoupper(collect(explode(' ', trim($u->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) }}</span>
                                                        <span class="person-pick__who"><b>{{ $u->name }}</b><small>{{ trim(($u->designation ?: '') . ' · ' . $u->employee_id, ' ·') }}</small></span>
                                                        <span class="person-pick__tick"><i class="fa fa-check"></i></span>
                                                    </label>
                                                @endforeach
                                                <p class="person-pick__none">No one matches.</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                            <p class="f-12 text-lightest mt-2 mb-0">Leave goes to the top manager up this chain; a manager's own leave to the CEO.</p>
                        </div>
                        <div class="modal-footer justify-content-between">
                            <a href="javascript:;" class="f-13 text-dark-grey" id="assign-reporting-clear"><i class="fa fa-times-circle mr-1"></i>Clear their Reporting To</a>
                            <span>
                                <button type="button" class="btn btn-secondary rounded f-14 p-2 mr-2" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary rounded f-14 p-2" id="assign-reporting-save"><i class="fa fa-check mr-1"></i>Assign</button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                // Each employee's level, for the pop-up to know whom they report to
                window.REPORT_LEVELS = @json($reportLevels);
            </script>
            <style>
                /* The person picker: a search box over a scrolling list, all inside the pop-up */
                .person-pick { border: 1px solid var(--epic-line, #e8e9e9); border-radius: 8px; overflow: hidden; }
                .person-pick__search { position: relative; border-bottom: 1px solid var(--epic-line, #e8e9e9); background: #fafafa; }
                .person-pick__search i, .person-pick__search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--epic-muted, #878a99); width: 13px; }
                .person-pick__search input { border: 0 !important; background: transparent !important; height: 40px; padding-left: 34px !important; font-size: 13px; box-shadow: none !important; }
                .person-pick__list { max-height: 248px; overflow-y: auto; }
                .person-pick__row { display: flex; align-items: center; gap: 10px; margin: 0; padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f3f3f4; }
                .person-pick__row:last-of-type { border-bottom: 0; }
                .person-pick__row:hover { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 5%, #fff); }
                .person-pick__row input { position: absolute; opacity: 0; pointer-events: none; }
                .person-pick__avatar { width: 30px; height: 30px; min-width: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
                    font-size: 11px; font-weight: 700; background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); }
                .person-pick__who { flex: 1; min-width: 0; line-height: 1.25; }
                .person-pick__who b { display: block; font-size: 13.5px; font-weight: 600; color: var(--epic-text, #212529); }
                .person-pick__who small { display: block; font-size: 11.5px; color: var(--epic-muted, #878a99); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .person-pick__tick { width: 20px; height: 20px; min-width: 20px; border-radius: 50%; border: 1.5px solid var(--epic-line, #d0d5dd); display: inline-flex; align-items: center; justify-content: center; font-size: 10px; color: transparent; }
                .person-pick__row input:checked ~ .person-pick__tick { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; }
                .person-pick__row:has(input:checked) { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 8%, #fff); }
                .person-pick__none { display: none; padding: 16px; margin: 0; text-align: center; font-size: 13px; color: var(--epic-muted, #878a99); }
                #assignReportingModal .modal-body { padding-bottom: 18px; }
                .report-chain { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-size: 12px; }
                .report-chain span { padding: 4px 10px; border-radius: 999px; border: 1px solid var(--epic-line, #e8e9e9); color: var(--epic-muted, #878a99); background: #fff; }
                .report-chain i { font-style: normal; color: var(--epic-muted, #878a99); }
                .report-chain span.is-from { border-color: var(--epic-dark, #111); color: var(--epic-dark, #111); font-weight: 600; }
                .report-chain span.is-to { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; font-weight: 600; }
            </style>
        @endif

        <!-- Task Box Start -->
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white table-responsive">

            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}

        </div>
        <!-- Task Box End -->
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>

        var startDate = null;
        var endDate = null;
        var lastStartDate = null;
        var lastEndDate = null;

        @if(request('startDate') != '' && request('endDate') != '' )
            startDate = '{{ request("startDate") }}';
        endDate = '{{ request("endDate") }}';
        @endif

            @if(request('lastStartDate') !=='' && request('lastEndDate') !=='' )
            lastStartDate = '{{ request("lastStartDate") }}';
        lastEndDate = '{{ request("lastEndDate") }}';
        @endif

        $('#employees-table').on('preXhr.dt', function (e, settings, data) {
            const status = $('#status').val();
            const employee = $('#employee').val();
            const role = $('#role').val();
            const gender = $('#gender').val();
            const skill = $('#skill').val();
            const designation = $('#designation').val();
            const department = $('#department').val();
            const employmentType = $('#employmentType').val();
            const reporting_employee = $('#reporting_employee').val();
            const searchText = $('#search-text-field').val();
            data['status'] = status;
            data['employee'] = employee;
            data['role'] = role;
            data['gender'] = gender;
            data['skill'] = skill;
            data['designation'] = designation;
            data['department'] = department;
            data['employmentType'] = employmentType;
            data['reporting_employee'] = reporting_employee;
            data['searchText'] = searchText;

            /* If any of these following filters are applied, then dashboard conditions will not work  */
            if (status == "all" || employee == "all" || role == "all" || designation == "all" || searchText == "") {
                data['startDate'] = startDate;
                data['endDate'] = endDate;
                data['lastStartDate'] = lastStartDate;
                data['lastEndDate'] = lastEndDate;
            }

        });

        const showTable = () => {
            window.LaravelDataTables["employees-table"].draw(true);
        }

        $('#employee, #status, #role, #gender, #skill, #designation, #department, #employmentType, #reporting_employee').on('change keyup',
            function () {
                if ($('#status').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else if ($('#employee').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else if ($('#role').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else if ($('#reporting_employee').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else if ($('#gender').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else if ($('#designation').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else if ($('#department').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                }else if ($('#employmentType').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else {
                    $('#reset-filters').addClass('d-none');
                }
                showTable();
            });

        $('#search-text-field').on('keyup', function () {
            if ($('#search-text-field').val() != "") {
                $('#reset-filters').removeClass('d-none');
            }
            // Always redraw so clearing the search box returns to the full list
            showTable();
        });

        $('#reset-filters, #reset-filters-2').click(function () {
            $('#filter-form')[0].reset();
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });


        // Assign Reporting To: the pop-up opens as soon as the action is picked
        // The level the ticked people are at decides whom they can report to:
        // employees -> a team lead, team leads -> a manager, managers -> the CEO
        var REPORTS_TO = { employee: 'team_lead', team_lead: 'manager', manager: 'ceo' };
        var LEVEL_NAME = { employee: 'employee', team_lead: 'team lead', manager: 'manager', ceo: 'CEO' };
        var assignTarget = null;

        var assignIds = [];

        // From the bulk action (the ticked rows) or a row's own menu (that employee)
        function openAssignReporting(onlyIds) {
            var ids = onlyIds || $("#employees-table input.select-table-row:checked").map(function () { return this.value; }).get();
            assignIds = ids;
            var levels = {};
            ids.forEach(function (id) { var l = (window.REPORT_LEVELS || {})[id] || 'employee'; levels[l] = (levels[l] || 0) + 1; });
            var kinds = Object.keys(levels);

            $('.report-pick').addClass('d-none');
            $('.report-chain span').removeClass('is-from is-to');
            $('#assign-reporting-mixed').addClass('d-none');
            $('#assign-reporting-save').prop('disabled', false);
            assignTarget = null;

            if (kinds.length > 1) {
                // One level at a time: each reports to a different kind of person
                $('#assign-reporting-what').text(ids.length + ' ticked');
                $('#assign-reporting-mixed').removeClass('d-none').text('The ticked people are at different levels (' +
                    kinds.map(function (k) { return levels[k] + ' ' + LEVEL_NAME[k] + (levels[k] > 1 ? 's' : ''); }).join(', ') +
                    '). Tick one level at a time: employees report to a team lead, team leads to a manager, managers to the CEO.');
                $('#assign-reporting-save').prop('disabled', true);
            } else if (kinds[0] === 'ceo') {
                $('#assign-reporting-what').text('The CEO is at the top and reports to no one.');
                $('.report-chain span[data-level=ceo]').addClass('is-from');
                $('#assign-reporting-save').prop('disabled', true);
            } else {
                var from = kinds[0];
                assignTarget = REPORTS_TO[from];
                $('.report-chain span[data-level="' + from + '"]').addClass('is-from');
                $('.report-chain span[data-level="' + assignTarget + '"]').addClass('is-to');
                var who = onlyIds && ids.length === 1
                    ? '<b>' + $('<span>').text($('#datatable-row-' + ids[0]).closest('tr').find('h5').first().text().trim() || 'This ' + LEVEL_NAME[from]).html() + '</b> (' + LEVEL_NAME[from] + ')'
                    : '<b>' + ids.length + ' ' + LEVEL_NAME[from] + (ids.length > 1 ? 's' : '') + '</b>';
                $('#assign-reporting-what').html(who + ' will report to ' + (assignTarget === 'ceo' ? 'the CEO' : 'a ' + LEVEL_NAME[assignTarget]) + ':');
                var $pick = $('.report-pick[data-level="' + assignTarget + '"]').removeClass('d-none');
                $pick.find('input[type=radio]').prop('checked', false);
                $pick.find('.person-pick__search input').val('').trigger('input');
                setTimeout(function () { $pick.find('.person-pick__search input').trigger('focus'); }, 400);
            }

            $('#assignReportingModal').modal('show');
        }

        $('#quick-action-type').change(function () {
            if ($(this).val() == 'assign-reporting') {
                openAssignReporting();
            }
            const actionValue = $(this).val();
            if (actionValue != '') {
                $('#quick-action-apply').removeAttr('disabled');

                if (actionValue == 'change-status') {
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

        $('#quick-action-apply').click(function () {
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

            } else if (actionValue == 'assign-reporting') {
                openAssignReporting();
            } else {
                applyQuickAction();
            }
        });

        // Typing narrows the list of people
        $('body').on('input', '.person-pick__search input', function () {
            var q = this.value.trim().toLowerCase(), shown = 0;
            $(this).closest('.person-pick').find('.person-pick__row').each(function () {
                var hit = !q || this.getAttribute('data-find').indexOf(q) !== -1;
                this.style.display = hit ? '' : 'none';
                shown += hit ? 1 : 0;
            });
            $(this).closest('.person-pick').find('.person-pick__none').toggle(shown === 0);
        });

        // A row's own menu
        $('body').on('click', '.row-assign-reporting', function () {
            openAssignReporting([String($(this).data('user-id'))]);
        });

        $('body').on('click', '.row-change-status', function () {
            var id = $(this).data('user-id'), status = $(this).data('status');
            Swal.fire({
                title: status === 'active' ? 'Mark this employee active?' : 'Mark this employee inactive?',
                text: status === 'active' ? 'They can sign in again.' : 'They can no longer sign in, and leave the active lists.',
                icon: 'question', showCancelButton: true, focusConfirm: false,
                confirmButtonText: status === 'active' ? 'Mark Active' : 'Mark Inactive', cancelButtonText: "@lang('app.cancel')",
                customClass: { confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
            }).then(function (result) {
                if (!result.isConfirmed) { return; }
                $.easyAjax({
                    url: "{{ route('employees.apply_quick_action') }}", type: 'POST', blockUI: true,
                    data: { _token: '{{ csrf_token() }}', action_type: 'change-status', row_ids: String(id), status: status },
                    success: function (response) { if (response.status == 'success') { showTable(); } }
                });
            });
        });

        $('#assign-reporting-clear').click(function () {
            saveReporting('0');
        });

        $('#assign-reporting-save').click(function () {
            var reportingTo = assignTarget ? $('.report-pick[data-level="' + assignTarget + '"] input[type=radio]:checked').val() : null;
            if (!reportingTo) {
                return Swal.fire({ icon: 'warning', text: 'Pick ' + (assignTarget === 'ceo' ? 'the CEO' : 'the ' + LEVEL_NAME[assignTarget]) + ' they report to.', buttonsStyling: false, customClass: { confirmButton: 'btn btn-primary' } });
            }
            saveReporting(reportingTo);
        });

        function saveReporting(reportingTo) {
            var rowIds = assignIds;

            $.easyAjax({
                url: "{{ route('employees.apply_quick_action') }}",
                type: 'POST',
                disableButton: true,
                buttonSelector: '#assign-reporting-save',
                blockUI: true,
                data: { _token: '{{ csrf_token() }}', action_type: 'assign-reporting', row_ids: rowIds.join(','), reporting_to: reportingTo },
                success: function (response) {
                    if (response.status == 'success') {
                        $('#assignReportingModal').modal('hide');
                        showTable();
                        resetActionButtons();
                        deSelectAll();
                        $('#quick-action-form').hide();
                    }
                }
            });
        }

        $('body').on('click', '.delete-table-row', function () {
            var id = $(this).data('user-id');
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
                    var url = "{{ route('employees.destroy', ':id') }}";
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
                        success: function (response) {
                            if (response.status == "success") {
                                showTable();
                            }
                        }
                    });
                }
            });
        });

        const applyQuickAction = () => {
            var rowdIds = $("#employees-table input:checkbox:checked").map(function () {
                return $(this).val();
            }).get();

            var url = "{{ route('employees.apply_quick_action') }}?row_ids=" + rowdIds;

            $.easyAjax({
                url: url,
                container: '#quick-action-form',
                type: "POST",
                disableButton: true,
                buttonSelector: "#quick-action-apply",
                data: $('#quick-action-form').serialize(),
                blockUI: true,
                success: function (response) {
                    if (response.status == 'success') {
                        showTable();
                        resetActionButtons();
                        deSelectAll();
                        $('#quick-action-form').hide();
                    }
                }
            })
        };


        $('body').on('change', '.assign_role', function () {
            var id = $(this).data('user-id');
            var role = $(this).val();
            var token = "{{ csrf_token() }}";

            if (typeof id !== 'undefined') {
                $.easyAjax({
                    url: "{{ route('employees.assign_role') }}",
                    type: "POST",
                    blockUI: true,
                    container: '#employees-table',
                    data: {
                        role: role,
                        userId: id,
                        _token: token
                    },
                    success: function (response) {
                        if (response.status == "success") {
                            window.LaravelDataTables["employees-table"].draw(true);
                        }
                    }
                })
            }

        });

        $('#designation-setting').click(function () {
            const url = "{{ route('designations.create') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        })

        $('.department-setting').click(function () {
            const url = "{{ route('departments.create') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });
    </script>
@endpush
