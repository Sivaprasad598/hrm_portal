@extends('layouts.app')

@push('styles')
    <style>
        .return-table th, .return-table td { white-space: nowrap; }
        .ecr-preview { font-family: Consolas, monospace; font-size: 11px; background: #1a202c; color: #cbd5e0;
                       padding: 10px; border-radius: 6px; max-height: 220px; overflow: auto; white-space: pre; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $short = function ($v) {
            $v = (float) $v;

            return $v >= 100000 ? '₹' . number_format($v / 100000, 2) . 'L' : '₹' . number_format($v);
        };
    @endphp

    <div class="content-wrapper">

        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('statutory-returns.index') }}" class="row align-items-end">
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.month')</label>
                        <select name="month" class="form-control f-14">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($m === $month)>
                                    {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.year')</label>
                        <select name="year" class="form-control f-14">
                            @foreach (range(now()->year - 3, now()->year) as $y)
                                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary f-14 w-100">@lang('app.apply')</button>
                    </div>
                    <div class="col-md-5 mb-2">
                        <a href="{{ route('statutory-returns.spreadsheet', ['which' => 'all', 'month' => $month, 'year' => $year]) }}"
                           class="btn btn-outline-secondary f-14 w-100">
                            <i class="fa fa-file-excel mr-1"></i> All three as one workbook
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($rows === [])
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-body text-center p-5">
                    <h6 class="f-16 mb-2">No payslips were issued for {{ $monthName }}</h6>
                    <p class="f-13 text-lightest mb-3">There is nothing to file until payroll has run.</p>
                    <a href="{{ route('payroll-run.index', ['month' => $month, 'year' => $year]) }}"
                       class="btn btn-sm btn-primary">Run payroll</a>
                </div>
            </div>
        @else
            @if ($readiness !== [])
                <div class="alert alert-warning f-13">
                    <strong>Check these before filing.</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($readiness as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                    <p class="mb-0 mt-1 f-12">
                        Fill them in with the
                        <a href="{{ route('employee-import.index') }}">spreadsheet import</a>, then come back.
                    </p>
                </div>
            @endif

            <div class="alert alert-info f-12">
                Every figure here comes from the {{ count($rows) }} payslip(s) issued for {{ $monthName }}, so what
                is filed and what was paid are the same thing. <strong>Treat the first upload of each file as a
                test</strong> — a portal can reject a file over a detail no published format mentions.
            </div>

            <div class="row">
                {{-- ------------------------------------------ provident fund --}}
                <div class="col-lg-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-header bg-white border-bottom d-flex align-items-center p-3">
                            <div class="mr-auto">
                                <h6 class="mb-0 f-14">Provident fund</h6>
                                <p class="mb-0 f-12 text-lightest">{{ $pf['totals']['members'] }} member(s)</p>
                            </div>
                            <a href="{{ route('statutory-returns.pf', ['month' => $month, 'year' => $year]) }}"
                               class="btn btn-sm btn-primary">
                                <i class="fa fa-download mr-1"></i> ECR text file
                            </a>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-sm f-13 mb-2">
                                <tr><td>Employee contribution</td><td class="text-right">{{ $money($pf['totals']['employee']) }}</td></tr>
                                <tr><td>Employer — provident fund</td><td class="text-right">{{ $money($pf['totals']['employer_epf']) }}</td></tr>
                                <tr><td>Employer — pension (8.33%)</td><td class="text-right">{{ $money($pf['totals']['pension']) }}</td></tr>
                                <tr class="font-weight-bold"><td>To remit</td><td class="text-right">{{ $money($pf['totals']['total']) }}</td></tr>
                            </table>

                            @if ($pf['lines'] !== [])
                                @php
                                    // The first few lines exactly as the portal reads them.
                                    $sample = array_slice($pf['lines'], 0, 4);
                                    $sampleText = implode("\n", array_map(fn ($line) => implode('#~#', $line), $sample));

                                    if (count($pf['lines']) > count($sample)) {
                                        $sampleText .= "\n… and " . (count($pf['lines']) - count($sample)) . ' more';
                                    }
                                @endphp
                                <p class="f-11 text-lightest mb-1">The first lines of the file, as the portal reads them:</p>
                                <div class="ecr-preview">{{ $sampleText }}</div>
                            @endif

                            @foreach ($pf['problems'] as $problem)
                                <p class="f-12 text-red mb-0 mt-1">{{ $problem }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- -------------------------------------- employee insurance --}}
                <div class="col-lg-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-header bg-white border-bottom d-flex align-items-center p-3">
                            <div class="mr-auto">
                                <h6 class="mb-0 f-14">Employee insurance</h6>
                                <p class="mb-0 f-12 text-lightest">{{ $esi['totals']['members'] }} insured person(s)</p>
                            </div>
                            <a href="{{ route('statutory-returns.spreadsheet', ['which' => 'esi', 'month' => $month, 'year' => $year]) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-file-excel mr-1"></i> Return
                            </a>
                        </div>
                        <div class="card-body p-3">
                            @if ($esi['totals']['members'] > 0)
                                <table class="table table-sm f-13 mb-2">
                                    <tr><td>Wages</td><td class="text-right">{{ $money($esi['totals']['wages']) }}</td></tr>
                                    <tr><td>Employee contribution</td><td class="text-right">{{ $money($esi['totals']['employee']) }}</td></tr>
                                    <tr><td>Employer contribution</td><td class="text-right">{{ $money($esi['totals']['employer']) }}</td></tr>
                                    <tr class="font-weight-bold"><td>To remit</td><td class="text-right">{{ $money($esi['totals']['total']) }}</td></tr>
                                </table>
                                @foreach ($esi['problems'] as $problem)
                                    <p class="f-12 text-red mb-0">{{ $problem }}</p>
                                @endforeach
                            @else
                                <p class="f-13 text-lightest mb-0">
                                    Nobody was within the wage threshold this month, so there is nothing to file.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ----------------------------------------- professional tax --}}
                <div class="col-lg-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-header bg-white border-bottom d-flex align-items-center p-3">
                            <div class="mr-auto">
                                <h6 class="mb-0 f-14">Professional tax</h6>
                                <p class="mb-0 f-12 text-lightest">{{ $pt['totals']['people'] }} employee(s)</p>
                            </div>
                            <a href="{{ route('statutory-returns.spreadsheet', ['which' => 'pt', 'month' => $month, 'year' => $year]) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-file-excel mr-1"></i> Challan detail
                            </a>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-sm f-13 mb-0">
                                <thead>
                                    <tr><th>Per head</th><th class="text-center">People</th><th class="text-right">Total</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($pt['bands'] as $band)
                                        <tr>
                                            <td>{{ $money($band['amount']) }}</td>
                                            <td class="text-center">{{ $band['people'] }}</td>
                                            <td class="text-right">{{ $money($band['total']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-lightest">Nothing due this month.</td></tr>
                                    @endforelse
                                </tbody>
                                @if ($pt['bands'] !== [])
                                    <tfoot>
                                        <tr class="font-weight-bold">
                                            <td>To remit</td>
                                            <td class="text-center">{{ $pt['totals']['people'] }}</td>
                                            <td class="text-right">{{ $money($pt['totals']['total']) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                {{-- --------------------------------------------- income tax --}}
                <div class="col-lg-6 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-header bg-white border-bottom d-flex align-items-center p-3">
                            <div class="mr-auto">
                                <h6 class="mb-0 f-14">Income tax deducted</h6>
                                <p class="mb-0 f-12 text-lightest">for the quarterly return and Form 16</p>
                            </div>
                            <a href="{{ route('income-tax.index') }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-calculator mr-1"></i> Income tax
                            </a>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-sm f-13 mb-0">
                                <tr><td>Deducted this month</td><td class="text-right">{{ $money($totals['tds']) }}</td></tr>
                                <tr><td>Employees it was taken from</td>
                                    <td class="text-right">{{ count(array_filter($rows, fn ($r) => (float) $r['tds'] > 0)) }}</td></tr>
                            </table>
                            <p class="f-12 text-lightest mb-0 mt-2">
                                The quarterly statement (Form 24Q) is filed on the income tax portal, and Part A of
                                Form 16 is downloaded from TRACES afterwards. Part B is produced here.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
