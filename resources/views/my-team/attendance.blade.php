{{-- A reporting manager's team over chosen dates, this salary period by default (App\Http\Controllers\MyTeamController) --}}
@extends('layouts.app')

@push('styles')
    {{-- The date picker's own styles (pages with a data table get them from datatable_css) --}}
    @include('sections.daterange_css')
@endpush

@section('content')
    @php
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
        $statusMeta = \App\Http\Controllers\PayrollAttendanceController::STATUSES;
    @endphp

    <div class="content-wrapper">
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3" style="gap:12px;">
                <h6 class="mb-0 f-14 mr-auto">Team attendance
                    <span class="text-lightest font-weight-normal">· {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span></h6>
                {{-- Any dates (up to today); opens on this salary period --}}
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <label class="mb-0 f-13 text-dark-grey" for="ta-range">Duration</label>
                    <input type="text" id="ta-range" readonly
                           class="form-control form-control-sm f-13" style="width:240px; cursor:pointer; background-color:#fff !important;"
                           value="{{ $from->format(company()->date_format) }} @lang('app.to') {{ $to->format(company()->date_format) }}">
                    @unless ($defaultRange)
                        <a href="{{ route('my-team.attendance') }}" class="btn btn-secondary btn-sm f-13">This period</a>
                    @endunless
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            @if ($isCurrent)<th>Today</th>@endif
                            <th class="text-right">Paid days</th>
                            <th class="text-right">LOP</th>
                            <th class="text-right">Late</th>
                            <th class="text-right">Leave days</th>
                            <th class="text-right">Pending leave</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                $t = $r['today'];
                                $tStatus = $t['status'] ?? null;
                                $tLabel = $tStatus ? ($statusMeta[$tStatus][0] ?? ucfirst(str_replace('_', ' ', $tStatus))) : '—';
                                // Today, before the day is over: punched in = in, not yet = not in yet
                                if ($t && in_array($tStatus, ['absent', 'half_day'], true)) {
                                    $tLabel = $t['first_in'] ? 'In since ' . substr($t['first_in'], 11, 5) : 'Not in yet';
                                    $tStatus = $t['first_in'] ? 'present' : 'absent';
                                }
                                $tColour = $statusMeta[$tStatus][1] ?? 'light';
                                $pending = (int) ($pendingLeaveByUser[$r['user']->id] ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('attendances.by_member') }}?user_id={{ $r['user']->id }}" class="d-flex align-items-center text-dark">
                                        <img src="{{ $r['user']->image_url }}" class="taskEmployeeImg rounded-circle mr-2" width="28" height="28" alt="">
                                        <span>
                                            <span class="d-block font-weight-bold">{{ $r['user']->name }}</span>
                                            <span class="f-11 emp-designation">{{ $r['user']->employeeDetail?->designation?->name }}</span>
                                        </span>
                                    </a>
                                </td>
                                @if ($isCurrent)
                                    <td><span class="badge badge-{{ $tColour }}">{{ $tLabel }}</span></td>
                                @endif
                                <td class="text-right">{{ $r['paid'] === null ? '—' : $fmt($r['paid']) . ' / ' . $r['counted'] }}</td>
                                <td class="text-right {{ ($r['lop'] ?? 0) > 0 ? 'text-danger font-weight-bold' : '' }}">{{ $r['lop'] === null ? '—' : $fmt($r['lop']) }}</td>
                                <td class="text-right {{ $r['late'] > 0 ? 'text-danger' : '' }}">{{ $r['late'] }}</td>
                                <td class="text-right">{{ $r['leave'] }}</td>
                                <td class="text-right">
                                    @if ($pending)
                                        <a href="{{ route('leaves.index') }}" class="badge badge-warning" title="Waiting for you or for HR">{{ $pending }} pending</a>
                                    @else
                                        <span class="text-lightest">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/jquery/daterangepicker.min.js') }}"></script>
    <script>
        // One date range, applied as soon as it is picked (up to today).
        $(function () {
            $('#ta-range').daterangepicker({
                autoUpdateInput: false,
                locale: daterangeLocale,
                linkedCalendars: false,
                showDropdowns: true,
                startDate: moment('{{ $from->toDateString() }}'),
                endDate: moment('{{ $to->toDateString() }}'),
                maxDate: moment('{{ $today->toDateString() }}'),
                ranges: daterangeConfig,
                opens: 'left'
            });

            $('#ta-range').on('apply.daterangepicker', function (ev, picker) {
                window.location = "{{ route('my-team.attendance') }}"
                    + '?from=' + picker.startDate.format('YYYY-MM-DD')
                    + '&to=' + picker.endDate.format('YYYY-MM-DD');
            });
        });
    </script>
@endpush
