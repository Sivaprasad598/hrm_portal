@extends('layouts.app')

@push('styles')
    <style>
        /* the financial year's pay periods, one tile each (21st to 20th) */
        .pi-months { display: flex; gap: 8px; margin-top: 6px; overflow-x: auto; padding: 9px 6px 2px 0; scrollbar-width: thin; }
        .pi-month { position: relative; flex: 1 0 96px; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 6px 7px;
            border: 1px solid #e4e7ec; border-radius: 10px; background: #fff; color: #344054; text-align: center; transition: .12s; }
        .pi-month:hover { border-color: var(--epic-blue, #cb6b4f); color: #101828; text-decoration: none; }
        .pi-month b { font-size: 14px; font-weight: 600; line-height: 1.2; }
        .pi-month .pi-month-year { font-weight: 400; font-size: 11px; color: #98a2b3; margin-left: 3px; }
        .pi-month small { font-size: 10.5px; color: #98a2b3; white-space: nowrap; }
        .pi-month.active { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; box-shadow: 0 2px 6px rgba(203, 107, 79, .35); }
        .pi-month.active small, .pi-month.active .pi-month-year { color: rgba(255, 255, 255, .85); }
        .pi-issued { position: absolute; top: -7px; right: -5px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #12b76a;
            color: #fff; font-size: 10px; font-weight: 700; line-height: 18px; border: 2px solid #fff; }
        .pi-chip { display: inline-block; border-radius: 12px; padding: 1px 8px; margin: 2px 4px 2px 0; font-size: 12px; background: #f2f4f7; color: #344054; }
        .pi-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .pi-stat { flex: 1 1 160px; border: 1px solid #eaecf0; border-radius: 10px; padding: 10px 14px; background: #fcfcfd; }
        .pi-stat b { display: block; font-size: 20px; font-weight: 600; line-height: 1.2; }
        .pi-stat span { font-size: 12px; color: #667085; }
        .pi-cond { display: flex; align-items: flex-start; gap: 10px; border-radius: 10px; padding: 10px 14px; margin-top: 12px; font-size: 13px; }
        .pi-cond.on { background: #ecfdf3; border: 1px solid #abefc6; color: #05603a; }
        .pi-cond.off { background: #fffaeb; border: 1px solid #fedf89; color: #93370d; }
        .pi-cond .fa { margin-top: 2px; }

        .pi-progress { display: flex; align-items: center; gap: 8px; margin: 0 0 16px; flex-wrap: wrap; }
        .pi-progress .pi-p { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #98a2b3; }
        .pi-progress .pi-p i { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #d0d5dd; display: inline-flex; align-items: center;
            justify-content: center; font-style: normal; font-size: 12px; font-weight: 700; background: #fff; }
        .pi-progress .pi-p.done { color: #067647; } .pi-progress .pi-p.done i { border-color: #12b76a; background: #12b76a; color: #fff; }
        .pi-progress .pi-p.now { color: #101828; font-weight: 600; }
        .pi-progress .pi-p.now i { border-color: var(--epic-blue, #cb6b4f); background: var(--epic-blue, #cb6b4f); color: #fff; }
        .pi-progress .pi-line { flex: 0 1 60px; height: 2px; background: #e4e7ec; }

        .pi-step h6 { font-size: 15px; margin-bottom: 4px; }
        .pi-formats { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
        .pi-format { flex: 1 1 240px; border: 1px solid #eaecf0; border-radius: 12px; padding: 14px; background: #fcfcfd; }
        .pi-format h6 { font-size: 14px; margin-bottom: 4px; }
        .pi-format p { font-size: 12px; color: #667085; margin-bottom: 10px; min-height: 36px; }
        .pi-drop { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 6px;
            border: 2px dashed #d0d5dd; border-radius: 12px; padding: 34px 16px; cursor: pointer; background: #fcfcfd; transition: .15s; margin: 0; }
        .pi-drop:hover, .pi-drop.over { border-color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 5%, #fff); }
        .pi-drop .fa { font-size: 26px; color: var(--epic-blue, #cb6b4f); }
        .pi-drop strong { font-size: 14px; color: #101828; }
        .pi-drop small { color: #667085; }
        .pi-drop.busy { pointer-events: none; opacity: .75; }
        .pi-drop input { display: none; }
        .pi-rules li { margin-bottom: 6px; }

        .pi-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        html body .pi-actions .btn { height: 38px; padding: 0 16px !important; display: inline-flex; align-items: center;
            border-radius: 8px !important; font-size: 14px; text-transform: none !important; white-space: nowrap; }
        html body .pi-format .btn { height: 34px; padding: 0 12px !important; display: inline-flex; align-items: center; border-radius: 8px !important;
            font-size: 13px; text-transform: none !important; }
        .pi-tiles { display: flex; flex-wrap: wrap; gap: 10px; }
        .pi-tile { flex: 1 1 150px; border-radius: 10px; padding: 10px 14px; background: #f9fafb; border: 1px solid #eaecf0; }
        .pi-tile b { display: block; font-size: 20px; font-weight: 600; line-height: 1.2; }
        .pi-tile span { font-size: 12px; color: #667085; }
        .pi-tile.good { background: #ecfdf3; border-color: #abefc6; } .pi-tile.good b { color: #067647; }
        .pi-tile.bad { background: #fef3f2; border-color: #fecdca; } .pi-tile.bad b { color: #b42318; }
        .pi-tile.money b { color: #101828; }
        .pi-box { border-radius: 10px; padding: 10px 14px; max-height: 200px; overflow: auto; }
        .pi-box.red { border: 1px solid #fecdca; background: #fffbfa; }
        .pi-box.amber { border: 1px solid #fedf89; background: #fffcf5; }
        .pi-badge { display: inline-block; border-radius: 12px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
        .pi-badge.fmt { background: #eef4ff; color: #3538cd; }
        .pi-badge.on { background: #ecfdf3; color: #067647; }
        .pi-badge.off { background: #fffaeb; color: #b54708; }

        .pi-table-wrap { overflow: auto; max-height: 64vh; }
        .pi-table { border-collapse: separate; border-spacing: 0; }
        .pi-table th, .pi-table td { white-space: nowrap; vertical-align: middle; }
        html body .pi-table td, html body .pi-table th { padding: 8px 10px !important; }
        .pi-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f1f3; }
        .pi-table tfoot td { position: sticky; bottom: 0; z-index: 2; background: #fff7ed; font-weight: 700; border-top: 2px solid #f79009 !important; }
        .pi-table .pi-name { position: sticky; left: 0; background: #fff; z-index: 1; }
        .pi-table thead .pi-name, .pi-table tfoot .pi-name { z-index: 3; }
        .pi-table thead .pi-name { background: #f1f1f3; } .pi-table tfoot .pi-name { background: #fff7ed; }
        .pi-table .pi-net { font-weight: 700; color: #067647; }
        .pi-table .pi-ded { color: #b42318; }
    </style>
@endpush

@section('content')
    @php
        $inr = fn ($v) => '₹' . number_format((float) $v, 2);
        $plural = fn ($n, $word) => $n . ' ' . $word . ($n === 1 ? '' : 's');
    @endphp
    <div class="content-wrapper" data-swap-links>

        {{-- the period, and the one condition --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between" style="gap: 6px 12px">
                    <h6 class="f-16 mb-0">Payroll for {{ \Carbon\Carbon::create($cycle->year, $cycle->month, 1)->format('F Y') }}
                        <span class="f-13 text-lightest font-weight-normal ml-1">pay period {{ $cycle->start->format('d M') }} – {{ $cycle->end->format('d M Y') }}</span></h6>
                    <span class="f-12 text-lightest">{{ $fyLabel }}: the pay periods that are over</span>
                </div>

                {{-- the financial year's pay periods (each 21st to 20th), oldest first; a dot = payslips already issued --}}
                <div class="pi-months" role="tablist">
                    @foreach ($periods as $p)
                        <a href="{{ route('payroll-import.index', ['period' => $p['key']]) }}" role="tab"
                           class="pi-month {{ $p['key'] === $periodKey ? 'active' : '' }}"
                           title="{{ $p['label'] }}{{ $p['issued'] ? ' · ' . $p['issued'] . ' payslip(s) already issued' : '' }}">
                            <b>{{ $p['short'] }}<span class="pi-month-year">{{ $p['year'] }}</span></b>
                            <small>{{ $p['dates'] }}</small>
                            @if ($p['issued'])<span class="pi-issued" title="{{ $p['issued'] }} payslip(s) already issued">{{ $p['issued'] }}</span>@endif
                        </a>
                    @endforeach
                </div>

                <div class="pi-stats">
                    <div class="pi-stat"><b>{{ $employeeCount }}</b><span>employees with a salary</span></div>
                    <div class="pi-stat"><b class="{{ $issuedCount ? 'text-primary' : 'text-lightest' }}">{{ $issuedCount }}</b><span>payslips already issued (left out)</span></div>
                    <div class="pi-stat"><b class="text-success">{{ max(0, $employeeCount - $issuedCount) }}</b><span>still to be paid</span></div>
                </div>

                <div class="pi-cond {{ $useAttendance ? 'on' : 'off' }}">
                    <i class="fa {{ $useAttendance ? 'fa-fingerprint' : 'fa-calendar-check' }}"></i>
                    <div>
                        @if ($useAttendance)
                            <strong>Attendance deductions are on.</strong> Days paid come from ERP attendance (biometric and uploaded):
                            loss of pay is deducted, and somebody with no attendance at all is not paid.
                        @else
                            <strong>Attendance deductions are off.</strong> The full month is paid; no loss of pay is deducted.
                            (PF, ESI, PT and TDS still apply.)
                        @endif
                        <a href="{{ route('payslip-settings.index') }}" class="ml-1 no-swap" style="text-decoration: underline">Change in Payroll Settings</a>
                    </div>
                </div>
            </div>
        </div>

        @if ($otherPreview)
            <div class="alert alert-warning f-13">
                A sheet for {{ $otherPreview['label'] ?? 'another period' }} ({{ $otherPreview['file'] ?? '' }}) is waiting to be confirmed.
                <a href="{{ route('payroll-import.index', ['period' => $otherPreview['period']]) }}">Open it</a>, or upload here to replace it.
            </div>
        @endif

        <div class="pi-progress">
            <span class="pi-p {{ $preview ? 'done' : 'now' }}"><i>{{ $preview ? '✓' : '1' }}</i> Download &amp; fill in</span>
            <span class="pi-line"></span>
            <span class="pi-p {{ $preview ? 'done' : 'now' }}"><i>{{ $preview ? '✓' : '2' }}</i> Upload</span>
            <span class="pi-line"></span>
            <span class="pi-p {{ $preview ? 'now' : '' }}"><i>3</i> Review &amp; generate</span>
        </div>

        @if (!$preview)
            <div class="row">
                <div class="col-lg-7 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100 pi-step">
                        <div class="card-body p-4">
                            <h6><span class="text-lightest mr-1">1.</span> Download a sheet and fill it in</h6>
                            <p class="f-13 text-lightest mb-0">Everyone with a salary and no payslip for the period yet. Pick the format you work in.</p>
                            <div class="pi-formats">
                                <div class="pi-format">
                                    <h6><i class="fa fa-magic text-primary mr-1"></i> Simple</h6>
                                    <p>Code, CTC, TDS, other earnings and deductions. The payslip is worked out for you, as Payroll Run.</p>
                                    <div class="pi-actions">
                                        <a href="{{ route('payroll-import.download', ['period' => $periodKey, 'format' => 'simple']) }}" class="btn btn-primary" download>
                                            <i class="fa fa-download mr-2"></i> Sheet
                                        </a>
                                        <a href="{{ route('payroll-import.sample-download', ['period' => $periodKey, 'format' => 'simple']) }}" class="btn btn-secondary" download>
                                            <i class="fa fa-file-excel mr-2"></i> Sample
                                        </a>
                                    </div>
                                </div>
                                <div class="pi-format">
                                    <h6><i class="fa fa-table text-primary mr-1"></i> Detailed (totals included)</h6>
                                    <p>Every figure of the payslip: Basic, allowances, PF, ESI, PT, TDS, loss of pay, Net. Used as you give it.</p>
                                    <div class="pi-actions">
                                        <a href="{{ route('payroll-import.download', ['period' => $periodKey, 'format' => 'detailed']) }}" class="btn btn-primary" download>
                                            <i class="fa fa-download mr-2"></i> Sheet
                                        </a>
                                        <a href="{{ route('payroll-import.sample-download', ['period' => $periodKey, 'format' => 'detailed']) }}" class="btn btn-secondary" download>
                                            <i class="fa fa-file-excel mr-2"></i> Sample
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100 pi-step">
                        <div class="card-body p-4">
                            <h6><span class="text-lightest mr-1">2.</span> Upload it</h6>
                            <p class="f-13 text-lightest">Either format; it is recognised by its headings. Nothing is generated yet: every payslip and the totals are shown first.</p>
                            <form id="pi-upload" enctype="multipart/form-data">
                                <input type="hidden" name="period" value="{{ $periodKey }}">
                                <label class="pi-drop" id="pi-drop">
                                    <input type="file" name="sheet" accept=".xlsx,.xls,.csv">
                                    <i class="fa fa-cloud-upload-alt"></i>
                                    <strong id="pi-drop-title">Drop the Excel file here, or click to choose</strong>
                                    <small id="pi-drop-note">.xlsx, .xls or .csv, up to 10 MB. It is checked as soon as it is chosen.</small>
                                </label>
                                <div class="text-red f-13 mt-2" id="pi-upload-error"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-lg-6 mb-3 mb-lg-0">
                            <h6 class="f-14 mb-2">What is checked</h6>
                            <ul class="f-13 text-dark-grey pi-rules pl-3 mb-0">
                                <li>Every <strong>Employee Code</strong> must be an employee on the books in the period. Name is optional.</li>
                                <li>Anyone who <strong>already has a payslip</strong> for the period is left out (revoke it first to issue again).</li>
                                <li>Somebody settled before the period, or the Super Admin login, is never paid.</li>
                                <li>Amounts must be numbers (commas and ₹ are fine), never negative.</li>
                                <li>Detailed sheets must add up: earnings = Gross; Gross − deductions = Net Pay (within a rupee).</li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="f-14 mb-2">How it is paid</h6>
                            <ul class="f-13 text-dark-grey pi-rules pl-3 mb-0">
                                <li><strong>Simple:</strong> the salary formula of Payroll Run; blank CTC / TDS = on record; pay elements already entered for the period are included.</li>
                                <li><strong>Detailed:</strong> your figures are the payslip.</li>
                                <li><strong>Several earnings / deductions:</strong> best on the sheet's <strong>Earnings &amp; Deductions</strong> tab,
                                    one line per item (Employee Code, Type, Item, Amount), any number per employee. Or as columns on the Payroll tab,
                                    headed "Earning: …" / "Deduction: …" (e.g. "Earning: Bonus"). Each is its own line on the payslip.</li>
                                <li><strong>Attendance {{ $useAttendance ? 'on' : 'off' }}:</strong>
                                    {{ $useAttendance ? 'loss of pay from ERP attendance.' : 'the full month; a detailed line with an Attendance Deduction is refused.' }}</li>
                                <li>The payslips are numbered and stored exactly as from Payroll Run, and appear under Payslips Issued.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- ----------------------------------------------- step 3: review --}}
            @php
                $t = $preview['totals'];
                $n = (int) $t['payslips'];
                $entries = $preview['entries'];
            @endphp

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px">
                        <div>
                            <h6 class="f-15 mb-1"><i class="fa fa-file-excel text-success mr-1"></i> {{ $preview['file'] ?? 'Uploaded sheet' }}
                                <span class="pi-badge fmt ml-1">{{ $preview['format'] === 'detailed' ? 'Detailed format' : 'Simple format' }}</span>
                                <span class="pi-badge {{ $preview['attendance'] ? 'on' : 'off' }} ml-1">{{ $preview['attendance'] ? 'Attendance deductions on' : 'No attendance deductions' }}</span>
                            </h6>
                            <p class="f-13 text-lightest mb-0">
                                @if ($n)
                                    Nothing is generated yet. Check every payslip and the totals below, then confirm.
                                @else
                                    No payslip can be generated from this sheet: see the lines below.
                                @endif
                            </p>
                        </div>
                        <div class="pi-actions">
                            {{-- Discard: forget this sheet (back to step 1). Upload another sheet: check a new one in its place. --}}
                            <button type="button" class="btn btn-secondary" id="pi-discard" title="Forget this sheet; nothing is generated">
                                <i class="fa fa-times mr-2"></i>Discard
                            </button>
                            <label class="btn btn-secondary mb-0" id="pi-reupload-btn" title="Choose another file: it is checked in place of this one">
                                <i class="fa fa-upload mr-2"></i><span>Upload another sheet</span>
                                <input type="file" id="pi-reupload" accept=".xlsx,.xls,.csv" hidden>
                            </label>
                            @if ($n)
                                <button type="button" class="btn btn-primary" id="pi-apply">
                                    <i class="fa fa-check mr-2"></i>Confirm and generate {{ $plural($n, 'payslip') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="pi-tiles mt-3">
                        <div class="pi-tile {{ $n ? 'good' : '' }}"><b>{{ $n }}</b><span>payslips to generate</span></div>
                        <div class="pi-tile money"><b>{{ $inr($t['gross'] + $t['earnings']) }}</b><span>total earnings</span></div>
                        <div class="pi-tile money"><b class="text-red">{{ $inr($t['deductions']) }}</b><span>total deductions</span></div>
                        <div class="pi-tile good"><b>{{ $inr($t['net']) }}</b><span>total net pay</span></div>
                        <div class="pi-tile {{ $preview['problems'] ? 'bad' : '' }}"><b>{{ count($preview['problems']) }}</b><span>lines left out</span></div>
                    </div>

                    @if (!empty($preview['extra_columns']) || !empty($preview['items_read']))
                        <p class="f-13 mt-3 mb-0"><span class="text-lightest mr-1">Extra earnings and deductions read:</span>
                            @foreach ($preview['extra_columns'] ?? [] as $col)<span class="pi-chip">{{ $col }} column</span>@endforeach
                            @if (!empty($preview['items_read']))
                                <span class="pi-chip"><i class="fa fa-list mr-1"></i>{{ $plural((int) $preview['items_read'], 'line') }} from the Earnings &amp; Deductions tab</span>
                            @endif
                        </p>
                    @endif

                    @if (!empty($preview['ignored_columns']))
                        <div class="pi-box amber mt-3">
                            <p class="f-13 font-weight-bold mb-1" style="color: #b54708"><i class="fa fa-question-circle mr-1"></i>
                                Columns not understood, so not used:
                                @foreach ($preview['ignored_columns'] as $col)<span class="pi-chip">{{ $col }}</span>@endforeach
                            </p>
                            <p class="f-12 text-dark-grey mb-0">If one of them is money to pay or recover, head it with a pay element's name,
                                or "Earning: …" / "Deduction: …", and upload the sheet again.</p>
                        </div>
                    @endif

                    @if ($preview['problems'])
                        <div class="pi-box red mt-3">
                            <p class="f-13 font-weight-bold text-red mb-1"><i class="fa fa-exclamation-circle mr-1"></i>
                                {{ $plural(count($preview['problems']), 'line') }} left out. The rest can still be generated; fix these in the sheet and upload them again.</p>
                            @foreach (array_slice($preview['problems'], 0, 200) as $p)
                                <p class="f-12 text-dark-grey mb-0">{{ is_numeric($p['line']) ? 'Payroll tab, line ' . $p['line'] : ucfirst($p['line']) }}@if ($p['code']) · {{ $p['code'] }}@endif: {{ $p['message'] }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if ($preview['warnings'])
                        <div class="pi-box amber mt-3">
                            <p class="f-13 font-weight-bold mb-1" style="color: #b54708"><i class="fa fa-info-circle mr-1"></i> To note (these are still generated)</p>
                            @foreach (array_slice($preview['warnings'], 0, 200) as $w)
                                <p class="f-12 text-dark-grey mb-0">Line {{ $w['line'] }} · {{ $w['code'] }}: {{ $w['message'] }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($entries)
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3 d-flex flex-wrap align-items-center" style="gap: 10px">
                        <h6 class="mb-0 f-14 mr-auto">The payslips: {{ $plural($n, 'employee') }}, {{ $preview['label'] }}</h6>
                        <span class="f-12 text-lightest">PF / ESI include the employer's share, taken off as on the company's payslips (CTC includes it).</span>
                    </div>
                    <div class="pi-table-wrap">
                        <table class="table table-bordered table-sm mb-0 f-13 pi-table">
                            <thead>
                                <tr>
                                    <th class="pi-name">@lang('app.employee')</th>
                                    <th class="text-right" title="Days paid of the days in the period">Days</th>
                                    <th class="text-right">Gross</th>
                                    <th class="text-right">Other earnings</th>
                                    <th class="text-right">PF / ESI / PT</th>
                                    <th class="text-right">TDS</th>
                                    <th class="text-right">Loss of pay</th>
                                    <th class="text-right">Other deductions</th>
                                    <th class="text-right">Net pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $e)
                                    @php $s = $e['show']; @endphp
                                    <tr>
                                        <td class="pi-name">{{ $e['name'] }}<div class="f-11 text-lightest">{{ $e['code'] }}{{ $e['designation'] ? ' · ' . $e['designation'] : '' }}</div></td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format($s['paid'], 2), '0'), '.') }} / {{ $s['working'] }}</td>
                                        <td class="text-right">{{ $inr($s['gross']) }}</td>
                                        <td class="text-right">{{ $s['other_earnings'] ? $inr($s['other_earnings']) : '—' }}
                                            @if (!empty($e['pending']) || !empty($e['lines']))
                                                <div class="f-10 text-lightest">{{ collect(array_merge($e['pending'], $e['lines']))->where('kind', 'earning')->pluck('label')->implode(', ') }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right pi-ded">{{ $inr($s['statutory']) }}</td>
                                        <td class="text-right pi-ded">{{ $s['tds'] ? $inr($s['tds']) : '—' }}</td>
                                        <td class="text-right pi-ded">{{ $s['lop'] ? $inr($s['lop']) : '—' }}</td>
                                        <td class="text-right pi-ded">{{ $s['other_deductions'] ? $inr($s['other_deductions']) : '—' }}
                                            @if (!empty($e['pending']) || !empty($e['lines']))
                                                <div class="f-10 text-lightest">{{ collect(array_merge($e['pending'], $e['lines']))->where('kind', 'deduction')->pluck('label')->implode(', ') }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right pi-net">{{ $inr($s['net']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="pi-name">Total ({{ $n }})</td>
                                    <td></td>
                                    <td class="text-right">{{ $inr($t['gross']) }}</td>
                                    <td class="text-right">{{ $inr($t['earnings']) }}</td>
                                    <td class="text-right">{{ $inr($t['statutory']) }}</td>
                                    <td class="text-right">{{ $inr($t['tds']) }}</td>
                                    <td class="text-right">{{ $inr($t['lop']) }}</td>
                                    <td class="text-right">{{ $inr($t['other_deductions']) }}</td>
                                    <td class="text-right">{{ $inr($t['net']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        @if ($history->isNotEmpty())
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-header bg-white border-bottom p-3 d-flex align-items-center">
                    <h6 class="mb-0 f-14 mr-auto">Recent imports</h6>
                    <a href="{{ route('payslips.generated') }}" class="f-13">Payslips Issued <i class="fa fa-arrow-right f-11"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 f-13">
                        <thead><tr><th>When</th><th>Period</th><th>File</th><th>Format</th><th>Attendance</th><th>By</th><th class="text-right">Payslips</th><th class="text-right">Net pay</th></tr></thead>
                        <tbody>
                            @foreach ($history as $h)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($h->created_at)->timezone(company()->timezone ?? 'Asia/Kolkata')->format('d M Y h:i A') }}</td>
                                    <td>{{ $h->period_label }}</td>
                                    <td class="text-lightest">{{ $h->file_name ?: '—' }}</td>
                                    <td>{{ ucfirst((string) $h->format) }}</td>
                                    <td>{{ $h->attendance ? 'deducted' : 'full month' }}</td>
                                    <td>{{ $h->by_name ?: '—' }}</td>
                                    <td class="text-right">{{ $h->payslips }}</td>
                                    <td class="text-right">{{ $inr($h->total_net) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <script>
        $(function () {
            var token = '{{ csrf_token() }}';
            var fail = function (xhr) {
                var r = xhr.responseJSON || {};
                var message = r.message || 'That did not work';
                if (r.errors) { message = Object.values(r.errors)[0][0]; }
                return message;
            };


            var drop = document.getElementById('pi-drop');
            var form = document.getElementById('pi-upload');
            var reset = function (error) {
                drop.classList.remove('busy');
                $('#pi-drop-title').text('Drop the Excel file here, or click to choose');
                $('#pi-drop-note').text('.xlsx, .xls or .csv, up to 10 MB. It is checked as soon as it is chosen.');
                $('#pi-upload-error').text(error || '');
                form.reset();
            };
            var send = function (file) {
                if (!file) { return; }
                var data = new FormData(form);
                data.set('sheet', file);
                data.append('_token', token);
                $('#pi-upload-error').text('');
                drop.classList.add('busy');
                $('#pi-drop-title').text(file.name);
                $('#pi-drop-note').html('<i class="fa fa-spinner fa-spin mr-1"></i> Checking every line and working out the payslips…');

                $.ajax({ url: '{{ route('payroll-import.preview') }}', type: 'POST', data: data, processData: false, contentType: false })
                    .done(function (r) {
                        if (r.status === 'success') { PageSwap.reload(); return; }
                        reset(r.message || 'That sheet could not be read.');
                    })
                    .fail(function (xhr) { reset(fail(xhr)); });
            };

            if (drop && form) {
                form.querySelector('input[type="file"]').addEventListener('change', function () { send(this.files[0]); });
                ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
                ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
                drop.addEventListener('drop', function (e) { send(e.dataTransfer.files[0]); });
            }

            $('#pi-discard').on('click', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Discard this sheet?',
                    text: 'No payslip has been generated from it, and none will be. You can upload it again later.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Discard',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }
                    button.prop('disabled', true);
                    $.post('{{ route('payroll-import.discard') }}', { _token: token }).always(function () { PageSwap.reload(); });
                });
            });

            // review step: a new file checked in place of this one, without going back
            $('#pi-reupload').on('change', function () {
                var file = this.files[0];
                if (!file) { return; }
                var data = new FormData();
                data.append('_token', token);
                data.append('period', '{{ $periodKey }}');
                data.append('sheet', file);
                $('#pi-reupload-btn').addClass('disabled').find('span').text('Checking ' + file.name + '…');

                $.ajax({ url: '{{ route('payroll-import.preview') }}', type: 'POST', data: data, processData: false, contentType: false })
                    .done(function (r) {
                        if (r.status === 'success') { PageSwap.reload(); return; }
                        Swal.fire({ icon: 'error', title: r.message || 'That sheet could not be read.' });
                    })
                    .fail(function (xhr) { Swal.fire({ icon: 'error', title: fail(xhr) }); })
                    .always(function () { $('#pi-reupload-btn').removeClass('disabled').find('span').text('Upload another sheet'); $('#pi-reupload').val(''); });
            });

            $('#pi-apply').on('click', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Generate these payslips?',
                    html: '{{ $preview ? $plural((int) $preview['totals']['payslips'], 'payslip') . ', net pay ' . $inr($preview['totals']['net']) . '.' : '' }}<br>They are numbered and issued as from Payroll Run.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm and generate',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }
                    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i>Generating…');

                    $.post('{{ route('payroll-import.apply') }}', { _token: token })
                        .done(function (r) {
                            Swal.fire({ icon: r.status === 'success' ? 'success' : 'error', title: r.message })
                                .then(function () { PageSwap.reload(); });
                        })
                        .fail(function (xhr) {
                            button.prop('disabled', false).html('<i class="fa fa-check mr-2"></i>Confirm and generate');
                            Swal.fire({ icon: 'error', title: fail(xhr) });
                        });
                });
            });
        });
    </script>
@endsection
