@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php $fmt = company()->date_format; @endphp
<div class="content-wrapper">

    <form method="GET" action="{{ route('job-positions.index') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-md-3 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Division</label>
                <select name="department_id" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">All</option>
                    @include('departments.partials.tree-options', ['departments' => $departments, 'selected' => request('department_id')])
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Type</label>
                <select name="type" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach (\App\Models\JobPosition::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Status</label>
                <select name="status" class="form-control select-picker">
                    <option value="">Not closed</option>
                    @foreach (\App\Models\JobPosition::STATUSES as $key => [$label])
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }} ({{ $statusCounts[$key] ?? 0 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5 col-12 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search job positions</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Title, code, location, skill">
            </div>
            <div class="col-md-2 mb-2 text-right">
                <button type="submit" class="btn btn-primary rounded f-14"><i class="fa fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('job-positions.index') }}" class="btn btn-secondary rounded f-14">Clear</a>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="f-15 font-weight-600 text-dark">{{ $positions->total() }} position(s)</span>
        @if ($canManage)
            <a href="{{ route('job-positions.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-plus mr-1"></i>Add Position</a>
        @endif
    </div>

    <div class="row">
        @forelse ($positions as $position)
            <div class="col-xl-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100 rc-position-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <a href="{{ route('job-positions.show', $position->id) }}" class="f-16 font-weight-600 text-darkest-grey">{{ $position->title }}</a>
                                <div class="f-12 text-dark-grey">{{ $position->code }}{{ $position->department ? ' · ' . $position->department->team_name : '' }}</div>
                            </div>
                            {!! $position->statusBadge() !!}
                        </div>

                        <div class="f-13 text-dark-grey my-2">
                            <span class="mr-3"><i class="fa fa-clock mr-1"></i>{{ \App\Models\JobPosition::TYPES[$position->employment_type] ?? $position->employment_type }}</span>
                            <span class="mr-3"><i class="fa fa-user-tie mr-1"></i>{{ $position->experienceText() }}</span>
                            @if ($position->location || $position->branch)
                                <span><i class="fa fa-map-marker-alt mr-1"></i>{{ $position->location ?: $position->branch->branch_name }}</span>
                            @endif
                            @if ($position->salaryText())
                                <div class="mt-1"><i class="fa fa-rupee-sign mr-1"></i>{{ $position->salaryText() }}</div>
                            @endif
                        </div>

                        <div class="d-flex text-center border rounded py-2 my-2 f-12">
                            <div class="flex-fill"><div class="f-16 font-weight-700">{{ $position->openings }}</div>Openings</div>
                            <div class="flex-fill border-left"><div class="f-16 font-weight-700">{{ $position->new_count }}</div>New</div>
                            <div class="flex-fill border-left"><div class="f-16 font-weight-700">{{ $position->interview_count }}</div>Interview</div>
                            <div class="flex-fill border-left"><div class="f-16 font-weight-700">{{ $position->joined_count }}</div>Joined</div>
                        </div>

                        <div class="mt-auto d-flex justify-content-between align-items-center f-12 text-dark-grey">
                            <span>
                                @if ($position->closing_date)
                                    <span class="{{ $position->closing_date->isPast() && $position->status === 'open' ? 'text-danger' : '' }}">Closes {{ $position->closing_date->format($fmt) }}</span>
                                @else
                                    No closing date
                                @endif
                            </span>
                            <span>
                                <a href="{{ route('job-applications.index', ['position_id' => $position->id]) }}" class="mr-2">Applicants ({{ $position->applications_count }})</a>
                                @if ($canManage)
                                    <a href="{{ route('job-positions.edit', $position->id) }}" class="openRightModal"><i class="fa fa-edit"></i></a>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="bg-white rounded b-shadow-4 p-4">
                    <x-cards.no-record icon="briefcase" message="No positions here. Add the first opening to start receiving candidates for it." />
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-2">{{ $positions->links() }}</div>
</div>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'active' => request()->hasAny(['status', 'type', 'department_id', 'search']) && request()->anyFilled(['status', 'type', 'department_id', 'search'])])
@endsection
