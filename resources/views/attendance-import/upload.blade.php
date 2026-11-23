{{-- One attendance upload, as it was saved (AttendanceImportController::show): the register of the
     days it wrote (employees x the period) and the days one by one. Side panel or page. --}}
@php
    $i = $upload['import'];
    $grid = $upload['grid'];
    $days = $upload['days'];
    $when = \Carbon\Carbon::parse($i->created_at)->timezone(company()->timezone ?? 'Asia/Kolkata');
    $cls = ['P' => 'au-p', 'HD1' => 'au-hd', 'HD2' => 'au-hd', 'A' => 'au-a'];
@endphp
<div class="au-panel">
    <style>
        .au-panel .au-head { padding: 20px; border-bottom: 1px solid #eaecf0; }
        .au-panel .au-tiles { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .au-panel .au-tile { flex: 1 1 110px; border: 1px solid #eaecf0; border-radius: 10px; padding: 8px 12px; background: #f9fafb; }
        .au-panel .au-tile b { display: block; font-size: 18px; font-weight: 600; }
        .au-panel .au-tile span { font-size: 12px; color: #667085; }
        .au-panel .au-grid-wrap { overflow: auto; max-height: 50vh; border-bottom: 1px solid #eaecf0; }
        .au-panel .au-grid { border-collapse: separate; border-spacing: 0; }
        html body .au-panel .au-grid td, html body .au-panel .au-grid th { padding: 4px 3px !important; vertical-align: middle; white-space: nowrap; }
        .au-panel .au-grid thead th { position: sticky; top: 0; background: #f1f1f3; z-index: 2; }
        .au-panel .au-grid .au-name { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 160px; padding: 4px 10px !important; }
        .au-panel .au-grid thead .au-name { background: #f1f1f3; z-index: 3; }
        .au-panel .au-grid .au-month { border-left: 2px solid #99a5b5 !important; }
        .au-panel .au-c { display: inline-block; min-width: 24px; padding: 2px 4px; border-radius: 4px; font-size: 10px; font-weight: 700; color: #fff; text-align: center; }
        .au-panel .au-p { background: #12b76a; } .au-panel .au-hd { background: #f79009; } .au-panel .au-a { background: #f04438; }
        .au-panel .au-none { color: #d0d5dd; font-size: 10px; }
        .au-panel .au-from { color: #8a94a6; text-decoration: line-through; }
        .au-panel .au-list { max-height: 40vh; overflow: auto; }
        html body .au-panel .au-list td, html body .au-panel .au-list th { padding: 7px 12px !important; white-space: nowrap; }
        .au-panel .au-list thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    </style>

    <div class="au-head">
        <h4 class="mb-1 f-19 font-weight-normal"><i class="fa fa-file-excel text-success mr-1"></i> {{ $i->file_name ?: 'Attendance upload' }}</h4>
        <p class="f-13 text-lightest mb-0">
            Pay period {{ $i->period_label }} · uploaded by {{ $i->by_name ?: '—' }} on {{ $when->format('d M Y, h:i A') }}
        </p>
        @if ($upload['rebuilt'])
            <p class="f-12 mt-2 mb-0" style="color: #b54708"><i class="fa fa-info-circle mr-1"></i>
                This upload was saved before uploads kept their days, so they are shown from the attendance it wrote, as it stands now.</p>
        @endif
        <div class="au-tiles">
            <div class="au-tile"><b class="text-success">{{ $i->days_created }}</b><span>days added</span></div>
            <div class="au-tile"><b class="text-success">{{ collect($days)->where('state', 'changed')->count() }}</b><span>days corrected</span></div>
            <div class="au-tile"><b class="{{ collect($days)->where('state', 'absent')->count() ? 'text-red' : 'text-lightest' }}">{{ collect($days)->where('state', 'absent')->count() }}</b><span>marked absent</span></div>
            <div class="au-tile"><b>{{ count($grid['rows']) }}</b><span>employees</span></div>
            <div class="au-tile"><b class="{{ $i->days_skipped ? 'text-red' : 'text-lightest' }}">{{ $i->days_skipped }}</b><span>left out when saved</span></div>
        </div>
    </div>

    @if (!$days)
        <p class="p-4 text-center text-lightest f-13 mb-0">No days could be found for this upload.</p>
    @else
        {{-- the register: what the upload saved, by employee and day --}}
        <div class="px-3 pt-3 pb-2 d-flex flex-wrap align-items-center" style="gap: 12px">
            <h6 class="f-14 mb-0 mr-auto">What it saved</h6>
            <span class="f-11 text-dark-grey"><b class="au-c au-p">P</b> present &nbsp; <b class="au-c au-hd">HD</b> half day &nbsp; <b class="au-c au-a">A</b> absent &nbsp; <span class="au-none">·</span> not in this upload</span>
        </div>
        <div class="au-grid-wrap">
            <table class="table table-bordered table-sm mb-0 f-12 au-grid">
                <thead>
                    <tr>
                        <th class="au-name">@lang('app.employee')</th>
                        @foreach ($grid['dates'] as $date)
                            @php $d = \Carbon\Carbon::parse($date); @endphp
                            <th class="text-center {{ $d->day === 1 ? 'au-month' : '' }}">{{ $d->day }}<br><span class="f-10 text-lightest">{{ $d->format('D')[0] }}</span></th>
                        @endforeach
                        <th class="text-right">P</th><th class="text-right">HD</th><th class="text-right">A</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grid['rows'] as $r)
                        <tr>
                            <td class="au-name">{{ $r['name'] }}<div class="f-10 text-lightest">{{ $r['code'] }}</div></td>
                            @foreach ($grid['dates'] as $date)
                                @php $day = $r['days'][$date] ?? null; @endphp
                                <td class="text-center {{ substr($date, 8, 2) === '01' ? 'au-month' : '' }}"
                                    @if ($day) title="{{ \Carbon\Carbon::parse($date)->format('D d M') }}: {{ $day['mark'] }}{{ $day['in'] ? ' ' . $day['in'] . '–' . $day['out'] : '' }}{{ $day['from'] ? ' (was ' . $day['from'] . ')' : '' }}" @endif>
                                    @if ($day)<b class="au-c {{ $cls[$day['mark']] ?? 'au-p' }}">{{ $day['mark'] }}</b>@else<span class="au-none">·</span>@endif
                                </td>
                            @endforeach
                            <td class="text-right font-weight-bold text-success">{{ $r['totals']['P'] ?: '' }}</td>
                            <td class="text-right">{{ $r['totals']['HD'] ?: '' }}</td>
                            <td class="text-right text-red">{{ $r['totals']['A'] ?: '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- day by day --}}
        <div class="px-3 pt-3 pb-2"><h6 class="f-14 mb-0">Day by day ({{ count($days) }})</h6></div>
        <div class="au-list">
            <table class="table table-hover mb-0 f-13">
                <thead><tr><th>@lang('app.employee')</th><th>Date</th><th>Saved</th><th>Before</th></tr></thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr>
                            <td>{{ $day['name'] }} <span class="f-11 text-lightest d-block">{{ $day['code'] }}</span></td>
                            <td>{{ \Carbon\Carbon::parse($day['date'])->format('D d M Y') }}</td>
                            <td>
                                <b class="au-c {{ $cls[$day['mark']] ?? 'au-p' }}">{{ $day['mark'] }}</b>
                                @if ($day['state'] === 'absent')
                                    <span class="text-red f-12 ml-1">attendance taken off</span>
                                @else
                                    <span class="ml-1">{{ $day['in'] }}–{{ $day['out'] }}</span>
                                    @if (!empty($day['overnight']))<span class="f-11 text-lightest">(next day)</span>@endif
                                @endif
                            </td>
                            <td>@if ($day['from'])<span class="au-from">{{ $day['from'] }}</span>@else<span class="text-lightest f-12">nothing on record</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
