@extends('layouts.app')

@php
    $h = fn ($v) => $v == 0 ? '–' : \App\Services\Payroll\OtBank::fmt(round($v, 2)) . 'h';
    $monthName = fn ($m) => \Carbon\Carbon::create($year, $m, 1)->format('M');
@endphp

@section('content')
<div class="content-wrapper">

    {{-- Filters --}}
    <form method="GET" action="{{ route('ot-hours.index') }}" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-lg-2 col-md-4 col-6">
                <label class="f-13 text-dark-grey mb-1">Year</label>
                <select name="year" class="form-control select-picker">
                    @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label class="f-13 text-dark-grey mb-1">Month</label>
                <select name="month" class="form-control select-picker">
                    <option value="">Whole year</option>
                    @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m == $month)>{{ \Carbon\Carbon::create($year, $m, 1)->format('F') }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label class="f-13 text-dark-grey mb-1">Department</label>
                <select name="department" class="form-control select-picker" data-live-search="true">
                    <option value="">All</option>
                    @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($id == $departmentId)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label class="f-13 text-dark-grey mb-1">Show</label>
                <select name="show" class="form-control select-picker">
                    <option value="with_ot" @selected($show === 'with_ot')>Employees with OT</option>
                    <option value="all" @selected($show === 'all')>Everyone</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control f-14" placeholder="Name or employee ID">
            </div>
            <div class="col-lg-2 col-md-4 col-6 text-right">
                <button type="submit" class="btn btn-primary rounded f-14"><i class="fa fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('ot-hours.index') }}" class="btn btn-secondary rounded f-14">Clear</a>
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <p class="f-13 text-dark-grey mb-0">The system counts overtime from {{ $goLive->format('d M Y') }}. Earlier months come from past records
            <span class="badge badge-info f-10">past</span>, entered on each employee's OT Bank tab or imported here.</p>
        <button type="button" class="btn btn-primary rounded f-14" data-toggle="modal" data-target="#ot-import-modal"><i class="fa fa-file-upload mr-1"></i>Import past OT</button>
    </div>

    @if (!$rules || !$rules->s->ot_enabled)
        <div class="alert alert-warning f-14">Overtime is switched off. Turn it on under Settings → Attendance Settings → Overtime.</div>
    @endif

    {{-- Totals --}}
    <div class="row mb-3">
        @foreach ([
            ['OT earned', $h($totals['earned']), $month ? \Carbon\Carbon::create($year, $month, 1)->format('F Y') : 'in ' . $year, 'text-primary'],
            ['Used for LOP', $h($totals['used_hours']), \App\Services\Payroll\OtBank::fmt($totals['used_days']) . ' day(s) covered', 'text-dark'],
            ['Encashed', $h($totals['encashed_hours']), '₹' . number_format($totals['encashed_amount'], 2), 'text-dark'],
            ['Balance held', $h($totals['balance']), '≈ ' . \App\Services\Payroll\OtBank::fmt(round($totals['balance'] / max(0.01, $dayHours), 1)) . ' days · ≈ ₹' . number_format($totals['balance_amount'], 0), 'text-success'],
        ] as [$label, $value, $sub, $class])
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="bg-white rounded b-shadow-4 p-3 h-100">
                    <p class="f-12 text-dark-grey text-uppercase mb-1">{{ $label }}</p>
                    <h3 class="mb-0 font-weight-bold {{ $class }}">{{ $value }}</h3>
                    <p class="f-12 text-lightest mb-0">{{ $sub }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded b-shadow-4">
        <div class="table-responsive" style="max-height:70vh">
            <table class="table table-hover mb-0 f-13 ot-table">
                <thead class="bg-light">
                    <tr>
                        <th class="ot-name">Employee</th>
                        @foreach ($periods as $m => $p)
                            <th class="text-right" title="{{ $p->describe() }}">{{ $monthName($m) }}</th>
                        @endforeach
                        <th class="text-right">{{ $month ? 'Total' : 'Year' }}</th>
                        <th class="text-right">Used for LOP</th>
                        <th class="text-right">Encashed</th>
                        <th class="text-right">Balance</th>
                        <th class="text-right">In days</th>
                        <th class="text-right">In amount</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="ot-name">
                            <a href="{{ route('employees.show', $r['id']) }}?tab=ot-bank" class="text-darkest-grey font-weight-500">{{ $r['name'] }}</a>
                            <br><span class="f-11 text-lightest">{{ $r['code'] }}{{ $r['department'] ? ' · ' . $r['department'] : '' }}</span>
                        </td>
                        @foreach ($r['cells'] as $m => $c)
                            <td class="text-right">
                                @if ($c['hours'] > 0)
                                    <a href="#" class="ot-cell" data-user="{{ $r['id'] }}" data-month="{{ $m }}"
                                       title="{{ $c['past'] ? 'From past records' : $c['days'] . ' day(s), ' . \App\Services\Payroll\OtBank::fmt(round($c['ot'], 2)) . 'h of overtime' }}">{{ $h($c['hours']) }}</a>
                                    @if ($c['past'])<br><span class="badge badge-info f-10">past</span>@endif
                                @else
                                    <span class="text-lightest">–</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-right font-weight-600">{{ $h($r['earned']) }}</td>
                        <td class="text-right">{{ $h($r['used_hours']) }}@if ($r['used_days'] > 0)<br><span class="f-11 text-lightest">{{ \App\Services\Payroll\OtBank::fmt($r['used_days']) }} day(s)</span>@endif</td>
                        <td class="text-right">{{ $h($r['encashed_hours']) }}@if ($r['encashed_amount'] > 0)<br><span class="f-11 text-lightest">₹{{ number_format($r['encashed_amount'], 2) }}</span>@endif</td>
                        <td class="text-right font-weight-bold text-success">{{ $h($r['balance']) }}</td>
                        <td class="text-right">{{ $r['balance'] > 0 ? \App\Services\Payroll\OtBank::fmt(round($r['balance_days'], 2)) : '–' }}</td>
                        <td class="text-right" title="at ₹{{ number_format($r['hourly'], 2) }} an hour">{{ $r['balance'] > 0 ? '₹' . number_format($r['balance_amount'], 2) : '–' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($periods) + 7 }}"><x-cards.no-record icon="clock" message="No overtime for this selection." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="f-12 text-lightest mt-2 mb-0">
        Hours are bank hours: overtime × its rate (a week-off hour at {{ \App\Services\Payroll\OtBank::fmt((float) ($rules->s->ot_rate_weekend ?? 1.5)) }}× counts
        {{ \App\Services\Payroll\OtBank::fmt((float) ($rules->s->ot_rate_weekend ?? 1.5)) }}h). Months are salary periods (21st to 20th).
        A day = {{ \App\Services\Payroll\OtBank::fmt($dayHours) }}h; the amount is at each employee's hourly rate today. Click a month to see its days.
    </p>
</div>

<div class="modal fade" id="ot-day-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title f-16">Overtime</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="ot-day-body"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="ot-import-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title f-16">Import past OT</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="ot-import-upload">
                    <ol class="f-14 text-dark-grey pl-3 mb-3">
                        <li><a href="{{ route('ot-past.import.template') }}" class="font-weight-600"><i class="fa fa-download mr-1"></i>Download the template</a>
                            and fill one row per employee per month: Employee ID, Year, Month and the hours. The second sheet explains each column.</li>
                        <li>Months up to {{ $goLive->copy()->subDay()->format('F Y') }}; hours as banked (already times the rate). A month kept day by day: one row per day with its Date.</li>
                        <li>Upload it. Every row is checked and shown first; nothing is saved until you press Import. A month uploaded again replaces its figures.</li>
                    </ol>
                    <form id="ot-import-form" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="import_file" accept=".xlsx,.xls,.csv" class="form-control-file mb-3">
                    </form>
                    <button type="button" class="btn btn-primary rounded f-14" id="ot-import-check"><i class="fa fa-search mr-1"></i>Check the file</button>
                </div>
                <div id="ot-import-review"></div>
            </div>
        </div>
    </div>
</div>

<style>
    .ot-table th { position: sticky; top: 0; background: #f9fafb; z-index: 2; white-space: nowrap; }
    .ot-table .ot-name { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 200px; }
    .ot-table thead .ot-name { z-index: 3; background: #f9fafb; }
    .ot-table td { white-space: nowrap; }
</style>
@endsection

@push('scripts')
<script>
    $('#ot-import-check').on('click', function () {
        $.easyAjax({
            url: "{{ route('ot-past.import.preview') }}", container: '#ot-import-form', type: 'POST', file: true,
            blockUI: true, disableButton: true, buttonSelector: '#ot-import-check',
            success: function (r) {
                if (r.status === 'success') { $('#ot-import-upload').addClass('d-none'); $('#ot-import-review').html(r.html); }
            }
        });
    });

    $('#ot-import-modal').on('click', '#ot-import-again', function () {
        $('#ot-import-review').html('');
        $('#ot-import-form')[0].reset();
        $('#ot-import-upload').removeClass('d-none');
    });

    $('#ot-import-modal').on('click', '#ot-import-run', function () {
        $.easyAjax({
            url: "{{ route('ot-past.import.run') }}", container: '#ot-import-run-form', type: 'POST', blockUI: true,
            disableButton: true, buttonSelector: '#ot-import-run', data: $('#ot-import-run-form').serialize(),
            success: function (r) { if (r.status === 'success') { window.location.reload(); } }
        });
    });

    $('body').on('click', '.ot-cell', function (e) {
        e.preventDefault();
        $.easyAjax({
            url: "{{ route('ot-hours.days') }}",
            type: 'GET',
            blockUI: true,
            data: { user_id: $(this).data('user'), month: $(this).data('month'), year: {{ $year }} },
            success: function (r) {
                if (r.status === 'success') {
                    $('#ot-day-body').html(r.html);
                    $('#ot-day-modal').modal('show');
                }
            }
        });
    });
</script>
@endpush
