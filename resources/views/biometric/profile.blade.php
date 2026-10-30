@extends('layouts.app')

@php
    use App\Services\Iapp\Reports\EmployeeAttendanceProfile as Profile;
    use App\Services\Iapp\Shifts\ShiftCompliance as Compliance;
    use App\Services\Iapp\Shifts\ShiftDetector as Detector;
@endphp

@push('styles')
    <style>
        /* The employee list is attached to the page body (see the picker setup),
           so it must sit above the cards; the modal layer is 1050. A card that
           clips its contents used to cut the names off. */
        body > .bootstrap-select .dropdown-menu,
        .bootstrap-select .dropdown-menu { z-index: 1060; }

        /* Belt and braces for the row holding the picker. */
        .employee-picker-row, .employee-picker-row .card { overflow: visible; }
    </style>
@endpush

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        <x-cards.data padding="false" class="mb-3 employee-picker-row">
            <form method="GET" action="{{ route('biometric.profile') }}" class="p-3">
                <div class="row align-items-end">
                    {{-- One searchable dropdown rather than a text box feeding a
                         second control: typing filtered the list server-side, so
                         choosing anyone took a round trip before the name even
                         appeared. The whole register is loaded once and filtered
                         in the browser. --}}
                    <div class="col-md-5 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                        <select name="employee" id="employee-picker" class="form-control f-14"
                                data-live-search="true" data-size="12">
                            <option value="">-- select an employee --</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}" @selected($selected?->id === $candidate->id)
                                        data-subtext="{{ $candidate->emp_code }} &middot; {{ number_format($candidate->punch_count) }} punches">
                                    {{ $candidate->employee_name ?: $candidate->emp_code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.startDate')</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.endDate')</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-1 mb-2">
                        <button type="submit" class="btn btn-primary f-14 w-100">@lang('app.apply')</button>
                    </div>
                </div>
            </form>
        </x-cards.data>

        @if (!$selected)
            <x-cards.data>
                <div class="text-center py-5">
                    <p class="mb-1 f-15 text-dark-grey">Choose an employee to see their attendance profile.</p>
                    <p class="mb-0 f-13 text-lightest">
                        Search by name or biometric code, then pick from the list.
                    </p>
                </div>
            </x-cards.data>
        @else
            @php $k = $profileData['kpis']; @endphp

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body p-3 d-flex align-items-center flex-wrap">
                    <div class="mr-4">
                        <h5 class="mb-0 f-18">{{ $selected->employee_name ?: $selected->emp_code }}</h5>
                        <p class="mb-0 f-12 text-lightest">
                            Biometric code <strong>{{ $selected->emp_code }}</strong>
                            @if ($selected->user)
                                &middot; linked to {{ $selected->user->name }}
                            @else
                                &middot; <span class="text-red">not linked to an ERP employee</span>
                            @endif
                        </p>
                    </div>
                    <div class="ml-auto f-12 text-lightest">
                        {{ \Carbon\Carbon::parse($startDate)->format(company()->date_format) }}
                        &ndash;
                        {{ \Carbon\Carbon::parse($endDate)->format(company()->date_format) }}
                    </div>
                </div>
            </div>

            {{-- Headline KPIs --}}
            @php
                // Days Present: out of the working days by the employee's shift
                // (week-offs, holidays and full-day leave taken out), over the
                // days the device has data for - the same days Absent counts.
                if ($k['expected_working_days'] > 0) {
                    $presentValue = $k['attended_working_days'] . ' / ' . $k['expected_working_days'];
                    $parts = [$k['covered_days'] . ' days'];
                    foreach (['week_off_days' => 'week-off', 'holiday_days' => 'holiday', 'leave_days' => 'on leave'] as $key => $word) {
                        if ($k[$key]) {
                            $parts[] = $k[$key] . ' ' . ($word === 'on leave' ? $word : \Illuminate\Support\Str::plural($word, $k[$key]));
                        }
                    }
                    $presentSub = implode(' − ', $parts) . ' · to ' . \Carbon\Carbon::parse($k['coverage_to'])->format('d M')
                        . ($k['off_day_present'] ? ' · +' . $k['off_day_present'] . ' on off day' . ($k['off_day_present'] > 1 ? 's' : '') : '');
                } else {
                    $presentValue = $k['days_present'];
                    $presentSub = 'no working days with device data';
                }

                $tiles = [
                    ['Days Present', $presentValue, 'text-dark-grey', $presentSub],
                    ['Time Inside', Profile::humanMinutes($k['total_inside_minutes']), 'text-primary', 'avg ' . Profile::humanMinutes($k['avg_inside_minutes']) . ' / day'],
                    ['Time Outside', Profile::humanMinutes($k['total_outside_minutes']), 'text-yellow', 'avg ' . Profile::humanMinutes($k['avg_outside_minutes']) . ' / day'],
                    ['Check-ins', $k['total_check_ins'], 'text-dark-grey', 'avg ' . $k['avg_check_ins'] . ' / day'],
                    ['Check-outs', $k['total_check_outs'], 'text-dark-grey', 'avg ' . $k['avg_exits'] . ' / day'],
                    ['Avg First In', $k['avg_first_in'] ?? '--', 'text-dark-grey', 'earliest ' . ($k['earliest_in'] ?? '--')],
                    ['Avg Last Out', $k['avg_last_out'] ?? '--', 'text-dark-grey', 'latest ' . ($k['latest_out'] ?? '--')],
                    ['Places', count($profileData['places']), 'text-dark-grey', 'controllers used'],
                ];
            @endphp

            <div class="row mb-3">
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

            {{-- Shift adherence, measured against the ERP shift --}}
            @php
                $matchColour = [
                    Detector::MATCH_MATCHES => 'success', Detector::MATCH_DIFFERS => 'warning',
                    Detector::MATCH_UNLISTED => 'warning', Detector::MATCH_UNASSIGNED => 'secondary',
                    Detector::MATCH_INSUFFICIENT => 'secondary',
                ][$k['shift_match']] ?? 'secondary';

                $sourceLabel = [
                    'schedule' => 'from roster',
                    'default' => 'company default',
                    'assumed' => 'default, assumed (not linked)',
                ][$k['shift_source']] ?? '';

                $shiftTiles = [
                    ['On Time', $k['on_time_pct'] !== null ? $k['on_time_pct'] . '%' : '--', 'text-primary', $k['on_time_days'] . ' of ' . ($k['on_time_days'] + $k['late_days']) . ' shift days'],
                    ['Late Arrivals', $k['late_days'], $k['late_days'] ? 'text-red' : 'text-dark-grey', Profile::humanMinutes($k['late_minutes']) . ' late in total'],
                    ['Early Exits', $k['early_exits'], $k['early_exits'] ? 'text-yellow' : 'text-dark-grey', Profile::humanMinutes($k['early_minutes']) . ' early in total'],
                    ['Overtime', Profile::humanMinutes($k['overtime_minutes']), 'text-dark-grey', $k['off_day_days'] . ' off-day(s) worked'],
                    ['Short Hours', $k['short_days'], $k['short_days'] ? 'text-yellow' : 'text-dark-grey', 'below shift minimum'],
                    ['Absent', $k['absent_days'], $k['absent_days'] ? 'text-red' : 'text-dark-grey', $k['attended_working_days'] . ' of ' . $k['expected_working_days'] . ' working days attended'],
                ];
            @endphp

            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center">
                        <div class="mr-4 mb-1">
                            <span class="f-12 text-lightest">Assigned shift</span>
                            <div class="f-14 f-w-500">{{ $k['shift_assigned'] }}</div>
                            <div class="f-11 text-lightest">{{ $sourceLabel }}</div>
                        </div>
                        <div class="mr-4 mb-1">
                            <span class="f-12 text-lightest">Detected from punches</span>
                            <div class="f-14 f-w-500">{{ $k['shift_detected'] }}</div>
                            <div class="f-11">
                                <span class="badge badge-secondary f-10">{{ $k['shift_confidence'] }} confidence</span>
                                <span class="badge badge-{{ $matchColour }} f-10">{{ Detector::MATCH_LABELS[$k['shift_match']] }}</span>
                            </div>
                        </div>
                        @if ($k['coverage_from'])
                            <div class="ml-auto f-11 text-lightest text-right mb-1">
                                Absence is counted only where device data exists:<br>
                                {{ \Carbon\Carbon::parse($k['coverage_from'])->format('d M') }}
                                &ndash;
                                {{ \Carbon\Carbon::parse($k['coverage_to'])->format('d M Y') }}
                            </div>
                        @endif
                    </div>

                    @if ($k['absent_dates'])
                        <p class="f-12 mb-0 mt-2">
                            <span class="text-red">Absent on working days:</span>
                            @foreach (array_slice($k['absent_dates'], 0, 12) as $absent)
                                <span class="badge badge-light f-11 mr-1">{{ \Carbon\Carbon::parse($absent)->format('D d M') }}</span>
                            @endforeach
                            @if (count($k['absent_dates']) > 12)
                                <span class="f-11 text-lightest">+{{ count($k['absent_dates']) - 12 }} more</span>
                            @endif
                        </p>
                    @endif

                    @if ($k['shift_conflict'])
                        <p class="f-11 text-yellow mb-0 mt-2">
                            Attendance settings say {{ $k['shift_conflict']['settings'][0] }}&ndash;{{ $k['shift_conflict']['settings'][1] }}
                            but the default shift is {{ $k['shift_conflict']['shift'][0] }}&ndash;{{ $k['shift_conflict']['shift'][1] }};
                            figures follow the shift record.
                        </p>
                    @endif
                </div>
            </div>

            <div class="row mb-3">
                @foreach ($shiftTiles as [$label, $value, $colour, $sub])
                    <div class="col-md-2 col-6 mb-2">
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

            <div class="row">
                {{-- Places visited --}}
                <div class="col-md-5 mb-3">
                    <x-cards.data :title="'Places Visited'" padding="false">
                        <div class="table-responsive">
                            <table class="table table-hover border-0 mb-0">
                                <thead>
                                    <tr>
                                        <th class="f-12">Device</th>
                                        <th class="f-12">Visits</th>
                                        <th class="f-12">Events</th>
                                        <th class="f-12">Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($profileData['places'] as $place)
                                        <tr>
                                            <td class="f-13">
                                                {{ $deviceNames[$place['controller_code']] ?? $place['controller_code'] }}
                                            </td>
                                            <td class="f-13">{{ $place['visits'] }}</td>
                                            <td class="f-13">{{ $place['events'] }}</td>
                                            <td class="f-13">
                                                {{ \Carbon\Carbon::parse($place['last_seen'])->format('d M H:i') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 f-13 text-lightest">No activity in range.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- A controller covers several doors and the punch feed does
                             not say which, so this is honest about its granularity. --}}
                        <p class="f-11 text-lightest px-3 py-2 mb-0 border-top-grey">
                            Each device may control several doors. The punch feed reports the device,
                            not the individual door, so room-level detail is not available.
                        </p>
                    </x-cards.data>
                </div>

                {{-- Activity by hour --}}
                <div class="col-md-7 mb-3">
                    <x-cards.data :title="'Activity by Hour'">
                        @php
                            $hourly = $profileData['hourly'];
                            $peak = max(1, max(array_map(fn ($h) => $h['in'] + $h['out'] + $h['scan'], $hourly)));
                        @endphp

                        <div class="d-flex align-items-end" style="height:150px; gap:2px;">
                            @foreach ($hourly as $hour => $counts)
                                <div class="flex-fill d-flex flex-column justify-content-end align-items-center"
                                     title="{{ sprintf('%02d:00 — %d in, %d out, %d scan', $hour, $counts['in'], $counts['out'], $counts['scan']) }}">
                                    <div class="w-100 bg-primary" style="height:{{ $counts['in'] / $peak * 120 }}px"></div>
                                    <div class="w-100 bg-warning" style="height:{{ $counts['out'] / $peak * 120 }}px"></div>
                                    <div class="w-100 bg-secondary" style="height:{{ $counts['scan'] / $peak * 120 }}px"></div>
                                    <span class="f-11 text-lightest">{{ $hour % 3 === 0 ? $hour : '' }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="f-11 text-lightest mb-0 mt-2">
                            <span class="badge badge-primary">&nbsp;</span> check-in
                            <span class="badge badge-warning ml-2">&nbsp;</span> check-out
                            <span class="badge badge-secondary ml-2">&nbsp;</span> face scan
                        </p>
                    </x-cards.data>
                </div>
            </div>

            {{-- Day-wise --}}
            <x-cards.data :title="'Day-wise Attendance'" padding="false">
                <div class="table-responsive">
                    <table class="table table-hover border-0 mb-0">
                        <thead>
                            <tr>
                                <th class="f-12">@lang('app.date')</th>
                                <th class="f-12">First In</th>
                                <th class="f-12">Last Out</th>
                                <th class="f-12">Check-ins</th>
                                <th class="f-12">Check-outs</th>
                                <th class="f-12">Inside</th>
                                <th class="f-12">Outside</th>
                                <th class="f-12">Shift</th>
                                <th class="f-12">Status</th>
                                <th class="f-12">Late</th>
                                <th class="f-12">Early / OT</th>
                                <th class="f-12">Places</th>
                                <th class="f-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($profileData['days'] as $day)
                                <tr>
                                    <td class="f-13">{{ \Carbon\Carbon::parse($day['date'])->format('D, d M Y') }}</td>
                                    <td class="f-13">{{ $day['first_in'] ?? '--' }}</td>
                                    <td class="f-13">
                                        @if ($day['last_out'])
                                            {{ $day['last_out'] }}
                                        @else
                                            <span class="text-yellow" title="No exit punch">--</span>
                                        @endif
                                    </td>
                                    <td class="f-13">{{ $day['check_ins'] }}</td>
                                    <td class="f-13">{{ $day['check_outs'] }}</td>
                                    <td class="f-13">{{ Profile::humanMinutes($day['inside_minutes']) }}</td>
                                    <td class="f-13">
                                        @if ($day['outside_minutes'] > 0)
                                            <span class="text-yellow">{{ Profile::humanMinutes($day['outside_minutes']) }}</span>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    @php
                                        $sh = $day['shift'];
                                        $statusColour = [
                                            Compliance::STATUS_ON_TIME => 'success', Compliance::STATUS_LATE => 'danger',
                                            Compliance::STATUS_HALF_DAY => 'warning', Compliance::STATUS_OFF_DAY => 'info',
                                        ][$sh['status']] ?? 'secondary';
                                        $reasonLabel = [
                                            'day_off' => 'Day off', 'week_off' => 'Week off', 'holiday' => 'Holiday',
                                            'leave' => 'On leave', 'half_leave' => 'Half-day leave',
                                        ][$sh['reason']] ?? null;
                                    @endphp
                                    <td class="f-13">
                                        {{ $sh['label'] }}
                                        @if ($sh['source'] === 'assumed')
                                            <div class="f-11 text-lightest">default, assumed</div>
                                        @elseif ($sh['source'] === 'schedule')
                                            <div class="f-11 text-lightest">rostered</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $statusColour }} f-11">{{ Compliance::LABELS[$sh['status']] ?? $sh['status'] }}</span>
                                        @if ($reasonLabel)
                                            <div class="f-11 text-lightest">{{ $sh['holiday'] ?: $reasonLabel }}</div>
                                        @endif
                                    </td>
                                    <td class="f-13">
                                        @if ($sh['late_minutes'] > 0)
                                            <span class="text-red">{{ Profile::humanMinutes($sh['late_minutes']) }}</span>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td class="f-13">
                                        @if (!$sh['exit_known'])
                                            <span class="text-lightest">exit missing</span>
                                        @elseif ($sh['early_minutes'] > 0)
                                            <span class="text-yellow">&minus;{{ Profile::humanMinutes($sh['early_minutes']) }}</span>
                                        @elseif ($sh['overtime_minutes'] > 0)
                                            <span class="text-primary">+{{ Profile::humanMinutes($sh['overtime_minutes']) }}</span>
                                        @else
                                            --
                                        @endif
                                        @if ($sh['short_hours'])
                                            <div><span class="badge badge-warning f-10">short</span></div>
                                        @endif
                                    </td>
                                    <td class="f-13">
                                        @foreach ($day['controller_visits'] as $code => $visits)
                                            <span class="badge badge-secondary f-10 mr-1"
                                                  title="{{ $visits }} visit(s)">
                                                {{ $deviceNames[$code] ?? $code }} &times;{{ $visits }}
                                            </span>
                                        @endforeach
                                    </td>
                                    <td class="text-right">
                                        {{-- The number the detail panel actually lists, which is
                                             every punch the device reported. The counted figure is
                                             shown inside the panel. --}}
                                        <button type="button" class="btn btn-sm btn-outline-secondary f-11 toggle-detail"
                                                data-target="detail-{{ $day['date'] }}"
                                                title="{{ ($day['repeat_events'] ?? 0) > 0
                                                    ? $day['repeat_events'] . ' of these repeat the punch before them, so ' . $day['total_events'] . ' are counted'
                                                    : '' }}">
                                            {{ $day['recorded_events'] ?? $day['total_events'] }} events
                                        </button>
                                    </td>
                                </tr>

                                {{-- Full punch timeline for the day --}}
                                <tr class="d-none" id="detail-{{ $day['date'] }}">
                                    <td colspan="13" class="bg-light">
                                        <div class="p-2">
                                            <p class="f-12 text-dark-grey mb-2">
                                                <strong>Sessions:</strong>
                                                @forelse ($day['sessions'] as $session)
                                                    <span class="badge badge-light f-11 mr-1">
                                                        {{ $session['in'] }} &rarr; {{ $session['out'] ?? 'open' }}
                                                        ({{ Profile::humanMinutes($session['minutes']) }})
                                                    </span>
                                                @empty
                                                    <span class="text-lightest">none paired</span>
                                                @endforelse
                                            </p>

                                            @php
                                                // Every punch the device reported. Repeats used to be
                                                // dropped before reaching here, which is why the punch
                                                // log could show a punch this panel had no trace of.
                                                $shownEvents = $day['all_events'] ?? $day['events'];
                                                $repeatCount = $day['repeat_events'] ?? 0;
                                            @endphp

                                            @if ($repeatCount > 0)
                                                <p class="f-11 text-lightest mb-2">
                                                    {{ $day['recorded_events'] }} punches recorded, of which
                                                    {{ $repeatCount }} {{ $repeatCount === 1 ? 'is a repeat' : 'are repeats' }}
                                                    of the punch before — same direction, same controller, within
                                                    {{ \App\Services\Iapp\Timeline\DayTimeline::COLLAPSE_SECONDS }} seconds.
                                                    Repeats are shown faded and do not count towards the figures above.
                                                </p>
                                            @endif

                                            <div class="d-flex flex-wrap">
                                                @foreach ($shownEvents as $event)
                                                    @php
                                                        $isScan = ($event['kind'] ?? null) === 'scan';
                                                        $isRepeat = (bool) ($event['repeat'] ?? false);
                                                    @endphp
                                                    <span class="badge f-11 mr-1 mb-1 badge-{{ $isScan ? 'secondary' : ($event['direction'] === 'in' ? 'success' : 'primary') }}"
                                                          @if ($isRepeat) style="opacity:.45" @endif
                                                          title="{{ $isRepeat
                                                                ? 'Counted as a repeat of the ' . ($event['repeat_of'] ?? 'previous') . ' punch, so it does not affect the day\'s figures'
                                                                : ($isScan ? 'Face reader — direction not reported by the device' : '') }}">
                                                        {{ $event['time'] }}
                                                        {{ $isScan ? 'SCAN' : strtoupper($event['direction'] ?? '?') }}
                                                        &middot; {{ $deviceNames[$event['controller']] ?? $event['controller'] }}
                                                        @if ($isRepeat)
                                                            <i class="fa fa-clone ml-1" aria-hidden="true"></i>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center py-5">
                                        <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                        <p class="mb-0 f-13 text-lightest">
                                            No punches for this employee in the selected period.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-cards.data>
        @endif
    </div>

