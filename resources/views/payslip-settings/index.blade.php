@extends('layouts.app')

@push('styles')
    <style>
        /* The preview is the template at its own width, scaled down to fit the
           card. transform keeps it legible where an iframe at card width would
           reflow the design into something that is not what gets issued.

           The two cards have descriptions of different lengths and designs of
           different heights, so without a column that stretches and a header
           and frame of fixed height the footers sat at different levels and
           one card ran taller than the other. */
        .tpl-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid #e6eaf0 !important;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .tpl-card .card-header { min-height: 92px; align-items: flex-start; }
        .tpl-card .card-header h5 { line-height: 1.3; }
        .tpl-card .card-footer { margin-top: auto; }
        .tpl-card.tpl-chosen { border-color: #1d9e6f !important; }
        .tpl-card:hover { box-shadow: 0 6px 16px rgba(16, 24, 40, .1) !important; }

        .tpl-frame {
            position: relative;
            height: 430px;
            overflow: hidden;
            background: #f4f6fa;
            border-top: 1px solid #e6eaf0;
            border-bottom: 1px solid #e6eaf0;
        }

        /* Centred rather than pinned to the left corner, so the sheet sits in
           the middle of the card the way it sits in the middle of a page. */
        .tpl-frame iframe {
            position: absolute;
            top: 0;
            left: 50%;
            width: 820px;
            height: 1160px;
            border: 0;
            background: #fff;
            transform: translateX(-50%) scale(.52);
            transform-origin: top center;
            box-shadow: 0 1px 4px rgba(16, 24, 40, .12);
        }
    </style>
@endpush


@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex ">

        @include('sections.setting-sidebar')

        <x-setting-card>

            <x-slot name="alert">
                <div class="row">
                    <div class="col-md-12">
                        <x-alert type="info" icon="info-circle">
                            <strong>@lang('modules.payslipSettings.infoTitle')</strong> —
                            @lang('modules.payslipSettings.infoDesc')
                        </x-alert>
                    </div>
                </div>
            </x-slot>

            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal  border-bottom-grey">
                        @lang($pageTitle)</h2>
                </div>
            </x-slot>

            <!-- PAYSLIP SETTINGS START -->
            <div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">

                <ul class="nav nav-tabs mb-3" id="payslipSettingsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="branches-tab" data-toggle="tab" href="#branchesTabPane"
                           role="tab" aria-controls="branchesTabPane" aria-selected="true">
                            <i class="fa fa-building mr-1"></i> @lang('modules.payslipSettings.branchesTab')
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="payroll-calculation-tab" data-toggle="tab"
                           href="#payrollCalculationTabPane" role="tab" aria-controls="payrollCalculationTabPane"
                           aria-selected="false">
                            <i class="fa fa-calculator mr-1"></i> @lang('modules.payslipSettings.payrollCalculation')
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="payslip-template-tab" data-toggle="tab"
                           href="#payslipTemplateTabPane" role="tab" aria-controls="payslipTemplateTabPane"
                           aria-selected="false">
                            <i class="fa fa-file-invoice mr-1"></i> Payslip Template
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="payslipSettingsTabContent">

                    <!-- BRANCHES TAB -->
                    <div class="tab-pane fade show active" id="branchesTabPane" role="tabpanel"
                         aria-labelledby="branches-tab">

                        <div class="d-flex justify-content-end mb-3">
                            <x-forms.button-primary icon="plus" id="addNewBranch" class="mr-2">
                                @lang('modules.payslipSettings.addNewBranch')
                            </x-forms.button-primary>
                            <x-forms.button-secondary icon="eye" id="previewPayslipBtn">
                                @lang('modules.payslipSettings.previewPayslip')
                            </x-forms.button-secondary>
                        </div>

                        <div class="table-responsive">
                            <x-table class="table-bordered" id="branchTable">
                                <x-slot name="thead">
                                    <th>#</th>
                                    <th>@lang('app.branch')</th>
                                    <th>@lang('modules.payslipSettings.branchAddress')</th>
                                    <th>@lang('modules.payslipSettings.branchEmail')</th>
                                    <th>@lang('modules.payslipSettings.branchPhone')</th>
                                    <th>@lang('app.status')</th>
                                    <th class="text-right">@lang('app.action')</th>
                                </x-slot>

                                @forelse($branches as $key => $branch)
                                    <tr id="branch-{{ $branch->id }}" class="p-5 min-h-100 branch-row"
                                        data-name="{{ $branch->branch_name }}"
                                        data-address='@json($branch->address)'
                                        data-emails='@json($branch->emails ?? [])'
                                        data-phones='@json($branch->phones ?? [])'>
                                        <td>{{ $key + 1 }}</td>
                                        <td class="font-weight-600">{{ $branch->branch_name }}</td>
                                        <td style="white-space: pre-line; max-width: 320px;">{{ $branch->address }}</td>
                                        <td>
                                            @foreach ($branch->emails ?? [] as $email)
                                                <div>{{ $email }}</div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach ($branch->phones ?? [] as $phone)
                                                <div>{{ $phone }}</div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if ($branch->status)
                                                <span class="badge badge-success">@lang('app.active')</span>
                                            @else
                                                <span class="badge badge-secondary">@lang('app.inactive')</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <div class="task_view">
                                                <a href="javascript:;" data-branch-id="{{ $branch->id }}"
                                                   class="edit-branch task_view_more d-flex align-items-center justify-content-center">
                                                    <i class="fa fa-edit icons mr-2"></i> @lang('app.edit')
                                                </a>
                                            </div>
                                            <div class="task_view mt-1 mt-lg-0 mt-md-0">
                                                <a href="javascript:;" data-branch-id="{{ $branch->id }}"
                                                   class="delete-branch task_view_more d-flex align-items-center justify-content-center">
                                                    <i class="fa fa-trash icons mr-2"></i> @lang('app.delete')
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <x-cards.no-record icon="building" :message="__('messages.noRecordFound')"/>
                                        </td>
                                    </tr>
                                @endforelse
                            </x-table>
                        </div>
                    </div>

                    <!-- PAYROLL CALCULATION TAB -->
                    <div class="tab-pane fade" id="payrollCalculationTabPane" role="tabpanel"
                         aria-labelledby="payroll-calculation-tab">

                        <div class="d-flex justify-content-end mb-3">
                            <x-forms.button-primary icon="plus" id="addPayrollCalculation">
                                @lang('modules.payslipSettings.addPayrollCalculation')
                            </x-forms.button-primary>
                        </div>

                        <div class="table-responsive">
                            <x-table class="table-bordered" id="payrollCalculationTable">
                                <x-slot name="thead">
                                    <th>#</th>
                                    <th>@lang('modules.payslipSettings.grossSalary')</th>
                                    <th>Basic %</th>
                                    <th>HRA %</th>
                                    <th>@lang('modules.payslipSettings.netSalary')</th>
                                    <th>@lang('app.createdAt')</th>
                                    <th class="text-right">@lang('app.action')</th>
                                </x-slot>

                                @forelse($payrollCalculations as $key => $calc)
                                    <tr id="payroll-calc-{{ $calc->id }}" class="p-5 min-h-100">
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ currency_format($calc->gross) }}</td>
                                        <td>{{ rtrim(rtrim(number_format($calc->basic_pct, 2), '0'), '.') }}%</td>
                                        <td>{{ rtrim(rtrim(number_format($calc->hra_pct, 2), '0'), '.') }}%</td>
                                        <td>{{ currency_format($calc->net_salary) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($calc->created_at)->format(company()->date_format) }}</td>
                                        <td class="text-right">
                                            <div class="task_view">
                                                <a href="javascript:;" data-calc-id="{{ $calc->id }}"
                                                   class="edit-payroll-calculation task_view_more d-flex align-items-center justify-content-center">
                                                    <i class="fa fa-edit icons mr-2"></i> @lang('app.edit')
                                                </a>
                                            </div>
                                            <div class="task_view mt-1 mt-lg-0 mt-md-0">
                                                <a href="javascript:;" data-calc-id="{{ $calc->id }}"
                                                   class="delete-payroll-calculation task_view_more d-flex align-items-center justify-content-center">
                                                    <i class="fa fa-trash icons mr-2"></i> @lang('app.delete')
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <x-cards.no-record icon="calculator" :message="__('messages.noRecordFound')"/>
                                        </td>
                                    </tr>
                                @endforelse
                            </x-table>
                        </div>
                    </div>

                    <!-- PAYSLIP TEMPLATE TAB -->
                    <div class="tab-pane fade" id="payslipTemplateTabPane" role="tabpanel"
                         aria-labelledby="payslip-template-tab">

                        <div class="alert alert-info f-13" role="alert">
                            <i class="fa fa-info-circle mr-1"></i>
                            This applies to payslips issued <strong>from now on</strong>. A payslip already
                            issued keeps the design it was issued in, so nothing that has been sent or filed
                            changes.
                        </div>

                        <div class="row">
                            @foreach ($payslipTemplates as $key => $meta)
                                <div class="col-lg-6 mb-4">
                                    <div class="card b-shadow-4 h-100 tpl-card {{ $key === $payslipTemplate ? 'tpl-chosen' : '' }}">
                                        <div class="card-header bg-white d-flex p-3">
                                            <div class="mr-auto pr-3">
                                                <h5 class="mb-1 f-15">{{ $meta['name'] }}</h5>
                                                <p class="mb-0 f-12 text-lightest">{{ $meta['blurb'] }}</p>
                                            </div>
                                            @if ($key === $payslipTemplate)
                                                <span class="badge badge-success f-11 flex-shrink-0">In use</span>
                                            @endif
                                        </div>

                                        {{-- The template itself, drawn from sample figures: what is shown
                                             here is what will be issued, not a picture of it. --}}
                                        <div class="tpl-frame">
                                            <iframe src="{{ route('payslip-template.preview', $key) }}"
                                                    title="{{ $meta['name'] }} preview" loading="lazy"></iframe>
                                        </div>

                                        <div class="card-footer bg-white d-flex align-items-center p-3">
                                            <a href="{{ route('payslip-template.preview', $key) }}" target="_blank"
                                               class="f-13 mr-auto">
                                                <i class="fa fa-external-link-alt mr-1"></i> Open full size
                                            </a>
                                            @if ($key === $payslipTemplate)
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="fa fa-check mr-1"></i> In use
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-primary use-template"
                                                        data-template="{{ $key }}" data-name="{{ $meta['name'] }}">
                                                    Use this template
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>

            </div>
            <!-- PAYSLIP SETTINGS END -->

        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

    <!-- PAYSLIP PREVIEW MODAL -->
    <div class="modal fade" id="payslipPreviewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('modules.payslipSettings.payslipPreview')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">×</span></button>
                </div>
                <div class="modal-body payslip-preview-body">
                    <div class="payslip-page" id="payslipPreview">

                        <div class="payslip-company">
                            <div class="payslip-logo">
                                @if (company()->logo_url ?? false)
                                    <img src="{{ company()->logo_url }}" alt="{{ company()->company_name }}"
                                         style="max-height:40px;">
                                @endif
                                <div class="payslip-company-name">{{ company()->company_name ?? config('app.name') }}</div>
                            </div>
                        </div>

                        <div class="payslip-line"></div>

                        <div class="payslip-title">@lang('modules.payslipSettings.compensationStructureTitle')</div>

                        <div class="employee-info">
                            <div><strong>@lang('app.menu.employeeName'):</strong> Mr. XXXXX</div>
                            <div><strong>@lang('app.employeeId'):</strong> EMP001</div>
                            <div><strong>@lang('app.designation'):</strong> --</div>
                            <div><strong>@lang('modules.payslipSettings.payMonth'):</strong> {{ now()->format('F Y') }}</div>
                        </div>

                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th>@lang('modules.payslipSettings.salaryComponents')</th>
                                    <th>@lang('app.amount')</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="total-row">
                                    <td>@lang('modules.payslipSettings.grossSalary')</td>
                                    <td id="previewGross">{{ currency_format(0) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="net-salary">
                            <span>@lang('modules.payslipSettings.netSalary')</span>
                            <strong id="previewNet">{{ currency_format(0) }}</strong>
                        </div>

                        <div class="payslip-branch">
                            <div class="branch-footer-title" id="previewBranchName"></div>
                            <div class="preview-branch-address" id="previewBranchAddress"></div>
                            <div class="preview-branch-emails" id="previewBranchEmails"></div>
                            <div class="preview-branch-phones" id="previewBranchPhones"></div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
                    <x-forms.button-primary id="printPayslipBtn" icon="print">@lang('modules.payslipSettings.printPayslip')</x-forms.button-primary>
                </div>
            </div>
        </div>
    </div>

    <!-- PAYROLL CALCULATION ADD/EDIT MODAL -->
    <div class="modal fade" id="payrollCalculationModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payrollCalculationModalTitle">@lang('modules.payslipSettings.payrollCalculation')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">×</span></button>
                </div>
                <div class="modal-body p-0">
                    <iframe
                        id="payrollCalculationIframe"
                        src=""
                        class="w-100"
                        style="min-height: 70vh; border: 0;"
                        title="@lang('modules.payslipSettings.payrollCalculation')">
                    </iframe>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        .payslip-preview-body {
            background: #f1f1f1;
            display: flex;
            justify-content: center;
            padding: 30px;
            overflow: auto;
        }

        .payslip-page {
            width: 794px;
            max-width: 100%;
            min-height: 700px;
            background: #fff;
            padding: 45px 50px;
            position: relative;
            font-family: "Times New Roman", Times, serif;
            font-size: 11px;
            color: #111;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .15);
        }

        .payslip-company {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .payslip-logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payslip-company-name {
            font-family: Arial, sans-serif;
            font-size: 20px;
            color: #555;
            font-weight: 600;
        }

        .payslip-line {
            height: 3px;
            background: #F4AC83;
            margin-bottom: 14px;
        }

        .payslip-title {
            background: #555;
            color: #fff;
            text-align: center;
            padding: 5px;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .employee-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #222;
            font-size: 10px;
        }

        .employee-info div {
            padding: 5px;
            border-bottom: 1px solid #222;
        }

        .employee-info div:nth-child(odd) {
            border-right: 1px solid #222;
        }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 10px;
        }

        .salary-table th {
            background: #F4AC83;
            border: 1px solid #222;
            padding: 5px;
            text-align: left;
        }

        .salary-table td {
            border: 1px solid #222;
            padding: 4px;
        }

        .salary-table td:last-child,
        .salary-table th:last-child {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background: #f5f5f5;
        }

        .net-salary {
            margin-top: 14px;
            border: 1px solid #222;
            background: #eee;
            padding: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        .payslip-branch {
            margin-top: 30px;
            border-top: 2px solid #F4AC83;
            padding-top: 9px;
            text-align: center;
        }

        .branch-footer-title {
            color: #C76F43;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .preview-branch-address {
            white-space: pre-line;
            font-size: 9px;
            line-height: 1.4;
            color: #777;
        }

        .preview-branch-emails,
        .preview-branch-phones {
            margin-top: 4px;
            font-size: 9px;
            line-height: 1.4;
            color: #777;
        }
    </style>
@endpush

@push('scripts')
    <script>

        $('#addNewBranch').click(function () {
            var url = "{{ route('payslip-settings.create') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('body').on('click', '.edit-branch', function () {
            var id = $(this).data('branch-id');
            var url = "{{ route('payslip-settings.edit', ':id') }}".replace(':id', id);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('body').on('click', '.delete-branch', function () {
            var id = $(this).data('branch-id');

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
                    var url = "{{ route('payslip-settings.destroy', ':id') }}".replace(':id', id);
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
                            if (response.status === "success") {
                                $('#branch-' + id).fadeOut();
                            }
                        }
                    });
                }
            });
        });

        function fillPayslipPreview($row) {
            $('#previewBranchName').text("{{ company()->company_name ?? '' }}");
            $('#previewBranchAddress').text($row.data('address') || '');

            var emails = $row.data('emails') || [];
            var phones = $row.data('phones') || [];

            $('#previewBranchEmails').html(emails.map(function (e) {
                return '<div>' + $('<div>').text(e).html() + '</div>';
            }).join(''));

            $('#previewBranchPhones').html(phones.map(function (p) {
                return '<div>' + $('<div>').text(p).html() + '</div>';
            }).join(''));
        }

        var payrollCalculationBaseUrl = "{{ route('legacy.page', 'payslip_inputs') }}";

        $('#addPayrollCalculation').click(function () {
            $('#payrollCalculationModalTitle').text("@lang('modules.payslipSettings.addPayrollCalculation')");
            $('#payrollCalculationIframe').attr('src', payrollCalculationBaseUrl);
            $('#payrollCalculationModal').modal('show');
        });

        $('body').on('click', '.edit-payroll-calculation', function () {
            var id = $(this).data('calc-id');
            $('#payrollCalculationModalTitle').text("@lang('modules.payslipSettings.editPayrollCalculation')");
            $('#payrollCalculationIframe').attr('src', payrollCalculationBaseUrl + '?id=' + id);
            $('#payrollCalculationModal').modal('show');
        });

        $('body').on('click', '.delete-payroll-calculation', function () {
            var id = $(this).data('calc-id');

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
                    var url = "{{ route('payslip-settings.payroll-calculations.destroy', ':id') }}".replace(':id', id);
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
                            if (response.status === "success") {
                                $('#payroll-calc-' + id).fadeOut();
                            }
                        }
                    });
                }
            });
        });

        // The iframe (payslip_inputs.php) posts this message right after a
        // successful save — reload so the list reflects the new/updated row.
        window.addEventListener('message', function (event) {
            if (event.data === 'payrollCalculationSaved') {
                window.location.reload();
            }
        });

        $('#previewPayslipBtn').click(function () {
            var $row = $('#branchTable .branch-row:visible').first();

            if ($row.length === 0) {
                toastr.error("@lang('modules.payslipSettings.noBranchForPreview')");
                return;
            }

            fillPayslipPreview($row);
            $('#payslipPreviewModal').modal('show');
        });

        $('#printPayslipBtn').click(function () {
            var printContents = document.getElementById('payslipPreview').outerHTML;
            var printWindow = window.open('', '_blank', 'width=900,height=1000');

            if (!printWindow) {
                toastr.error("@lang('messages.popupBlocked')");
                return;
            }

            printWindow.document.write('<!DOCTYPE html><html><head><title>Payslip</title></head><body>' + printContents + '</body></html>');
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        });

    </script>
@endpush

@push('scripts')
<script>
    $(document).ready(function () {
        // ---- choosing a payslip template --------------------------------
        $(document).on('click', '.use-template', function () {
            var button = $(this);

            $.easyAjax({
                url: "{{ route('payslip-template.update') }}",
                type: 'POST',
                data: { template: button.data('template'), _token: '{{ csrf_token() }}' },
                success: function () {
                    window.location.href = "{{ route('payslip-settings.index') }}#payslipTemplateTabPane";
                    window.location.reload();
                }
            });
        });
    });
</script>
@endpush
