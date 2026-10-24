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

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        {{-- Year selector --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3 d-flex align-items-center flex-wrap">
                <div class="mr-auto">
                    <h5 class="mb-1 f-18">Leave year end — {{ $fy['label'] }}</h5>
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
        <div class="row mb-3">
            @php
                $tiles = [
                    ['Employees', number_format($result['totals']['employees']), 'text-dark-grey', 'with leave to settle'],
                    ['Carried forward', $fmt($result['totals']['carried']) . ' days', 'text-primary', 'into the next year'],
                    ['Lapsed', $fmt($result['totals']['lapsed']) . ' days', 'text-lightest', 'discarded'],
                    ['Encashed', $money($result['totals']['amount']), 'text-success',
                        $fmt($result['totals']['encashed_days']) . ' days, paid on the next payslip'],
                ];
            @endphp
            @foreach ($tiles as [$label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                            <h4 class="mb-0 f-21 {{ $colour }}">{{ $value }}</h4>
                            <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Detail --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto">
                    <h6 class="mb-0 f-14">By employee</h6>
                    <p class="mb-0 f-12 text-lightest">
                        Encashment valued from
                        <strong>{{ ucfirst($result['settings']['basis']) }}</strong>
                        @if ($result['settings']['source'] === 'ctc')
                            (CTC ÷ 12{{ $result['settings']['basis'] === 'basic' ? ', basic ' . $fmt($result['settings']['basic_pct']) . '% unless set per employee' : '' }})
                        @else
                            (latest payslip)
                        @endif
                        ÷ {{ $result['settings']['divisor_label'] ?? $fmt($result['settings']['divisor']) }} per day
                        @unless ($closed)
                            &middot; <a href="{{ route('leaves-settings.index') }}?tab=encashment">change</a>
                        @endunless
                    </p>
                </div>

                <input type="text" class="form-control form-control-sm w-auto mr-2" id="ye-filter" placeholder="Filter employees…">

                @unless ($closed)
                    <button type="button" class="btn btn-sm btn-danger" id="close-year"
                        @if ($result['totals']['employees'] === 0) disabled @endif>
                        <i class="fa fa-lock mr-1"></i> Close {{ $fy['label'] }}
                    </button>
                @endunless
            </div>

            <div class="table-responsive">
                <table class="table mb-0 f-13">
                    <thead>
                        <tr class="bg-light">
                            <th>Employee / leave</th>
                            <th>Rule</th>
                            <th class="text-right" title="Carried in from the previous year">Opening</th>
                            <th class="text-right" title="Earned during this year">Earned</th>
                            <th class="text-right" title="Approved leave taken this year">Used</th>
                            <th class="text-right">Balance</th>
                            <th class="text-right text-primary">Carry</th>
                            <th class="text-right">Lapse</th>
                            <th class="text-right text-success">Encash</th>
                            <th class="text-right text-success">Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    @forelse ($result['rows'] as $employee)
                        <tbody class="ye-employee" data-search="{{ strtolower($employee['name'] . ' ' . $employee['employee_id']) }}">
                            <tr class="bg-light-grey">
                                <td colspan="9">
                                    <strong>{{ $employee['name'] }}</strong>
                                    <span class="f-11 text-lightest ml-1">
                                        {{ $employee['employee_id'] ? 'ID ' . $employee['employee_id'] . ' · ' : '' }}
                                        joined {{ $employee['joining_date'] ? \Carbon\Carbon::parse($employee['joining_date'])->format('d M Y') : '—' }}
                                    </span>
                                </td>
                                <td class="text-right font-weight-bold text-success">
                                    {{ $employee['amount'] > 0 ? $money($employee['amount']) : '' }}
                                </td>
                                <td></td>
                            </tr>
                            @foreach ($employee['types'] as $row)
                                @php [$colour, $label] = $ruleBadge[$row['rule']] ?? ['secondary', $row['rule']]; @endphp
                                <tr>
                                    <td class="pl-4">
                                        {{ $row['type_name'] }}
                                        @if ($row['code'])
                                            <span class="badge badge-light border f-10 ml-1">{{ $row['code'] }}</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-{{ $colour }}">{{ $label }}</span></td>
                                    <td class="text-right">{{ $fmt($row['opening']) }}</td>
                                    <td class="text-right">{{ $fmt($row['accrued']) }}</td>
                                    <td class="text-right">{{ $fmt($row['used']) }}</td>
                                    <td class="text-right font-weight-bold">{{ $fmt($row['balance']) }}</td>
                                    <td class="text-right text-primary">{{ $row['carried'] > 0 ? $fmt($row['carried']) : '—' }}</td>
                                    <td class="text-right text-lightest">{{ $row['lapsed'] > 0 ? $fmt($row['lapsed']) : '—' }}</td>
                                    <td class="text-right text-success">{{ $row['encashed_days'] > 0 ? $fmt($row['encashed_days']) : '—' }}</td>
                                    <td class="text-right text-success">
                                        @if ($row['amount'] > 0)
                                            {{ $money($row['amount']) }}
                                            @if (!empty($row['rate']))
                                                <br><span class="f-10 text-lightest">{{ $money($row['rate']) }}/day</span>
                                            @endif
                                            @if (($row['payout'] ?? null) === 'paid')
                                                <br><span class="badge badge-success f-10">paid</span>
                                            @elseif (($row['payout'] ?? null) === 'pending')
                                                <br><span class="badge badge-warning f-10">awaiting payslip</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="f-11 text-lightest">
                                        {{ implode(' · ', $row['notes']) }}
                                        @if (!empty($row['salary_source']))
                                            <span class="d-block">{{ $row['salary_source'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tbody>
                            <tr>
                                <td colspan="11" class="text-center text-lightest p-4">
                                    No employee has leave to settle for this year.
                                    Leave types marked <em>unlimited</em> are never settled.
                                </td>
                            </tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>

        <p class="f-11 text-lightest">
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
            $('#ye-filter').on('keyup', function () {
                var term = $(this).val().toLowerCase().trim();
                $('.ye-employee').each(function () {
                    $(this).toggle(term === '' || $(this).data('search').indexOf(term) !== -1);
                });
            });

            $('#close-year').on('click', function () {
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
