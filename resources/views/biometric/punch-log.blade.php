@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab, 'navFilters' => true,
            'navFiltersActive' => request()->filled('department_id') || request()->filled('user_id') || request()->filled('controller_code'),
            'navFiltersOpen' => request()->hasAny(['start_date', 'end_date', 'department_id', 'user_id', 'controller_code'])])

        {{-- Filters: a card of their own, opened from "Filters" at the top right --}}
        <div class="bio-filter-card">
            {{-- No Apply / Reset: any change reloads the list (the card stays open) --}}
            <form method="GET" action="{{ route('biometric.punch-log') }}" class="p-3" id="pl-filters">
                <div class="row align-items-end">
                    <div class="col-md mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.startDate')</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.endDate')</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control f-14">
                    </div>
                    <div class="col-md mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.department')</label>
                        <select name="department_id" id="pick-department" class="form-control f-14 select-picker" data-live-search="true" data-size="8">
                            <option value="">@lang('app.all')</option>
                            @foreach ($departments as $deptId => $deptName)
                                <option value="{{ $deptId }}" @selected($departmentId == $deptId)>{{ $deptName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                        {{-- Searchable by name, employee ID, device code or card ID (QA TC-0016) --}}
                        <select name="user_id" id="pick-employee" class="form-control f-14 select-picker" data-live-search="true" data-size="8"
                                title="@lang('app.all')">
                            <option value="">@lang('app.all')</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" data-department="{{ $employee->department_id }}"
                                        data-tokens="{{ $employee->tokens }}" data-subtext="{{ $employee->subtext }}"
                                        @selected($userId == $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md mb-2">
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
                </div>
            </form>
        </div>

        <x-cards.data padding="false">

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

            {{-- Show N entries · Showing x to y of z · pages: like the app's other tables --}}
            <div class="pl-pager">
                <form method="GET" class="pl-pager__size">
                    @foreach (request()->except(['per_page', 'page']) as $k => $v)
                        @if (!is_array($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                    @endforeach
                    <span>Show</span>
                    <select name="per_page" class="form-control" onchange="this.form.submit()">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($punches->perPage() === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <span>entries</span>
                </form>
                <div class="pl-pager__info">
                    @if ($punches->total())
                        Showing {{ $punches->firstItem() }} to {{ $punches->lastItem() }} of {{ number_format($punches->total()) }} entries
                    @endif
                </div>
                @if ($punches->hasPages())
                    <div class="pl-pager__links">{{ $punches->onEachSide(1)->links() }}</div>
                @endif
            </div>
            <style>
                .pl-pager { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 20px; padding: 16px 12px 4px; }
                .pl-pager__size { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px; color: #344054; }
                .pl-pager__size select { width: 72px; height: 36px; border-radius: 8px; }
                .pl-pager__info { margin-left: auto; font-size: 14px; color: #475467; }
                .pl-pager__links .pagination { margin: 0; }
            </style>

        </x-cards.data>
    </div>

@endsection

@push('scripts')
    @include('biometric.partials.department-filter', ['department' => 'pick-department', 'employee' => 'pick-employee'])
    <script>
        // every filter applies as soon as it changes (dates, department, employee, device)
        $(function () {
            var form = document.getElementById('pl-filters');
            $(form).on('change', 'input, select', function () {
                // a department change also clears the employee no longer in it (department-filter): send after it
                setTimeout(function () { form.submit(); }, 0);
            });
        });
    </script>
@endpush
