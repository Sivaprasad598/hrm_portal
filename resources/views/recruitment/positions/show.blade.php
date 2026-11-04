@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php $fmt = company()->date_format; @endphp
<div class="content-wrapper">

    <div class="bg-white rounded b-shadow-4 p-20 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <div class="mb-2">
                <a href="{{ route('job-positions.index') }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Positions</a>
                <h4 class="f-21 font-weight-normal mb-1 mt-1">{{ $position->title }} {!! $position->statusBadge() !!}</h4>
                <div class="f-13 text-dark-grey">
                    {{ $position->code }}
                    · {{ \App\Models\JobPosition::TYPES[$position->employment_type] ?? $position->employment_type }}
                    · {{ $position->experienceText() }}
                    @if ($position->salaryText()) · {{ $position->salaryText() }} @endif
                    @if ($position->closing_date) · closes {{ $position->closing_date->format($fmt) }} @endif
                </div>
            </div>
            @if ($canManage)
                <div class="mb-2">
                    <a href="{{ route('candidates.create', ['position_id' => $position->id]) }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-user-plus mr-1"></i>Add Candidate</a>
                    <a href="{{ route('job-positions.edit', $position->id) }}" class="btn btn-secondary rounded f-14 openRightModal ml-1"><i class="fa fa-edit mr-1"></i>Edit</a>
                </div>
            @endif
        </div>

        <div class="row mt-3 f-13">
            @foreach ([
                'Division' => $position->department?->team_name, 'Designation' => $position->designation?->name,
                'Branch' => $position->branch?->branch_name, 'Location' => $position->location,
                'Openings' => $position->openings . ' (' . ($stageCounts['joined'] ?? 0) . ' filled)',
                'Added by' => $position->createdBy?->name,
            ] as $label => $value)
                <div class="col-md-2 col-6 mb-2">
                    <p class="text-dark-grey mb-0 f-12">{{ $label }}</p>
                    <p class="mb-0">{{ $value ?: '--' }}</p>
                </div>
            @endforeach
        </div>

        @if ($position->skills)
            <div class="mt-2">
                @foreach (array_filter(array_map('trim', explode(',', $position->skills))) as $skill)
                    <span class="rc-chip">{{ $skill }}</span>
                @endforeach
            </div>
        @endif

        @if ($position->description)
            <details class="mt-2">
                <summary class="f-13 text-primary cursor-pointer">Job description</summary>
                <div class="rc-pre mt-2">{{ $position->description }}</div>
            </details>
        @endif
    </div>

    <h5 class="f-16 font-weight-600 mb-2">Candidates</h5>
    <div class="rc-board mb-3">
        @foreach (array_merge(\App\Models\JobApplication::ACTIVE, ['joined', 'rejected', 'on_hold', 'withdrawn']) as $stage)
            @php $inStage = $applications->where('stage', $stage); @endphp
            @continue(in_array($stage, ['rejected', 'on_hold', 'withdrawn']) && $inStage->isEmpty())
            <div class="rc-col">
                <div class="rc-col-head"><span>{!! \App\Models\JobApplication::badge($stage) !!}</span><span class="text-dark-grey">{{ $inStage->count() }}</span></div>
                @foreach ($inStage as $application)
                    <div class="rc-card">
                        <a href="{{ route('candidates.show', $application->candidate_id) }}" class="rc-name">{{ $application->candidate->name }}</a>
                        <div class="rc-meta">{{ $application->candidate->experienceText() }}{{ $application->candidate->current_location ? ' · ' . $application->candidate->current_location : '' }}</div>
                        <div class="rc-meta">{{ $application->candidate->sourceLabel() }} · {{ $application->created_at->format($fmt) }}</div>
                        @if ($application->stage_reason)
                            <div class="rc-meta text-danger">{{ $application->stage_reason }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
@endsection
