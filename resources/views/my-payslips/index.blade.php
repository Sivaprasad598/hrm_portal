@extends('layouts.app')

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $round = fn ($v) => '₹' . number_format((float) $v, 0);
        $fy = \App\Services\Payroll\TaxCalculator::financialYear();
        $fyStart = \App\Services\Payroll\TaxCalculator::yearStart($fy);
        // The current financial year's slips (by the period they pay for)
        $thisYear = $payslips->filter(fn ($p) => $p->period_start && \Carbon\Carbon::parse($p->period_start)->gte($fyStart));
        $latest = $payslips->first();
        $tds = (float) $thisYear->sum('tds');
        $pfEsi = (float) $thisYear->sum('pf') + (float) $thisYear->sum('esi');
        $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp

    <style>
        /* the app's standard: no heading (the top bar names the page); counts and the
           payslips as white rounded cards on the grey page, a count badge on the section */
        .mp-std .mp-bar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; }
        body.epic .mp-std .mp-bar .btn { height: 38px; border-radius: 8px !important; display: inline-flex; align-items: center; padding: 0 14px !important; font-size: 14px; }
        .mp-std .ms-stats { margin-bottom: 16px; }
        .mp-std .ms-stats > * { background: #fff; border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
        .mp-std .mp-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 16px 20px; margin-bottom: 16px; }
        .mp-std .mp-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .mp-std .mp-card-head h5 { font-size: 15px; font-weight: 600; margin: 0; color: #1f2937; }
        .mp-std .mp-count { background: #f3f4f6; color: #4b5563; border-radius: 10px; font-size: 12px; padding: 1px 8px; }
        .mp-std .ms-empty { border: 1px dashed #e5e7eb; border-radius: 10px; }
    </style>

    {{-- (no heading or intro text: the top bar names the page) --}}
    <div class="content-wrapper mp-std">
        {{-- Only once a payslip has been issued: before that there is nothing to certify. --}}
        @if ($payslips->isNotEmpty())
            <div class="mp-bar">
                <a href="{{ route('my-payslips.form-16') }}" class="btn btn-outline-primary rounded f-14">
                    <i class="fa fa-file-alt mr-1"></i> My Form 16
                </a>
            </div>
        @endif

        <div>
            <div>
                <div class="ms-stats">
                    <div class="ms-stat is-total">
                        <div class="lbl"><span class="ic" style="--ic-bg:var(--epic-blue,#cb6b4f);--ic-fg:#fff"><i class="fa fa-wallet"></i></span>Last take-home</div>
                        <div class="val">{{ $latest ? $round($latest->take_home) : '--' }}</div>
                        <div class="sub">{{ $latest ? $latest->period : 'no payslip yet' }}</div>
                    </div>
                    <div class="ms-stat">
                        <div class="lbl"><span class="ic" style="--ic-bg:#e7f6ec;--ic-fg:#15803d"><i class="fa fa-coins"></i></span>Paid this year</div>
                        <div class="val">{{ $round($thisYear->sum('take_home')) }}</div>
                        <div class="sub">take-home, FY {{ $fy }} ({{ $thisYear->count() }} {{ $thisYear->count() === 1 ? 'month' : 'months' }})</div>
                    </div>
                    <div class="ms-stat">
                        <div class="lbl"><span class="ic" style="--ic-bg:#eef2ff;--ic-fg:#4338ca"><i class="fa fa-piggy-bank"></i></span>PF &amp; ESI this year</div>
                        <div class="val">{{ $round($pfEsi) }}</div>
                        <div class="sub">your share, saved for you</div>
                    </div>
                    <div class="ms-stat">
                        <div class="lbl"><span class="ic" style="--ic-bg:#fff4e5;--ic-fg:#b45309"><i class="fa fa-landmark"></i></span>Tax (TDS) this year</div>
                        <div class="val">{{ $round($tds) }}</div>
                        <div class="sub">{{ $tds ? 'shown on your Form 16' : 'no tax deducted so far' }}</div>
                    </div>
                </div>

                <div class="mp-card">
                <div class="mp-card-head"><h5>Payslips</h5><span class="mp-count">{{ $payslips->count() }}</span></div>

                @if ($payslips->isEmpty())
                    <div class="ms-empty">
                        <i class="fa fa-file-invoice"></i>
                        <b>No payslips yet</b>
                        One appears here for each month once payroll has issued and approved it.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="ms-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Payslip no.</th>
                                    <th class="num">Days paid</th>
                                    <th class="num">Take-home</th>
                                    <th>Status</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payslips as $payslip)
                                    <tr>
                                        <td>
                                            <b>{{ $payslip->period }}</b>
                                            @if ($payslip->period_start)
                                                <div class="muted">{{ \Carbon\Carbon::parse($payslip->period_start)->format('d M') }} – {{ \Carbon\Carbon::parse($payslip->period_end)->format('d M Y') }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $payslip->payslip_number }}</td>
                                        <td class="num">{{ $days($payslip->days_present) }} <span class="muted">/ {{ $days($payslip->working_days) }}</span></td>
                                        <td class="num">
                                            <b class="f-15">{{ $money($payslip->take_home) }}</b>
                                            @if ($payslip->extra_earnings > 0 || $payslip->extra_deductions > 0)
                                                <div class="muted">
                                                    @if ($payslip->extra_earnings > 0)<span class="text-success">+{{ $money($payslip->extra_earnings) }} extra</span>@endif
                                                    @if ($payslip->extra_deductions > 0) <span class="text-danger ml-1">−{{ $money($payslip->extra_deductions) }} deducted</span>@endif
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="ms-pill {{ $payslip->approval_status === 'paid' ? 'ok' : '' }}">{{ $payslip->approval_status === 'paid' ? 'Paid' : 'Approved' }}</span>
                                            @if ($payslip->sent_at)
                                                <div class="muted">e-mailed {{ \Carbon\Carbon::parse($payslip->sent_at)->format('d M Y') }}</div>
                                            @endif
                                        </td>
                                        <td class="num">
                                            <a href="{{ route('my-payslips.show', $payslip->id) }}" class="btn btn-sm btn-outline-secondary mr-1"><i class="fa fa-eye mr-1"></i>View</a>
                                            <a href="{{ route('my-payslips.pdf', $payslip->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-download mr-1"></i>PDF</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>
@endsection