@endsection

@push('scripts')
    <script>
        $(function () {
            // No `title` option: it clears a pre-selected value, which is how the
            // picker on Employee Mapping once lost its suggestion.
            $('#employee-picker').selectpicker({
                liveSearch: true,
                liveSearchPlaceholder: 'Type a name or biometric code…',
                noneResultsText: 'No employee matches {0}',
                size: 12,
                width: '100%',
                // The list is attached to the page rather than to the card, which
                // clips anything taller than itself — the names were being cut off
                // behind the card below.
                container: 'body'
            });

            // Choosing someone is the whole intent, so don't make them press Apply.
            $('#employee-picker').on('changed.bs.select', function () {
                if ($(this).val()) {
                    $(this).closest('form').trigger('submit');
                }
            });

            // The same for the dates: once someone is chosen, a new range loads by
            // itself. A short pause lets both dates be changed in one go.
            let dateTimer = null;

            $('.employee-picker-row input[type="date"]').on('change', function () {
                let form = $(this).closest('form');
                let start = form.find('[name="start_date"]').val();
                let end = form.find('[name="end_date"]').val();

                clearTimeout(dateTimer);

                if (!$('#employee-picker').val() || !start || !end || start > end) {
                    return;
                }

                dateTimer = setTimeout(function () { form.trigger('submit'); }, 700);
            });
        });
    </script>
<script>
    $('.toggle-detail').on('click', function () {
        $('#' + $(this).data('target')).toggleClass('d-none');
    });
</script>
@endpush
