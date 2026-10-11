@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Day-type totals for the selected range --}}
        @php
            $tiles = [
                ['label' => 'Total', 'value' => $summary['total'], 'color' => 'text-dark-grey'],
                ['label' => 'Present', 'value' => $summary['present'], 'color' => 'text-primary'],
                ['label' => 'Absent', 'value' => $summary['absent'], 'color' => 'text-red'],
                ['label' => 'Leave', 'value' => $summary['leave'], 'color' => 'text-yellow'],
                ['label' => 'Week Off', 'value' => $summary['week_off'], 'color' => 'text-lightest'],
                ['label' => 'Holiday', 'value' => $summary['holiday'], 'color' => 'text-lightest'],
            ];
        @endphp

        <div class="row mb-3">
            @foreach ($tiles as $tile)
                <div class="col-md-2 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $tile['label'] }}</p>
                            <h4 class="mb-0 f-21 {{ $tile['color'] }}">{{ $tile['value'] }}</h4>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <x-cards.data padding="false">

            {{-- Filters --}}
            <form method="GET" action="{{ route('biometric.attendance') }}" class="p-3 border-bottom-grey" id="attendance-filters">
                <div class="row align-items-end">
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.startDate')</label>
                        <input type="date" name="start_date" id="filter_start_date" value="{{ $startDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.endDate')</label>
                        <input type="date" name="end_date" id="filter_end_date" value="{{ $endDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">Device</label>
                        <select name="controller_code" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach ($devices as $code => $name)
                                <option value="{{ $code }}" @selected($controllerCode == $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.department')</label>
                        <select name="department_id" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($departmentId == $department->id)>
                                    {{ $department->team_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                        <select name="user_id" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected($userId == $employee->id)>
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.status')</label>
                        <select name="day_type" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach (['present', 'absent', 'leave', 'week_off', 'holiday'] as $type)
                                <option value="{{ $type }}" @selected($dayType == $type)>
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="d-flex align-items-center flex-wrap mt-2">
                    <button type="submit" class="btn btn-primary f-14 mr-2">@lang('app.apply')</button>
                    <a href="{{ route('biometric.attendance') }}" class="btn btn-secondary f-14 mr-2">@lang('app.reset')</a>

                    {{-- Pulls the selected range from the device, then rebuilds the
                         attendance days from those punches. --}}
                    <button type="button" class="btn btn-outline-primary f-14 mr-3" id="pull-from-device">
                        <i class="fa fa-download mr-1"></i> Pull from Device
                    </button>

                    <span class="f-12 text-lightest">
                        {{ number_format($punchCount) }} punch(es) stored for this range
                    </span>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.employee')</th>
                            <th class="f-12">Emp ID</th>
                            <th class="f-12">@lang('app.date')</th>
                            <th class="f-12">First In</th>
                            <th class="f-12">Last Out</th>
                            <th class="f-12">Work Hrs</th>
                            <th class="f-12">Sessions</th>
                            <th class="f-12">Device</th>
                            <th class="f-12">Punches</th>
                            <th class="f-12">@lang('app.status')</th>
                            <th class="f-12">ERP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td class="f-13">
                                    @if ($record->user)
                                        {{ $record->user->name }}
                                    @else
                                        <span class="text-red">{{ $record->employee_name ?: '--' }}</span>
                                        <span class="badge badge-danger f-11 ml-1">Unmatched</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $record->emp_id }}</td>
                                <td class="f-13">{{ $record->attendance_date?->format(company()->date_format) }}</td>
                                <td class="f-13">{{ $record->first_in?->format(company()->time_format) ?: '--' }}</td>
                                <td class="f-13">
                                    @if ($record->last_out)
                                        {{ $record->last_out->format(company()->time_format) }}
                                    @else
                                        <span class="text-yellow" title="No exit punch recorded">--</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $record->work_hour ?: '--' }}</td>
                                <td class="f-13">
                                    @php $sessions = $record->sessions ?: []; @endphp
                                    @if ($sessions)
                                        <span title="{{ collect($sessions)->map(fn ($s) => ($s['in'] ?? '?') . ' - ' . ($s['out'] ?? 'open'))->implode(', ') }}">
                                            {{ count($sessions) }}
                                        </span>
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="f-13">
                                    {{ $devices[$record->controller_code] ?? ($record->controller_code ?: '--') }}
                                    @php $all = $record->controller_codes ?: []; @endphp
                                    @if (count($all) > 1)
                                        <span class="text-lightest f-11" title="{{ implode(', ', $all) }}">+{{ count($all) - 1 }}</span>
                                    @endif
                                </td>
                                {{-- The device's own total. punch_count holds the figure
                                     after gate repeats are collapsed, which read as wrong
                                     here: the column says PUNCHES and the line above the
                                     table reports how many are stored for the range. --}}
                                <td class="f-13">
                                    @php
                                        $rawPunches = $record->raw_punch_count ?? $record->punch_count;
                                        $collapsed = $rawPunches - (int) $record->punch_count;
                                    @endphp

                                    {{ $rawPunches ?: '--' }}

                                    @if ($collapsed > 0)
                                        <span class="text-lightest f-11"
                                              title="{{ $collapsed }} of these repeat the punch before them (same direction, same controller, within {{ \App\Services\Iapp\Timeline\DayTimeline::COLLAPSE_SECONDS }}s), so {{ $record->punch_count }} are counted towards the day's figures.">
                                            ({{ $record->punch_count }} counted)
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $record->day_type_color }} f-11">
                                        {{ ucwords(str_replace('_', ' ', $record->day_type)) }}
                                    </span>
                                </td>
                                <td>
                                    {{-- What reached the ERP's attendance, read from the links
                                         Attendance Sync writes. The staging table's own
                                         promote_status is from an older design and never
                                         leaves 'pending'. --}}
                                    @if ($record->erp_written ?? false)
                                        <span class="badge badge-success f-11"
                                              title="Attendance Sync has written this day into the ERP's attendance.">
                                            In Attendance
                                        </span>
                                    @elseif (is_null($record->user_id))
                                        <span class="badge badge-danger f-11"
                                              title="This device code is not mapped to an employee, so the day cannot be written.">
                                            Unmapped
                                        </span>
                                    @else
                                        <span class="badge badge-secondary f-11"
                                              title="Mapped, but Attendance Sync has not written this day yet.">
                                            Not Synced
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                    <p class="mb-0 f-13 text-lightest">
                                        Choose a date range and use <strong>Pull from Device</strong> to fetch it.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="p-3">
                    {{ $records->links() }}
                </div>
            @endif

        </x-cards.data>
    </div>

