{{-- Project -> Profitability (ProjectController::show ?tab=profitability, App\Services\Tracker\ProjectFinance):
     revenue, staff cost, expenses and profit over the project's months, and where it is heading.
     Super Admin, Admin, CEO and CFO only. --}}
@php
    $f = $finance;
    $t = $f['totals'];
    $fc = $f['forecast'];
    $money = fn ($v) => ($v < 0 ? '−' : '') . '₹' . number_format(abs((float) $v));
    $basisLabel = ['earned' => 'Earned (budget × % complete)', 'billed' => 'Billed (invoices)', 'received' => 'Received (payments)'];
@endphp
<div class="pf" data-swap-links>
    <div class="pf-bar">
        <div class="pf-seg" role="tablist" aria-label="Revenue">
            @foreach ($basisLabel as $key => $label)
                <a href="{{ route('projects.show', $project->id) }}?tab=profitability&basis={{ $key }}" class="{{ $f['method'] === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <span class="pf-cut"><i class="fa fa-info-circle mr-1"></i>{{ $f['cutoff'] ? 'Staff cost from payslips up to ' . $f['cutoff']->format('M Y') . '; months after are estimated from the monthly salary' : 'No payslips yet: staff cost is estimated from the monthly salary' }}</span>
    </div>

    {{-- The numbers --}}
    <div class="pf-tiles">
        <div class="pf-tile"><span>Budget</span><b>{{ $f['budget'] ? $money($f['budget']) : '—' }}</b><small>contract value</small></div>
        <div class="pf-tile"><span>Revenue to date</span><b>{{ $money($t['revenue']) }}</b><small>{{ strtolower(explode(' (', $basisLabel[$f['method']])[0]) }}</small></div>
        <div class="pf-tile"><span>Cost to date</span><b>{{ $money($t['cost']) }}</b><small>staff {{ $money($t['staff']) }} · expenses {{ $money($t['direct']) }} · overheads {{ $money($t['overhead']) }}</small></div>
        <div class="pf-tile {{ $t['profit'] < 0 ? 'is-bad' : 'is-good' }}"><span>Profit to date</span><b>{{ $money($t['profit']) }}</b><small>{{ $t['margin'] !== null ? $t['margin'] . '% margin' : 'no revenue yet' }}</small></div>
        <div class="pf-tile {{ ($fc['final_margin'] ?? 0) < 0 ? 'is-bad' : '' }}"><span>At completion</span><b>{{ $fc['final_profit'] !== null ? $money($fc['final_profit']) : '—' }}</b>
            <small>{{ $fc['final_margin'] !== null ? $fc['final_margin'] . '% margin · ' : '' }}{{ $money($fc['remaining_cost']) }} still to spend on {{ $fc['remaining_hours'] }} h</small></div>
    </div>

    <div class="row">
        <div class="col-xl-8 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-chart-line mr-1"></i>Month by month</h5><span class="text-dark-grey f-12">revenue and cost bars · running profit line</span></div>
                @include('project-tracker.partials.chart', ['id' => 'pf-months', 'type' => 'axis-mixed', 'labels' => array_column($f['months'], 'month'),
                    'datasets' => [['Revenue', array_column($f['months'], 'revenue'), 'bar'], ['Cost', array_column($f['months'], 'cost'), 'bar'], ['Running profit', array_column($f['months'], 'cumulative'), 'line']],
                    'colors' => ['#12b76a', '#f04438', '#3d78e3'], 'height' => 270, 'format' => 'money'])
            </div>
        </div>
        <div class="col-xl-4 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-chart-pie mr-1"></i>Where the cost goes</h5></div>
                @if ($t['cost'] > 0)
                    @include('project-tracker.partials.chart', ['id' => 'pf-split', 'type' => 'donut', 'labels' => ['Staff', 'Expenses', 'Overheads'],
                        'datasets' => [['Cost', [$t['staff'], $t['direct'], $t['overhead']]]], 'colors' => ['#3d78e3', '#f79009', '#98a2b3'], 'height' => 250, 'format' => 'money'])
                @else
                    <p class="f-13 text-dark-grey mb-0">No cost yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- The months --}}
    <div class="rc-list-card mb-3">
        <div class="table-responsive">
            <table class="pd-money pf-table">
                <thead><tr><th>Month</th><th>Hours</th><th>Done</th><th>Revenue</th><th>Staff</th><th>Expenses</th><th>Overheads</th><th>Profit</th><th>Running</th></tr></thead>
                <tbody>
                    @foreach ($f['months'] as $m)
                        <tr class="{{ $m['estimated'] ? 'is-est' : '' }}">
                            <td>{{ $m['month'] }} @if ($m['estimated'])<span class="pf-tag" title="Staff cost from the monthly salary (no payslip for this month)">estimated</span>@endif</td>
                            <td>{{ $m['hours'] }}</td>
                            <td>{{ $m['pct'] }}%</td>
                            <td>{{ $money($m['revenue']) }}</td>
                            <td>{{ $money($m['staff']) }}</td>
                            <td>{{ $money($m['direct']) }}</td>
                            <td>{{ $money($m['overhead']) }}</td>
                            <td class="{{ $m['profit'] < 0 ? 'text-danger' : 'pd-pos' }}">{{ $money($m['profit']) }}</td>
                            <td class="{{ $m['cumulative'] < 0 ? 'text-danger' : '' }}">{{ $money($m['cumulative']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- The people --}}
    <div class="rc-list-card">
        <div class="pd-head px-4 pt-3"><h5><i class="fa fa-users mr-1"></i>Staff cost by person</h5><span class="text-dark-grey f-12">hours on this project × their cost per hour that month</span></div>
        <div class="table-responsive">
            <table class="pd-money pf-table">
                <thead><tr><th>Employee</th><th>Hours</th><th>Cost per hour</th><th>Cost</th></tr></thead>
                <tbody>
                    @forelse ($financePeople as $p)
                        <tr>
                            <td>{{ $p->name }} <span class="text-dark-grey f-12">{{ $p->designation }}</span> @if ($p->estimated)<span class="pf-tag">part estimated</span>@endif</td>
                            <td>{{ $p->hours }}</td>
                            <td>{{ $money($p->per_hour) }}</td>
                            <td>{{ $money($p->cost) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-dark-grey">No hours logged on this project yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('project-tracker.partials.dash-styles')
@include('recruitment.partials.styles')
<style>
    .pf-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
    .pf-seg { display: inline-flex; flex-wrap: wrap; padding: 3px; background: #fff; border-radius: 9px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
    .pf-seg a { padding: 6px 12px; border-radius: 7px; font-size: 13px; font-weight: 500; color: #475467; }
    .pf-seg a:hover { text-decoration: none; background: #f2f4f7; }
    .pf-seg a.active { background: var(--epic-blue, #cb6b4f); color: #fff; }
    .pf-cut { font-size: 12.5px; color: #667085; }
    .pf-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .pf-tile { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 16px 18px; display: flex; flex-direction: column; border-top: 3px solid #eaecf0; }
    .pf-tile span { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #667085; }
    .pf-tile b { font-size: 22px; color: #101828; margin: 4px 0 2px; }
    .pf-tile small { font-size: 12px; color: #98a2b3; }
    .pf-tile.is-good { border-top-color: #12b76a; } .pf-tile.is-good b { color: #067647; }
    .pf-tile.is-bad { border-top-color: #f04438; } .pf-tile.is-bad b { color: #b42318; }
    .pf-table td, .pf-table th { padding-left: 16px !important; padding-right: 16px !important; }
    .pf-table tr.is-est td { background: #fcfcfd; }
    .pf-tag { font-size: 10.5px; font-weight: 600; background: #fffaeb; color: #b54708; border-radius: 4px; padding: 1px 6px; margin-left: 4px; text-transform: uppercase; letter-spacing: .03em; }
</style>
