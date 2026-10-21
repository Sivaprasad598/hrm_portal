@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
    <x-filters.filter-box>
        <div class="select-box py-2 d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Status</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter_status">
                    <option value="all">All</option>
                    @foreach (\App\Models\Asset::STATUSES as $key => [$label])
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Type</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter_type" data-live-search="true">
                    <option value="all">All</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Held By</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter_holder" data-live-search="true" data-size="8">
                    <option value="all">All</option>
                    @foreach ($holders as $holder)
                        <option value="{{ $holder->id }}">{{ $holder->name }}{{ $holder->employee_id ? ' (' . $holder->employee_id . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Warranty</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter_warranty">
                    <option value="all">All</option>
                    <option value="soon">Ends within 30 days</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Due</p>
            <div class="select-status">
                <select class="form-control select-picker" id="filter_due">
                    <option value="all">All</option>
                    <option value="overdue">Overdue</option>
                    <option value="soon">Within 30 days</option>
                    <option value="service">Service due (30 days)</option>
                </select>
            </div>
        </div>

        @if ($branches->count() > 1)
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">Branch</p>
                <div class="select-status">
                    <select class="form-control select-picker" id="filter_branch">
                        <option value="all">All</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <div class="task-search d-flex py-1 px-lg-3 px-0 border-right-grey align-items-center">
            <form class="w-100 mr-1 mr-lg-0 mr-md-1 ml-md-1 ml-0 ml-lg-0" onsubmit="return false">
                <div class="input-group bg-grey rounded">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-0 bg-additional-grey"><i class="fa fa-search f-13 text-dark-grey"></i></span>
                    </div>
                    <input type="text" class="form-control f-14 p-1 border-additional-grey" id="search-text-field"
                           placeholder="Name, code, serial, employee">
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

    <div class="row mb-3">
        @foreach ([
            ['all', 'Total Assets', $counts->sum(), '#6c757d', 'fa-boxes', '#e9ecef'],
            ['available', 'Available', $counts['available'] ?? 0, '#28a745', 'fa-check-circle', '#d4edda'],
            ['assigned', 'Assigned', $counts['assigned'] ?? 0, '#007bff', 'fa-user-check', '#d6e9ff'],
            ['repair', 'Under Repair', $counts['repair'] ?? 0, '#ffc107', 'fa-tools', '#fff3cd'],
            ['lost', 'Lost', $counts['lost'] ?? 0, '#dc3545', 'fa-exclamation-triangle', '#f8d7da'],
        ] as [$status, $label, $count, $colour, $icon, $bg])
            <div class="col-xl col-md-4 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ $colour }} !important; cursor:pointer;"
                     onclick="filterByStatus('{{ $status }}')">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">{{ $label }}</p>
                            <h3 class="font-weight-700 mb-0" style="color:{{ $colour }}">{{ $count }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:46px;height:46px;background:{{ $bg }};">
                            <i class="fa {{ $icon }} f-18" style="color:{{ $colour }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($overdue)
        <div class="alert alert-warning f-14">
            <i class="fa fa-clock mr-1"></i> {{ $overdue }} asset(s) are past their expected return date.
            <a href="{{ route('assets.assigned', ['overdue' => 1]) }}" class="font-weight-600">See them</a>
        </div>
    @endif

    @if ($dueOverdue || $dueSoon)
        <div class="alert {{ $dueOverdue ? 'alert-danger' : 'alert-warning' }} f-14">
            <i class="fa fa-calendar-times mr-1"></i>
            @if ($dueOverdue)
                <strong>{{ $dueOverdue }}</strong> asset(s) have a service, renewal or warranty past its date.
                <a href="javascript:;" class="font-weight-600" onclick="$('#filter_due').val('overdue').selectpicker('refresh').trigger('change')">See them</a>
            @endif
            @if ($dueSoon)
                {{ $dueOverdue ? ' · ' : '' }}<strong>{{ $dueSoon }}</strong> due within 30 days.
                <a href="javascript:;" class="font-weight-600" onclick="$('#filter_due').val('soon').selectpicker('refresh').trigger('change')">See them</a>
            @endif
        </div>
    @endif

    @if ($warrantySoon)
        <div class="alert alert-info f-14">
            <i class="fa fa-shield-alt mr-1"></i> The warranty on {{ $warrantySoon }} asset(s) ends within 30 days.
            <a href="javascript:;" class="font-weight-600" onclick="$('#filter_warranty').val('soon').selectpicker('refresh').trigger('change')">See them</a>
        </div>
    @endif

    <div class="d-flex justify-content-between action-bar">
        <div id="table-actions" class="d-block d-lg-flex align-items-center">
            <x-forms.link-primary :link="route('assets.create')" class="mr-3 openRightModal float-left mb-2 mb-lg-0" icon="plus">
                Add Asset
            </x-forms.link-primary>
            <x-forms.link-secondary :link="route('assets.assign-form')" class="mr-3 openRightModal float-left mb-2 mb-lg-0" icon="user-plus">
                Assign Asset
            </x-forms.link-secondary>
            <x-forms.link-secondary :link="route('assets.import')" class="mr-3 openRightModal float-left mb-2 mb-lg-0" icon="file-upload">
                Import
            </x-forms.link-secondary>
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
            table = window.LaravelDataTables['assets-table'];

            $('#assets-table').on('preXhr.dt', function (e, settings, data) {
                data.status = $('#filter_status').val();
                data.type = $('#filter_type').val();
                data.branch = $('#filter_branch').val() || 'all';
                data.holder = $('#filter_holder').val();
                data.warranty = $('#filter_warranty').val();
                data.due = $('#filter_due').val();
                data.searchText = $('#search-text-field').val();
            });

            function toggleReset() {
                var filtered = $('#filter_status').val() !== 'all' || $('#filter_type').val() !== 'all'
                    || $('#filter_holder').val() !== 'all' || $('#filter_warranty').val() !== 'all' || $('#filter_due').val() !== 'all'
                    || ($('#filter_branch').val() || 'all') !== 'all' || $('#search-text-field').val() !== '';
                $('#reset-filters').toggleClass('d-none', !filtered);
            }

            $('#filter_status, #filter_type, #filter_branch, #filter_holder, #filter_warranty, #filter_due').on('change', function () {
                toggleReset();
                table.draw();
            });

            let searchTimer;
            $('#search-text-field').on('keyup', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { toggleReset(); table.draw(); }, 300);
            });

            $('#reset-filters').on('click', function () {
                $('#filter_status, #filter_type, #filter_branch, #filter_holder, #filter_warranty, #filter_due').val('all').selectpicker('refresh');
                $('#search-text-field').val('');
                toggleReset();
                table.draw();
            });

            $('body').on('click', '.delete-asset', function () {
                const id = $(this).data('asset-id');

                Swal.fire({
                    title: "@lang('messages.sweetAlertTitle')",
                    text: 'The asset is removed from the register. Only an asset that was never assigned can be deleted.',
                    icon: 'warning',
                    showCancelButton: true,
                    focusConfirm: false,
                    confirmButtonText: "@lang('messages.confirmDelete')",
                    cancelButtonText: "@lang('app.cancel')",
                    customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
                    showClass: {popup: 'swal2-noanimation', backdrop: 'swal2-noanimation'},
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.easyAjax({
                            type: 'POST',
                            url: "{{ route('assets.destroy', ':id') }}".replace(':id', id),
                            blockUI: true,
                            data: {_token: '{{ csrf_token() }}', _method: 'DELETE'},
                            success: function (response) {
                                if (response.status === 'success') {
                                    table.draw(false);
                                }
                            }
                        });
                    }
                });
            });
        });

        function filterByStatus(status) {
            $('#filter_status').val(status).selectpicker('refresh').trigger('change');
        }
    </script>
@endpush
