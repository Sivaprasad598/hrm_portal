@extends('layouts.app')

@php
    use App\Services\Leaves\LeaveYearEnd;
    $fmt = fn ($v) => LeaveYearEnd::fmt((float) $v);
    $money = fn ($v) => '₹' . number_format((float) $v, 2);
    $closed = $fy['closed'];
    $ruleBadge = [
        'lapse' => ['secondary', 'Lapse'],
        'carry forward' => ['primary', 'Carry forward'],
        'paid' => ['success', 'Encash'],
    ];
@endphp

@push('styles')
    <style>
        .ye-rules { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; padding: 12px 16px; margin: -6px 0 18px;
            background: #fff; border: 1px solid var(--border-color, #e2e8f0); border-radius: var(--card-radius, 12px); font-size: 13px; }
        .ye-rules__title { font-weight: 600; color: var(--epic-muted, #6b7280); margin-right: 4px; }
        .ye-rules__item i { font-size: 10px; color: var(--epic-muted, #99a5b5); }
        .ye-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .ye-tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #f4f6f9; border-radius: 10px; }
        .ye-tabs a { padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--epic-text, #475467); text-decoration: none; }
        .ye-tabs a b { font-weight: 700; margin-left: 4px; color: var(--epic-muted, #878a99); }
        .ye-tabs a.active { background: #fff; color: var(--epic-blue, #cb6b4f); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
        .ye-tabs a.active b { color: var(--epic-blue, #cb6b4f); }
        .ye-search { position: relative; }
        .ye-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #98a2b3; }
        .ye-search input { height: 38px; width: 260px; max-width: 100%; padding: 0 12px 0 32px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; font-size: 13px; }
        .ye-search input:focus { outline: 0; border-color: var(--epic-blue, #cb6b4f); box-shadow: 0 0 0 3px color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, transparent); }
        .ye-toggle { font-size: 12.5px; font-weight: 600; color: var(--epic-blue, #cb6b4f); white-space: nowrap; }
        .ye-toggle .fa-chevron-down { font-size: 10px; transition: transform .15s ease; }
        .ye-employee.open .ye-toggle .fa-chevron-down { transform: rotate(180deg); }
        .ye-employee.open .ye-row td { background: #fafbfc; }
        .ye-detail > td { padding: 0 12px 14px !important; background: #fafbfc; }
        .ye-sub { width: 100%; font-size: 12.5px; background: #fff; border: 1px solid var(--border-color, #e8e9e9); border-radius: 8px; border-collapse: separate; overflow: hidden; }
        .ye-sub th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--epic-muted, #878a99); font-weight: 600; padding: 8px 10px; background: #fafbfc; border-bottom: 1px solid var(--border-color, #eef0f3); }
        .ye-sub td { padding: 8px 10px; border-bottom: 1px solid #f2f4f7; }
        .ye-sub tr:last-child td { border-bottom: 0; }
        .ye-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; font-size: 13px; color: var(--epic-muted, #6b7280); }
        .ye-pages { display: inline-flex; gap: 4px; flex-wrap: wrap; }
        .ye-pages a { min-width: 32px; height: 32px; padding: 0 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 6px; color: var(--epic-text, #475467); text-decoration: none; font-size: 13px; }
        .ye-pages a.active { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; }
        .ye-pages a.disabled { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        {{-- Year selector --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3 d-flex align-items-center flex-wrap">
                <div class="mr-auto">
                    <h5 class="mb-1 f-18">Year-end settlement — {{ $fy['label'] }}</h5>
                    <p class="mb-0 f-13 text-lightest">
                        {{ $fy['start']->format('d M Y') }} to {{ $fy['end']->format('d M Y') }}
                        &middot; financial year starts in {{ $yearStartMonth }}
                        (<a href="{{ route('leaves-settings.index') }}?tab=general">change</a>)
                    </p>
                </div>

                <div class="d-flex align-items-center flex-wrap">
                    @foreach ($years as $year)
                        <a href="{{ route('leave-year-end.index', ['fy' => $year['label']]) }}"
                           class="btn btn-sm mr-2 mb-1 {{ $year['label'] === $fy['label'] ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ $year['label'] }}
                            @if ($year['closed'])
                                <i class="fa fa-lock ml-1" title="Closed"></i>
                            @elseif (!$year['ended'])
                                <span class="f-11 ml-1">(in progress)</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- State of this year --}}
        @if ($closed)
            <div class="alert alert-success f-13">
                <i class="fa fa-lock mr-1"></i>
                <strong>Closed</strong> on {{ \Carbon\Carbon::parse($result['run']->run_at)->format('d M Y H:i') }}.
                These are the figures that were applied. A year can only be closed once.
            </div>
        @else
            <div class="alert alert-info f-13">
                <strong>Preview — nothing has been changed yet.</strong>
                Each leave type's <em>Unused leave</em> rule decides what happens to the balance:
                lapsed, carried forward (up to its maximum) or encashed on the next payslip.
                @unless ($fy['ended'])
                    <br><strong class="text-red">This year has not ended.</strong>
                    Closing it now works from leave taken so far, and nothing earned after today would be counted.
                @endunless
            </div>
        @endif

        @foreach ($result['warnings'] as $warning)
            <div class="alert alert-warning f-13">{{ $warning }}</div>
        @endforeach

        {{-- Totals --}}
        @php
            // What each leave type does with unused days, said once for the year
            $typeRules = collect($result['rows'])->flatMap(fn ($e) => $e['types'])
                ->unique('type_name')->map(fn ($t) => [$t['type_name'], $t['code'] ?? null, $t['rule']])->values();
            $ruleWords = ['lapse' => ['lapses', 'bad'], 'carry forward' => ['carried forward', 'ok'], 'paid' => ['encashed (paid)', 'warn']];
            // One line per employee: their totals, and what kind of settlement it is
            $people = collect($result['rows'])->map(function ($e) {
                $t = collect($e['types']);
                $e['sum'] = [
                    'balance' => (float) $t->sum('balance'), 'carried' => (float) $t->sum('carried'),
                    'lapsed' => (float) $t->sum('lapsed'), 'encashed' => (float) $t->sum('encashed_days'),
                ];
                $e['kinds'] = array_values(array_filter([
                    $e['sum']['carried'] > 0 ? 'carry' : null, $e['sum']['lapsed'] > 0 ? 'lapse' : null,
                    $e['sum']['encashed'] > 0 ? 'encash' : null,
                ]));
                $e['noted'] = $t->contains(fn ($r) => !empty($r['notes']));
                return $e;
            });
            $count = fn ($k) => $people->filter(fn ($e) => in_array($k, $e['kinds'], true))->count();
        @endphp

        <div class="ms-stats">
            <div class="ms-stat is-total">
                <div class="lbl"><span class="ic" style="--ic-bg:var(--epic-blue,#cb6b4f);--ic-fg:#fff"><i class="fa fa-users"></i></span>Employees</div>
                <div class="val">{{ number_format($result['totals']['employees']) }}</div>
                <div class="sub">with leave to settle</div>
            </div>
            <div class="ms-stat">
                <div class="lbl"><span class="ic" style="--ic-bg:#e7f6ec;--ic-fg:#15803d"><i class="fa fa-share"></i></span>Carried forward</div>
                <div class="val">{{ $fmt($result['totals']['carried']) }}<small>days</small></div>
                <div class="sub">move into next year's balance</div>
            </div>
            <div class="ms-stat">
                <div class="lbl"><span class="ic" style="--ic-bg:#fdecec;--ic-fg:#b42318"><i class="fa fa-times"></i></span>Lapsed</div>
                <div class="val">{{ $fmt($result['totals']['lapsed']) }}<small>days</small></div>
                <div class="sub">unused, and dropped</div>
            </div>
            <div class="ms-stat">
                <div class="lbl"><span class="ic" style="--ic-bg:#fff4e5;--ic-fg:#b45309"><i class="fa fa-rupee-sign"></i></span>Encashed</div>
                <div class="val">{{ $money($result['totals']['amount']) }}</div>
                <div class="sub">{{ $fmt($result['totals']['encashed_days']) }} days, paid on the next payslip</div>
            </div>
        </div>

        {{-- The rule, in plain words --}}
        @if ($typeRules->isNotEmpty())
            <div class="ye-rules">
                <span class="ye-rules__title">What happens to unused leave</span>
                @foreach ($typeRules as [$name, $code, $rule])
                    @php [$word, $tone] = $ruleWords[$rule] ?? [$rule, '']; @endphp
                    <span class="ye-rules__item"><b>{{ $name }}</b>{{ $code ? ' (' . $code . ')' : '' }} <i class="fa fa-arrow-right mx-1"></i><span class="ms-pill {{ $tone }}">{{ $word }}</span></span>
                @endforeach
            </div>
        @endif

        {{-- By employee: one line each, details on demand, a page at a time --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-0 pt-4 d-flex align-items-start justify-content-between flex-wrap">
                <div>
                    <h4 class="f-18 f-w-500 mb-0">By employee</h4>
                    <p class="ms-intro">
                        Encashment valued from <b>{{ ucfirst($result['settings']['basis']) }}</b>
                        @if ($result['settings']['source'] === 'ctc')
                            (CTC ÷ 12{{ $result['settings']['basis'] === 'basic' ? ', basic ' . $fmt($result['settings']['basic_pct']) . '% unless set per employee' : '' }})
                        @else
                            (latest payslip)
                        @endif
                        ÷ {{ $result['settings']['divisor_label'] ?? $fmt($result['settings']['divisor']) }} per day
                        @unless ($closed)
                            · <a href="{{ route('leaves-settings.index') }}?tab=encashment">change</a>
                        @endunless
                    </p>
                </div>
                @unless ($closed)
                    <button type="button" class="btn btn-danger rounded f-14 mt-1" id="close-year"
                        @if ($result['totals']['employees'] === 0) disabled @endif>
                        <i class="fa fa-lock mr-1"></i> Close {{ $fy['label'] }}
                    </button>
                @endunless
            </div>

            <div class="card-body pt-2">
                <div class="ye-bar">
                    <div class="ye-tabs" id="ye-tabs">
                        <a href="javascript:;" class="active" data-kind="">All <b>{{ $people->count() }}</b></a>
                        <a href="javascript:;" data-kind="carry">Carrying forward <b>{{ $count('carry') }}</b></a>
                        <a href="javascript:;" data-kind="lapse">Lapsing <b>{{ $count('lapse') }}</b></a>
                        <a href="javascript:;" data-kind="encash">Encashing <b>{{ $count('encash') }}</b></a>
                    </div>
                    <div class="ye-search"><i class="fa fa-search"></i><input type="text" id="ye-filter" placeholder="Search name or ID"></div>
                </div>

                <div class="table-responsive">
                    <style>
                        .ye-split { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; margin-top: 4px; }
                        .ye-split__item { font-size: 11.5px; font-weight: 500; padding: 1px 7px; border-radius: 999px; background: #f2f4f7; color: #475467; white-space: nowrap; }
                    </style>
                    <table class="ms-table ye-list">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th class="num" title="Days left at year end, by leave type">Total</th>
                                <th class="num">Carried forward</th>
                                <th class="num">Lapsed</th>
                                <th class="num">Encashed</th>
                                <th class="num"></th>
                            </tr>
                        </thead>
                        @forelse ($people as $i => $employee)
                            <tbody class="ye-employee" data-search="{{ strtolower($employee['name'] . ' ' . $employee['employee_id']) }}" data-kinds="{{ implode(' ', $employee['kinds']) }}">
                                <tr class="ye-row">
                                    <td>
                                        <b>{{ $employee['name'] }}</b>
                                        <div class="muted">{{ $employee['employee_id'] ? $employee['employee_id'] . ' · ' : '' }}joined {{ $employee['joining_date'] ? \Carbon\Carbon::parse($employee['joining_date'])->format('d M Y') : '—' }}</div>
                                    </td>
                                    <td class="num">
                                        <b>{{ $fmt($employee['sum']['balance']) }}</b> <span class="muted">days</span>
                                        {{-- which leave makes up the total: CL 8 · SL 8 · EL 12 --}}
                                        <div class="ye-split">
                                            @foreach ($employee['types'] as $t)
                                                @if ($t['balance'] > 0)
                                                    <span class="ye-split__item">{{ $t['code'] ?: $t['type_name'] }} {{ $fmt($t['balance']) }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="num">@if ($employee['sum']['carried'] > 0)<span class="ms-pill ok">{{ $fmt($employee['sum']['carried']) }} days</span>@else<span class="nil">—</span>@endif</td>
                                    <td class="num">@if ($employee['sum']['lapsed'] > 0)<span class="ms-pill bad">{{ $fmt($employee['sum']['lapsed']) }} days</span>@else<span class="nil">—</span>@endif</td>
                                    <td class="num">@if ($employee['amount'] > 0)<span class="ms-pill warn">{{ $money($employee['amount']) }}</span><div class="muted">{{ $fmt($employee['sum']['encashed']) }} days</div>@else<span class="nil">—</span>@endif</td>
                                    <td class="num">
                                        <a href="javascript:;" class="ye-toggle">Details @if ($employee['noted'])<i class="fa fa-info-circle ml-1 text-warning" title="Has notes"></i>@endif <i class="fa fa-chevron-down ml-1"></i></a>
                                    </td>
                                </tr>
                                <tr class="ye-detail d-none">
                                    <td colspan="6">
                                        <table class="ye-sub">
                                            <thead><tr><th>Leave type</th><th>Rule</th><th class="num" title="Carried in from last year">Opening</th><th class="num" title="Earned this year">Earned</th><th class="num" title="Approved leave taken">Used</th><th class="num">Left</th><th class="num">Carry</th><th class="num">Lapse</th><th class="num">Encash</th><th>Notes</th></tr></thead>
                                            <tbody>
                                            @foreach ($employee['types'] as $row)
                                                @php [$word, $tone] = $ruleWords[$row['rule']] ?? [$row['rule'], '']; @endphp
                                                <tr>
                                                    <td><b>{{ $row['type_name'] }}</b>{{ $row['code'] ? ' (' . $row['code'] . ')' : '' }}</td>
                                                    <td><span class="ms-pill {{ $tone }}">{{ $word }}</span></td>
                                                    <td class="num">{{ $fmt($row['opening']) }}</td>
                                                    <td class="num">{{ $fmt($row['accrued']) }}</td>
                                                    <td class="num">{{ $fmt($row['used']) }}</td>
                                                    <td class="num"><b>{{ $fmt($row['balance']) }}</b></td>
                                                    <td class="num">{!! $row['carried'] > 0 ? $fmt($row['carried']) : '<span class="nil">—</span>' !!}</td>
                                                    <td class="num">{!! $row['lapsed'] > 0 ? $fmt($row['lapsed']) : '<span class="nil">—</span>' !!}</td>
                                                    <td class="num">
                                                        @if ($row['amount'] > 0)
                                                            {{ $fmt($row['encashed_days']) }} = {{ $money($row['amount']) }}
                                                            @if (!empty($row['rate']))<div class="muted">{{ $money($row['rate']) }}/day</div>@endif
                                                            @if (($row['payout'] ?? null) === 'paid')<div><span class="ms-pill ok">paid</span></div>
                                                            @elseif (($row['payout'] ?? null) === 'pending')<div><span class="ms-pill warn">awaiting payslip</span></div>@endif
                                                        @else<span class="nil">—</span>@endif
                                                    </td>
                                                    <td class="muted">{{ implode(' · ', $row['notes']) }}@if (!empty($row['salary_source']))<div>{{ $row['salary_source'] }}</div>@endif</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody><tr><td colspan="6"><div class="ms-empty"><i class="fa fa-calendar-check"></i><b>Nothing to settle</b>No employee has leave to settle for this year. Unlimited leave types are never settled.</div></td></tr></tbody>
                        @endforelse
                        <tbody id="ye-none" class="d-none"><tr><td colspan="6"><div class="ms-empty py-3">No employee matches.</div></td></tr></tbody>
                    </table>
                </div>

                {{-- A page at a time --}}
                <div class="ye-pager" id="ye-pager">
                    <span id="ye-showing"></span>
                    <span class="ye-pages" id="ye-pages"></span>
                </div>
            </div>
        </div>

        <p class="f-12 text-lightest">
            Earned leave counts a month when the employee was eligible by the 15th of it; yearly leave is earned
            in proportion to those months, rounded to the half day. Leave available only after confirmation
            starts the day after the probation end date. Employees who have left are settled through their exit,
            not here.
        </p>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // One page of employees at a time, after the tab and the search
            var PER = 15, page = 1, kind = '', term = '';
            var $all = $('.ye-employee');

            function draw() {
                var match = $all.filter(function () {
                    var e = $(this);
                    return (term === '' || String(e.data('search')).indexOf(term) !== -1)
                        && (kind === '' || (' ' + e.data('kinds') + ' ').indexOf(' ' + kind + ' ') !== -1);
                });
                var pages = Math.max(1, Math.ceil(match.length / PER));
                page = Math.min(page, pages);
                $all.hide();
                match.slice((page - 1) * PER, page * PER).show();
                $('#ye-none').toggleClass('d-none', match.length > 0);
                $('#ye-showing').text(match.length
                    ? 'Showing ' + ((page - 1) * PER + 1) + '–' + Math.min(page * PER, match.length) + ' of ' + match.length + ' employees'
                    : '');
                var html = '<a href="javascript:;" data-p="' + (page - 1) + '" class="' + (page === 1 ? 'disabled' : '') + '">‹</a>';
                for (var p = 1; p <= pages; p++) {
                    if (pages > 9 && p !== 1 && p !== pages && Math.abs(p - page) > 2) {
                        if (p === 2 || p === pages - 1) { html += '<a class="disabled">…</a>'; }
                        continue;
                    }
                    html += '<a href="javascript:;" data-p="' + p + '" class="' + (p === page ? 'active' : '') + '">' + p + '</a>';
                }
                html += '<a href="javascript:;" data-p="' + (page + 1) + '" class="' + (page === pages ? 'disabled' : '') + '">›</a>';
                $('#ye-pages').html(html);
            }

            $('#ye-pages').off('click.ye').on('click.ye', 'a[data-p]', function () { page = +$(this).data('p'); draw(); });
            $('#ye-tabs').off('click.ye').on('click.ye', 'a', function () {
                $('#ye-tabs a').removeClass('active'); $(this).addClass('active');
                kind = $(this).data('kind') || ''; page = 1; draw();
            });
            $('#ye-filter').off('input.ye').on('input.ye', function () { term = $(this).val().toLowerCase().trim(); page = 1; draw(); });
            $(document).off('click.yeToggle').on('click.yeToggle', '.ye-toggle', function () {
                var e = $(this).closest('.ye-employee');
                e.toggleClass('open');
                e.find('.ye-detail').toggleClass('d-none', !e.hasClass('open'));
            });
            draw();

            $('#close-year').off('click.ye').on('click.ye', function () {
                Swal.fire({
                    title: 'Close {{ $fy['label'] }}?',
                    html: '<p class="mb-2" style="font-size:13px">' +
                        '{{ $fmt($result['totals']['carried']) }} days carried forward, ' +
                        '{{ $fmt($result['totals']['lapsed']) }} lapsed, ' +
                        '{{ $fmt($result['totals']['encashed_days']) }} encashed for ' +
                        '{{ $money($result['totals']['amount']) }}.</p>' +
                        '<p class="mb-0" style="font-size:12px;color:#888">This can only be done once for this year. ' +
                        'Encashments are paid on each employee\'s next payslip.</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Close the year',
                    confirmButtonColor: '#d33',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    $.easyAjax({
                        url: "{{ route('leave-year-end.apply') }}",
                        type: 'POST',
                        blockUI: true,
                        data: { fy: '{{ $fy['label'] }}', _token: '{{ csrf_token() }}' },
                        success: function (response) {
                            if (response.status === 'success') {
                                window.location.reload();
                            }
                        }
                    });
                });
            });
        });
    </script>
@endpush
