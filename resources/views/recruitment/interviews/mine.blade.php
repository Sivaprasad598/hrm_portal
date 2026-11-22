@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $me = (int) user()->id;
    $sections = [
        ['title' => 'To do', 'list' => $toDo, 'kind' => 'todo'],
        ['title' => 'Upcoming', 'list' => $upcoming, 'kind' => 'upcoming'],
        ['title' => 'Done', 'list' => $done, 'kind' => 'done'],
    ];
@endphp
<style>
    /* the app's standard: counts and sections as white rounded cards on the grey page */
    .mi-std .ms-stats { margin-bottom: 16px; }
    .mi-std .ms-stats > * { background: #fff; border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .mi-std .mi-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 16px 20px; margin-bottom: 16px; }
    .mi-std .mi-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
    .mi-std .mi-card-head h5 { font-size: 15px; font-weight: 600; margin: 0; color: #1f2937; }
    .mi-std .mi-count { background: #f3f4f6; color: #4b5563; border-radius: 10px; font-size: 12px; padding: 1px 8px; }
    .mi-std .ms-item { border: 1px solid #eef0f3; border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; background: #fff; }
    .mi-std .ms-item:last-child { margin-bottom: 0; }
    .mi-std .mi-card--confirm { border-left: 4px solid #0ba5ec; }
    .mi-std .ms-stats:has(> :nth-child(4)) { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    @media (max-width: 991.98px) { .mi-std .ms-stats:has(> :nth-child(4)) { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .mi-std .ms-empty { border: 1px dashed #e5e7eb; border-radius: 10px; text-align: center; color: #9ca3af; }
    body.epic .mi-std .ms-item .btn-sm { height: 38px; border-radius: 8px !important; padding: 0 14px !important; display: inline-flex; align-items: center; font-size: 14px; }
</style>
{{-- (no heading or intro text: the top bar names the page) --}}
<div class="content-wrapper mi-std" data-swap-links>
    <div>
        <div>
            <div class="ms-stats">
                <div class="ms-stat {{ $toDo->isNotEmpty() ? 'is-warn' : '' }}">
                    <div class="lbl"><span class="ic" style="--ic-bg:#fff4e5;--ic-fg:#b45309"><i class="fa fa-clipboard-check"></i></span>To do</div>
                    <div class="val">{{ $toDo->count() }}</div>
                    <div class="sub">{{ $toDo->isNotEmpty() ? 'waiting for your scorecard / marks' : 'nothing waiting on you' }}</div>
                </div>
                @if ($toConfirm->isNotEmpty())
                    <div class="ms-stat is-warn">
                        <div class="lbl"><span class="ic" style="--ic-bg:#e0f2fe;--ic-fg:#0369a1"><i class="fa fa-hourglass-half"></i></span>Slots to confirm</div>
                        <div class="val">{{ $toConfirm->count() }}</div>
                        <div class="sub">suggested by the system, waiting for HR</div>
                    </div>
                @endif
                <div class="ms-stat">
                    <div class="lbl"><span class="ic" style="--ic-bg:#e0f2fe;--ic-fg:#0369a1"><i class="fa fa-calendar-alt"></i></span>Upcoming</div>
                    <div class="val">{{ $upcoming->count() }}</div>
                    <div class="sub">scheduled with you on the panel</div>
                </div>
                <div class="ms-stat">
                    <div class="lbl"><span class="ic" style="--ic-bg:#e7f6ec;--ic-fg:#15803d"><i class="fa fa-check"></i></span>Done</div>
                    <div class="val">{{ $done->count() }}</div>
                    <div class="sub">your recent rounds</div>
                </div>
            </div>

            {{-- HR: the slots the system suggested, confirmed (or changed) from here --}}
            @if ($toConfirm->isNotEmpty())
                <div class="mi-card mi-card--confirm">
                    <div class="mi-card-head"><h5>Slots to confirm</h5><span class="mi-count">{{ $toConfirm->count() }}</span></div>
                    @foreach ($toConfirm as $i)
                        <div class="ms-item d-flex flex-wrap justify-content-between align-items-center f-13" style="gap: 10px">
                            <div class="mr-2">
                                <a href="{{ route('candidates.show', $i->application?->candidate_id) }}" class="f-14 font-weight-600 text-dark">{{ $i->application?->candidate?->name }}</a>
                                <span class="ms-muted">· {{ $i->application?->positionLabel() }}</span>
                                <div class="text-dark-grey mt-1">
                                    <b>{{ $i->round }}</b> ({{ $i->isTest() ? 'Test' : 'Interview' }}) ·
                                    <i class="fa {{ \App\Models\RecruitInterview::MODES[$i->mode][1] ?? 'fa-circle' }} mx-1"></i>{{ $i->modeLabel() }} · {{ $i->whenText() }}
                                    @if ($i->interviewer_ids) · <i class="fa fa-users mx-1"></i>{{ $i->interviewers()->pluck('name')->implode(', ') }}@endif
                                </div>
                            </div>
                            @include('recruitment.partials.confirm-slot', ['interview' => $i])
                        </div>
                    @endforeach
                </div>
            @endif

            @foreach ($sections as $section)
                @php
                    $list = $section['list'];
                    $kind = $section['kind'];
                @endphp
                <div class="mi-card">
                <div class="mi-card-head"><h5>{{ $section['title'] }}</h5><span class="mi-count">{{ $list->count() }}</span></div>
                @forelse ($list as $i)
                    @php
                        $application = $i->application;
                        $candidate = $application?->candidate;
                        $myCard = $i->feedback->firstWhere('user_id', $me);
                    @endphp
                    <div class="ms-item d-flex flex-wrap justify-content-between align-items-center f-13">
                        <div class="mr-2">
                            <b class="f-14">{{ $candidate?->name }}</b>
                            <span class="ms-muted">· {{ $application?->positionLabel() }}</span>
                            <div class="text-dark-grey mt-1">
                                <b>{{ $i->round }}</b> ({{ $i->isTest() ? 'Test' : 'Interview' }}) ·
                                <i class="fa {{ \App\Models\RecruitInterview::MODES[$i->mode][1] ?? 'fa-circle' }} mx-1"></i>{{ $i->modeLabel() }} · {{ $i->whenText() }}
                                @if ($i->meeting_link) · <a href="{{ $i->meeting_link }}" target="_blank">Join</a> @endif
                                @if ($i->location) · {{ $i->location }} @endif
                            </div>
                            @if ($kind === 'done')
                                <div class="text-dark-grey mt-1">
                                    @if ($i->isTest())
                                        {{ $i->marks !== null ? 'Marks: ' . $automation->scoreText($i) : '' }}
                                    @elseif ($myCard)
                                        Your score: {{ $myCard->overall }}/5 {!! $myCard->recommendationBadge() !!}
                                    @endif
                                    {!! $i->resultBadge() !!}
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('recruit-interviews.feedback', $i->id) }}" class="btn btn-sm {{ $kind === 'todo' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            @if ($kind === 'todo')
                                <i class="fa fa-clipboard-check mr-1"></i>{{ $i->isTest() ? 'Enter marks' : 'Give scorecard' }}
                            @else
                                Open
                            @endif
                        </a>
                    </div>
                @empty
                    <div class="ms-empty py-3">Nothing here.</div>
                @endforelse
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
