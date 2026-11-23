{{-- One employee's availability over the next three months (Resource Planner / Project Dashboard). --}}
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $palette = ['#3d78e3', '#12b76a', '#f79009', '#7a5af8', '#ee46bc', '#0ba5ec', '#a16207', '#667085'];
    $colours = [];
    $i = 0;
    foreach ($projectNames as $pid => $p) { $colours[$pid] = $p->projectType?->color ?: $palette[$i++ % count($palette)]; }
    $max = max(1, collect($weeks)->max(fn ($w) => max($w['capacity'], $w['booked'])));
    $detail = $person->employeeDetail;
    $weekData = array_map(fn ($w) => ['label' => $w['label'], 'free' => $w['free'], 'capacity' => $w['capacity']], $weeks);
@endphp
<div class="av bg-white">
    <div class="av-head">
        <img src="{{ $person->image_url }}" alt="">
        <div>
            <h4>{{ $person->name }}</h4>
            <span>{{ $detail?->designation?->name ?? '—' }}{{ $detail?->department ? ' · ' . $detail->department->team_name : '' }} · {{ $num($hoursPerDay) }} h a working day</span>
        </div>
    </div>

    {{-- The next three months --}}
    <div class="av-months">
        @foreach ($months as $m)
            <div class="av-month">
                <span>{{ $m['label'] }}</span>
                <b class="{{ $m['free'] < 0 ? 'text-danger' : '' }}">{{ $num($m['free']) }} h free</b>
                <small>{{ $num($m['booked']) }} of {{ $num($m['capacity']) }} h booked</small>
                <div class="pd-bar"><span style="width: {{ min(100, $m['load']) }}%; background: {{ $m['load'] > 100 ? '#f04438' : ($m['load'] >= 80 ? '#f79009' : '#12b76a') }}"></span></div>
            </div>
        @endforeach
    </div>

    {{-- Week by week, booked per project against what they have --}}
    <div class="av-sec">
        <h6>Week by week</h6>
        <div class="av-chart">
            @foreach ($weeks as $w)
                <div class="av-col" title="{{ $num($w['booked']) }} of {{ $num($w['capacity']) }} h booked">
                    <div class="av-stack" style="height: 160px">
                        <div class="av-cap" style="height: {{ round($w['capacity'] / $max * 100) }}%"></div>
                        <div class="av-booked" style="height: {{ round(min($w['booked'], $max) / $max * 100) }}%">
                            @foreach ($w['byProject'] as $pid => $h)
                                <span style="flex: {{ $h }}; background: {{ $colours[$pid] ?? '#98a2b3' }}"></span>
                            @endforeach
                        </div>
                    </div>
                    <b class="{{ $w['free'] < 0 ? 'text-danger' : '' }}">{{ $num($w['free']) }}</b>
                    <small>{{ $w['label'] }}</small>
                </div>
            @endforeach
        </div>
        <div class="av-legend">
            <span><i style="background: #eef2f6; border: 1px dashed #98a2b3"></i>hours they have</span>
            @foreach ($projectNames as $pid => $p)<span><i style="background: {{ $colours[$pid] }}"></i>{{ $p->project_name }}</span>@endforeach
            <span class="text-dark-grey">· the number under each week is the free hours</span>
        </div>
    </div>

    {{-- Can they take more? --}}
    <div class="av-sec">
        <h6>Check a booking</h6>
        <div class="av-check">
            <label>Hours a week <input type="number" min="1" max="80" step="1" id="av-hours" class="form-control" value="20"></label>
            <label>From <select id="av-from" class="form-control">@foreach ($weeks as $k => $w)<option value="{{ $k }}">{{ $w['label'] }}</option>@endforeach</select></label>
            <label>For <select id="av-len" class="form-control">@foreach ([1, 2, 4, 6, 8, 12] as $n)<option value="{{ $n }}" @selected($n === 4)>{{ $n }} week{{ $n > 1 ? 's' : '' }}</option>@endforeach</select></label>
        </div>
        <div id="av-answer" class="av-answer"></div>
    </div>

    {{-- Their open work --}}
    <div class="av-sec">
        <h6>Open work</h6>
        @forelse ($tasks as $task)
            <div class="av-task">
                <span><b>{{ $task->heading }}</b><small>{{ $task->project?->project_name }}</small></span>
                <span class="{{ $task->due_date && $task->due_date->isPast() ? 'text-danger' : 'text-dark-grey' }}">{{ $task->due_date ? $task->due_date->format(company()->date_format) : 'no due date' }}
                    · {{ $num((int) $task->estimate_hours + $task->estimate_minutes / 60) }} h</span>
            </div>
        @empty
            <p class="f-13 text-dark-grey mb-0">No open work.</p>
        @endforelse
    </div>
</div>

@include('project-tracker.partials.dash-styles')
<style>
    .av { font-size: 13.5px; }
    .av-head { display: flex; align-items: center; gap: 14px; padding: 20px 24px; border-bottom: 1px solid #eaecf0; }
    .av-head img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; }
    .av-head h4 { font-size: 18px; font-weight: 600; margin: 0; color: #101828; }
    .av-head span { font-size: 13px; color: #667085; }
    .av-months { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; padding: 18px 24px; border-bottom: 1px solid #eaecf0; }
    .av-month { background: #f9fafb; border: 1px solid #f2f4f7; border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; }
    .av-month span { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085; }
    .av-month b { font-size: 18px; color: #101828; margin-top: 4px; }
    .av-month small { font-size: 12px; color: #98a2b3; }
    .av-sec { padding: 18px 24px; border-bottom: 1px solid #eaecf0; }
    .av-sec h6 { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #667085; margin: 0 0 12px; }
    .av-chart { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; }
    .av-col { flex: 1 0 48px; display: flex; flex-direction: column; align-items: center; font-size: 11.5px; }
    .av-stack { position: relative; width: 30px; display: flex; align-items: flex-end; }
    .av-cap { position: absolute; bottom: 0; left: 0; right: 0; background: #eef2f6; border: 1px dashed #98a2b3; border-radius: 6px 6px 0 0; }
    .av-booked { position: relative; width: 100%; display: flex; flex-direction: column-reverse; border-radius: 6px 6px 0 0; overflow: hidden; }
    .av-booked span { display: block; min-height: 2px; }
    .av-col b { margin-top: 5px; color: #101828; }
    .av-col small { color: #98a2b3; white-space: nowrap; }
    .av-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 10px; font-size: 12px; color: #475467; }
    .av-legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -2px; margin-right: 5px; }
    .av-check { display: flex; flex-wrap: wrap; gap: 12px; }
    .av-check label { display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; color: #667085; margin: 0; }
    .av-check .form-control { height: 38px; min-width: 120px; }
    .av-answer { margin-top: 12px; }
    .av-weeks { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .av-weeks span { font-size: 12px; padding: 4px 8px; border-radius: 6px; }
    .av-verdict { padding: 10px 12px; border-radius: 8px; font-size: 13.5px; }
    .av-verdict.ok { background: #ecfdf3; color: #067647; } .av-verdict.no { background: #fef3f2; color: #b42318; } .av-verdict.part { background: #fffaeb; color: #b54708; }
    .av-task { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-top: 1px solid #f2f4f7; }
    .av-sec h6 + .av-task { border-top: 0; padding-top: 0; }
    .av-task span:first-child { display: flex; flex-direction: column; min-width: 0; }
    .av-task small { color: #667085; }
    .av-task span:last-child { white-space: nowrap; font-size: 12.5px; }
</style>

<script>
    // the booking check: week by week, is there room for the hours asked?
    (function () {
        var weeks = @json($weekData);
        function check() {
            var hours = +document.getElementById('av-hours').value || 0;
            var from = +document.getElementById('av-from').value;
            var len = +document.getElementById('av-len').value;
            var chosen = weeks.slice(from, from + len);
            var fits = chosen.filter(function (w) { return w.free >= hours; }).length;
            var html = chosen.map(function (w) {
                var tone = w.free >= hours ? 'pd-cell--low' : (w.free > 0 ? 'pd-cell--full' : 'pd-cell--over');
                return '<span class="' + tone + '">' + w.label + ': ' + Math.max(0, Math.round(w.free * 10) / 10) + ' h free</span>';
            }).join('');
            var verdict = fits === chosen.length ? ['ok', 'Yes — ' + hours + ' h a week fits in every one of these ' + chosen.length + ' week(s).']
                : (fits === 0 ? ['no', 'No — none of these weeks has ' + hours + ' h free.']
                    : ['part', 'Partly — ' + fits + ' of ' + chosen.length + ' weeks have ' + hours + ' h free.']);
            document.getElementById('av-answer').innerHTML = '<div class="av-verdict ' + verdict[0] + '">' + verdict[1] + '</div><div class="av-weeks">' + html + '</div>';
        }
        ['av-hours', 'av-from', 'av-len'].forEach(function (id) { var el = document.getElementById(id); el.addEventListener('input', check); el.addEventListener('change', check); });
        check();
    })();
</script>
