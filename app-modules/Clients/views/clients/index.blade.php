@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

{{-- Clients (ClientController::index), the standard page (docs/PAGE_RULES.md): Add Client / Import /
     Export and Filters at the top right; the filters are GET parameters, hidden until Filters is
     pressed (open when one is in use) and applied at once; the list is the clients DataTable. --}}
@php
    $f = fn ($key) => request($key, 'all') ?: 'all';
    $filtered = collect(['client', 'category_id', 'sub_category_id', 'status', 'project_id', 'contract_type_id', 'country_id', 'verification'])
        ->contains(fn ($key) => $f($key) !== 'all')
        || filled(request('search')) || filled(request('start'));
    $pending = $f('verification') === 'no';
    $canAdd = in_array($addClientPermission, ['all', 'added', 'both']);
@endphp

@section('content')
@include('recruitment.partials.styles')

<div class="content-wrapper" data-swap-links>

    <form method="GET" action="{{ route('clients.index') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <input type="hidden" name="start" id="cl-start" value="{{ request('start') }}">
        <input type="hidden" name="end" id="cl-end" value="{{ request('end') }}">
        <div class="row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('modules.client.addedOn')</label>
                <input type="text" class="form-control f-14" id="datatableRange" readonly
                    placeholder="@lang('placeholders.dateRange')" style="background: #fff !important; cursor: pointer;">
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('app.category')</label>
                <select name="category_id" id="filter_category_id" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($f('category_id') == $category->id)>{{ $category->category_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('modules.productCategory.subCategory')</label>
                <select name="sub_category_id" id="filter_sub_category_id" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($subcategories->where('category_id', $f('category_id')) as $sub)
                        <option value="{{ $sub->id }}" @selected($f('sub_category_id') == $sub->id)>{{ $sub->category_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('app.status')</label>
                <select name="status" id="status" class="form-control select-picker">
                    <option value="all">@lang('app.all')</option>
                    <option value="active" @selected($f('status') === 'active')>@lang('app.active')</option>
                    <option value="deactive" @selected($f('status') === 'deactive')>@lang('app.inactive')</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" id="search-text-field" value="{{ request('search') }}" class="form-control f-14"
                    placeholder="Name, company, email or mobile">
            </div>
        </div>
        <div class="row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('app.client')</label>
                <select name="client" id="client" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($f('client') == $client->id)>{{ $client->name }}{{ $client->company_name ? ' · ' . $client->company_name : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('app.project')</label>
                <select name="project_id" id="project_id" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($f('project_id') == $project->id)>{{ $project->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('modules.contracts.contractType')</label>
                <select name="contract_type_id" id="contract_type_id" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected($f('contract_type_id') == $contract->id)>{{ $contract->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">@lang('app.country')</label>
                <select name="country_id" id="country_id" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected($f('country_id') == $country->id)>{{ $country->nicename }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Verified</label>
                <select name="verification" id="verification" class="form-control select-picker">
                    <option value="all">@lang('app.all')</option>
                    <option value="yes" @selected($f('verification') === 'yes')>@lang('app.yes')</option>
                    <option value="no" @selected($pending)>@lang('app.no')</option>
                </select>
            </div>
        </div>
    </form>

    {{-- the top bar: the list switch on the left; Add Client, Import, Export (put in #table-actions by the table) --}}
    <div class="btn-group cl-switch" role="group" data-rc-bar data-no-bar>
        <a href="{{ route('clients.index') }}" class="btn btn-secondary f-14 {{ $pending ? '' : 'is-on' }}">
            <i class="bi bi-list-ul mr-1"></i>@lang('app.menu.clients')
        </a>
        <a href="{{ route('clients.index', ['verification' => 'no']) }}" class="btn btn-secondary f-14 {{ $pending ? 'is-on' : '' }}">
            <i class="bi bi-person-x mr-1"></i>@lang('modules.dashboard.verificationPending')
        </a>
    </div>
    <div id="table-actions" class="d-inline-flex align-items-center cl-actions" data-rc-bar data-no-bar>
        @if ($canAdd)
            <a href="{{ route('clients.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-plus mr-1"></i>@lang('app.addClient')</a>
            <a href="{{ route('clients.import') }}" class="btn btn-secondary rounded f-14 d-none d-lg-inline-flex"><i class="fa fa-file-upload mr-1"></i>@lang('app.importExcel')</a>
        @endif
    </div>

    {{-- ticked rows: change status / delete --}}
    <div class="d-flex justify-content-end mb-2" data-no-bar>
        <x-datatable.actions>
            <div class="select-status mr-3">
                <select name="action_type" class="form-control select-picker" id="quick-action-type" disabled>
                    <option value="">@lang('app.selectAction')</option>
                    <option value="change-status">@lang('modules.tasks.changeStatus')</option>
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

    <div class="d-flex flex-column w-tables rounded bg-white table-responsive cl-table">
        {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}
    </div>
</div>

<style>
    .rc-std-bar > .cl-switch { margin-right: auto !important; }
    .cl-switch .btn { height: 38px; display: inline-flex; align-items: center; }
    html body.epic .cl-switch .btn.is-on { background: var(--epic-blue, #cb6b4f) !important; border-color: var(--epic-blue, #cb6b4f) !important; color: #fff !important; }
    html body.epic .cl-switch .btn.is-on i { color: #fff !important; }
    .cl-actions { gap: 10px; }
    .cl-actions > .btn, .cl-actions .dt-buttons .btn, .cl-actions .dt-button { height: 38px; display: inline-flex; align-items: center; margin: 0 !important; border-radius: 8px !important; }
    .cl-actions .dt-buttons { display: inline-flex; }
    .cl-table { border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    @media (max-width: 767px) { .rc-std-bar > .cl-switch { margin-right: 0 !important; width: 100%; } }
</style>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'searchLabel' => 'Search clients', 'active' => $filtered])
@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        $('#clients-table').on('preXhr.dt', function(e, settings, data) {
            data['startDate'] = $('#cl-start').val() || null;
            data['endDate'] = $('#cl-end').val() || null;
            data['status'] = $('#status').val();
            data['client'] = $('#client').val();
            data['category_id'] = $('#filter_category_id').val();
            data['sub_category_id'] = $('#filter_sub_category_id').val();
            data['project_id'] = $('#project_id').val();
            data['contract_type_id'] = $('#contract_type_id').val();
            data['country_id'] = $('#country_id').val();
            data['verification'] = $('#verification').val();
            data['searchText'] = $('#search-text-field').val();
        });
        const showTable = () => {
            window.LaravelDataTables["clients-table"].draw(true);
        }

        $(function () {
            const dateFormat = '{{ company()->moment_date_format }}';
            const range = $('#datatableRange');

            // the date range is a filter like the others: picking one (or clearing it) applies it at once
            range.on('apply.daterangepicker', function (ev, picker) {
                $('#cl-start').val(picker.startDate.format(dateFormat));
                $('#cl-end').val(picker.endDate.format(dateFormat));
                PageSwap.submit(document.getElementById('rc-filter-form'));
            });
            range.on('cancel.daterangepicker', function () {
                range.val('');
                $('#cl-start, #cl-end').val('');
                PageSwap.submit(document.getElementById('rc-filter-form'));
            });

            @if (filled(request('start')) && filled(request('end')))
                range.val(@json(request('start') . ' ' . __('app.to') . ' ' . request('end')));
                if (range.data('daterangepicker')) {
                    range.data('daterangepicker').setStartDate(@json(request('start')));
                    range.data('daterangepicker').setEndDate(@json(request('end')));
                }
            @endif
        });

        $('#quick-action-type').change(function() {
            const actionValue = $(this).val();
            if (actionValue !== '') {
                $('#quick-action-apply').removeAttr('disabled');

                if (actionValue === 'change-status') {
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

        $('body').on('click', '.verify-user', function() {
            const id = $(this).data('user-id');
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "@lang('messages.approvalWarning')",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('app.approve')",
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
                    var url = "{{ route('clients.approve', ':id') }}";
                    url = url.replace(':id', id);

                    var token = "{{ csrf_token() }}";

                    $.easyAjax({
                        type: 'POST',
                        url: url,
                        data: {
                            '_token': token
                        },
                        success: function(response) {
                            if (response.status == "success") {
                                showTable();
                            }
                        }
                    });
                }
            });
        });

        $('body').on('click', '.delete-table-row', function() {
            const id = $(this).data('user-id');
            var url = "{{ route('clients.finance_count', ':id') }}";
            url = url.replace(':id', id)
            var token = "{{ csrf_token() }}";
            $.easyAjax({
                type: 'GET',
                url: url,
                data: {
                    '_token': token
                },
                success: function(response) {
                    if (response.status == "success") {
                        Swal.fire({
                            title: "@lang('messages.sweetAlertTitle')",
                            text: response.deleteClient + "@lang('messages.recoverRecord')",
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
                                var url = "{{ route('clients.destroy', ':id') }}";
                                url = url.replace(':id', id);
                                $.easyAjax({
                                    type: 'POST',
                                    url: url,
                                    data: {
                                        '_token': token,
                                        '_method': 'DELETE'
                                    },
                                    success: function(response) {
                                        if (response.status == "success") {
                                            showTable();
                                        }
                                    }
                                });
                            }
                        });
                    }
                }
            });
        });

        const applyQuickAction = () => {
            var rowdIds = $("#clients-table input:checkbox:checked").map(function() {
                return $(this).val();
            }).get();

            const url = "{{ route('clients.apply_quick_action') }}?row_ids=" + rowdIds;

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
    </script>
@endpush
