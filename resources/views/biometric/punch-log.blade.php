@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        <x-cards.data padding="false">

            {{-- Filters --}}
            <form method="GET" action="{{ route('biometric.punch-log') }}" class="p-3 border-bottom-grey">
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
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Device</label>
                        <select name="controller_code" class="form-control f-14">
                            <option value="">@lang('app.all')</option>
                            @foreach ($controllers as $code => $name)
                                <option value="{{ $code }}" @selected($controllerCode == $code)>
                                    {{ $name ?: $code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary f-14 mr-1">@lang('app.apply')</button>
                        <a href="{{ route('biometric.punch-log') }}" class="btn btn-secondary f-14">@lang('app.reset')</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.employee')</th>
                            <th class="f-12">Emp Code</th>
                            <th class="f-12">Card ID</th>
                            <th class="f-12">Punch Time</th>
                            <th class="f-12">Direction</th>
                            <th class="f-12">Controller</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($punches as $punch)
                            <tr>
                                <td class="f-13">
                                    @if ($punch->user)
                                        {{ $punch->user->name }}
                                    @else
                                        <span class="badge badge-danger f-11">Unmatched</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $punch->emp_code ?: '--' }}</td>
                                <td class="f-13">{{ $punch->card_id ?: '--' }}</td>
                                <td class="f-13">
                                    {{ $punch->punch_time?->format(company()->date_format . ' ' . company()->time_format) ?: '--' }}
                                </td>
                                <td>
                                    @if ($punch->punch_direction === 'in')
                                        <span class="badge badge-success f-11">IN</span>
                                    @elseif ($punch->punch_direction === 'out')
                                        <span class="badge badge-primary f-11">OUT</span>
                                    @else
                                        <span class="badge badge-secondary f-11">Unknown</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $punch->controller_code ?: '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                    <p class="mb-0 f-13 text-lightest">
                                        Raw device punches appear here once a device transaction sync has run.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($punches->hasPages())
                <div class="p-3">
                    {{ $punches->links() }}
                </div>
            @endif

        </x-cards.data>
    </div>

@endsection
