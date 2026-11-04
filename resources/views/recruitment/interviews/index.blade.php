@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="btn-group mb-2">
            @foreach (['upcoming' => 'Upcoming', 'today' => 'Today', 'past' => 'Past', 'feedback' => 'Waiting for scorecards'] as $key => $label)
                <a href="{{ route('recruit-interviews.index', array_merge(request()->except('page'), ['when' => $key])) }}"
                   class="btn btn-sm {{ $when === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('recruit-interviews.index') }}" class="d-flex flex-wrap align-items-center mb-2">
            <input type="hidden" name="when" value="{{ $when }}">
            <select name="position_id" class="form-control form-control-sm f-13 mr-2" style="width: 200px" onchange="this.form.submit()">
                <option value="">All positions</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control form-control-sm f-13 mr-2" style="width: 140px" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach (\App\Models\RecruitInterview::STATUSES as $key => [$label])
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="mine" name="mine" value="1" @checked(request('mine')) onchange="this.form.submit()">
                <label class="custom-control-label f-13" for="mine">I am on the panel</label>
            </div>
        </form>
    </div>

    <div class="bg-white rounded b-shadow-4 table-responsive">
        <table class="table table-hover mb-0 f-14">
            <thead class="bg-light">
                <tr><th>When</th><th>Candidate</th><th>Round</th><th>Panel</th><th>Status</th><th>Scorecards</th><th class="text-right pr-4">Action</th></tr>
            </thead>
            <tbody>
            @forelse ($interviews as $interview)
                @php
                    $start = $interview->starts_at->copy()->tz($tz);
                    $missing = $interview->missingFeedbackIds();
                @endphp
                <tr>
                    <td class="f-13">
                        <strong class="{{ $start->isToday() ? 'text-danger' : '' }}">{{ $start->isToday() ? 'Today' : $start->format($fmt) }}</strong>
                        <div>{{ $start->format(company()->time_format) }} · {{ $interview->duration }} min</div>
                    </td>
                    <td>
                        <a href="{{ route('candidates.show', $interview->application->candidate_id) }}" class="text-darkest-grey font-weight-600">{{ $interview->application->candidate->name }}</a>
                        <div class="f-12 text-dark-grey">{{ $interview->application->positionLabel() }}</div>
                    </td>
                    <td class="f-13">{{ $interview->round }}
                        <div class="text-dark-grey"><i class="fa {{ \App\Models\RecruitInterview::MODES[$interview->mode][1] ?? 'fa-circle' }} mr-1"></i>{{ $interview->modeLabel() }}
                            @if ($interview->meeting_link) · <a href="{{ $interview->meeting_link }}" target="_blank">link</a> @endif</div>
                    </td>
                    <td class="f-13">{{ collect($interview->interviewer_ids)->map(fn ($id) => $interviewerNames[$id] ?? '')->filter()->implode(', ') }}</td>
                    <td>{!! $interview->statusBadge() !!}
                        @if ($interview->status === 'scheduled' && $interview->starts_at->lt(now()->subHours(2)))
                            <div class="f-11 text-danger">past — record the outcome</div>
                        @endif
                    </td>
                    <td class="f-13">
                        {{ $interview->feedback->count() }}/{{ count($interview->interviewer_ids ?: []) }}
                        @if ($interview->status === 'completed' && $missing)
                            <div class="f-11 text-warning">waiting for {{ collect($missing)->map(fn ($id) => $interviewerNames[$id] ?? '')->filter()->implode(', ') }}</div>
                        @endif
                    </td>
                    <td class="text-right pr-4 text-nowrap">
                        <a href="{{ route('recruit-interviews.feedback', $interview->id) }}" class="btn btn-sm btn-outline-primary">{{ $interview->isInterviewer(user()->id) && !$interview->feedback->contains('user_id', user()->id) ? 'Give scorecard' : 'Open' }}</a>
                        @if ($canManage && $interview->status === 'scheduled')
                            <a href="{{ route('recruit-interviews.close-form', $interview->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Outcome</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-cards.no-record icon="calendar" message="No interviews here." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $interviews->links() }}</div>
</div>
@endsection
