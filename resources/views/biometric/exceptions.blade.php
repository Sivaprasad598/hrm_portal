@extends('layouts.app')

@php
    use App\Services\Iapp\Reports\AttendanceExceptions as Exceptions;
@endphp

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        @php
            $tiles = [
                [Exceptions::TYPE_ALL, 'Days Needing Review', $summary[Exceptions::TYPE_ALL], 'text-red', 'of ' . $summary['total_days'] . ' attendance days'],
                [Exceptions::TYPE_NO_EXIT, 'No Exit Punch', $summary[Exceptions::TYPE_NO_EXIT], 'text-yellow', 'hours will be short'],
                [Exceptions::TYPE_SINGLE_PUNCH, 'Single Punch', $summary[Exceptions::TYPE_SINGLE_PUNCH], 'text-yellow', 'cannot be paired'],
                [Exceptions::TYPE_LONG_DAY, 'Unusually Long Day', $summary[Exceptions::TYPE_LONG_DAY], 'text-dark-grey', 'over ' . intdiv(Exceptions::LONG_DAY_MINUTES, 60) . ' hours'],
            ];
        @endphp

        <div class="row mb-3">
            @foreach ($tiles as [$key, $label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <a href="{{ route('biometric.exceptions', ['type' => $key, 'start_date' => $startDate, 'end_date' => $endDate, 'controller_code' => $controllerCode]) }}"
                       class="text-decoration-none">
                        <div class="card bg-white border-0 b-shadow-4 h-100 {{ $type === $key ? 'border-primary' : '' }}">
                            <div class="card-body p-3">
                                <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                                <h4 class="mb-0 f-21 {{ $value > 0 ? $colour : 'text-dark-grey' }}">{{ $value }}</h4>
                                <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        @if ($summary[Exceptions::TYPE_UNLINKED] > 0)
            {{-- Kept out of the headline count: until mapping is done almost every
                 day is unlinked, and it would bury the real punch problems. --}}
            <div class="alert alert-info f-13">
                <strong>{{ $summary[Exceptions::TYPE_UNLINKED] }}</strong> attendance day(s) belong to biometric codes
                not yet linked to an ERP employee.
                <a href="{{ route('biometric.employees') }}">Link them in Employee Mapping</a>
                or <a href="{{ route('biometric.exceptions', ['type' => Exceptions::TYPE_UNLINKED, 'start_date' => $startDate, 'end_date' => $endDate]) }}">list them here</a>.
            </div>
        @endif

        <x-cards.data padding="false" class="mb-3">
            <form method="GET" action="{{ route('biometric.exceptions') }}" class="p-3 border-bottom-grey">
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
                        <label class="f-12 text-dark-grey">Exception</label>
                        <select name="type" class="form-control f-14">
                            <option value="{{ Exceptions::TYPE_ALL }}" @selected($type === Exceptions::TYPE_ALL)>All punch exceptions</option>
                            @foreach (Exceptions::LABELS as $key => $label)
                                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
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
                        <a href="{{ route('biometric.exceptions') }}" class="btn btn-secondary f-14">@lang('app.reset')</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.employee')</th>
                            <th class="f-12">@lang('app.date')</th>
                            <th class="f-12">First In</th>
                            <th class="f-12">Last Out</th>
                            <th class="f-12">Punches</th>
                            <th class="f-12">Work Hrs</th>
                            <th class="f-12">Device</th>
                            <th class="f-12">Issue</th>
                            <th class="f-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exceptionRows as $record)
                            <tr>
                                <td class="f-13">
                                    <span class="f-w-500">{{ $record->user->name ?? ($record->employee_name ?: $record->emp_id) }}</span>
                                    <div class="f-11 text-lightest">{{ $record->emp_id }}</div>
                                </td>
                                <td class="f-13">{{ $record->attendance_date?->format('D, d M Y') }}</td>
                                <td class="f-13">{{ $record->first_in?->format('H:i') ?: '--' }}</td>
                                <td class="f-13">
                                    @if ($record->last_out)
                                        {{ $record->last_out->format('H:i') }}
                                    @else
                                        <span class="text-yellow">missing</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $record->punch_count ?: '--' }}</td>
                                <td class="f-13">{{ $record->work_hour ?: '--' }}</td>
                                <td class="f-13">{{ $devices[$record->controller_code] ?? ($record->controller_code ?: '--') }}</td>
                                <td>
                                    @foreach (Exceptions::classify($record) as $issue)
                                        <span class="badge badge-{{ $issue === Exceptions::TYPE_UNLINKED ? 'secondary' : 'warning' }} f-11 mr-1">
                                            {{ Exceptions::LABELS[$issue] }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="text-right">
                                    @if (isset($employeeIds[$record->emp_id]))
                                        <a href="{{ route('biometric.profile', ['employee' => $employeeIds[$record->emp_id], 'start_date' => $record->attendance_date->toDateString(), 'end_date' => $record->attendance_date->toDateString()]) }}"
                                           class="btn btn-sm btn-outline-secondary f-11">View day</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">No exceptions in this period.</p>
                                    <p class="mb-0 f-13 text-lightest">Every attendance day has a paired entry and exit.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($exceptionRows->hasPages())
                <div class="p-3">{{ $exceptionRows->links() }}</div>
            @endif
        </x-cards.data>

        {{-- Punches the devices could not attribute to anyone --}}
        <x-cards.data :title="'Unidentified Punches'" padding="false">
            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.date')</th>
                            <th class="f-12">Device</th>
                            <th class="f-12">Punches</th>
                            <th class="f-12">First</th>
                            <th class="f-12">Last</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unidentified as $group)
                            <tr>
                                <td class="f-13">{{ \Carbon\Carbon::parse($group->day)->format('D, d M Y') }}</td>
                                <td class="f-13">{{ $devices[$group->controller_code] ?? ($group->controller_code ?: '--') }}</td>
                                <td class="f-13"><span class="text-red f-w-500">{{ $group->punches }}</span></td>
                                <td class="f-13">{{ \Carbon\Carbon::parse($group->first_seen)->format('H:i') }}</td>
                                <td class="f-13">{{ \Carbon\Carbon::parse($group->last_seen)->format('H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 f-13 text-lightest">Every punch in this period was attributed to an employee.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="f-11 text-lightest px-3 py-2 mb-0 border-top-grey">
                Punches with no employee code: a failed read, an unenrolled card, or someone following another
                person through a door. A device with a sudden spike is worth checking.
            </p>
        </x-cards.data>

    </div>

@endsection
