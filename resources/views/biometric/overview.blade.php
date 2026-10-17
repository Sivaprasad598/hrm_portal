@extends('layouts.app')

@push('styles')
    <style>
        .bio-flow { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .bio-step { position: relative; border-top: 3px solid var(--step-color, #d0d5dd); }
        .bio-step .bio-step-no { width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center;
            justify-content: center; font-size: 12px; font-weight: 600; color: #fff; background: var(--step-color, #98a2b3); }
        .bio-step:not(:last-child)::after { content: '\f054'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
            position: absolute; right: -12px; top: 42%; color: #c0c6cf; font-size: 12px; z-index: 1; }
        .bio-step .bio-big { font-size: 20px; font-weight: 600; }
        .bio-ok { --step-color: #12b76a; }
        .bio-warn { --step-color: #f79009; }
        .bio-bad { --step-color: #f04438; }
        .bio-off { --step-color: #98a2b3; }
        .bio-switch { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; margin-right: 14px; }
        .bio-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        @media (max-width: 991.98px) {
            .bio-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .bio-step::after { display: none; }
        }
        @media (max-width: 575.98px) {
            .bio-flow { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@php
    $tz = company()->timezone;
    $fmt = fn ($c) => $c ? $c->copy()->timezone($tz)->format('d M, h:i A') : '—';
    $ago = fn ($c) => $c ? $c->diffForHumans() : 'never';

    $pullState = !$status->deviceInUse() ? 'bio-off'
        : (!$lastPull || $lastPull->status === 'failed' ? 'bio-bad'
        : ($dataThrough && $dataThrough->gte($status->today()->copy()->subDays(2)) ? 'bio-ok' : 'bio-warn'));
    $pushState = !$status->autoPush() ? 'bio-warn' : ($pendingPush > 0 ? 'bio-warn' : 'bio-ok');
    $draft = (int) ($lastCycleSlips['draft'] ?? 0);
    $approved = (int) ($lastCycleSlips['approved'] ?? 0);
    $paid = (int) ($lastCycleSlips['paid'] ?? 0);
    $payState = !$status->autoPayslips() ? 'bio-warn' : ($draft + $approved > 0 ? 'bio-warn' : 'bio-ok');
@endphp

@section('content')
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Automation health --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between">
                <div class="mb-2 mb-md-0">
                    @if ($status->schedulerRunning())
                        <span class="badge badge-success p-2 f-12"><i class="fa fa-sync-alt mr-1"></i> Automation running</span>
                        <span class="f-12 text-lightest ml-1">scheduler last ran {{ $ago($status->schedulerLastTick()) }}</span>
                    @else
                        <span class="badge badge-danger p-2 f-12"><i class="fa fa-pause-circle mr-1"></i> Automation stopped</span>
                        <span class="f-12 text-dark-grey ml-1">
                            The scheduler {{ $status->schedulerLastTick() ? 'last ran ' . $ago($status->schedulerLastTick()) : 'has not run' }} —
                            nothing pulls, writes attendance or makes payslips until it runs again. Use the buttons below meanwhile.
                        </span>
                    @endif
                </div>
                <div>
                    @foreach ([['Daily run at ' . ($setting?->daily_pull_time ?: '11:00'), $status->autoPull()], ['Write to ERP attendance', $status->autoPush()], ['Draft payslips', $status->autoPayslips()]] as [$label, $on])
                        <span class="bio-switch"><span class="bio-dot" style="background: {{ $on ? '#12b76a' : '#98a2b3' }}"></span>{{ $label }}: <strong>{{ $on ? 'On' : 'Off' }}</strong></span>
                    @endforeach
                    <a href="{{ route('biometric-settings.index') }}" class="f-12"><i class="fa fa-cog"></i> Settings</a>
                </div>
            </div>
        </div>

        {{-- The four steps --}}
        <div class="bio-flow mb-3">
            <div class="card bg-white border-0 b-shadow-4 bio-step {{ $pullState }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2"><span class="bio-step-no mr-2">1</span><span class="f-14 f-w-500">Pull from device</span></div>
                    <div class="bio-big text-dark">{{ $dataThrough ? $dataThrough->format('d M') : '—' }}</div>
                    <div class="f-12 text-lightest mb-2">punches complete up to this day</div>
                    <div class="f-12 text-dark-grey">
                        Last pull: {{ $fmt($lastPull?->finished_at ?? $lastPull?->started_at) }}
                        @if ($lastPull)
                            <span class="badge {{ $lastPull->status === 'failed' ? 'badge-danger' : 'badge-light border' }}">{{ ucfirst($lastPull->status) }}</span>
                        @endif
                    </div>
                    @if ($lastPull && $lastPull->status === 'failed')
                        <div class="f-11 text-red mt-1">{{ \Illuminate\Support\Str::limit($lastPull->message, 120) }}</div>
                    @endif
                    <a href="{{ route('biometric.punch-log') }}" class="f-12 d-inline-block mt-2">Punch Log →</a>
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4 bio-step {{ $exceptionDays > 0 ? 'bio-warn' : 'bio-ok' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2"><span class="bio-step-no mr-2">2</span><span class="f-14 f-w-500">Review device days</span></div>
                    <div class="bio-big {{ $exceptionDays > 0 ? 'text-warning' : 'text-dark' }}">{{ $exceptionDays }}</div>
                    <div class="f-12 text-lightest mb-2">device day(s) this cycle need a look (missing exit, single punch…)</div>
                    <div class="f-12 text-dark-grey mb-1">
                        Yesterday: <strong>{{ $openReviews }}</strong> open exception(s) for all employees
                        (absent, late, short hours…)
                    </div>
                    <div class="f-12 text-dark-grey">Built automatically after every pull.</div>
                    <a href="{{ route('biometric.review') }}" class="f-12 d-inline-block mt-2 mr-2">Daily Review →</a>
                    <a href="{{ route('biometric.exceptions') }}" class="f-12 d-inline-block mt-2 mr-2">Exceptions →</a>
                    <a href="{{ route('biometric.attendance') }}" class="f-12 d-inline-block mt-2">Device Attendance →</a>
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4 bio-step {{ $pushState }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2"><span class="bio-step-no mr-2">3</span><span class="f-14 f-w-500">ERP attendance</span></div>
                    <div class="bio-big {{ $pendingPush > 0 ? 'text-warning' : 'text-dark' }}">{{ $pendingPush }}</div>
                    <div class="f-12 text-lightest mb-2">device day(s) not written to attendance yet</div>
                    <div class="f-12 text-dark-grey">
                        @if ($status->autoPush())
                            Written automatically after each pull.
                        @else
                            Automatic writing is <strong>off</strong> — use Attendance Sync.
                        @endif
                        Last write: {{ $fmt($lastPush?->finished_at) }}
                    </div>
                    <a href="{{ route('biometric.attendance-sync') }}" class="f-12 d-inline-block mt-2">Attendance Sync →</a>
                </div>
            </div>

            <div class="card bg-white border-0 b-shadow-4 bio-step {{ $payState }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2"><span class="bio-step-no mr-2">4</span><span class="f-14 f-w-500">Payslips</span></div>
                    <div class="bio-big text-dark">{{ $lastCycle->label }}</div>
                    <div class="f-12 text-lightest mb-2">{{ $lastCycle->describe() }}</div>
                    <div class="f-12 text-dark-grey">
                        {{ $draft }} to approve · {{ $approved }} approved · {{ $paid }} paid
                    </div>
                    <div class="f-12 text-lightest mt-1">
                        Current cycle {{ $currentCycle->label }} ends {{ $currentCycle->end->format('d M') }};
                        @if ($status->autoPayslips())
                            drafts are made automatically on {{ $currentCycle->end->copy()->addDay()->format('d M') }}.
                        @else
                            automatic drafts are off.
                        @endif
                    </div>
                    <a href="{{ route('payslips.generated') }}" class="f-12 d-inline-block mt-2 mr-2">Payslips Issued →</a>
                    <a href="{{ route('payroll-run.index') }}" class="f-12 d-inline-block mt-2">Payroll Run →</a>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Run now --}}
            <div class="col-lg-6 mb-3">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <h5 class="f-15 f-w-500 mb-1">Pull now</h5>
                        <p class="f-12 text-lightest mb-3">
                            Runs steps 1–3 straight away: pulls from the device, rebuilds the device days and
                            {{ $status->autoPush() ? 'writes them into ERP attendance' : 'stops there (automatic writing is off)' }}.
                            A day that already has attendance from somewhere else (web clock-in, manual entry) is left as it is.
                        </p>

                        <form id="bio-pull-form" class="row" autocomplete="off">
                            @csrf
                            <div class="col-md-12 mb-2">
                                <label class="f-13 text-dark-grey mb-1">Employee</label>
                                <select name="emp_code" id="bio-emp" class="form-control select-picker" data-live-search="true" data-size="8">
                                    <option value="">Everybody</option>
                                    @foreach ($mappedEmployees as $employee)
                                        <option value="{{ $employee->emp_code }}">{{ $employee->user?->name ?? $employee->employee_name }} ({{ $employee->emp_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="f-13 text-dark-grey mb-1">From</label>
                                <input type="date" name="start_date" class="form-control height-35 f-14" value="{{ $status->today()->copy()->subDays(3)->toDateString() }}">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="f-13 text-dark-grey mb-1">To</label>
                                <input type="date" name="end_date" class="form-control height-35 f-14" value="{{ $status->today()->toDateString() }}">
                            </div>
                            <div class="col-md-12">
                                <p class="f-11 text-lightest mb-2">Up to 31 days for everybody, 92 days for one employee.</p>
                                <button type="button" id="bio-pull" class="btn btn-primary f-14"><i class="fa fa-download mr-1"></i> Pull now</button>
                            </div>
                        </form>

                        @if ($lastRun)
                            <div class="f-12 mt-3 p-2 rounded {{ $lastRun['ok'] ? 'bg-light' : 'alert-danger' }}">
                                <strong>Last run</strong> ({{ $lastRun['trigger'] }}, {{ \Carbon\Carbon::parse($lastRun['at'])->diffForHumans() }}): {{ $lastRun['message'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Needs attention --}}
            <div class="col-lg-6 mb-3">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <h5 class="f-15 f-w-500 mb-3">Needs attention</h5>
                        @php $attention = 0; @endphp

                        @if (!$dailyRun || $dailyRun->status !== 'reviewed' || $openReviews > 0 || !$dailyRun->device_complete)
                            @php $attention++; @endphp
                            <div class="d-flex mb-3">
                                <i class="fa fa-clipboard-check {{ $openReviews > 0 ? 'text-warning' : 'text-primary' }} mt-1 mr-2"></i>
                                <div class="f-13">
                                    @if (!$dailyRun || $dailyRun->status !== 'reviewed')
                                        Yesterday ({{ $yesterday->format('D d M') }}) has not been reviewed yet{{ $dailyRun?->last_error ? ' — ' . \Illuminate\Support\Str::limit($dailyRun->last_error, 90) : '' }}.
                                        It runs at {{ $setting?->daily_pull_time ?: '11:00' }} and is retried until 19:00.
                                    @else
                                        {{ $openReviews }} attendance exception(s) from {{ $yesterday->format('D d M') }} still open.
                                        @unless ($dailyRun->device_complete)
                                            <span class="text-warning">Device data was incomplete; it is pulled again automatically.</span>
                                        @endunless
                                    @endif
                                    <a href="{{ route('biometric.review', ['date' => $yesterday->toDateString()]) }}" class="f-12 d-block">Daily Review →</a>
                                </div>
                            </div>
                        @endif

                        @if (!$status->schedulerRunning())
                            @php $attention++; @endphp
                            <div class="d-flex mb-3">
                                <i class="fa fa-exclamation-circle text-red mt-1 mr-2"></i>
                                <div class="f-13">The scheduler is not running, so nothing happens on its own.
                                    <div class="f-12 text-lightest">On this PC: enable the Windows task "HRM Portal Scheduler". On the server: a cron entry running <code>php artisan schedule:run</code> every minute.</div>
                                </div>
                            </div>
                        @endif

                        @if ($unmapped->isNotEmpty())
                            @php $attention++; @endphp
                            <div class="d-flex mb-3">
                                <i class="fa fa-user-slash text-warning mt-1 mr-2"></i>
                                <div class="f-13">{{ $unmapped->count() }} active employee(s) are not linked to a device code, so their days come only from web clock-ins:
                                    <div class="f-12 text-dark-grey">{{ $unmapped->take(8)->pluck('name')->implode(', ') }}{{ $unmapped->count() > 8 ? ' …' : '' }}</div>
                                    <a href="{{ route('biometric.employees') }}" class="f-12">Employee Mapping →</a>
                                </div>
                            </div>
                        @endif

                        @if ($draft > 0)
                            @php $attention++; @endphp
                            <div class="d-flex mb-3">
                                <i class="fa fa-file-invoice text-primary mt-1 mr-2"></i>
                                <div class="f-13">{{ $draft }} draft payslip(s) for {{ $lastCycle->label }} are waiting for approval.
                                    <a href="{{ route('payslips.generated') }}" class="f-12">Review →</a>
                                </div>
                            </div>
                        @endif

                        @if (!empty($lastPayslipRun['waiting']))
                            @php $attention++; @endphp
                            <div class="d-flex mb-3">
                                <i class="fa fa-hourglass-half text-warning mt-1 mr-2"></i>
                                <div class="f-13">{{ count($lastPayslipRun['waiting']) }} payslip(s) for {{ $lastPayslipRun['period'] }} are waiting (tried again each morning):
                                    <ul class="f-12 text-dark-grey pl-3 mb-0">
                                        @foreach (array_slice($lastPayslipRun['waiting'], 0, 5) as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if ($departed > 0)
                            @php $attention++; @endphp
                            <div class="d-flex mb-3">
                                <i class="fa fa-door-open text-warning mt-1 mr-2"></i>
                                <div class="f-13">{{ $departed }} person(s) who have left are still enrolled on a device.
                                    <a href="{{ route('biometric.actions') }}" class="f-12">Notice →</a>
                                </div>
                            </div>
                        @endif

                        @if ($attention === 0)
                            <p class="f-13 text-dark-grey mb-0"><i class="fa fa-check-circle text-success mr-1"></i> Nothing needs attention.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#bio-emp').selectpicker({container: 'body'});

        $('#bio-pull').click(function () {
            $.easyAjax({
                url: "{{ route('biometric.sync') }}",
                type: 'POST',
                container: '#bio-pull-form',
                blockUI: true,
                disableButton: true,
                buttonSelector: '#bio-pull',
                data: $('#bio-pull-form').serialize(),
                success: function (response) {
                    if (response.status === 'success') {
                        setTimeout(function () { window.location.reload(); }, 1500);
                    }
                }
            });
        });
    </script>
@endpush
