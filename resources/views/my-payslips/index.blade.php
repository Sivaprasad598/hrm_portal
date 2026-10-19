@extends('layouts.app')

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
    @endphp

    <div class="content-wrapper">
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto">
                <h6 class="mb-0 f-14">Your payslips</h6>
                <p class="mb-0 f-12 text-lightest">
                    Every payslip issued to you, newest first. Open one to read it, or download it to keep.
                    A payslip appears here once payroll has approved it.
                </p>
                </div>
                {{-- The one tax document an employee actually needs for filing. --}}
                {{-- Only once a payslip has been issued: before that there is nothing to certify (the page is a 404). --}}
                @if ($payslips->isNotEmpty())
                    <a href="{{ route('my-payslips.form-16') }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                        <i class="fa fa-file-alt mr-1"></i> My Form 16
                    </a>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead>
                        <tr>
                            <th>@lang('app.month')</th>
                            <th>Payslip no.</th>
                            <th class="text-center">Days paid</th>
                            <th class="text-right">Take-home</th>
                            <th>Status</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payslips as $payslip)
                            <tr>
                                <td>
                                    {{ $payslip->period }}
                                    @if ($payslip->period_start)
                                        <span class="f-11 text-lightest d-block">
                                            {{ \Carbon\Carbon::parse($payslip->period_start)->format('d M') }}
                                            to {{ \Carbon\Carbon::parse($payslip->period_end)->format('d M Y') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="f-12">{{ $payslip->payslip_number }}</td>
                                <td class="text-center">
                                    {{ rtrim(rtrim(number_format((float) $payslip->days_present, 2), '0'), '.') }}
                                    <span class="text-lightest">/ {{ $payslip->working_days }}</span>
                                </td>
                                <td class="text-right font-weight-bold">
                                    {{ $money($payslip->take_home) }}
                                    @if ($payslip->extra_earnings > 0 || $payslip->extra_deductions > 0)
                                        <span class="f-11 text-lightest d-block">
                                            @if ($payslip->extra_earnings > 0)
                                                includes {{ $money($payslip->extra_earnings) }} extra
                                            @endif
                                            @if ($payslip->extra_deductions > 0)
                                                less {{ $money($payslip->extra_deductions) }} deducted
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $payslip->approval_status === 'paid' ? 'success' : 'light border' }}">
                                        {{ $payslip->approval_status === 'paid' ? 'Paid' : 'Approved' }}
                                    </span>
                                    @if ($payslip->sent_at)
                                        <span class="f-11 text-lightest d-block">
                                            e-mailed {{ \Carbon\Carbon::parse($payslip->sent_at)->format('d M Y') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('my-payslips.show', $payslip->id) }}"
                                       class="btn btn-sm btn-outline-secondary mr-1">
                                        <i class="fa fa-eye mr-1"></i> View
                                    </a>
                                    <a href="{{ route('my-payslips.pdf', $payslip->id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-download mr-1"></i> PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-lightest p-5 f-13">
                                    You have no payslips yet. One appears here for each month once payroll
                                    has issued and approved it.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