@endsection

@push('scripts')
<script>
    $('#pull-from-device').on('click', function () {
        var button = $(this);
        var startDate = $('#filter_start_date').val();
        var endDate = $('#filter_end_date').val();

        if (!startDate || !endDate) {
            Swal.fire({ icon: 'warning', text: 'Choose a start and end date first.' });
            return;
        }

        // The feed returns 100 rows per request and ignores page size, so a
        // long range means many sequential calls — worth warning about rather
        // than appearing to hang.
        Swal.fire({
            title: 'Pull from device?',
            text: 'Fetches punches for the selected range and rebuilds attendance. This can take a while for a long range.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Pull now',
            cancelButtonText: '@lang('app.cancel')'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Pulling…');

            $.easyAjax({
                url: "{{ route('biometric.sync') }}",
                type: 'POST',
                data: { start_date: startDate, end_date: endDate, _token: '{{ csrf_token() }}' },
                success: function (response) {
                    if (response.status === 'success') {
                        window.location.reload();
                        return;
                    }

                    button.prop('disabled', false).html('<i class="fa fa-download mr-1"></i> Pull from Device');
                },
                error: function () {
                    button.prop('disabled', false).html('<i class="fa fa-download mr-1"></i> Pull from Device');
                }
            });
        });
    });
</script>
@endpush
