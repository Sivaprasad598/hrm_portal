@extends('layouts.app')

@push('styles')
    <style>
        .rv-card { border-left: 3px solid var(--rv, #d0d5dd); }
        .rv-card .rv-num { font-size: 22px; font-weight: 600; line-height: 1.2; }
        .rv-flag { font-size: 11px; margin: 0 4px 2px 0; }
        .rv-resolved { opacity: .6; }
        .rv-table td { vertical-align: middle; }
    </style>
@endpush

@php
    use App\Models\AttendanceReview;
    $tz = company()->timezone;
    $time = fn ($c) => $c ? $c->copy()->timezone($tz)->format('h:i A') . ($c->copy()->timezone($tz)->toDateString() !== $date->toDateString() ? ' (+1d)' : '') : '--';
    $hours = fn ($m) => $m > 0 ? intdiv($m, 60) . 'h ' . sprintf('%02d', $m % 60) . 'm' : '--';
    $query = fn ($extra) => route('biometric.review', array_merge(['date' => $date->toDateString(), 'show' => $show], $extra));
    $cards = [
        ['Open exceptions', $counts['open'], '#f04438', ['show' => 'open']],
        ['Absent, no leave', $counts['absent'], '#f04438', ['status' => 'absent', 'show' => 'all']],
        ['Half day / short hours', $counts['short'], '#f79009', ['show' => 'exceptions']],
        ['Missing exit / single punch', $counts['missing_exit'], '#f79009', ['flag' => 'missing_exit', 'show' => 'all']],
        ['Late', $counts['late'], '#f79009', ['flag' => 'late', 'show' => 'all']],
        ['Leave issues', $counts['leave'], '#7a5af8', ['show' => 'exceptions']],
    ];
@endphp

@section('content')
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Date and day status --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <a href="{{ route('biometric.review', ['date' => $date->copy()->subDay()->toDateString(), 'show' => $show]) }}" class="btn btn-secondary btn-sm mr-2"><i class="fa fa-chevron-left"></i></a>
                    <input type="date" id="rv-date" class="form-control height-35 f-14 mr-2" style="width: 170px" value="{{ $date->toDateString() }}" max="{{ now($tz)->subDay()->toDateString() }}">
                    <a href="{{ route('biometric.review', ['date' => $date->copy()->addDay()->toDateString(), 'show' => $show]) }}" class="btn btn-secondary btn-sm mr-3 {{ $canGoForward ? '' : 'disabled' }}"><i class="fa fa-chevron-right"></i></a>
                    <h5 class="mb-0 f-16 f-w-500">{{ $date->format('l, d M Y') }}</h5>
                </div>

                <div class="d-flex align-items-center flex-wrap">
                    <span class="f-12 mr-3">
                        @if (!$run)
                            <span class="badge badge-light border">Not reviewed yet</span>
                            <span class="text-lightest">— runs automatically the next night ({{ $setting?->stepTime('pull_time') ?? '01:00' }}–{{ $setting?->stepTime('reconcile_time') ?? '03:00' }})</span>
                        @else
                            <span class="badge {{ $run->status === 'reviewed' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($run->status) }}</span>
                            @if ($run->device_complete)
                                <span class="text-lightest">device data complete</span>
                            @else
                                <span class="text-warning" title="{{ $run->last_error }}">device not reached ({{ $run->attempts }} attempt(s)) — retried automatically</span>
                            @endif
                            @if ($run->reviewed_at)
                                <span class="text-lightest">· {{ $run->reviewed_at->timezone($tz)->format('d M h:i A') }}</span>
                            @endif
                        @endif
                    </span>
                    @if ($canAct)
                        <button type="button" class="btn btn-secondary btn-sm mr-2 rv-run" data-pull="0"><i class="fa fa-redo mr-1"></i> Review again</button>
                        <button type="button" class="btn btn-primary btn-sm rv-run" data-pull="1"><i class="fa fa-download mr-1"></i> Get latest punches &amp; review</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Counts --}}
        <div class="row">
            @foreach ($cards as [$label, $value, $color, $link])
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <a href="{{ $query($link) }}" class="text-decoration-none">
                        <div class="card bg-white border-0 b-shadow-4 rv-card h-100" style="--rv: {{ $value > 0 ? $color : '#d0d5dd' }}">
                            <div class="card-body p-3">
                                <div class="rv-num {{ $value > 0 ? 'text-dark' : 'text-lightest' }}">{{ $value }}</div>
                                <div class="f-12 text-dark-grey">{{ $label }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('biometric.review') }}" class="row align-items-end" id="rv-filters">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <div class="col-md-3 col-6 mb-2">
                        <label class="f-13 text-dark-grey mb-1">Show</label>
                        <select name="show" class="form-control select-picker" data-live-search="true">
                            <option value="exceptions" @selected($show === 'exceptions')>Exceptions ({{ $counts['exceptions'] }})</option>
                            <option value="open" @selected($show === 'open')>Not resolved yet ({{ $counts['open'] }})</option>
                            <option value="all" @selected($show === 'all')>Everybody ({{ $counts['employees'] }})</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <label class="f-13 text-dark-grey mb-1">Status</label>
                        <select name="status" class="form-control select-picker" data-live-search="true">
                            <option value="">All</option>
                            @foreach (AttendanceReview::STATUSES as $key => [$label])
                                <option value="{{ $key }}" @selected($statusFilter === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <label class="f-13 text-dark-grey mb-1">Flag</label>
                        <select name="flag" class="form-control select-picker" data-live-search="true">
                            <option value="">All</option>
                            @foreach (AttendanceReview::FLAGS as $key => $label)
                                <option value="{{ $key }}" @selected($flagFilter === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <button type="submit" class="btn btn-primary f-14 mr-2">Apply</button>
                        <a href="{{ route('biometric.review', ['date' => $date->toDateString()]) }}" class="btn btn-secondary f-14">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- The day, employee by employee --}}
        <div class="card bg-white border-0 b-shadow-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13 rv-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="pl-3">Employee</th>
                            <th>Status</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>On site</th>
                            <th>Day credit</th>
                            <th>Source</th>
                            <th class="text-right pr-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reviews as $review)
                            <tr class="{{ $review->resolved_at ? 'rv-resolved' : '' }}">
                                <td class="pl-3">
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $review->user?->image_url }}" class="rounded-circle mr-2" width="28" height="28" alt="">
                                        <div>
                                            @if (isset($profileIds[$review->user_id]))
                                                <a href="{{ route('biometric.profile', ['employee' => $profileIds[$review->user_id], 'start_date' => $date->toDateString(), 'end_date' => $date->toDateString()]) }}" class="text-darkest-grey f-w-500" title="Employee 360 for this day">{{ $review->user?->name }}</a>
                                            @else
                                                <span class="text-darkest-grey f-w-500">{{ $review->user?->name }}</span>
                                            @endif
                                            @if ($review->employee_notified_at)
                                                <div class="f-11 text-lightest"><i class="fa fa-bell"></i> told {{ $review->employee_notified_at->timezone($tz)->format('d M h:i A') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $review->badge() }}">{{ $review->label() }}</span>
                                    <div>
                                        @foreach ($review->flags ?? [] as $flag)
                                            <span class="badge badge-light border rv-flag">{{ AttendanceReview::FLAGS[$flag] ?? $flag }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $time($review->first_in) }}</td>
                                <td>{{ $time($review->last_out) }}</td>
                                <td>{{ $hours($review->presence_minutes) }}</td>
                                <td><strong>{{ rtrim(rtrim(number_format($review->credit, 1), '0'), '.') ?: '0' }}</strong></td>
                                <td class="text-capitalize">{{ $review->source ?? '--' }}</td>
                                <td class="text-right pr-3">
                                    @if ($review->resolved_at)
                                        <div class="f-11 text-lightest">Resolved by {{ $review->resolvedBy?->name ?? '--' }}</div>
                                        @if ($review->note)
                                            <div class="f-11 text-dark-grey">{{ $review->note }}</div>
                                        @endif
                                        @if ($canAct)
                                            <a href="javascript:;" class="f-12 rv-reopen" data-id="{{ $review->id }}">Open again</a>
                                        @endif
                                    @elseif ($review->is_exception && $canAct)
                                        <button type="button" class="btn btn-sm btn-secondary rv-resolve" data-id="{{ $review->id }}" data-name="{{ $review->user?->name }}">
                                            <i class="fa fa-check mr-1"></i> Resolve
                                        </button>
                                    @endif
                                    <a href="{{ route('attendances.index') }}" class="f-12 d-block mt-1">Attendance →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-lightest py-4">
                                    @if ($counts['employees'] === 0)
                                        This day has not been reviewed yet. Use <strong>Pull &amp; review</strong>, or wait for the daily run.
                                    @else
                                        Nothing to show for this filter.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#rv-filters .select-picker').on('changed.bs.select', function () {
            $('#rv-filters').submit();
        });

        $('#rv-date').on('change', function () {
            window.location = "{{ route('biometric.review') }}?show={{ $show }}&date=" + this.value;
        });

        $('.rv-run').on('click', function () {
            let pull = $(this).data('pull');

            $.easyAjax({
                url: "{{ route('biometric.review.run') }}",
                type: 'POST',
                blockUI: true,
                container: '.content-wrapper',
                data: {date: '{{ $date->toDateString() }}', pull: pull, _token: '{{ csrf_token() }}'},
                success: function (response) {
                    if (response.status === 'success') {
                        setTimeout(function () { window.location.reload(); }, 1200);
                    }
                }
            });
        });

        $('.rv-resolve').on('click', function () {
            let id = $(this).data('id');

            Swal.fire({
                title: 'Resolve ' + $(this).data('name') + "'s day?",
                input: 'text',
                inputPlaceholder: 'Note (optional), e.g. on client visit, leave applied',
                showCancelButton: true,
                confirmButtonText: 'Mark resolved',
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.easyAjax({
                        url: "{{ route('biometric.review.resolve') }}",
                        type: 'POST',
                        data: {id: id, note: result.value, _token: '{{ csrf_token() }}'},
                        success: function (response) {
                            if (response.status === 'success') { window.location.reload(); }
                        }
                    });
                }
            });
        });

        $('.rv-reopen').on('click', function () {
            $.easyAjax({
                url: "{{ route('biometric.review.resolve') }}",
                type: 'POST',
                data: {id: $(this).data('id'), reopen: 1, _token: '{{ csrf_token() }}'},
                success: function (response) {
                    if (response.status === 'success') { window.location.reload(); }
                }
            });
        });
    </script>
@endpush
