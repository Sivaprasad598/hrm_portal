@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper">

    {{-- Which interviews (tabs) on the left, the filters on the right, one bar --}}
    <div class="rc-toolbar mb-3">
        <nav class="rc-tabs" aria-label="Interviews">
            @foreach (['confirm' => ['Slots to confirm', 'fa-hourglass-half'], 'upcoming' => ['Upcoming', 'fa-calendar-alt'], 'today' => ['Today', 'fa-calendar-day'],
                       'past' => ['Past', 'fa-history'], 'feedback' => ['Waiting for scores', 'fa-star-half-alt']] as $key => [$label, $icon])
                <a href="{{ route('recruit-interviews.index', array_merge(request()->except('page'), ['when' => $key])) }}"
                   class="rc-tab {{ $when === $key ? 'active' : '' }}" @if ($when === $key) aria-current="page" @endif>
                    <i class="fa {{ $icon }}"></i>{{ $label }}
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('recruit-interviews.index') }}" class="rc-filters">
            <input type="hidden" name="when" value="{{ $when }}">
            <select name="position_id" class="form-control f-13" onchange="this.form.submit()" aria-label="Position">
                <option value="">All positions</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control f-13" onchange="this.form.submit()" aria-label="Status">
                <option value="">Any status</option>
                @foreach (\App\Models\RecruitInterview::STATUSES as $key => [$label])
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="rc-check mb-0">
                <input type="checkbox" name="mine" value="1" @checked(request('mine')) onchange="this.form.submit()">
                <span>I am on the panel</span>
            </label>
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
                    <td>{!! $interview->statusBadge() !!} {!! $interview->resultBadge() !!}
                        @if ($interview->status === 'scheduled' && $interview->starts_at->lt(now()->subHours(2)))
                            <div class="f-11 text-danger">past — record the outcome</div>
                        @endif
                    </td>
                    <td class="f-13">
                        @if ($interview->isTest())
                            {{ $interview->marks !== null ? \App\Models\RecruitRound::num($interview->marks) . ' / ' . \App\Models\RecruitRound::num($interview->max_marks ?: 100) : 'marks —' }}
                        @else
                            {{ $interview->feedback->count() }}/{{ count($interview->interviewer_ids ?: []) }}
                            @if ($interview->score !== null) · <strong>{{ \App\Models\RecruitRound::num($interview->score) }}/5</strong> @endif
                        @endif
                        @if (!$interview->isTest() && $interview->status === 'completed' && $missing)
                            <div class="f-11 text-warning">waiting for {{ collect($missing)->map(fn ($id) => $interviewerNames[$id] ?? '')->filter()->implode(', ') }}</div>
                        @endif
                    </td>
                    <td class="text-right pr-4 text-nowrap">
                        @if ($interview->status === 'proposed')
                            @if ($canManage)
                                <a href="javascript:;" class="btn btn-sm btn-primary confirm-slot" data-id="{{ $interview->id }}"><i class="fa fa-check mr-1"></i>Confirm</a>
                                <a href="{{ route('recruit-interviews.edit', $interview->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Change</a>
                            @endif
                        @else
                            <a href="{{ route('recruit-interviews.feedback', $interview->id) }}" class="btn btn-sm btn-outline-primary">{{ $interview->isInterviewer(user()->id) && !$interview->feedback->contains('user_id', user()->id) && !$interview->isTest() ? 'Give scorecard' : ($interview->isTest() && $interview->marks === null ? 'Enter marks' : 'Open') }}</a>
                        @endif
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
@include('recruitment.partials.standard-bar', ['form' => '.rc-filters', 'active' => request()->anyFilled(['position_id', 'status', 'mine'])])
@endsection

@push('scripts')
<script>
    // A suggested slot, confirmed in one click (the panel is told; the candidate is emailed when they have an email)
    $('body').on('click', '.confirm-slot', function () {
        $.easyAjax({
            url: '{{ route('recruit-interviews.confirm', ':id') }}'.replace(':id', $(this).data('id')), type: 'POST', blockUI: true,
            data: {_token: '{{ csrf_token() }}', email_candidate: 1},
            success: function (response) { if (response.status === 'success') { window.location.reload(); } }
        });
    });
</script>
@endpush
