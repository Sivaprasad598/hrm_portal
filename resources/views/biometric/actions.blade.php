@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab, 'outstanding' => $counts['total']])

        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">To remove</p>
                        <h4 class="mb-0 f-21 text-dark-grey">{{ $counts['total'] }}</h4>
                        <p class="mb-0 f-11 text-lightest">enrolment{{ $counts['total'] === 1 ? '' : 's' }} on the devices</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Still punching</p>
                        <h4 class="mb-0 f-21 {{ $counts['still_punching'] > 0 ? 'text-danger' : 'text-dark-grey' }}">
                            {{ $counts['still_punching'] }}
                        </h4>
                        <p class="mb-0 f-11 text-lightest">after their last working day</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Can still sign in</p>
                        <h4 class="mb-0 f-21 {{ $counts['login_open'] > 0 ? 'text-warning' : 'text-dark-grey' }}">
                            {{ $counts['login_open'] }}
                        </h4>
                        <p class="mb-0 f-11 text-lightest">ERP account still open</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Terminated</p>
                        <h4 class="mb-0 f-21 text-dark-grey">{{ $counts['terminated'] }}</h4>
                        <p class="mb-0 f-11 text-lightest">of those listed</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info f-13 mb-3" role="alert">
            <i class="fa fa-info-circle mr-1"></i>
            This is a notice, not an action. The integration only ever reads from the
            devices &mdash; it cannot remove an enrolment, and will not try. Take each
            person off on the device itself, then they stop appearing here.
        </div>

        <x-cards.data :title="'Enrolments belonging to people who have left'">
            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Device code</th>
                            <th>Why</th>
                            <th>Last working day</th>
                            <th>Last punch</th>
                            <th>ERP login</th>
                            <th>What to do</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="{{ $row['punches_after_leaving'] > 0 ? 'bg-light-red' : '' }}">
                                <td>
                                    <span class="f-13 text-dark-grey">{{ $row['name'] }}</span>
                                    @if ($row['email'])
                                        <br><span class="f-11 text-lightest">{{ $row['email'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="f-13 text-dark-grey">{{ $row['emp_code'] }}</span>
                                    @if ($row['card_id'] && $row['card_id'] !== '0000')
                                        <br><span class="f-11 text-lightest">card {{ $row['card_id'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $row['reason'] === 'terminated' ? 'danger' : 'warning' }} f-11">
                                        {{ $row['reason_label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="f-13 text-dark-grey">
                                        {{ \Carbon\Carbon::parse($row['last_working_date'])->format('d-m-Y') }}
                                    </span>
                                    <br><span class="f-11 text-lightest">{{ $row['days_since'] }} day{{ $row['days_since'] === 1 ? '' : 's' }} ago</span>
                                </td>
                                <td>
                                    @if ($row['last_seen_at'])
                                        <span class="f-13 text-dark-grey">
                                            {{ \Carbon\Carbon::parse($row['last_seen_at'])->format('d-m-Y H:i') }}
                                        </span>
                                        <br><span class="f-11 text-lightest">{{ number_format($row['punch_count']) }} punches in all</span>
                                    @else
                                        <span class="f-13 text-lightest">never</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['erp_login_open'])
                                        <span class="badge badge-warning f-11">still open</span>
                                    @else
                                        <span class="badge badge-secondary f-11">closed</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['punches_after_leaving'] > 0)
                                        <span class="f-13 text-danger font-weight-bold">
                                            Used {{ $row['punches_after_leaving'] }} time{{ $row['punches_after_leaving'] === 1 ? '' : 's' }} since leaving
                                        </span>
                                        <br>
                                        <span class="f-11 text-lightest">
                                            most recently
                                            {{ \Carbon\Carbon::parse($row['last_punch_after_leaving'])->format('d-m-Y H:i') }}
                                            &mdash; remove this enrolment on the device
                                        </span>
                                    @else
                                        <span class="f-13 text-dark-grey">Remove this enrolment on the device</span>
                                        @if (!$row['device_active'])
                                            <br><span class="f-11 text-lightest">the device already shows it inactive</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">Nothing outstanding.</p>
                                    <p class="mb-0 f-13 text-lightest">
                                        @if ($departedWithoutEnrolment > 0)
                                            {{ $departedWithoutEnrolment }}
                                            {{ $departedWithoutEnrolment === 1 ? 'person has' : 'people have' }}
                                            left, and none of them holds an enrolment on the devices.
                                        @else
                                            Nobody who has left still holds an enrolment on the devices.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-cards.data>
    </div>
@endsection
