@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $query = request()->except(['view', 'page']);
@endphp
<div class="content-wrapper">

    <form method="GET" action="{{ route('job-applications.index') }}" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <input type="hidden" name="view" value="{{ $board ? 'board' : 'list' }}">
        <div class="row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Position</label>
                <select name="position_id" class="form-control select-picker" data-live-search="true">
                    <option value="">All positions</option>
                    <option value="unassigned" @selected(request('position_id') === 'unassigned')>Unassigned ({{ $unassignedCount }})</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->title }} ({{ $position->code }}){{ $position->status === 'closed' ? ' — closed' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Stage</label>
                <select name="stage" class="form-control select-picker">
                    <option value="active" @selected($stage === 'active')>Still moving</option>
                    @unless ($board)<option value="all" @selected($stage === 'all')>All</option>@endunless
                    @foreach (\App\Models\JobApplication::STAGES as $key => [$label])
                        <option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Source</label>
                <select name="source" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach (\App\Models\Candidate::SOURCES as $key => [$label])
                        <option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Name, email, mobile, skill">
            </div>
            <div class="col-md-2 mb-2 text-right">
                <button type="submit" class="btn btn-primary rounded f-14"><i class="fa fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('job-applications.index', ['view' => $board ? 'board' : 'list']) }}" class="btn btn-secondary rounded f-14">Clear</a>
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="btn-group mb-2">
            <a href="{{ route('job-applications.index', $query + ['view' => 'board']) }}" class="btn btn-sm {{ $board ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fa fa-columns mr-1"></i>Board</a>
            <a href="{{ route('job-applications.index', $query + ['view' => 'list']) }}" class="btn btn-sm {{ $board ? 'btn-outline-secondary' : 'btn-primary' }}"><i class="fa fa-list mr-1"></i>List</a>
        </div>
        @if ($canManage)
            <a href="{{ route('candidates.create', ['position_id' => is_numeric(request('position_id')) ? request('position_id') : null]) }}" class="btn btn-primary rounded f-14 openRightModal mb-2"><i class="fa fa-user-plus mr-1"></i>Add Candidate</a>
        @endif
    </div>

    @if ($board)
        <div class="rc-board">
            @foreach ($columns as $column)
                @php $cards = $applications->get($column, collect()); @endphp
                <div class="rc-col">
                    <div class="rc-col-head"><span>{!! \App\Models\JobApplication::badge($column) !!}</span><span class="text-dark-grey">{{ $cards->count() }}</span></div>
                    @forelse ($cards as $application)
                        <div class="rc-card">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('candidates.show', $application->candidate_id) }}" class="rc-name">{{ $application->candidate->name }}</a>
                                @if ($canManage)
                                    <div class="dropdown">
                                        <a href="#" class="text-dark-grey px-1" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu dropdown-menu-right f-13">
                                            @foreach (\App\Services\Recruit\Pipeline::choices($application) as $to)
                                                <a class="dropdown-item openRightModal" href="{{ route('job-applications.move-form', [$application->id, 'to' => $to]) }}">Move to {{ \App\Models\JobApplication::STAGES[$to][0] }}</a>
                                            @endforeach
                                            @if (in_array($application->stage, ['new', 'shortlisted', 'interview', 'on_hold']))
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item openRightModal" href="{{ route('recruit-interviews.create', ['application' => $application->id]) }}">Schedule interview</a>
                                            @endif
                                            @if ($application->stage === 'selected')
                                                <a class="dropdown-item openRightModal" href="{{ route('job-offers.create', ['application' => $application->id]) }}">Make offer</a>
                                            @endif
                                            @if ($application->stage === 'offer_accepted')
                                                <a class="dropdown-item" href="{{ route('candidates.convert', $application->candidate_id) }}">Convert to employee</a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="rc-meta">{{ $application->positionLabel() }}</div>
                            <div class="rc-meta">{{ $application->candidate->experienceText() }}{{ $application->candidate->current_location ? ' · ' . $application->candidate->current_location : '' }}</div>
                            <div class="rc-meta d-flex justify-content-between mt-1">
                                <span><i class="fa {{ \App\Models\Candidate::SOURCES[$application->source][1] ?? 'fa-circle' }} mr-1"></i>{{ ($application->stage_changed_at ?? $application->created_at)->diffForHumans(null, true) }}</span>
                                @if ($application->candidate->resume)
                                    <a href="{{ $application->candidate->resume->url }}" target="_blank" title="Resume"><i class="fa fa-file-alt"></i></a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="f-12 text-dark-grey text-center my-3">None</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded b-shadow-4 table-responsive">
            <table class="table table-hover mb-0 f-14">
                <thead class="bg-light">
                    <tr><th>Candidate</th><th>Position</th><th>Stage</th><th>Source</th><th>Applied</th><th class="text-center">Interviews done</th><th class="text-right pr-4">Action</th></tr>
                </thead>
                <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>
                            <a href="{{ route('candidates.show', $application->candidate_id) }}" class="text-darkest-grey font-weight-600">{{ $application->candidate->name }}</a>
                            <div class="f-12 text-dark-grey">{{ $application->candidate->email }}{{ $application->candidate->mobile ? ' · ' . $application->candidate->mobile : '' }}</div>
                        </td>
                        <td class="f-13">{!! $application->position ? e($application->position->title) : '<span class="text-warning">Unassigned</span>' !!}</td>
                        <td>{!! $application->stageBadge() !!}
                            @if ($application->stage_reason)<div class="f-12 text-dark-grey">{{ $application->stage_reason }}</div>@endif</td>
                        <td class="f-13">{{ \App\Models\Candidate::SOURCES[$application->source][0] ?? $application->source }}</td>
                        <td class="f-13">{{ $application->created_at->format($fmt) }}</td>
                        <td class="text-center">{{ $application->interviews_done }}</td>
                        <td class="text-right pr-4">
                            @if ($canManage && $application->stage !== 'joined')
                                <a href="{{ route('job-applications.move-form', $application->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">{{ $application->position ? 'Move' : 'Assign' }}</a>
                            @endif
                            <a href="{{ route('candidates.show', $application->candidate_id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-cards.no-record icon="user" message="No applications here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $applications->links() }}</div>
    @endif
</div>
@endsection
