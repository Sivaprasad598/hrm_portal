@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $me = (int) user()->id;
    $sections = [
        ['title' => 'To do', 'sub' => 'Waiting for your scorecard / marks', 'list' => $toDo, 'kind' => 'todo'],
        ['title' => 'Upcoming', 'sub' => 'Scheduled with you on the panel', 'list' => $upcoming, 'kind' => 'upcoming'],
        ['title' => 'Done', 'sub' => 'Your recent rounds', 'list' => $done, 'kind' => 'done'],
    ];
@endphp
<div class="content-wrapper">
    <div class="card bg-white border-0 b-shadow-4">
        <div class="card-header bg-white border-0 pt-4">
            <h4 class="f-18 f-w-500 mb-0">My Interviews</h4>
            <p class="ms-intro">The recruitment rounds you are on. Give your scorecard (or the marks, for a test) once the round has taken place —
                the candidate moves to the next round as soon as every score is in.</p>
        </div>
        <div class="card-body pt-2">
            <div class="ms-stats">
                <div class="ms-stat {{ $toDo->isNotEmpty() ? 'is-warn' : '' }}">
                    <div class="lbl"><span class="ic" style="--ic-bg:#fff4e5;--ic-fg:#b45309"><i class="fa fa-clipboard-check"></i></span>To do</div>
                    <div class="val">{{ $toDo->count() }}</div>
                    <div class="sub">{{ $toDo->isNotEmpty() ? 'waiting for your scorecard / marks' : 'nothing waiting on you' }}</div>
                </div>
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

            @foreach ($sections as $section)
                @php
                    $list = $section['list'];
                    $kind = $section['kind'];
                @endphp
                <div class="ms-sec {{ $loop->first ? '' : 'mt-4' }}"><h5>{{ $section['title'] }} <span>{{ $section['sub'] }}</span></h5></div>
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
            @endforeach
        </div>
    </div>
</div>
@endsection
