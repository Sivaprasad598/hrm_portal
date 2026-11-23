{{-- One person's KPIs for a month (App\Services\Tracker\EmployeeKpi::profile): the score, each measure
     against the Division's average, and the last six months. Used by the Employee KPIs side panel, the
     KPI tab on the employee's page and My Space -> My KPI.
     Needs $person, $month (Carbon), $months, $kpiProfile, $monthUrl (fn (Carbon) => url), $panel (bool). --}}
@php
    $kp = $kpiProfile;
    $k = $kp['k'];
    $score = $k['score'] ?? null;
    [$bandLabel, $bandColour] = \App\Services\Tracker\EmployeeKpi::band($score);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $chartId = 'kpi-trend-' . $person->id . ($panel ? '-p' : '');
    $detail = [
        'utilisation' => $k ? $num($k['hours']) . ' h logged of ' . $num($k['capacity']) . ' h available' : null,
        'productivity' => $k && $k['expected'] > 0 ? $num($k['units']) . ' done of ' . $num($k['expected']) . ' expected at their rate' : 'No counted work, or no daily rate set for their designation',
        'on_time' => $k && $k['finished'] ? $k['on_time_count'] . ' of ' . $k['finished'] . ' finished items by the due date' : 'No items finished this month',
        'estimate' => $k && $k['finished'] ? 'On the ' . $k['finished'] . ' finished items' : 'No items finished this month',
        'quality' => $k ? ($k['rework'] ? $k['rework'] . ' sent back for rework' : 'Nothing sent back') : null,
    ];
