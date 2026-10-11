@extends('layouts.app')

@php
    use App\Services\Iapp\Reports\EmployeeAttendanceProfile as Profile;
    use App\Services\Iapp\Shifts\ShiftCalendar as Calendar;
    use App\Services\Iapp\Shifts\ShiftDetector as Detector;

    $matchColours = [
        Detector::MATCH_MATCHES => 'success',
        Detector::MATCH_DIFFERS => 'warning',
        Detector::MATCH_UNLISTED => 'warning',
        Detector::MATCH_UNASSIGNED => 'secondary',
        Detector::MATCH_INSUFFICIENT => 'secondary',
    ];

    $confidenceColours = ['high' => 'success', 'medium' => 'info', 'low' => 'secondary', 'none' => 'secondary'];
@endphp

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        <div class="alert alert-info f-13">
            <strong>Shifts come from the ERP.</strong>
            The biometric system exposes no shift, roster or week-off data, so each attended day is measured
            against the employee's ERP schedule, or the default shift
            <strong>{{ Calendar::label($defaultShift) }}</strong> where none is rostered.
            The detected pattern is inferred from punches and only suggests — nothing here changes a roster.
        </div>

        @if ($shiftConflict)
            <div class="alert alert-warning f-13">
                <strong>Conflicting office hours.</strong>
                Attendance settings say <strong>{{ $shiftConflict['settings'][0] }}–{{ $shiftConflict['settings'][1] }}</strong>,
                but the default shift <strong>{{ $shiftConflict['shift_name'] }}</strong> is
                <strong>{{ $shiftConflict['shift'][0] }}–{{ $shiftConflict['shift'][1] }}</strong>.
                These figures follow the shift record, which is what the ERP's attendance code uses.
                Aligning the two is recommended.
            </div>
        @endif

        @php
            $s = $shiftSummary;
            $tiles = [
                ['On Time', $s['on_time_pct'] !== null ? $s['on_time_pct'] . '%' : '--', 'text-primary', $s['on_time'] . ' of ' . ($s['on_time'] + $s['late_days']) . ' shift days'],
                ['Late Arrivals', $s['late_days'], $s['late_days'] ? 'text-red' : 'text-dark-grey', Profile::humanMinutes($s['late_minutes']) . ' late in total'],
                ['Early Exits', $s['early_exits'], $s['early_exits'] ? 'text-yellow' : 'text-dark-grey', $s['short_days'] . ' day(s) short of minimum'],
                ['Overtime', Profile::humanMinutes($s['overtime_minutes']), 'text-dark-grey', $s['off_day_days'] . ' off-day(s) worked'],
                ['Unlisted Patterns', $s['unlisted'], $s['unlisted'] ? 'text-yellow' : 'text-dark-grey', 'no matching ERP shift'],
                ['Differs from Roster', $s['differs'], $s['differs'] ? 'text-yellow' : 'text-dark-grey', 'detected ≠ assigned'],
            ];
        @endphp

        <div class="row mb-3">
            @foreach ($tiles as [$label, $value, $colour, $sub])
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

        @if ($s['assumed'] > 0)
            <p class="f-12 text-lightest mb-3">
                {{ $s['assumed'] }} of {{ $s['employees'] }} biometric code(s) are not linked to an ERP employee, so they are
                measured against the default shift and without leave or department holidays.
                <a href="{{ route('biometric.employees') }}">Link them</a> for exact figures.
            </p>
        @endif

        <x-cards.data padding="false">
            <form method="GET" action="{{ route('biometric.shifts') }}" class="p-3 border-bottom-grey">
                <div class="row align-items-end">
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.startDate')</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.endDate')</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Pattern</label>
                        <select name="match" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach (Detector::MATCH_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected($matchFilter === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Device</label>
                        <select name="controller_code" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach ($devices as $code => $name)
                                <option value="{{ $code }}" @selected($controllerCode == $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary f-14 mr-1">@lang('app.apply')</button>
                        <a href="{{ route('biometric.shifts') }}" class="btn btn-secondary f-14">@lang('app.reset')</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.employee')</th>
                            <th class="f-12">Assigned Shift</th>
                            <th class="f-12">Detected Pattern</th>
                            <th class="f-12">Days</th>
                            <th class="f-12">On Time</th>
                            <th class="f-12">Late</th>
                            <th class="f-12">Early Exits</th>
                            <th class="f-12">Overtime</th>
                            <th class="f-12">Short</th>
                            <th class="f-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shiftRows as $row)
                            @php $d = $row['detection']; @endphp
                            <tr>
                                <td class="f-13">
                                    <span class="f-w-500">{{ $row['erp_name'] ?? ($row['device_name'] ?: $row['emp_code']) }}</span>
                                    <div class="f-11 text-lightest">
                                        {{ $row['emp_code'] }}
                                        @if ($row['assumed'])
                                            &middot; not linked
                                        @endif
                                    </div>
                                </td>
                                <td class="f-13">
                                    {{ Calendar::label($row['assigned']) }}
                                    @if ($row['assumed'])
                                        <div class="f-11 text-lightest">default, assumed</div>
                                    @endif
                                </td>
                                <td class="f-13">
                                    {{ $d['label'] }}
                                    <div class="f-11">
                                        @if ($d['start'])
                                            <span class="text-lightest">median {{ $d['start'] }}–{{ $d['end'] ?? '?' }}</span>
                                        @endif
                                        <span class="badge badge-{{ $confidenceColours[$d['confidence']] ?? 'secondary' }} f-10">{{ $d['confidence'] }}</span>
                                        <span class="badge badge-{{ $matchColours[$row['match']] ?? 'secondary' }} f-10">{{ Detector::MATCH_LABELS[$row['match']] }}</span>
                                    </div>
                                </td>
                                <td class="f-13">
                                    {{ $row['days_attended'] }}
                                    @if ($row['off_day_days'])
                                        <div class="f-11 text-lightest">{{ $row['off_day_days'] }} off-day</div>
                                    @endif
                                </td>
                                <td class="f-13">{{ $row['on_time_pct'] !== null ? $row['on_time_pct'] . '%' : '--' }}</td>
                                <td class="f-13">
                                    @if ($row['late_days'])
                                        <span class="text-red">{{ $row['late_days'] }}</span>
                                        <div class="f-11 text-lightest">{{ Profile::humanMinutes($row['late_minutes']) }}</div>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="f-13">
                                    @if ($row['early_exits'])
                                        <span class="text-yellow">{{ $row['early_exits'] }}</span>
                                        <div class="f-11 text-lightest">{{ Profile::humanMinutes($row['early_minutes']) }}</div>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="f-13">{{ Profile::humanMinutes($row['overtime_minutes']) }}</td>
                                <td class="f-13">{{ $row['short_days'] }}</td>
                                <td class="text-right">
                                    @if (isset($employeeIds['c:' . $row['emp_code']]))
                                        <a href="{{ route('biometric.profile', ['employee' => $employeeIds['c:' . $row['emp_code']], 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                                           class="btn btn-sm btn-outline-secondary f-11">Employee 360</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                    <p class="mb-0 f-13 text-lightest">No attendance in this period.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="f-11 text-lightest px-3 py-2 mb-0 border-top-grey">
                Late follows the ERP rule: strict shifts only, after start plus the shift's late-mark grace. Half days
                count as late. Detected patterns use median first-in and last-out: arrival must fall within
                {{ Detector::TOLERANCE_MINUTES }} minutes of an ERP shift's start, while departure may differ by up to
                {{ intdiv(Detector::END_TOLERANCE_MINUTES, 60) }} hours, since staying late is overtime rather than
                a different shift. Confidence rises with more days and steadier arrival.
            </p>
        </x-cards.data>

    </div>

@endsection
