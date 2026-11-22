{{-- The quick view's rounds, in full: each round of the position's plan with its slot, panel,
     score and HR's decision, the step it is waiting for, and that step's button. On top, what
     stands between the applicant and Selected. $application, $canManage. --}}
@php
    $automation = app(\App\Services\Recruit\RecruitAutomation::class);
    $planRounds = $automation->rounds($application);
    $live = !in_array($application->stage, ['offered', 'offer_accepted', 'joined', 'rejected', 'withdrawn'], true);
    $toDecide = $planRounds->filter(fn ($r) => $r->interview && $r->state === 'to_decide');
    $open = $planRounds->filter(fn ($r) => !in_array($r->state, ['passed', 'waived'], true));
    $extras = $application->interviews()->whereNull('recruit_round_id')->whereIn('status', ['scheduled', 'completed', 'proposed'])->get();
    $inside = 'inModal';
@endphp

@if ($planRounds->isNotEmpty() || $extras->isNotEmpty())
    <div class="qv-sec">
        <h6>Rounds</h6>

        {{-- What is next --}}
        @if ($live && $planRounds->isNotEmpty() && in_array($application->stage, ['shortlisted', 'interview'], true))
            @if ($open->isEmpty())
                <div class="qv-next qv-next--ok">
                    <span><i class="fa fa-check-circle mr-1"></i>Every round is cleared. The applicant can be moved to <strong>Selected</strong>.</span>
                    @if ($canManage)
                        <a href="{{ route('job-applications.move-form', $application->id) }}?to=selected" class="btn btn-sm btn-success openRightModal {{ $inside }}">Move to Selected</a>
                    @endif
                </div>
            @elseif ($toDecide->isNotEmpty())
                @php $first = $toDecide->first(); @endphp
                <div class="qv-next qv-next--warn">
                    <span><i class="fa fa-gavel mr-1"></i><strong>{{ $first->round->name }}</strong> is waiting for your decision ({{ $automation->scoreText($first->interview) }}{{ $first->round->cut_off === null ? ', no pass mark set' : '' }}). Selected opens once every round is cleared or waived.</span>
                    @if ($canManage)
                        <a href="{{ route('recruit-interviews.decide-form', $first->interview->id) }}" class="btn btn-sm btn-primary openRightModal {{ $inside }}">Decide</a>
                    @endif
                </div>
            @else
                <div class="qv-next">
                    <span><i class="fa fa-info-circle mr-1"></i>Selected opens once every round is cleared or waived. Still open: {{ $open->map(fn ($r) => $r->round->name)->implode(', ') }}.</span>
                </div>
            @endif
        @endif

        @foreach ($planRounds as $item)
            @php
                $iv = $item->interview;
                $round = $item->round;
                $state = $item->state;
            @endphp
            <div class="qv-round {{ in_array($state, ['passed', 'waived']) ? 'is-done' : '' }} {{ $state === 'failed' ? 'is-failed' : '' }} {{ $state === 'to_decide' ? 'is-wait' : '' }}">
                <span class="qv-round-no">{!! in_array($state, ['passed', 'waived']) ? '<i class="fa fa-check"></i>' : $item->no !!}</span>
                <div class="qv-round-body">
                    <div class="qv-round-head">
                        <strong>{{ $round->name }}</strong>
                        <span class="text-dark-grey">{{ $round->isTest() ? 'Test' : 'Interview' }} · {{ $round->passText() }}</span>
                        {!! \App\Services\Recruit\RecruitAutomation::stateBadge($state) !!}
                    </div>
                    @if ($iv)
                        <div class="qv-round-meta">
                            <span><i class="fa fa-calendar"></i>{{ $iv->whenText() }}</span>
                            <span><i class="fa {{ \App\Models\RecruitInterview::MODES[$iv->mode][1] ?? 'fa-circle' }}"></i>{{ $iv->modeLabel() }}{{ $iv->location ? ' · ' . $iv->location : '' }}</span>
                            <span><i class="fa fa-users"></i>{{ $iv->interviewers()->pluck('name')->implode(', ') ?: 'no panel yet' }}</span>
                            @if (!$round->isTest() && in_array($iv->status, ['scheduled', 'completed']))
                                <span><i class="fa fa-clipboard-check"></i>scorecards {{ $iv->feedback->count() }}/{{ count($iv->interviewer_ids ?: []) }}</span>
                            @endif
                        </div>
                        @if ($iv->score !== null || $iv->marks !== null)
                            <div class="qv-round-score">Score: {{ $automation->scoreText($iv) }}</div>
                        @endif
                        @foreach ($iv->feedback as $card)
                            <div class="qv-round-card"><strong>{{ $card->user?->name }}</strong> · {{ $card->overall }}/5 {!! $card->recommendationBadge() !!}
                                @if ($card->comments)<div class="text-dark-grey">{{ \Illuminate\Support\Str::limit($card->comments, 160) }}</div>@endif</div>
                        @endforeach
                        @if ($iv->result_note && $iv->result_by)
                            <div class="qv-round-card"><i class="fa fa-gavel mr-1"></i>HR: {{ $iv->result_note }}</div>
                        @endif

                        @if ($canManage && $live)
                            <div class="qv-round-actions">
                                @if ($state === 'proposed')
                                    @include('recruitment.partials.confirm-slot', ['interview' => $iv, 'confirmInModal' => true])
                                @elseif ($iv->status === 'scheduled' && !in_array($state, ['passed', 'failed', 'waived'], true))
                                    <a href="{{ route('recruit-interviews.edit', $iv->id) }}" class="btn btn-sm btn-outline-secondary openRightModal {{ $inside }}">Reschedule</a>
                                @endif
                                @if (in_array($iv->status, ['scheduled', 'completed']))
                                    <a href="{{ route('recruit-interviews.feedback', $iv->id) }}" class="btn btn-sm btn-outline-secondary">{{ $round->isTest() ? ($iv->marks === null ? 'Enter marks' : 'Marks') : 'Scorecards' }}</a>
                                @endif
                                @if (in_array($state, ['awaiting', 'to_decide', 'passed', 'failed', 'scheduled']))
                                    <a href="{{ route('recruit-interviews.decide-form', $iv->id) }}" class="btn btn-sm {{ $state === 'to_decide' ? 'btn-primary' : 'btn-outline-secondary' }} openRightModal {{ $inside }}">{{ in_array($state, ['passed', 'failed']) ? 'Change decision' : ($state === 'to_decide' ? 'Decide' : 'Decide / waive') }}</a>
                                @endif
                            </div>
                        @endif
                    @else
                        <div class="text-lightest f-13">{{ $item->no === 1 ? 'Starts when the applicant is shortlisted.' : 'Set up when the round before it is cleared.' }}</div>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Interviews outside the plan (scheduled by hand): shown, they do not hold up Selected --}}
        @foreach ($extras as $iv)
            <div class="qv-round qv-round--extra">
                <span class="qv-round-no"><i class="fa fa-plus"></i></span>
                <div class="qv-round-body">
                    <div class="qv-round-head"><strong>{{ $iv->round }}</strong><span class="text-dark-grey">extra interview</span>{!! $iv->statusBadge() !!}</div>
                    <div class="qv-round-meta">
                        <span><i class="fa fa-calendar"></i>{{ $iv->whenText() }}</span>
                        <span><i class="fa fa-users"></i>{{ $iv->interviewers()->pluck('name')->implode(', ') ?: 'no panel yet' }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
