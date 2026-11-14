@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
<x-filters.filter-box>

    {{-- DATE RANGE --}}
    <div class="select-box d-flex pr-2 border-right-grey border-right-grey-sm-0">
        <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.duration')</p>
        <div class="select-status d-flex">
            <input type="text"
                class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                id="clientSubRange" placeholder="@lang('placeholders.dateRange')">
        </div>
    </div>

    {{-- STATUS --}}
    <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
        <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.status')</p>
        <div class="select-status">
            <select class="form-control select-picker" id="filter_status" data-size="8">
                <option value="all">@lang('app.all')</option>
                <option value="active">@lang('app.active')</option>
                <option value="inactive">@lang('app.inactive')</option>
            </select>
        </div>
    </div>

    {{-- ROTATION --}}
    <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
        <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.frequency')</p>
        <div class="select-status">
            <select class="form-control select-picker" id="filter_rotation" data-size="8">
                <option value="all">@lang('app.all')</option>
                @foreach ($rotations as $rotation)
                    <option value="{{ $rotation }}">{{ ucfirst($rotation) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- RESET --}}
    <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
        <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
            @lang('app.clearFilters')
        </x-forms.button-secondary>
    </div>

</x-filters.filter-box>
@endsection

@section('content')
<div class="content-wrapper">

    {{-- BACK BUTTON --}}
    <div class="d-block d-lg-flex d-md-flex justify-content-between mb-3">
        <div id="table-actions" class="flex-grow-1 align-items-center">
            <x-forms.link-secondary :link="route('invoices.index')" icon="arrow-left" class="mr-3 float-left">
                @lang('app.menu.invoices')
            </x-forms.link-secondary>
        </div>
    </div>

    {{-- STAT CARDS --}}
    <div class="row mb-4">
        <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
            <div class="bg-white p-20 rounded b-shadow-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="f-15 f-w-500 mb-20 text-darkest-grey">@lang('app.totalSubscriptions')</h5>
                    <p class="mb-0 f-24 font-weight-bold text-primary">{{ $statsTotal }}</p>
                </div>
                <i class="bi bi-credit-card-2-front text-lightest f-24"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
            <div class="bg-white p-20 rounded b-shadow-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="f-15 f-w-500 mb-20 text-darkest-grey">@lang('app.activeSubscriptions')</h5>
                    <p class="mb-0 f-24 font-weight-bold text-success">{{ $statsActive }}</p>
                </div>
                <i class="bi bi-check-circle text-lightest f-24"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
            <div class="bg-white p-20 rounded b-shadow-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="f-15 f-w-500 mb-20 text-darkest-grey">@lang('app.suspended')</h5>
                    <p class="mb-0 f-24 font-weight-bold text-danger">{{ $statsSuspended }}</p>
                </div>
                <i class="bi bi-slash-circle text-lightest f-24"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="bg-white p-20 rounded b-shadow-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="f-15 f-w-500 mb-20 text-darkest-grey">@lang('app.totalAmount')</h5>
                    <p class="mb-0 f-24 font-weight-bold text-info">{{ $statsTotalAmount }}</p>
                </div>
                <i class="bi bi-cash-stack text-lightest f-24"></i>
            </div>
        </div>
    </div>

    {{-- DATATABLE --}}
    <div class="d-flex flex-column w-tables rounded mt-3 bg-white">
        {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}
    </div>

</div>
@endsection

@push('scripts')
    @include('sections.datatable_js')
    <script>
        // ── Date picker ──────────────────────────────────────────
        $('#clientSubRange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: "@lang('app.clearFilters')",
                format: '{{ company()->moment_date_format }}'
            }
        });

        $('#clientSubRange').on('apply.daterangepicker', function (ev, picker) {
            $(this).val(
                picker.startDate.format('{{ company()->moment_date_format }}') +
                ' @lang("app.to") ' +
                picker.endDate.format('{{ company()->moment_date_format }}')
            );
            updateResetBtn();
            showTable();
        });

        $('#clientSubRange').on('cancel.daterangepicker', function () {
            $(this).val('');
            updateResetBtn();
            showTable();
        });

        // ── Filter selects ────────────────────────────────────────
        $('#filter_status, #filter_rotation').on('change', function () {
            updateResetBtn();
            showTable();
        });

        $('#reset-filters').on('click', function () {
            $('#filter_status, #filter_rotation').val('all').selectpicker('refresh');
            $('#clientSubRange').val('');
            $(this).addClass('d-none');
            showTable();
        });

        function updateResetBtn() {
            var dirty = $('#filter_status').val()   !== 'all'
                     || $('#filter_rotation').val() !== 'all'
                     || $('#clientSubRange').val()  !== '';
            dirty ? $('#reset-filters').removeClass('d-none') : $('#reset-filters').addClass('d-none');
        }

        // ── DataTable pre-XHR hook ────────────────────────────────
        $('#client-subscriptions-table').on('preXhr.dt', function (e, settings, data) {
            var range = $('#clientSubRange').val();
            var startDate = null, endDate = null;

            if (range) {
                var parts = range.split(' @lang("app.to") ');
                startDate = parts[0] || null;
                endDate   = parts[1] || null;
            }

            data['status']    = $('#filter_status').val();
            data['rotation']  = $('#filter_rotation').val();
            data['startDate'] = startDate;
            data['endDate']   = endDate;
        });

        const showTable = () => window.LaravelDataTables['client-subscriptions-table'].draw(true);

        // ── Payment history modal ─────────────────────────────────
        $('body').on('click', '.btn-client-logs', function () {
            var url = $(this).data('url');
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        // ── Cancel subscription ───────────────────────────────────
        $('body').on('click', '.btn-client-cancel-sub', function () {
            var id = $(this).data('id');

            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "@lang('messages.subscriptionCancelText')",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('app.yes')",
                cancelButtonText: "@lang('app.no')",
                customClass: { confirmButton: 'btn btn-danger mr-3', cancelButton: 'btn btn-secondary' },
                showClass: { popup: 'swal2-noanimation', backdrop: 'swal2-noanimation' },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    $.easyAjax({
                        type: 'POST',
                        url: "{{ route('client.subscriptions.cancel', ':id') }}".replace(':id', id),
                        blockUI: true,
                        data: { '_token': "{{ csrf_token() }}", '_method': 'PATCH' },
                        success: function (r) {
                            if (r.status === 'success') { showTable(); }
                        }
                    });
                }
            });
        });
    </script>
@endpush
