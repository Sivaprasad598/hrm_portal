@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php $fmt = company()->date_format; @endphp
<div class="content-wrapper" data-swap-links>

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

    @if ($canManage)
        <a href="{{ route('job-positions.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-plus mr-1"></i>Add Position</a>
    @endif

    <div class="row">
        @forelse ($positions as $position)
            @php
                $applicants = route('job-applications.index', ['position_id' => $position->id]);
                $filled = min((int) $position->joined_count, max(1, (int) $position->openings));
                $late = $position->closing_date && $position->closing_date->isPast() && $position->status === 'open';
            @endphp
            <div class="col-xl-4 col-md-6 mb-3">
                <div class="rc-pos">
                    <div class="rc-pos-head">
                        <div style="min-width: 0">
                            <a href="{{ route('job-positions.show', $position->id) }}" class="rc-pos-title">{{ $position->title }}</a>
                            <div class="rc-pos-sub">{{ $position->code }}{{ $position->department ? ' · ' . $position->department->team_name : '' }}</div>
                        </div>
                        {!! $position->statusBadge() !!}
                    </div>

                    <div class="rc-pos-meta">
                        <span><i class="fa fa-clock"></i>{{ \App\Models\JobPosition::TYPES[$position->employment_type] ?? $position->employment_type }}</span>
                        <span><i class="fa fa-user-tie"></i>{{ $position->experienceText() }}</span>
                        @if ($position->location || $position->branch)
                            <span><i class="fa fa-map-marker-alt"></i>{{ $position->location ?: $position->branch->branch_name }}</span>
                        @endif
                        @if ($position->salaryText())
                            <span><i class="fa fa-rupee-sign"></i>{{ $position->salaryText() }}</span>
                        @endif
                    </div>

                    <div class="rc-pos-stats">
                        <div><b>{{ $position->openings }}</b>Openings</div>
                        <a href="{{ $applicants }}" class="{{ $position->new_count ? 'is-hot' : '' }}"><b>{{ $position->new_count }}</b>New</a>
                        <a href="{{ $applicants }}"><b>{{ $position->interview_count }}</b>Interview</a>
                        <a href="{{ $applicants }}"><b>{{ $position->joined_count }}</b>Joined</a>
                    </div>

                    <div class="rc-pos-fill">
                        <div class="rc-pos-fill-bar"><span style="width: {{ $position->openings ? round($filled / max(1, $position->openings) * 100) : 0 }}%"></span></div>
                        <div class="rc-pos-fill-text"><span>{{ (int) $position->joined_count }} of {{ $position->openings }} filled</span><span>{{ $position->applications_count }} applicant(s)</span></div>
                    </div>

                    <div class="rc-pos-foot">
                        <span class="rc-pos-close {{ $late ? 'is-late' : '' }}"><i class="fa fa-calendar-alt mr-1"></i>{{ $position->closing_date ? ($late ? 'Closed for applications ' : 'Closes ') . $position->closing_date->format($fmt) : 'No closing date' }}</span>
                        <span class="d-inline-flex" style="gap: 6px">
                            @if ($canManage)
                                <a href="{{ route('job-positions.edit', $position->id) }}" class="btn btn-outline-secondary openRightModal" title="Edit the position"><i class="fa fa-edit"></i></a>
                            @endif
                            <a href="{{ $applicants }}" class="btn btn-outline-primary">Applicants <i class="fa fa-arrow-right ml-1"></i></a>
                        </span>
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

    {{-- Only when the positions run past one page: the pages, nothing else --}}
    @if ($positions->hasPages())
        <div class="mt-2">{{ $positions->onEachSide(1)->links() }}</div>
    @endif
</div>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'active' => request()->hasAny(['status', 'type', 'department_id', 'search']) && request()->anyFilled(['status', 'type', 'department_id', 'search'])])
@endsection
