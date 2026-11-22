@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper rd" data-swap-links>
    @php
        $pipelineFlow = ['new', 'shortlisted', 'interview', 'selected', 'offered', 'offer_accepted', 'joined'];
        $closedStages = array_values(array_filter(array_keys(\App\Models\JobApplication::STAGES), fn ($s) => !in_array($s, $pipelineFlow, true)));
        $flowMax = max(1, collect($pipelineFlow)->max(fn ($s) => $stageCounts[$s] ?? 0));
        $staleOffers = $offersOut->filter(fn ($o) => $o->sent_at && $o->sent_at->diffInDays(now()) >= 3);
        $attention = ($unassigned ? 1 : 0) + ($awaitingFeedback->isNotEmpty() ? 1 : 0) + $toConfirm->count() + $staleOffers->count() + $toJoin->count();
        $byDay = $upcoming->groupBy(fn ($i) => $i->starts_at->copy()->tz($tz)->toDateString());
    @endphp

    {{-- (no in-page title: the top bar has it) --}}
    @if ($canManage)
        <div class="d-flex justify-content-end mb-3" style="gap: 8px">
            <a href="{{ route('job-positions.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-plus mr-1"></i>Add Position</a>
            <a href="{{ route('candidates.create') }}" class="btn btn-secondary rounded f-14 openRightModal"><i class="fa fa-user-plus mr-1"></i>Add Candidate</a>
        </div>
    @endif

    <div class="row">
        @foreach ([
            ['Open Positions', $openPositions->count(), $openings . ' opening(s) to fill', '#3d78e3', 'fa-briefcase', route('job-positions.index', ['status' => 'open'])],
            ['New This Week', $newThisWeek, 'applications received', '#0ea5e9', 'fa-inbox', route('job-applications.index', ['stage' => 'new'])],
            ['In Pipeline', collect(\App\Models\JobApplication::ACTIVE)->sum(fn ($s) => $stageCounts[$s] ?? 0), $candidateCount . ' in the talent pool', '#7c3aed', 'fa-stream', route('job-applications.index')],
            ['Interviews (7 days)', $upcoming->count(), $overdueInterviews ? $overdueInterviews . ' past, not closed' : 'scheduled', '#f59e0b', 'fa-calendar-alt', route('recruit-interviews.index')],
            ['Joined This Month', $joinedThisMonth, $toJoin->count() . ' accepted, yet to join', '#16a34a', 'fa-user-check', route('job-applications.index', ['stage' => 'joined', 'view' => 'list'])],
        ] as [$label, $count, $sub, $colour, $icon, $link])
            <div class="col-xl col-md-4 col-sm-6 mb-3">
                <a href="{{ $link }}" class="card h-100 rc-tile text-decoration-none" style="--rc: {{ $colour }}">
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <p>{{ $label }}</p>
                            <h3 class="font-weight-700 mb-0">{{ $count }}</h3>
                            <span class="f-12">{{ $sub }}</span>
                        </div>
                        <span class="rd-chip"><i class="fa {{ $icon }}"></i></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row">
        {{-- The work: what needs HR, how the hiring flows, this week --}}
        <div class="col-xl-8">

            {{-- Needs your attention: every pending action in one list, each with its button --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-bolt mr-1"></i>Needs your attention</h5>
                        @if ($attention)<span class="rd-count rd-count--hot">{{ $attention }}</span>@endif</div>
                    @if (!$attention)
                        <div class="rd-clear"><i class="fa fa-check-circle mr-2"></i>All caught up — nothing is waiting on HR.</div>
                    @endif

                    @foreach ($toConfirm as $slot)
                        @php $blocker = $slot->confirmBlocker(); @endphp
                        <div class="rd-act">
                            <span class="rd-act-ic" style="--c:#0ba5ec"><i class="fa fa-hourglass-half"></i></span>
                            <div class="rd-act-body">
                                <div><a href="{{ route('candidates.show', $slot->application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $slot->application->candidate->name }}</a>
                                    <span class="text-dark-grey">· {{ $slot->application->positionLabel() }} · {{ $slot->round }}</span></div>
                                <div class="rd-act-sub">Slot to confirm · {{ $slot->whenText() }} · {{ $slot->interviewers()->pluck('name')->implode(', ') ?: 'no panel yet' }}</div>
                            </div>
                            @include('recruitment.partials.confirm-slot', ['interview' => $slot])
                        </div>
                    @endforeach
                    @if ($toConfirmCount > $toConfirm->count())
                        <a href="{{ route('recruit-interviews.index', ['when' => 'confirm']) }}" class="rd-more">{{ $toConfirmCount - $toConfirm->count() }} more slot(s) to confirm →</a>
                    @endif

                    @foreach ($staleOffers as $offer)
                        <div class="rd-act">
                            <span class="rd-act-ic" style="--c:#7a5af8"><i class="fa fa-paper-plane"></i></span>
                            <div class="rd-act-body">
                                <div><a href="{{ route('candidates.show', $offer->application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $offer->application->candidate->name }}</a>
                                    <span class="text-dark-grey">· {{ $offer->designation?->name }}</span></div>
                                <div class="rd-act-sub">Offer sent {{ $offer->sent_at->diffInDays(now()) }} days ago, no reply yet
                                    @if ($offer->valid_until)<span class="{{ $offer->valid_until->isPast() ? 'rd-warn' : '' }}">· valid till {{ $offer->valid_until->format($fmt) }}</span>@endif</div>
                            </div>
                            @if ($canManage)
                                <a href="{{ route('job-offers.respond-form', [$offer->id, 'to' => 'accepted']) }}" class="btn btn-sm btn-outline-secondary openRightModal">Record reply</a>
                            @endif
                        </div>
                    @endforeach

                    @foreach ($toJoin as $application)
                        <div class="rd-act">
                            <span class="rd-act-ic" style="--c:#12b76a"><i class="fa fa-user-check"></i></span>
                            <div class="rd-act-body">
                                <div><a href="{{ route('candidates.show', $application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $application->candidate->name }}</a>
                                    <span class="text-dark-grey">· {{ $application->positionLabel() }}</span></div>
                                <div class="rd-act-sub">Offer accepted{{ $application->offer?->joining_date ? ' · joins ' . $application->offer->joining_date->format($fmt) : '' }} · register them as an employee</div>
                            </div>
                            @if ($canManage)
                                @include('recruitment.partials.register-button', ['candidate' => $application->candidate, 'regButtonClass' => 'btn btn-sm btn-success'])
                            @endif
                        </div>
                    @endforeach

                    @if ($awaitingFeedback->isNotEmpty())
                        <div class="rd-act">
                            <span class="rd-act-ic" style="--c:#2e90fa"><i class="fa fa-clipboard-check"></i></span>
                            <div class="rd-act-body">
                                <div class="font-weight-600">{{ $awaitingFeedback->count() }} round(s) held, waiting for scorecards / marks</div>
                                <div class="rd-act-sub">{{ $awaitingFeedback->take(3)->map(fn ($i) => $i->application?->candidate?->name . ' (' . $i->round . ')')->implode(', ') }}{{ $awaitingFeedback->count() > 3 ? ' …' : '' }}</div>
                            </div>
                            <a href="{{ route('recruit-interviews.index', ['when' => 'feedback']) }}" class="btn btn-sm btn-outline-secondary">See them</a>
                        </div>
                    @endif

                    @if ($unassigned)
                        <div class="rd-act">
                            <span class="rd-act-ic" style="--c:#f79009"><i class="fa fa-inbox"></i></span>
                            <div class="rd-act-body">
                                <div class="font-weight-600">{{ $unassigned }} application(s) came in without a position</div>
                                <div class="rd-act-sub">From the website or the careers mailbox — put each on the right opening</div>
                            </div>
                            <a href="{{ route('job-applications.index', ['position_id' => 'unassigned', 'view' => 'list']) }}" class="btn btn-sm btn-outline-secondary">Assign them</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- The hiring funnel: how many are at each step; the closed ones apart --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-stream mr-1"></i>Pipeline</h5>
                        <a href="{{ route('job-applications.index') }}" class="f-13">Open the board</a></div>
                    <div class="rd-funnel">
                        @foreach ($pipelineFlow as $stage)
                            @php [$label, $class, $icon] = \App\Models\JobApplication::STAGES[$stage]; $n = $stageCounts[$stage] ?? 0; @endphp
                            <a href="{{ route('job-applications.index', ['stage' => $stage, 'view' => in_array($stage, \App\Models\JobApplication::ACTIVE) ? 'board' : 'list']) }}" class="rd-step">
                                <span class="rd-step-n">{{ $n }}</span>
                                <span class="rd-step-l"><i class="fa {{ $icon }} mr-1 text-{{ $class }}"></i>{{ $label }}</span>
                                <span class="rd-step-bar"><span style="height: {{ $n ? max(8, round($n / $flowMax * 100)) : 0 }}%"></span></span>
                            </a>
                        @endforeach
                    </div>
                    <div class="rd-closed">
                        <span class="text-dark-grey">Closed:</span>
                        @foreach ($closedStages as $stage)
                            @php [$label, $class] = \App\Models\JobApplication::STAGES[$stage]; @endphp
                            <a href="{{ route('job-applications.index', ['stage' => $stage, 'view' => 'list']) }}" class="rd-pill">{{ $label }} <b>{{ $stageCounts[$stage] ?? 0 }}</b></a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- This week's interviews, day by day --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-calendar-alt mr-1"></i>Interviews — next 7 days</h5>
                        <a href="{{ route('recruit-interviews.index') }}" class="f-13">All interviews</a></div>
                    @forelse ($byDay as $day => $list)
                        @php $d = \Carbon\Carbon::parse($day); $today = $d->isToday(); @endphp
                        <div class="rd-day">
                            <div class="rd-date {{ $today ? 'is-today' : '' }}"><span>{{ $today ? 'Today' : $d->format('D') }}</span><b>{{ $d->format('d') }}</b><span>{{ $d->format('M') }}</span></div>
                            <div class="flex-grow-1" style="min-width: 0">
                                @foreach ($list as $interview)
                                    <a href="{{ route('recruit-interviews.feedback', $interview->id) }}" class="rd-iv">
                                        <span class="rd-iv-time">{{ $interview->starts_at->copy()->tz($tz)->format(company()->time_format) }}</span>
                                        <span class="rd-iv-who"><b>{{ $interview->application->candidate->name }}</b>
                                            <span class="text-dark-grey">· {{ $interview->round }} · {{ $interview->application->positionLabel() }}</span></span>
                                        <span class="rd-iv-mode"><i class="fa {{ \App\Models\RecruitInterview::MODES[$interview->mode][1] ?? 'fa-circle' }} mr-1"></i>{{ $interview->modeLabel() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-cards.no-record icon="calendar" message="No interviews in the next 7 days." />
                    @endforelse
                </div>
            </div>

            {{-- Open positions with how far each is filled --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-briefcase mr-1"></i>Open positions</h5>
                        <a href="{{ route('job-positions.index') }}" class="f-13">All positions</a></div>
                    @forelse ($openPositions->take(8) as $position)
                        @php $pct = $position->openings ? min(100, round($position->joined_count / $position->openings * 100)) : 0; @endphp
                        <div class="rd-pos">
                            <div class="rd-pos-name"><a href="{{ route('job-positions.show', $position->id) }}" class="font-weight-600 text-darkest-grey">{{ $position->title }}</a>
                                <span class="text-lightest f-12">{{ $position->code }}</span></div>
                            <div class="rd-pos-nums"><span><b>{{ $position->active_count }}</b> in pipeline</span><span><b>{{ $position->joined_count }}</b>/{{ $position->openings }} filled</span></div>
                            <div class="rd-pos-bar"><span style="width: {{ $pct }}%"></span></div>
                            <div class="rd-pos-close {{ $position->closing_date?->isPast() ? 'text-danger' : '' }}">{{ $position->closing_date ? 'Closes ' . $position->closing_date->format($fmt) : 'No closing date' }}</div>
                        </div>
                    @empty
                        <x-cards.no-record icon="briefcase" message="No open positions." />
                    @endforelse
                </div>
            </div>
        </div>

        {{-- The state of things --}}
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-file-signature mr-1"></i>Offers out</h5><span class="rd-count">{{ $offersOut->count() }}</span></div>
                    @forelse ($offersOut as $offer)
                        <div class="rd-row">
                            <div style="min-width: 0">
                                <a href="{{ route('candidates.show', $offer->application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $offer->application->candidate->name }}</a>
                                <div class="rd-act-sub">{{ $offer->designation?->name }} · sent {{ $offer->sent_at?->tz($tz)->format($fmt) }}{{ $offer->emailed_at ? ' · can answer online' : '' }}</div>
                            </div>
                            @if ($offer->valid_until)
                                <span class="rd-pill {{ $offer->valid_until->isPast() ? 'is-bad' : '' }}">till {{ $offer->valid_until->format($fmt) }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="f-13 text-dark-grey mb-0">No offers waiting for a reply.</p>
                    @endforelse
                </div>
            </div>

            @if ($roundStats->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="rd-head"><h5 class="f-15"><i class="fa fa-award mr-1"></i>Rounds <span class="rd-note">last 90 days</span></h5></div>
                        @foreach ($roundStats as $stat)
                            @php $total = (int) $stat->passed + (int) $stat->failed; $pct = $total ? round($stat->passed / $total * 100) : 0; @endphp
                            <div class="rd-round">
                                <div class="d-flex justify-content-between"><span class="font-weight-600">{{ $stat->round }}</span><span><b>{{ $pct }}%</b> <span class="text-dark-grey">cleared</span></span></div>
                                <div class="rd-pos-bar"><span style="width: {{ $pct }}%; background: {{ $pct >= 60 ? '#12b76a' : ($pct >= 35 ? '#f79009' : '#f04438') }}"></span></div>
                                <div class="rd-act-sub">{{ (int) $stat->passed }} cleared · {{ (int) $stat->failed }} not ·
                                    avg {{ \App\Models\RecruitRound::num(round((float) $stat->avg_score, 1)) }}{{ $stat->round_type === 'test' ? ($stat->out_of ? ' / ' . \App\Models\RecruitRound::num(round($stat->out_of)) : '') : ' / 5' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-globe mr-1"></i>Sources <span class="rd-note">30 days</span></h5></div>
                    @php $totalSources = max(1, $sources->sum()); @endphp
                    @foreach (\App\Models\Candidate::SOURCES as $key => [$label, $icon])
                        @continue(!($sources[$key] ?? 0))
                        <div class="rd-round">
                            <div class="d-flex justify-content-between"><span><i class="fa {{ $icon }} mr-1 text-lightest"></i>{{ $label }}</span><b>{{ $sources[$key] }}</b></div>
                            <div class="rd-pos-bar"><span style="width: {{ round($sources[$key] * 100 / $totalSources) }}%; background: var(--epic-blue, #cb6b4f)"></span></div>
                        </div>
                    @endforeach
                    @if ($sources->isEmpty())
                        <p class="f-13 text-dark-grey mb-0">Nothing received in the last 30 days.</p>
                    @endif

                    @if ($topReferrers->isNotEmpty())
                        <div class="rd-sub-head"><i class="fa fa-user-friends mr-1"></i>Top referrers <span class="rd-note">90 days</span></div>
                        @foreach ($topReferrers as $r)
                            <div class="rd-row">
                                <a href="{{ route('candidates.index', ['referred_by' => $r->id]) }}" class="text-darkest-grey" style="min-width: 0">{{ $r->label ?? '--' }}</a>
                                <span class="rd-act-sub text-nowrap">{{ $r->referred }} referred · {{ $r->joined }} joined</span>
                            </div>
                        @endforeach
                    @endif

                    @if ($setting)
                        <div class="rd-sub-head"><i class="fa fa-plug mr-1"></i>Intake</div>
                        <div class="rd-row"><span><i class="fa fa-envelope mr-1 text-lightest"></i>Careers mailbox</span>
                            <span class="rd-pill {{ $setting->mailboxReady() ? 'is-ok' : '' }}">{{ $setting->mailboxReady() ? 'On' : 'Off' }}</span></div>
                        <div class="rd-row"><span><i class="fa fa-globe mr-1 text-lightest"></i>Website API</span>
                            <span class="rd-pill {{ $setting->api_enabled ? 'is-ok' : '' }}">{{ $setting->api_enabled ? 'On' : 'Off' }}</span></div>
                        @if ($setting->mail_last_result)<div class="rd-act-sub mt-1">{{ $setting->mail_last_result }}</div>@endif
                        <div class="f-13 mt-2"><a href="{{ route('recruitment-settings.index') }}">Settings</a> · <a href="{{ route('recruitment-settings.inbox') }}">Incoming log</a></div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="rd-head"><h5 class="f-15"><i class="fa fa-history mr-1"></i>Recent activity</h5></div>
                    @forelse ($recent->take(8) as $log)
                        @php [$label, $icon, $colour] = \App\Models\RecruitLog::ACTIONS[$log->action] ?? [ucfirst($log->action), 'fa-circle', 'text-dark-grey']; @endphp
                        <div class="rd-feed">
                            <i class="fa {{ $icon }} {{ $colour }}"></i>
                            <div style="min-width: 0">
                                <a href="{{ route('candidates.show', $log->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $log->candidate_name }}</a>
                                @if ($log->action === 'stage')
                                    {!! \App\Models\JobApplication::badge($log->from_stage) !!} → {!! \App\Models\JobApplication::badge($log->to_stage) !!}
                                @else
                                    <span class="text-dark-grey">· {{ $label }}</span>
                                @endif
                                <div class="rd-act-sub">{{ \Carbon\Carbon::parse($log->created_at)->tz($tz)->diffForHumans() }}{{ $log->doneBy ? ' · ' . $log->doneBy->name : '' }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="f-13 text-dark-grey mb-0">Nothing yet. Add a position, then candidates — or switch on the careers mailbox in Settings.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Job Dashboard: white cards, 12px corners, one soft shadow; the work on the left, the state of things on the right */
    .rd .card { border: 0 !important; border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06) !important; }
    .rd .card-body { padding: 18px 20px; }
    .rd .rc-tile { transition: box-shadow .15s, transform .15s; }
    .rd .rc-tile:hover { box-shadow: 0 8px 22px rgba(16, 24, 40, .10) !important; transform: translateY(-1px); }
    .rd .rc-tile .card-body { align-items: flex-start; }
    .rd .rc-tile p { font-size: 11.5px; line-height: 1.35; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #667085; margin: 0 0 6px; }
    .rd .rc-tile h3 { color: #101828 !important; font-size: 26px; line-height: 1.15; }
    .rd .rc-tile span.f-12 { color: #98a2b3; }
    .rd .rc-tile .card-body > div { min-width: 0; padding-right: 8px; }
    .rd .rd-chip { width: 38px; height: 38px; flex: none; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--rc) 12%, #fff); color: var(--rc); font-size: 17px; }
    .rd .rd-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
    .rd .rd-head h5 { font-size: 13px !important; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #475467 !important; margin: 0; }
    .rd .rd-head h5 .fa { color: var(--epic-blue, #cb6b4f); }
    .rd .rd-note { font-size: 11.5px; font-weight: 400; text-transform: none; letter-spacing: 0; color: #98a2b3; margin-left: 4px; }
    .rd .rd-count { font-size: 12px; background: #f2f4f7; color: #475467; border-radius: 10px; padding: 1px 9px; font-weight: 600; }
    .rd .rd-count--hot { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); }
    .rd .rd-clear { padding: 14px; border-radius: 10px; background: #ecfdf3; color: #067647; font-size: 14px; }
    .rd .rd-act { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid #f2f4f7; flex-wrap: wrap; }
    .rd .rd-head + .rd-act { border-top: 0; padding-top: 4px; }
    .rd .rd-act-ic { flex: 0 0 34px; width: 34px; height: 34px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--c) 12%, #fff); color: var(--c); font-size: 14px; }
    .rd .rd-act-body { flex: 1; min-width: 220px; font-size: 13.5px; }
    .rd .rd-act-sub { font-size: 12.5px; color: #667085; margin-top: 2px; }
    .rd .rd-warn { display: inline-block; margin-left: 6px; color: #b54708; background: #fffaeb; border-radius: 4px; padding: 0 6px; font-size: 12px; }
    .rd .rd-more { display: inline-block; font-size: 13px; margin: 4px 0 4px 46px; }
    .rd .btn-sm { border-radius: 8px !important; height: 32px; display: inline-flex; align-items: center; padding: 0 12px !important; white-space: nowrap; }
    .rd .rd-funnel { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 767.98px) { .rd .rd-funnel { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .rd .rd-step { position: relative; display: flex; flex-direction: column; padding: 10px 10px 0; border: 1px solid #eaecf0; border-radius: 10px; background: #fcfcfd;
        color: inherit; text-decoration: none; overflow: hidden; min-height: 108px; }
    .rd .rd-step:hover { border-color: var(--epic-blue, #cb6b4f); background: #fff; }
    .rd .rd-step-n { font-size: 22px; font-weight: 700; color: #101828; line-height: 1.1; }
    .rd .rd-step-l { font-size: 12px; color: #475467; line-height: 1.3; min-height: 32px; }
    .rd .rd-step-bar { display: flex; align-items: flex-end; height: 34px; margin: 8px -10px 0; }
    .rd .rd-step-bar span { display: block; width: 100%; background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 22%, #fff); border-top: 2px solid var(--epic-blue, #cb6b4f); }
    .rd .rd-closed { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; font-size: 12.5px; }
    .rd .rd-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; background: #f2f4f7; color: #475467; border-radius: 999px; padding: 2px 10px; white-space: nowrap; }
    .rd a.rd-pill:hover { background: #eaecf0; text-decoration: none; }
    .rd .rd-pill.is-ok { background: #dcfae6; color: #067647; }
    .rd .rd-pill.is-bad { background: #fee4e2; color: #b42318; }
    .rd .rd-day { display: flex; gap: 14px; padding: 10px 0; border-top: 1px solid #f2f4f7; }
    .rd .rd-head + .rd-day { border-top: 0; padding-top: 0; }
    .rd .rd-date { flex: 0 0 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 10px; background: #f2f4f7;
        padding: 6px 0; font-size: 11px; color: #667085; line-height: 1.2; text-transform: uppercase; height: 62px; }
    .rd .rd-date b { font-size: 19px; color: #101828; }
    .rd .rd-date.is-today { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); }
    .rd .rd-iv { display: flex; align-items: center; gap: 12px; padding: 7px 10px; border-radius: 8px; color: inherit; font-size: 13.5px; }
    .rd .rd-iv:hover { background: #f9fafb; text-decoration: none; }
    .rd .rd-iv-time { flex: 0 0 74px; font-weight: 600; color: #101828; }
    .rd .rd-iv-who { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rd .rd-iv-mode { font-size: 12.5px; color: #667085; white-space: nowrap; }
    .rd .rd-pos { display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; padding: 10px 0; border-top: 1px solid #f2f4f7; font-size: 13.5px; }
    .rd .rd-head + .rd-pos { border-top: 0; padding-top: 0; }
    .rd .rd-pos-nums { display: flex; gap: 14px; font-size: 12.5px; color: #667085; }
    .rd .rd-pos-nums b { color: #101828; }
    .rd .rd-pos-bar { grid-column: 1 / -1; height: 6px; border-radius: 999px; background: #f2f4f7; overflow: hidden; margin: 6px 0 2px; }
    .rd .rd-pos-bar span { display: block; height: 100%; background: #12b76a; border-radius: 999px; }
    .rd .rd-pos-close { grid-column: 1 / -1; font-size: 12px; color: #98a2b3; }
    .rd .rd-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid #f2f4f7; font-size: 13.5px; }
    .rd .rd-head + .rd-row { border-top: 0; padding-top: 0; }
    .rd .rd-round { padding: 6px 0 8px; font-size: 13.5px; }
    .rd .rd-sub-head { margin: 14px 0 6px; padding-top: 12px; border-top: 1px dashed #eaecf0; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #475467; }
    .rd .rd-feed { display: flex; gap: 10px; padding: 8px 0; border-top: 1px solid #f2f4f7; font-size: 13px; }
    .rd .rd-head + .rd-feed { border-top: 0; padding-top: 0; }
    .rd .rd-feed > .fa { width: 16px; text-align: center; margin-top: 3px; }
</style>
@endsection
