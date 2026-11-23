@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper" data-swap-links>

    {{-- Which interviews (tabs) on the left, the filters on the right, one bar --}}
    <div class="rc-toolbar mb-3">
        <nav class="rc-tabs" aria-label="Interviews">
            @foreach (['confirm' => ['Slots to confirm', 'fa-hourglass-half'], 'upcoming' => ['Upcoming', 'fa-calendar-alt'], 'today' => ['Today', 'fa-calendar-day'],
                       'past' => ['Past', 'fa-history'], 'feedback' => ['Waiting for scores', 'fa-star-half-alt']] as $key => [$label, $icon])
                <a href="{{ route('recruit-interviews.index', array_merge(request()->except('page'), ['when' => $key])) }}"
                   class="rc-tab {{ $when === $key ? 'active' : '' }}" @if ($when === $key) aria-current="page" @endif>
                    <i class="fa {{ $icon }}"></i>{{ $label }}
                    @if (($tabCounts[$key] ?? null) !== null)<span class="rc-tab-n {{ $key === 'confirm' || $key === 'feedback' ? 'is-hot' : '' }}">{{ $tabCounts[$key] }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('recruit-interviews.index') }}" class="rc-filters">
            <input type="hidden" name="when" value="{{ $when }}">
            <select name="position_id" class="form-control f-13" onchange="PageSwap.submit(this.form)" aria-label="Position">
                <option value="">All positions</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control f-13" onchange="PageSwap.submit(this.form)" aria-label="Status">
                <option value="">Any status</option>
                @foreach (\App\Models\RecruitInterview::STATUSES as $key => [$label])
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="rc-check mb-0">
                <input type="checkbox" name="mine" value="1" @checked(request('mine')) onchange="PageSwap.submit(this.form)">
                <span>I am on the panel</span>
            </label>
        </form>
    </div>

    @php
        $groups = $interviews->getCollection()->groupBy(fn ($i) => $i->starts_at->copy()->tz($tz)->toDateString());
        $todayDate = now($tz)->toDateString();
        $tomorrowDate = now($tz)->addDay()->toDateString();
        $me = (int) user()->id;
    @endphp
    <div class="rc-list-card iv-list">
        @forelse ($groups as $day => $list)
            @php $d = \Carbon\Carbon::parse($day); @endphp
            <div class="iv-day {{ $day === $todayDate ? 'is-today' : '' }}">
                <span class="iv-day-name">{{ $day === $todayDate ? 'Today' : ($day === $tomorrowDate ? 'Tomorrow' : $d->format('l')) }}</span>
                <span class="iv-day-date">{{ $d->format($fmt) }}</span>
                <span class="iv-day-n">{{ $list->count() }} interview{{ $list->count() > 1 ? 's' : '' }}</span>
            </div>
            @foreach ($list as $interview)
                @php
                    $start = $interview->starts_at->copy()->tz($tz);
                    $missing = $interview->missingFeedbackIds();
                    $panelIds = array_map('intval', $interview->interviewer_ids ?: []);
                    $cards = $interview->feedback->count();
                    $overdue = $interview->status === 'scheduled' && $interview->starts_at->lt(now()->subHours(2));
                    $done = in_array($interview->status, ['completed', 'cancelled', 'no_show'], true);
                    $candidate = $interview->application->candidate;
                    $myTurn = in_array($me, $panelIds, true) && !$interview->result && ($interview->isTest() ? $interview->marks === null : !$interview->feedback->contains('user_id', $me))
                        && ($interview->status === 'completed' || $interview->starts_at->isPast());
                @endphp
                <div class="iv-row {{ $done ? 'is-done' : '' }} {{ $overdue ? 'is-late' : '' }}">
                    <div class="iv-time">
                        <b>{{ $start->format(company()->time_format) }}</b>
                        <span>{{ $interview->duration }} min</span>
                    </div>
                    <div class="iv-who">
                        <span class="rc-avatar">{{ $candidate->initials() }}</span>
                        <div style="min-width: 0">
                            <a href="{{ route('candidates.show', $interview->application->candidate_id) }}" class="iv-name">{{ $candidate->name }}</a>
                            <div class="iv-sub">{{ $interview->application->positionLabel() }}</div>
                        </div>
                    </div>
                    <div class="iv-round">
                        <div class="iv-round-name">{{ $interview->round }}</div>
                        <div class="iv-chips">
                            <span><i class="fa {{ $interview->isTest() ? 'fa-file-alt' : 'fa-comments' }}"></i>{{ $interview->isTest() ? 'Test' : 'Interview' }}</span>
                            <span><i class="fa {{ \App\Models\RecruitInterview::MODES[$interview->mode][1] ?? 'fa-circle' }}"></i>{{ $interview->modeLabel() }}</span>
                            @if ($interview->meeting_link)<a href="{{ $interview->meeting_link }}" target="_blank" class="no-swap"><i class="fa fa-video"></i>Join</a>@endif
                        </div>
                    </div>
                    <div class="iv-panel" title="{{ collect($panelIds)->map(fn ($id) => $interviewerNames[$id] ?? '')->filter()->implode(', ') }}">
                        @forelse (array_slice($panelIds, 0, 3) as $pid)
                            @php $pname = $interviewerNames[$pid] ?? '?'; @endphp
                            <span class="iv-face {{ $interview->feedback->contains('user_id', $pid) ? 'has-card' : '' }}">{{ collect(preg_split('/\s+/', trim($pname)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</span>
                        @empty
                            <span class="iv-sub">no panel</span>
                        @endforelse
                        <span class="iv-panel-names">{{ collect($panelIds)->map(fn ($id) => $interviewerNames[$id] ?? '')->filter()->implode(', ') }}</span>
                    </div>
                    <div class="iv-state">
                        <div>{!! $interview->statusBadge() !!} {!! $interview->resultBadge() !!}</div>
                        @if ($overdue)<div class="iv-warn">Time passed — record the outcome</div>@endif
                    </div>
                    <div class="iv-score">
                        @if ($interview->isTest())
                            <b>{{ $interview->marks !== null ? \App\Models\RecruitRound::num($interview->marks) . ' / ' . \App\Models\RecruitRound::num($interview->max_marks ?: 100) : '—' }}</b>
                            <span>marks</span>
                        @else
                            <b>{{ $interview->score !== null ? \App\Models\RecruitRound::num($interview->score) . ' / 5' : '—' }}</b>
                            <span>{{ $cards }}/{{ count($panelIds) }} scorecards</span>
                            <div class="iv-bar"><span style="width: {{ count($panelIds) ? round($cards / count($panelIds) * 100) : 0 }}%"></span></div>
                        @endif
                        @if (!$interview->isTest() && $interview->status === 'completed' && $missing)
                            <div class="iv-warn">waiting for {{ collect($missing)->map(fn ($id) => $interviewerNames[$id] ?? '')->filter()->implode(', ') }}</div>
                        @endif
                    </div>
                    <div class="iv-act">
                        @if ($interview->status === 'proposed')
                            @include('recruitment.partials.confirm-slot', ['interview' => $interview])
                        @else
                            @if ($myTurn)
                                <a href="{{ route('recruit-interviews.feedback', $interview->id) }}" class="btn btn-sm btn-primary"><i class="fa fa-clipboard-check mr-1"></i>{{ $interview->isTest() ? 'Enter marks' : 'Give scorecard' }}</a>
                            @else
                                <a href="{{ route('recruit-interviews.feedback', $interview->id) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                            @endif
                            @if ($canManage && $interview->status === 'scheduled')
                                <a href="{{ route('recruit-interviews.close-form', $interview->id) }}" class="btn btn-sm {{ $overdue ? 'btn-primary' : 'btn-outline-secondary' }} openRightModal">Outcome</a>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        @empty
            <div class="p-4"><x-cards.no-record icon="calendar" message="No interviews here." /></div>
        @endforelse
        @include('recruitment.partials.list-footer', ['paginator' => $interviews])
    </div>
</div>
<style>
    .rc-tab-n { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; font-size: 11.5px;
        font-weight: 600; background: #f2f4f7; color: #475467; }
    .rc-tab.active .rc-tab-n { background: rgba(255, 255, 255, .25); color: #fff; }
    .rc-tab-n.is-hot { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); }
    .iv-list .iv-day { display: flex; align-items: baseline; gap: 10px; padding: 12px 20px; background: #f9fafb; border-bottom: 1px solid #eaecf0; }
    .iv-list .iv-day:not(:first-child) { border-top: 1px solid #eaecf0; }
    .iv-day-name { font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #344054; }
    .iv-day.is-today .iv-day-name { color: var(--epic-blue, #cb6b4f); }
    .iv-day-date { font-size: 13px; color: #667085; }
    .iv-day-n { margin-left: auto; font-size: 12px; color: #98a2b3; }
    /* every row shares the same columns (the buttons' column has a set width, so a wider button
       such as Give scorecard does not push that row's columns out of line with the others) */
    .iv-row { display: grid; grid-template-columns: 90px minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, .8fr) 236px;
        gap: 14px; align-items: center; padding: 14px 20px; border-bottom: 1px solid #f2f4f7; font-size: 13.5px; }
    .iv-row:hover { background: #fcfcfd; }
    .iv-row.is-done { opacity: .72; }
    .iv-row.is-late { box-shadow: inset 3px 0 0 #f79009; }
    .iv-time b { display: block; font-size: 15px; color: #101828; }
    .iv-time span, .iv-sub { font-size: 12.5px; color: #667085; }
    .iv-who { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .iv-name { font-weight: 600; color: #101828; }
    .iv-round-name { font-weight: 500; color: #1d2939; }
    .iv-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
    .iv-chips > * { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: #475467; background: #f2f4f7; border-radius: 999px; padding: 2px 8px; }
    .iv-chips i { color: #98a2b3; font-size: 10px; }
    .iv-panel { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; min-width: 0; }
    .iv-face { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600;
        background: #eef4ff; color: #3538cd; border: 2px solid #fff; box-shadow: 0 0 0 1px #e4e7ec; }
    .iv-face.has-card { background: #dcfae6; color: #067647; }
    .iv-panel-names { width: 100%; font-size: 12px; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .iv-score b { display: block; color: #101828; }
    .iv-score span { font-size: 12px; color: #667085; }
    .iv-bar { height: 4px; border-radius: 999px; background: #f2f4f7; margin-top: 4px; overflow: hidden; max-width: 110px; }
    .iv-bar span { display: block; height: 100%; background: #12b76a; }
    .iv-warn { font-size: 12px; color: #b54708; margin-top: 3px; }
    .iv-act { display: flex; flex-wrap: nowrap; justify-content: flex-end; gap: 6px; min-width: 0; }
    .iv-act .btn-sm { height: 32px; display: inline-flex; align-items: center; padding: 0 12px !important; border-radius: 8px !important; white-space: nowrap; }
    @media (max-width: 1199.98px) {
        .iv-row { grid-template-columns: 80px 1fr 1fr; }
        .iv-panel, .iv-state, .iv-score { grid-column: span 1; }
        .iv-act { grid-column: 1 / -1; justify-content: flex-start; }
    }
</style>
@include('recruitment.partials.standard-bar', ['form' => '.rc-filters', 'active' => request()->anyFilled(['position_id', 'status', 'mine'])])
@endsection