@endphp
<div class="kpi" data-swap-links>
    <div class="kpi-top">
        <div class="kpi-ring" style="--p: {{ $score ?? 0 }}; --c: {{ $bandColour }}"><span>{{ $score ?? '—' }}</span></div>
        <div class="kpi-who">
            <h4>{{ $person->name }}</h4>
            <p>{{ $person->employeeDetail?->designation?->name ?? '—' }}{{ $person->employeeDetail?->department ? ' · ' . $person->employeeDetail->department->team_name : '' }}</p>
            <span class="kpi-band" style="--c: {{ $bandColour }}">{{ $bandLabel }}</span>
            @if ($kp['teamScore'] !== null)
                <span class="kpi-vs">Division average {{ $kp['teamScore'] }} · {{ $kp['teamSize'] }} {{ \Illuminate\Support\Str::plural('person', $kp['teamSize']) }}</span>
            @endif
        </div>
        <div class="kpi-month">
            <label class="f-12 text-dark-grey mb-1 d-block">Month</label>
            <select class="form-control f-14 kpi-month-pick" aria-label="Month" data-live-search="true" data-size="8"
                data-live-search-placeholder="Search month">
                @foreach ($months as $m)
                    <option value="{{ $monthUrl($m) }}" @selected($m->format('Y-m') === $month->format('Y-m'))>{{ $m->format('F Y') }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (!$k || ($k['hours'] <= 0 && $score === null))
        <div class="kpi-none"><i class="fa fa-chart-bar"></i><p>No project work recorded for {{ $month->format('F Y') }}. KPIs come from hours logged on projects and the work moved on the project tracker.</p></div>
    @else
        <div class="kpi-grid">
            @foreach (\App\Services\Tracker\EmployeeKpi::METRICS as $key => [$label, $hint])
                @php
                    $v = $k['metrics'][$key];
                    $avg = $kp['team'][$key] ?? null;
                    $w = $kp['weights'][$key] ?? 0;
                    $good = $key === 'utilisation' ? ($v !== null && $v >= 85 && $v <= 110) : ($v !== null && $v >= 85);
                    $low = $v !== null && ($key === 'utilisation' ? ($v < 60 || $v > 125) : $v < 60);
                @endphp
                <div class="kpi-card {{ $v === null ? 'is-empty' : ($good ? 'is-good' : ($low ? 'is-low' : '')) }}">
                    <div class="kpi-card-head"><span>{{ $label }}</span><small title="Share of the score">{{ $w ? $w . '%' : 'not scored' }}</small></div>
                    <b>{{ $v === null ? '—' : $v . '%' }}</b>
                    <div class="kpi-bar"><i style="width: {{ min(100, (int) $v) }}%"></i>@if ($avg !== null)<em style="left: {{ min(100, $avg) }}%" title="Division average {{ $avg }}%"></em>@endif</div>
                    <p>{{ $detail[$key] ?? $hint }}</p>
                    <small class="kpi-avg">{{ $avg !== null ? 'Division ' . $avg . '%' : $hint }}</small>
                </div>
            @endforeach
            <div class="kpi-card kpi-card--plain">
                <div class="kpi-card-head"><span>Held up</span><small>not scored</small></div>
                <b>{{ $k['blocked_days'] }} <small class="f-13 font-weight-normal">{{ \Illuminate\Support\Str::plural('day', $k['blocked_days']) }}</small></b>
                <p>Days their open work was blocked</p>
            </div>
        </div>
        @if ($k['type'])
            <p class="kpi-note"><i class="fa fa-info-circle mr-1"></i>Weighted as <b>{{ $k['type']->name }}</b> work, where most of their hours went. The weights are set per project type in Settings → Project Tracker.</p>
        @endif
    @endif

    <div class="kpi-trend">
        <div class="pd-head"><h5><i class="fa fa-chart-line mr-1"></i>Last six months</h5><span class="text-dark-grey f-12">score and utilisation</span></div>
        @include('project-tracker.partials.chart', ['id' => $chartId, 'type' => 'axis-mixed', 'labels' => array_column($kp['trend'], 'month'),
            'datasets' => [['Score', array_map(fn ($r) => $r['score'] ?? 0, $kp['trend']), 'bar'], ['Utilisation %', array_map(fn ($r) => $r['utilisation'] ?? 0, $kp['trend']), 'line']],
            'colors' => ['#3d78e3', '#f79009'], 'height' => 220, 'fill' => false])
    </div>
</div>

<script>
    // the month list: a searchable dropdown (the app's select picker), newest first
    // (on a full page load the picker's script comes after this one, so wait for the page)
    $(function () {
        $('.kpi-month-pick').each(function () {
            if ($.fn.selectpicker && !$(this).parent().hasClass('bootstrap-select')) { $(this).selectpicker({ style: 'btn-light', width: '100%' }); }
        });
    });
    $('.kpi-month-pick').off('change.kpi').on('change.kpi', function () {
        var url = this.value;
        @if ($panel)
            // in the side panel: load the month into the same panel
            $.easyAjax({ url: url, blockUI: true, container: '.kpi-wrap', success: function (r) { if (r.status === 'success') { $('.kpi-wrap').replaceWith(r.html); } } });
        @else
            window.PageSwap ? PageSwap.open(url) : (window.location.href = url);
        @endif
    });
</script>
@include('project-tracker.partials.dash-styles')
<style>
    .kpi-top { display: flex; flex-wrap: wrap; align-items: center; gap: 18px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 18px 20px; margin-bottom: 14px; }
    .kpi-ring { --p: 0; --c: #3d78e3; width: 92px; height: 92px; border-radius: 50%; flex: none; display: grid; place-items: center;
        background: conic-gradient(var(--c) calc(var(--p) * 1%), #eaecf0 0); }
    .kpi-ring span { width: 72px; height: 72px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-size: 26px; font-weight: 700; color: #101828; }
    .kpi-who { flex: 1 1 220px; min-width: 0; }
    .kpi-who h4 { margin: 0; font-size: 18px; color: #101828; }
    .kpi-who p { margin: 2px 0 8px; font-size: 13px; color: #667085; }
    .kpi-band { display: inline-block; font-size: 12px; font-weight: 600; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); border-radius: 20px; padding: 3px 10px; margin-right: 8px; }
    .kpi-vs { font-size: 12.5px; color: #667085; }
    .kpi-month { flex: 0 0 200px; }
    .kpi-month .bootstrap-select > .dropdown-toggle { height: 38px; background: #fff; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 14px; box-shadow: none; }
    .kpi-month .bootstrap-select > .dropdown-toggle:focus, .kpi-month .bootstrap-select.show > .dropdown-toggle { border-color: var(--epic-blue, #cb6b4f); outline: 0 !important; }
    .kpi-month .bootstrap-select .dropdown-menu { border: 1px solid #eaecf0; border-radius: 10px; box-shadow: 0 12px 24px rgba(16, 24, 40, .12); padding: 6px 0; }
    .kpi-month .bootstrap-select .bs-searchbox { padding: 4px 10px 8px; }
    .kpi-month .bootstrap-select .bs-searchbox .form-control { height: 34px; font-size: 13.5px; border-radius: 6px; }
    .kpi-month .bootstrap-select .dropdown-menu li a { padding: 8px 14px; font-size: 13.5px; }
    .kpi-month .bootstrap-select .dropdown-menu li.selected a, .kpi-month .bootstrap-select .dropdown-menu li.active a { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff); color: var(--epic-blue, #cb6b4f); font-weight: 600; }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
    .kpi-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 14px 16px; border-left: 3px solid #d0d5dd; display: flex; flex-direction: column; }
    .kpi-card.is-good { border-left-color: #12b76a; } .kpi-card.is-low { border-left-color: #f04438; } .kpi-card.is-empty b { color: #98a2b3; }
    .kpi-card-head { display: flex; justify-content: space-between; align-items: baseline; gap: 6px; }
    .kpi-card-head span { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #475467; }
    .kpi-card-head small { font-size: 11px; color: #98a2b3; white-space: nowrap; }
    .kpi-card b { font-size: 24px; color: #101828; margin: 4px 0 6px; }
    .kpi-card p { font-size: 12.5px; color: #667085; margin: 8px 0 2px; }
    .kpi-avg { font-size: 11.5px; color: #98a2b3; }
    .kpi-bar { position: relative; height: 6px; border-radius: 3px; background: #f2f4f7; }
    .kpi-bar i { position: absolute; inset: 0 auto 0 0; border-radius: 3px; background: #3d78e3; }
    .kpi-card.is-good .kpi-bar i { background: #12b76a; } .kpi-card.is-low .kpi-bar i { background: #f04438; }
    .kpi-bar em { position: absolute; top: -4px; width: 2px; height: 14px; background: #101828; border-radius: 1px; }
    .kpi-card--plain { border-left-color: #eaecf0; }
    .kpi-note { font-size: 12.5px; color: #667085; margin: 10px 2px 0; }
    .kpi-none { background: #fff; border-radius: 12px; padding: 28px; text-align: center; color: #667085; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .kpi-none i { font-size: 26px; color: #d0d5dd; margin-bottom: 8px; }
    .kpi-none p { margin: 0 auto; max-width: 460px; font-size: 13.5px; }
    .kpi-trend { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 16px 18px 6px; margin-top: 14px; }
    @media (max-width: 575px) { .kpi-month { flex: 1 1 100%; } }
</style>
