@extends('layouts.app')

@php
    $h = fn ($v) => $v == 0 ? '–' : \App\Services\Payroll\OtBank::fmt(round($v, 2)) . 'h';
    $monthName = fn ($m) => \Carbon\Carbon::create($year, $m, 1)->format('M');
@endphp

@section('content')
{{-- Standard layout (as Run Payroll): Filters and Import Past OT on the tab row,
     the filter card hidden until "Filters", stat cards, the table in a card. --}}
@push('page-tab-actions')
    <button type="button" class="btn-primary rounded f-14" data-toggle="modal" data-target="#ot-import-modal">
        <i class="fa fa-file-upload mr-1"></i>Import Past OT
    </button>
@endpush
@include('sections.card-filter-toggle', ['card' => '#ot-filter-card',
    'active' => $month || $departmentId || $show !== 'with_ot' || (string) $search !== ''])

<div class="content-wrapper arw-dash">

    {{-- Filters (hidden until "Filters" at the top right) --}}
    <div class="card bg-white border-0 b-shadow-4 mb-3" id="ot-filter-card">
        <div class="card-body p-3">
            {{-- Applied as soon as a filter changes (no Apply button): drop-downs at
                 once, the search after a pause in typing or on Enter. --}}
            <form method="GET" action="{{ route('ot-hours.index') }}" id="ot-filter-form">
                <div class="row align-items-end">
                    <div class="col-lg-2 col-md-4 col-6 mb-2">
                        <label class="f-12 text-dark-grey">Year</label>
                        <select name="year" class="form-control f-14 select-picker">
                            @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-2">
                        <label class="f-12 text-dark-grey">Month</label>
                        <select name="month" class="form-control f-14 select-picker">
                            <option value="">Whole year</option>
                            @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m == $month)>{{ \Carbon\Carbon::create($year, $m, 1)->format('F') }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-2">
                        <label class="f-12 text-dark-grey">Division</label>
                        <select name="department" class="form-control f-14 select-picker" data-live-search="true">
                            <option value="">All</option>
                            @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($id == $departmentId)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-2">
                        <label class="f-12 text-dark-grey">Show</label>
                        <select name="show" class="form-control f-14 select-picker">
                            <option value="with_ot" @selected($show === 'with_ot')>Employees with OT</option>
                            <option value="all" @selected($show === 'all')>Everyone</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-12 mb-2">
                        <label class="f-12 text-dark-grey">Search</label>
                        <input type="text" name="search" value="{{ $search }}" class="form-control f-14" id="ot-search"
                               placeholder="Name or employee ID" autocomplete="off">
                    </div>
                    <div class="col-lg-1 col-md-6 col-12 mb-2">
                        <a href="{{ route('ot-hours.index') }}" class="btn btn-secondary f-14 w-100">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if (!$rules || !$rules->s->ot_enabled)
        <div class="alert alert-warning f-13">Overtime is switched off. Turn it on under Settings → Attendance Settings → Overtime.</div>
    @endif

    <div class="alert alert-info f-13 d-flex align-items-start">
        <i class="fa fa-info-circle mr-2 mt-1"></i>
        <div>The system counts overtime from attendance from <strong>{{ $goLive->format('d M Y') }}</strong>.
            Earlier months come from past records <span class="badge badge-info f-10">past</span>, entered on each employee's
            Overtime Balance tab or uploaded with <strong>Import Past OT</strong> (Employee ID, Year, Jan … Dec).</div>
    </div>

    {{-- Totals --}}
    <div class="row mb-3">
        @foreach ([
            ['OT earned', $h($totals['earned']), $month ? \Carbon\Carbon::create($year, $month, 1)->format('F Y') : 'in ' . $year, 'text-primary'],
            ['Used for LOP', $h($totals['used_hours']), \App\Services\Payroll\OtBank::fmt($totals['used_days']) . ' day(s) covered', 'text-dark-grey'],
            ['Encashed', $h($totals['encashed_hours']), '₹' . number_format($totals['encashed_amount'], 2), 'text-dark-grey'],
            ['Balance held', $h($totals['balance']), '≈ ' . \App\Services\Payroll\OtBank::fmt(round($totals['balance'] / max(0.01, $dayHours), 1)) . ' days · ≈ ₹' . number_format($totals['balance_amount'], 0), 'text-success'],
        ] as [$label, $value, $sub, $class])
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                        <h4 class="mb-0 f-21 {{ $class }}">{{ $value }}</h4>
                        <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- The table --}}
    <div class="card bg-white border-0 b-shadow-4 mb-3">
        <div class="card-header bg-white border-bottom p-3">
            <h6 class="mb-0 f-14">Overtime by month · {{ $month ? \Carbon\Carbon::create($year, $month, 1)->format('F Y') : $year }}</h6>
            <p class="mb-0 f-12 text-lightest">{{ count($rows) }} employee(s) · bank hours per salary month · click a month to see its days</p>
        </div>
        <div class="table-responsive" style="max-height:70vh">
            <table class="table table-hover mb-0 f-13 ot-table">
                <thead>
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
        <p class="f-11 text-lightest mb-0 px-3 py-2 border-top">
            Hours are bank hours: overtime × its rate (a week-off hour at {{ \App\Services\Payroll\OtBank::fmt((float) ($rules->s->ot_rate_weekend ?? 1.5)) }}× counts
            {{ \App\Services\Payroll\OtBank::fmt((float) ($rules->s->ot_rate_weekend ?? 1.5)) }}h). Months are salary periods (21st to 20th).
            A day = {{ \App\Services\Payroll\OtBank::fmt($dayHours) }}h; the amount is at each employee's hourly rate today.
        </p>
    </div>
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
                        <li><a href="{{ route('ot-past.import.template') }}" class="font-weight-600"><i class="fa fa-download mr-1"></i>Download the template</a>:
                            <strong>Employee ID, Year, Jan … Dec</strong>, one row per employee per year with each month's OT hours
                            (leave a month empty when there was none; two years of history = two rows).</li>
                        <li>Months up to {{ $goLive->copy()->subDay()->format('F Y') }} only (from {{ $goLive->format('d M Y') }} OT comes from attendance).
                            For an opening balance only, put the total in {{ $goLive->copy()->subDay()->format('M Y') }}. The second sheet explains each column.</li>
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
    .ot-table th { position: sticky; top: 0; background: #f8f9fa !important; z-index: 2; white-space: nowrap; box-shadow: inset 0 -1px 0 #dee2e6; }
    .ot-table .ot-name { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 200px; }
    .ot-table thead .ot-name { z-index: 3; background: #f8f9fa !important; }
    .ot-table td { white-space: nowrap; }
</style>
@endsection

@push('scripts')
<script>
    // Filters apply on change. The filter card is kept open across the reload,
    // so the next filter can be picked straight away.
    (function () {
        var form = document.getElementById('ot-filter-form');
        var submitted = false;
        var submit = function () {
            if (submitted) { return; }
            submitted = true;
            try { sessionStorage.setItem('ot-filters-open', '1'); } catch (e) {}
            form.submit();
        };

        $('#ot-filter-form select').on('change', submit);

        var timer = null, last = $('#ot-search').val();
        $('#ot-search').on('input', function () {
            clearTimeout(timer);
            var value = $(this).val();
            timer = setTimeout(function () { if (value !== last) { submit(); } }, 700);
        }).on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); submit(); }
        });

        var reopen = false;
        try { reopen = sessionStorage.getItem('ot-filters-open') === '1'; sessionStorage.removeItem('ot-filters-open'); } catch (e) {}
        if (reopen) {
            document.body.classList.add('card-filters-open');
            $('.card-filter-btn').addClass('is-open').attr('aria-expanded', 'true');
            // the search keeps its focus, cursor at the end, while typing on
            if ($('#ot-search').val()) {
                var input = document.getElementById('ot-search');
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        }
    })();

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
