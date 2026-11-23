@extends('layouts.app')

@push('styles')
    <style>
        /* header: the period and where it stands */
        .ai-periods { display: flex; flex-wrap: wrap; gap: 8px; }
        .ai-periods a { border: 1px solid #e4e7ec; border-radius: 20px; padding: 6px 14px; font-size: 13px; color: #344054; background: #fff; }
        .ai-periods a.active { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; }
        .ai-periods a small { opacity: .75; margin-left: 4px; }
        .ai-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .ai-stat { flex: 1 1 160px; border: 1px solid #eaecf0; border-radius: 10px; padding: 10px 14px; background: #fcfcfd; }
        .ai-stat b { display: block; font-size: 20px; font-weight: 600; line-height: 1.2; }
        .ai-stat span { font-size: 12px; color: #667085; }

        /* where you are: download -> upload -> review */
        .ai-progress { display: flex; align-items: center; gap: 8px; margin: 0 0 16px; flex-wrap: wrap; }
        .ai-progress .ai-p { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #98a2b3; }
        .ai-progress .ai-p i { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #d0d5dd; display: inline-flex; align-items: center;
            justify-content: center; font-style: normal; font-size: 12px; font-weight: 700; background: #fff; }
        .ai-progress .ai-p.done { color: #067647; } .ai-progress .ai-p.done i { border-color: #12b76a; background: #12b76a; color: #fff; }
        .ai-progress .ai-p.now { color: #101828; font-weight: 600; }
        .ai-progress .ai-p.now i { border-color: var(--epic-blue, #cb6b4f); background: var(--epic-blue, #cb6b4f); color: #fff; }
        .ai-progress .ai-line { flex: 0 1 60px; height: 2px; background: #e4e7ec; }

        /* step 1 and 2 */
        .ai-step-card h6 { font-size: 15px; margin-bottom: 4px; }
        .ai-marks { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0 16px; }
        .ai-mark { border: 1px solid #eaecf0; border-radius: 8px; padding: 6px 10px; font-size: 12px; color: #475467; background: #fcfcfd; }
        .ai-drop { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 6px;
            border: 2px dashed #d0d5dd; border-radius: 12px; padding: 26px 16px; cursor: pointer; background: #fcfcfd; transition: .15s; margin: 0; }
        .ai-drop:hover, .ai-drop.over { border-color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 5%, #fff); }
        .ai-drop .fa { font-size: 26px; color: var(--epic-blue, #cb6b4f); }
        .ai-drop strong { font-size: 14px; color: #101828; }
        .ai-drop small { color: #667085; }
        .ai-drop.busy { pointer-events: none; opacity: .75; }
        .ai-drop input { display: none; }

        /* how to fill */
        .ai-how td, .ai-how th { padding: 7px 10px !important; }
        .ai-rules li { margin-bottom: 6px; }

        /* review */
        .ai-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        html body .ai-actions .btn, html body .ai-btn { height: 38px; padding: 0 16px !important; display: inline-flex; align-items: center;
            border-radius: 8px !important; font-size: 14px; text-transform: none !important; white-space: nowrap; }
        .ai-tiles { display: flex; flex-wrap: wrap; gap: 10px; }
        .ai-tile { flex: 1 1 130px; border-radius: 10px; padding: 10px 14px; background: #f9fafb; border: 1px solid #eaecf0; }
        .ai-tile b { display: block; font-size: 20px; font-weight: 600; line-height: 1.2; }
        .ai-tile span { font-size: 12px; color: #667085; }
        .ai-tile.good { background: #ecfdf3; border-color: #abefc6; } .ai-tile.good b { color: #067647; }
        .ai-tile.bad { background: #fef3f2; border-color: #fecdca; } .ai-tile.bad b { color: #b42318; }
        .ai-tile.quiet b { color: #98a2b3; }
        .ai-problems { border: 1px solid #fecdca; background: #fffbfa; border-radius: 10px; padding: 10px 14px; max-height: 200px; overflow: auto; }
        .ai-from { color: #8a94a6; text-decoration: line-through; }
        .ai-to { color: #1d9e6f; font-weight: 600; }
        .ai-table th, .ai-table td { white-space: nowrap; }
        .ai-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .ai-details > summary { cursor: pointer; list-style: revert; }
        .ai-history-row { cursor: pointer; }

        /* the review register */
        .ai-grid-wrap { overflow: auto; max-height: 62vh; }
        .ai-grid { border-collapse: separate; border-spacing: 0; }
        .ai-grid thead th { position: sticky; top: 0; z-index: 2; background: #f1f1f3; }
        .ai-grid .ai-name { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 170px; }
        .ai-grid thead .ai-name { z-index: 3; background: #f1f1f3; }
        .ai-grid td, .ai-grid th { vertical-align: middle; }
        html body .ai-grid td, html body .ai-grid th { padding: 4px 3px !important; height: auto !important; }
        html body .ai-grid .ai-name { padding: 4px 10px !important; }
        .ai-grid .ai-month { border-left: 2px solid #99a5b5 !important; }
        .ai-c { display: inline-block; min-width: 22px; padding: 2px 4px; border-radius: 4px; font-size: 10px; font-weight: 700; line-height: 14px; text-align: center; }
        .ai-c-p { color: #067647; } .ai-c-hd { color: #b54708; } .ai-c-a { color: #d92d20; } .ai-c-o { color: #b54708; }
        .ai-c-w, .ai-c-h, .ai-c-l { color: #98a2b3; font-weight: 600; } .ai-c-none { color: #d0d5dd; }
        .ai-c.ai-changed { color: #fff; }
        .ai-c-p.ai-changed { background: #12b76a; } .ai-c-hd.ai-changed { background: #f79009; } .ai-c-a.ai-changed { background: #f04438; }
        .ai-legend { display: flex; flex-wrap: wrap; gap: 4px 12px; }
        .ai-legend .ai-c { margin-right: 3px; }
    </style>
@endpush

@section('content')
    @php
        $gs = $shift ?? ['start' => '09:30', 'end' => '18:30', 'half' => '14:00', 'name' => 'General Shift'];
        $allLocked = $employeeCount > 0 && $lockedCount >= $employeeCount;
    @endphp
    <div class="content-wrapper" data-swap-links>

        {{-- the period, and where it stands --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap align-items-start justify-content-between" style="gap: 12px">
                    <div>
                        <h6 class="f-16 mb-1">Attendance for {{ $cycle->describe() }}</h6>
                        <p class="f-13 text-lightest mb-0">
                            Pay period {{ $cycle->start->format('d M Y') }} to {{ $cycle->end->format('d M Y') }}
                            @if ($lastDay < $cycle->end->toDateString())
                                · running: days up to {{ \Carbon\Carbon::parse($lastDay)->format('d M') }} can be uploaded
                            @endif
                        </p>
                    </div>
                    <div class="ai-periods">
                        @foreach ($periods as $p)
                            <a href="{{ route('attendance-import.index', ['period' => $p['key']]) }}" class="{{ $p['key'] === $periodKey ? 'active' : '' }}">
                                {{ $p['label'] }}@if ($p['running'])<small>(running)</small>@endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="ai-stats">
                    <div class="ai-stat"><b>{{ $employeeCount }}</b><span>employees in the period</span></div>
                    <div class="ai-stat"><b class="{{ max(0, $employeeCount - $lockedCount) ? 'text-success' : 'text-lightest' }}">{{ max(0, $employeeCount - $lockedCount) }}</b><span>open for upload</span></div>
                    <div class="ai-stat"><b class="{{ $lockedCount ? 'text-red' : 'text-lightest' }}">{{ $lockedCount }}</b><span>payroll generated: days locked</span></div>
                </div>

                @if ($allLocked)
                    <div class="alert alert-secondary f-13 mt-3 mb-0">
                        <i class="fa fa-lock mr-1"></i> Payroll for {{ $cycle->describe() }} is generated for everyone, so its attendance can no longer be changed.
                    </div>
                @endif
            </div>
        </div>

        @if ($otherPreview)
            <div class="alert alert-warning f-13">
                A sheet for {{ $otherPreview['label'] ?? 'another period' }} ({{ $otherPreview['file'] ?? '' }}) is waiting to be saved.
                <a href="{{ route('attendance-import.index', ['period' => $otherPreview['period']]) }}">Open it</a>, or upload here to replace it.
            </div>
        @endif

        {{-- where you are --}}
        <div class="ai-progress">
            <span class="ai-p {{ $preview ? 'done' : 'now' }}"><i>{{ $preview ? '✓' : '1' }}</i> Download &amp; fill in</span>
            <span class="ai-line"></span>
            <span class="ai-p {{ $preview ? 'done' : 'now' }}"><i>{{ $preview ? '✓' : '2' }}</i> Upload</span>
            <span class="ai-line"></span>
            <span class="ai-p {{ $preview ? 'now' : '' }}"><i>3</i> Review &amp; save</span>
        </div>

        @if (!$preview)
            {{-- ---------------------------------------------- steps 1 and 2 --}}
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100 ai-step-card">
                        <div class="card-body p-4">
                            <h6><span class="text-lightest mr-1">1.</span> Download the sheet and fill it in</h6>
                            <p class="f-13 text-lightest mb-0">
                                Every employee and every day of the period so far, with what is on record already filled in.
                                Put a mark against the days you want to set; times are optional.
                            </p>
                            <div class="ai-marks">
                                <span class="ai-mark"><b class="ai-c ai-c-p ai-changed mr-1">P</b> present · {{ $gs['start'] }}–{{ $gs['end'] }}</span>
                                <span class="ai-mark"><b class="ai-c ai-c-hd ai-changed mr-1">HD1</b> half day, morning · {{ $gs['start'] }}–{{ $gs['half'] }}</span>
                                <span class="ai-mark"><b class="ai-c ai-c-hd ai-changed mr-1">HD2</b> half day, afternoon · {{ $gs['half'] }}–{{ $gs['end'] }}</span>
                                <span class="ai-mark"><b class="ai-c ai-c-a ai-changed mr-1">A</b> absent</span>
                            </div>
                            <div class="ai-actions">
                                <a href="{{ route('attendance-import.download', ['period' => $periodKey]) }}" class="btn btn-primary" download>
                                    <i class="fa fa-download mr-2"></i> Download the sheet
                                </a>
                                <a href="{{ route('attendance-import.sample-download', ['period' => $periodKey]) }}" class="btn btn-secondary" download>
                                    <i class="fa fa-file-excel mr-2"></i> Sample sheet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100 ai-step-card">
                        <div class="card-body p-4">
                            <h6><span class="text-lightest mr-1">2.</span> Upload it</h6>
                            <p class="f-13 text-lightest">Nothing is saved yet: the next screen shows the register as it will be, and you confirm.</p>
                            <form id="ai-upload" enctype="multipart/form-data">
                                <input type="hidden" name="period" value="{{ $periodKey }}">
                                <label class="ai-drop {{ $allLocked ? 'busy' : '' }}" id="ai-drop">
                                    <input type="file" name="sheet" accept=".xlsx,.xls,.csv" @disabled($allLocked)>
                                    <i class="fa fa-cloud-upload-alt"></i>
                                    <strong id="ai-drop-title">Drop the Excel file here, or click to choose</strong>
                                    <small id="ai-drop-note">.xlsx, .xls or .csv, up to 10 MB. It is checked as soon as it is chosen.</small>
                                </label>
                                <div class="text-red f-13 mt-2" id="ai-upload-error"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-lg-7 mb-3 mb-lg-0">
                            <h6 class="f-14 mb-2">How each day is read</h6>
                            <div class="table-responsive">
                                <table class="table table-sm f-13 mb-0 ai-how">
                                    <thead><tr><th style="width: 38%">In the sheet</th><th>What is saved</th></tr></thead>
                                    <tbody>
                                        <tr><td><b class="ai-c ai-c-p ai-changed mr-1">P</b> no times</td><td>{{ $gs['start'] }}–{{ $gs['end'] }} ({{ $gs['name'] }}, or the employee's own shift)</td></tr>
                                        <tr><td><b class="ai-c ai-c-hd ai-changed mr-1">HD1</b> no times</td><td>half day, <strong>morning</strong>: {{ $gs['start'] }}–{{ $gs['half'] }}</td></tr>
                                        <tr><td><b class="ai-c ai-c-hd ai-changed mr-1">HD2</b> no times</td><td>half day, <strong>afternoon</strong>: {{ $gs['half'] }}–{{ $gs['end'] }}</td></tr>
                                        <tr><td><b class="ai-c ai-c-a ai-changed mr-1">A</b></td><td>the day's attendance is taken off</td></tr>
                                        <tr><td>P, HD1 or HD2 with In / Out</td><td>the times you give</td></tr>
                                        <tr><td>only In / Out</td><td>those times</td></tr>
                                        <tr><td class="text-lightest">nothing</td><td class="text-lightest">the day is left as it is</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <h6 class="f-14 mb-2">Good to know</h6>
                            <ul class="f-13 text-dark-grey ai-rules pl-3 mb-0">
                                <li><strong>Employee Code</strong> and <strong>Date</strong> (DD-MM-YYYY) are needed. Name is optional.</li>
                                <li>Times as HH:MM, 24-hour. An Out earlier than In is the next morning (night shift).</li>
                                <li>Only this pay period and the running one, and only days that have happened.</li>
                                <li><strong>Once an employee's payroll for the period is generated, their days cannot be changed.</strong>
                                    Revoking the payslip opens them again.</li>
                                <li>Saved days show as <b style="color: #1d7044">E</b> (Excel upload) on the Attendance Register.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- ----------------------------------------------- step 3: review --}}
            @php
                $s = $preview['summary'];
                $writes = $s['new'] + $s['changed'] + ($s['absent'] ?? 0);
                $grid = $preview['grid'] ?? ['dates' => [], 'rows' => []];
                $gridRows = array_slice($grid['rows'], 0, 300);
                $cellClass = ['P' => 'ai-c-p', 'HD' => 'ai-c-hd', 'A' => 'ai-c-a', 'O' => 'ai-c-o', 'W' => 'ai-c-w', 'H' => 'ai-c-h', 'L' => 'ai-c-l', '' => 'ai-c-none'];
                $plural = fn ($n, $word) => $n . ' ' . $word . ($n === 1 ? '' : 's');
            @endphp

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px">
                        <div>
                            <h6 class="f-15 mb-1"><i class="fa fa-file-excel text-success mr-1"></i> {{ $preview['file'] ?? 'Uploaded sheet' }}</h6>
                            <p class="f-13 text-lightest mb-0">
                                @if ($writes > 0)
                                    Nothing is saved yet. Check the register below, then confirm.
                                @else
                                    Every day in this sheet is already as on record: there is nothing to save.
                                @endif
                            </p>
                        </div>
                        <div class="ai-actions">
                            {{-- Discard: forget this sheet (back to step 1). Upload another sheet: check a new one in its place. --}}
                            <button type="button" class="btn btn-secondary" id="ai-discard" title="Forget this sheet; nothing is saved">
                                <i class="fa fa-times mr-2"></i>Discard
                            </button>
                            <label class="btn btn-secondary mb-0" id="ai-reupload-btn" title="Choose another file: it is checked in place of this one">
                                <i class="fa fa-upload mr-2"></i><span>Upload another sheet</span>
                                <input type="file" id="ai-reupload" accept=".xlsx,.xls,.csv" hidden>
                            </label>
                            @if ($writes > 0)
                                <button type="button" class="btn btn-primary" id="ai-apply">
                                    <i class="fa fa-check mr-2"></i>Confirm and save {{ $plural($writes, 'day') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="ai-tiles mt-3">
                        <div class="ai-tile {{ $s['new'] ? 'good' : 'quiet' }}"><b>{{ $s['new'] }}</b><span>days added</span></div>
                        <div class="ai-tile {{ $s['changed'] ? 'good' : 'quiet' }}"><b>{{ $s['changed'] }}</b><span>days corrected</span></div>
                        <div class="ai-tile {{ ($s['absent'] ?? 0) ? 'bad' : 'quiet' }}"><b>{{ $s['absent'] ?? 0 }}</b><span>marked absent</span></div>
                        <div class="ai-tile quiet"><b>{{ $s['unchanged'] }}</b><span>already as on record</span></div>
                        <div class="ai-tile {{ $s['locked'] ? 'bad' : 'quiet' }}"><b>{{ $s['locked'] }}</b><span>locked (payroll generated)</span></div>
                        <div class="ai-tile {{ $s['problems'] ? 'bad' : 'quiet' }}"><b>{{ $s['problems'] }}</b><span>lines with a problem</span></div>
                    </div>

                    @if ($preview['problems'])
                        <div class="ai-problems mt-3">
                            <p class="f-13 font-weight-bold text-red mb-1"><i class="fa fa-exclamation-circle mr-1"></i>
                                {{ $plural(count($preview['problems']), 'line') }} left out. The rest can still be saved; fix these in the sheet and upload again.</p>
                            @foreach (array_slice($preview['problems'], 0, 200) as $p)
                                <p class="f-12 text-dark-grey mb-0">
                                    Line {{ $p['line'] }}@if ($p['code']) · {{ $p['code'] }}@endif @if ($p['date']) · {{ $p['date'] }}@endif: {{ $p['message'] }}
                                </p>
                            @endforeach
                            @if (count($preview['problems']) > 200)
                                <p class="f-12 text-lightest mb-0">and {{ count($preview['problems']) - 200 }} more.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- the upload as the Attendance Register will show it, for everyone it changes --}}
            @if ($gridRows)
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-header bg-white border-bottom p-3 d-flex flex-wrap align-items-center" style="gap: 10px">
                        <h6 class="mb-0 f-14 mr-auto">After saving: {{ $plural(count($grid['rows']), 'employee') }}, {{ $cycle->describe() }}</h6>
                        <div class="f-11 text-dark-grey ai-legend">
                            <span><b class="ai-c ai-c-p ai-changed">P</b><b class="ai-c ai-c-hd ai-changed">HD</b><b class="ai-c ai-c-a ai-changed">A</b> from this upload</span>
                            <span><b class="ai-c ai-c-p">P</b> Present</span>
                            <span><b class="ai-c ai-c-hd">HD</b> Half day</span>
                            <span><b class="ai-c ai-c-o">O</b> No clock-out</span>
                            <span><b class="ai-c ai-c-w">W</b> Week off</span>
                            <span><b class="ai-c ai-c-h">H</b> Holiday</span>
                            <span><b class="ai-c ai-c-l">L</b> Leave</span>
                        </div>
                    </div>
                    <div class="ai-grid-wrap">
                        <table class="table table-bordered table-sm mb-0 f-12 ai-grid">
                            <thead>
                                <tr>
                                    <th class="ai-name">@lang('app.employee')</th>
                                    @foreach ($grid['dates'] as $date)
                                        @php $d = \Carbon\Carbon::parse($date); @endphp
                                        <th class="text-center px-1 {{ $d->day === 1 ? 'ai-month' : '' }}">{{ $d->day }}<br><span class="f-10 text-lightest">{{ $d->format('D')[0] }}</span></th>
                                    @endforeach
                                    <th class="text-right" title="Present days">P</th>
                                    <th class="text-right" title="Half days">HD</th>
                                    <th class="text-right" title="Working days with no attendance">Absent</th>
                                    <th class="text-right" title="Days this upload changes">Changed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gridRows as $r)
                                    <tr>
                                        <td class="ai-name text-nowrap">{{ $r['name'] }}<div class="f-10 text-lightest">{{ $r['code'] }}</div></td>
                                        @foreach ($grid['dates'] as $date)
                                            @php [$code, $changed, $tip] = $r['days'][$date]; @endphp
                                            <td class="text-center px-1 {{ substr($date, 8, 2) === '01' ? 'ai-month' : '' }}"
                                                title="{{ \Carbon\Carbon::parse($date)->format('D d M') }}{{ $tip ? ': ' . $tip : '' }}">
                                                <b class="ai-c {{ $cellClass[$code] ?? '' }} {{ $changed ? 'ai-changed' : '' }}">{{ $code !== '' ? $code : '·' }}</b>
                                            </td>
                                        @endforeach
                                        <td class="text-right font-weight-bold text-success">{{ $r['totals']['P'] }}</td>
                                        <td class="text-right">{{ $r['totals']['HD'] ?: '' }}</td>
                                        <td class="text-right {{ $r['totals']['absent'] ? 'text-red' : 'text-lightest' }}">{{ $r['totals']['absent'] }}</td>
                                        <td class="text-right font-weight-bold">{{ $r['totals']['changed'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($grid['rows']) > 300)
                        <p class="f-12 text-lightest p-3 mb-0 border-top">Showing 300 of {{ count($grid['rows']) }} employees; all of them are saved.</p>
                    @endif
                </div>
            @endif

            @if (!$preview['changes'])
                <div class="card bg-white border-0 b-shadow-4 mb-3">
                    <div class="card-body p-4 text-center">
                        <i class="fa fa-check-circle text-success f-21 mb-2"></i>
                        <p class="f-14 mb-1 text-darkest-grey">This sheet matches the attendance on record, so there is nothing to save.</p>
                        <p class="f-13 text-lightest mb-3">
                            To make changes, put <strong>P</strong>, <strong>HD1</strong>, <strong>HD2</strong> or <strong>A</strong> in the Attendance column
                            (or In / Out times) against the days, then upload it again.
                        </p>
                        <div class="ai-actions justify-content-center">
                            <a href="{{ route('attendance-import.sample-download', ['period' => $periodKey]) }}" class="btn btn-secondary" download>
                                <i class="fa fa-file-excel mr-2"></i> Sample sheet
                            </a>
                            <a href="{{ route('attendance-import.download', ['period' => $periodKey]) }}" class="btn btn-secondary" download>
                                <i class="fa fa-download mr-2"></i> The period's sheet
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <details class="card bg-white border-0 b-shadow-4 mb-3 ai-details" @if (!$gridRows) open @endif>
                    <summary class="p-3 f-14 font-weight-bold">Line by line: {{ $plural(count($preview['changes']), 'day') }}</summary>
                    <div class="table-responsive" style="max-height: 60vh">
                        <table class="table table-hover mb-0 f-13 ai-table">
                            <thead>
                                <tr><th>Line</th><th>@lang('app.employee')</th><th>Date</th><th>Change</th><th class="text-right">Hours</th></tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($preview['changes'], 0, 500) as $c)
                                    <tr>
                                        <td class="text-lightest">{{ $c['line'] }}</td>
                                        <td>{{ $c['name'] }} <span class="f-11 text-lightest d-block">{{ $c['code'] }}</span></td>
                                        <td>{{ \Carbon\Carbon::parse($c['date'])->format('D d M') }}</td>
                                        <td>
                                            @if ($c['state'] === 'absent')
                                                <span class="ai-from">{{ $c['from'] }}</span>
                                                <i class="fa fa-arrow-right f-10 mx-1 text-lightest"></i>
                                                <b class="ai-c ai-c-a ai-changed">A</b> <span class="text-red f-12">attendance taken off</span>
                                            @else
                                                @if ($c['state'] === 'changed')
                                                    <span class="ai-from">{{ $c['from'] }}</span>
                                                    <i class="fa fa-arrow-right f-10 mx-1 text-lightest"></i>
                                                @else
                                                    <span class="badge badge-light mr-1">new</span>
                                                @endif
                                                <b class="ai-c {{ $c['half'] ? 'ai-c-hd' : 'ai-c-p' }} ai-changed px-1">{{ $c['mark'] }}</b>
                                                @if ($c['half'])<span class="f-11 text-lightest">{{ ($c['session'] ?? 'first') === 'second' ? 'afternoon' : 'morning' }}</span>@endif
                                                <span class="ai-to">{{ $c['in'] }}–{{ $c['out'] }}</span>
                                                @if ($c['overnight'])<span class="f-11 text-lightest">(next day)</span>@endif
                                                @if (!empty($c['filled']))<span class="f-11 text-lightest d-block">{{ $c['filled'] }}</span>@endif
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if (isset($c['minutes'])){{ intdiv($c['minutes'], 60) }}h {{ str_pad($c['minutes'] % 60, 2, '0', STR_PAD_LEFT) }}m @else — @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($preview['changes']) > 500)
                        <p class="f-12 text-lightest p-3 mb-0 border-top">Showing the first 500 of {{ count($preview['changes']) }} days; all of them are saved.</p>
                    @endif
                </details>
            @endif
        @endif

        {{-- who uploaded what --}}
        @if ($history->isNotEmpty())
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-header bg-white border-bottom p-3 d-flex align-items-center">
                    <h6 class="mb-0 f-14 mr-auto">Recent uploads</h6>
                    <span class="f-12 text-lightest">Click an upload to see what it saved</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 f-13 ai-history">
                        <thead><tr><th>When</th><th>Period</th><th>File</th><th>By</th><th class="text-right">Added</th><th class="text-right">Corrected / absent</th><th class="text-right">Left out</th></tr></thead>
                        <tbody>
                            @foreach ($history as $h)
                                <tr class="ai-history-row" data-open="{{ route('attendance-import.show', $h->id) }}" title="See what this upload saved">
                                    <td>{{ \Carbon\Carbon::parse($h->created_at)->timezone(company()->timezone ?? 'Asia/Kolkata')->format('d M Y h:i A') }}</td>
                                    <td>{{ $h->period_label }}</td>
                                    <td><a href="{{ route('attendance-import.show', $h->id) }}" class="openRightModal"><i class="fa fa-file-excel text-success mr-1"></i>{{ $h->file_name ?: 'upload' }}</a></td>
                                    <td>{{ $h->by_name ?: '—' }}</td>
                                    <td class="text-right">{{ $h->days_created }}</td>
                                    <td class="text-right">{{ $h->days_replaced }}</td>
                                    <td class="text-right">{{ $h->days_skipped }}</td>
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

            // step 2: a file chosen or dropped is checked at once
            var drop = document.getElementById('ai-drop');
            var form = document.getElementById('ai-upload');
            var send = function (file) {
                if (!file) { return; }
                var data = new FormData(form);
                data.set('sheet', file);
                data.append('_token', token);
                $('#ai-upload-error').text('');
                drop.classList.add('busy');
                $('#ai-drop-title').text(file.name);
                $('#ai-drop-note').html('<i class="fa fa-spinner fa-spin mr-1"></i> Reading the sheet…');

                $.ajax({ url: '{{ route('attendance-import.preview') }}', type: 'POST', data: data, processData: false, contentType: false })
                    .done(function (r) {
                        if (r.status === 'success') { PageSwap.reload(); return; }
                        reset(r.message || 'That sheet could not be read.');
                    })
                    .fail(function (xhr) { reset(fail(xhr)); });
            };
            var reset = function (error) {
                drop.classList.remove('busy');
                $('#ai-drop-title').text('Drop the Excel file here, or click to choose');
                $('#ai-drop-note').text('.xlsx, .xls or .csv, up to 10 MB. It is checked as soon as it is chosen.');
                $('#ai-upload-error').text(error || '');
                form.reset();
            };

            if (drop && form) {
                form.querySelector('input[type="file"]').addEventListener('change', function () { send(this.files[0]); });
                ['dragenter', 'dragover'].forEach(function (t) {
                    drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('over'); });
                });
                ['dragleave', 'drop'].forEach(function (t) {
                    drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('over'); });
                });
                drop.addEventListener('drop', function (e) { send(e.dataTransfer.files[0]); });
            }

            // a recent upload: clicking anywhere on its row opens it in the side panel (its file link)
            $('.ai-history-row').on('click', function (e) {
                if (e.target.closest('a')) { return; }   // the link itself opens the panel already
                e.preventDefault();
                $(this).find('a.openRightModal').first().trigger('click');
            });

            $('#ai-discard').on('click', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Discard this sheet?',
                    text: 'Nothing from it has been saved, and nothing will be. You can upload it again later.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Discard',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }
                    button.prop('disabled', true);
                    $.post('{{ route('attendance-import.discard') }}', { _token: token }).always(function () { PageSwap.reload(); });
                });
            });

            // review step: a new file checked in place of this one, without going back
            $('#ai-reupload').on('change', function () {
                var file = this.files[0];
                if (!file) { return; }
                var data = new FormData();
                data.append('_token', token);
                data.append('period', '{{ $periodKey }}');
                data.append('sheet', file);
                $('#ai-reupload-btn').addClass('disabled').find('span').text('Reading ' + file.name + '…');

                $.ajax({ url: '{{ route('attendance-import.preview') }}', type: 'POST', data: data, processData: false, contentType: false })
                    .done(function (r) {
                        if (r.status === 'success') { PageSwap.reload(); return; }
                        Swal.fire({ icon: 'error', title: r.message || 'That sheet could not be read.' });
                    })
                    .fail(function (xhr) { Swal.fire({ icon: 'error', title: fail(xhr) }); })
                    .always(function () { $('#ai-reupload-btn').removeClass('disabled').find('span').text('Upload another sheet'); $('#ai-reupload').val(''); });
            });

            $('#ai-apply').on('click', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Save this attendance?',
                    text: 'The days in colour are saved to attendance and show as Excel upload. Anything with payroll generated in the meantime is left out.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm and save',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }
                    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i>Saving…');

                    $.post('{{ route('attendance-import.apply') }}', { _token: token })
                        .done(function (r) {
                            Swal.fire({ icon: r.status === 'success' ? 'success' : 'error', title: r.message })
                                .then(function () { PageSwap.reload(); });
                        })
                        .fail(function (xhr) {
                            button.prop('disabled', false).html('<i class="fa fa-check mr-2"></i>Confirm and save');
                            Swal.fire({ icon: 'error', title: fail(xhr) });
                        });
                });
            });
        });
    </script>
@endsection
