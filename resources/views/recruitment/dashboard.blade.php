@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper rd">

    {{-- (no in-page title: the top bar has it) --}}
    <div class="d-flex flex-wrap justify-content-end align-items-center mb-3">
        @if ($canManage)
            <div class="mb-2">
                <a href="{{ route('job-positions.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-plus mr-1"></i>Add Position</a>
                <a href="{{ route('candidates.create') }}" class="btn btn-secondary rounded f-14 openRightModal ml-1"><i class="fa fa-user-plus mr-1"></i>Add Candidate</a>
            </div>
        @endif
    </div>

    <div class="row">
        @foreach ([
            ['Open Positions', $openPositions->count(), $openings . ' opening(s) to fill', '#3d78e3', 'fa-briefcase', route('job-positions.index', ['status' => 'open'])],
            ['New This Week', $newThisWeek, 'applications received', '#0ea5e9', 'fa-inbox', route('job-applications.index', ['stage' => 'new'])],
            ['In Pipeline', collect(\App\Models\JobApplication::ACTIVE)->sum(fn ($s) => $stageCounts[$s] ?? 0), $candidateCount . ' in the talent pool', '#7c3aed', 'fa-stream', route('job-applications.index')],
            ['Interviews (7 days)', $upcoming->count(), $overdueInterviews ? $overdueInterviews . ' past, not closed' : 'scheduled', '#f59e0b', 'fa-calendar-alt', route('recruit-interviews.index')],
            ['Joined This Month', $joinedThisMonth, $toJoin->count() . ' accepted, yet to join', '#16a34a', 'fa-user-check', route('job-applications.index', ['stage' => 'joined', 'view' => 'list'])],
        ] as [$label, $count, $sub, $colour, $icon, $link])
            <div class="col-xl col-md-4 col-sm-6 mb-3">
                <a href="{{ $link }}" class="card border-0 shadow-sm h-100 rc-tile text-decoration-none" style="--rc: {{ $colour }}">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted f-12 mb-1 text-uppercase font-weight-600">{{ $label }}</p>
                            <h3 class="font-weight-700 mb-0">{{ $count }}</h3>
                            <span class="f-12 text-dark-grey">{{ $sub }}</span>
                        </div>
                        <span class="rd-chip"><i class="fa {{ $icon }}"></i></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if ($unassigned)
        <div class="alert alert-warning f-14">
            <i class="fa fa-inbox mr-1"></i> <strong>{{ $unassigned }}</strong> application(s) came in without a position.
            <a href="{{ route('job-applications.index', ['position_id' => 'unassigned', 'view' => 'list']) }}" class="font-weight-600">Assign them</a>
        </div>
    @endif

    @if ($awaitingFeedback->isNotEmpty())
        <div class="alert alert-info f-14">
            <i class="fa fa-clipboard-check mr-1"></i> <strong>{{ $awaitingFeedback->count() }}</strong> round(s) held are waiting for scorecards / marks.
            <a href="{{ route('recruit-interviews.index', ['when' => 'feedback']) }}" class="font-weight-600">See them</a>
        </div>
    @endif

    {{-- Slots the system suggested: one click to confirm --}}
    @if ($toConfirm->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="f-15 font-weight-600 mb-0"><i class="fa fa-clock text-info mr-1"></i>Slots to confirm <span class="badge badge-info ml-1">{{ $toConfirmCount }}</span></h5>
                    <a href="{{ route('recruit-interviews.index', ['when' => 'confirm']) }}" class="f-13">All</a>
                </div>
                @foreach ($toConfirm as $slot)
                    <div class="d-flex flex-wrap justify-content-between align-items-center border-top py-2 f-13">
                        <div class="mr-2">
                            <a href="{{ route('candidates.show', $slot->application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $slot->application->candidate->name }}</a>
                            <span class="text-dark-grey">· {{ $slot->application->positionLabel() }} · <strong>{{ $slot->round }}</strong></span>
                            <div class="text-dark-grey">{{ $slot->whenText() }} · {{ $slot->interviewers()->pluck('name')->implode(', ') ?: 'no panel yet' }}</div>
                        </div>
                        <div class="text-nowrap">
                            <a href="javascript:;" class="btn btn-sm btn-primary confirm-slot" data-id="{{ $slot->id }}"><i class="fa fa-check mr-1"></i>Confirm</a>
                            <a href="{{ route('recruit-interviews.edit', $slot->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Change</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Rounds: how candidates are doing in each --}}
    @if ($roundStats->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="f-15 font-weight-600 mb-3">Rounds <span class="f-12 text-dark-grey font-weight-normal">(decided in the last 90 days)</span></h5>
                <div class="d-flex flex-wrap" style="gap: 10px">
                    @foreach ($roundStats as $stat)
                        @php $total = (int) $stat->passed + (int) $stat->failed; @endphp
                        <div class="border rounded px-3 py-2" style="min-width: 170px">
                            <div class="f-13 font-weight-600">{{ $stat->round }}</div>
                            <div class="f-21 font-weight-700">{{ $total ? round($stat->passed / $total * 100) : 0 }}%<span class="f-12 text-dark-grey font-weight-normal"> cleared</span></div>
                            <div class="f-12 text-dark-grey">{{ (int) $stat->passed }} cleared · {{ (int) $stat->failed }} not ·
                                avg {{ \App\Models\RecruitRound::num(round((float) $stat->avg_score, 1)) }}{{ $stat->round_type === 'test' ? ($stat->out_of ? ' / ' . \App\Models\RecruitRound::num(round($stat->out_of)) : '') : ' / 5' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Pipeline --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="f-15 font-weight-600 mb-3">Pipeline</h5>
            <div class="d-flex flex-wrap" style="gap: 10px">
                @foreach (\App\Models\JobApplication::STAGES as $stage => [$label, $class, $icon])
                    @continue($stage === 'withdrawn' && !($stageCounts[$stage] ?? 0))
                    <a href="{{ route('job-applications.index', ['stage' => $stage, 'view' => in_array($stage, \App\Models\JobApplication::ACTIVE) ? 'board' : 'list']) }}"
                       class="border rounded px-3 py-2 text-decoration-none text-center" style="min-width: 110px">
                        <i class="fa {{ $icon }} text-{{ $class }}"></i>
                        <div class="f-21 font-weight-700 text-dark">{{ $stageCounts[$stage] ?? 0 }}</div>
                        <div class="f-12 text-dark-grey">{{ $label }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="f-15 font-weight-600 mb-0">Interviews — next 7 days</h5>
                        <a href="{{ route('recruit-interviews.index') }}" class="f-13">All interviews</a>
                    </div>
                    @forelse ($upcoming as $interview)
                        @php $isToday = $interview->starts_at->copy()->tz($tz)->isToday(); @endphp
                        <div class="d-flex align-items-center py-2 border-bottom">
                            <div class="text-center mr-3" style="min-width: 54px">
                                <div class="f-11 text-uppercase {{ $isToday ? 'text-danger font-weight-700' : 'text-dark-grey' }}">{{ $isToday ? 'Today' : $interview->starts_at->copy()->tz($tz)->format('D') }}</div>
                                <div class="f-14 font-weight-600">{{ $interview->starts_at->copy()->tz($tz)->format(company()->time_format) }}</div>
                            </div>
                            <div class="flex-grow-1">
                                <a href="{{ route('candidates.show', $interview->application->candidate_id) }}" class="f-14 font-weight-600 text-darkest-grey">{{ $interview->application->candidate->name }}</a>
                                <div class="f-12 text-dark-grey">{{ $interview->round }} · {{ $interview->application->positionLabel() }} · {{ $interview->modeLabel() }}</div>
                            </div>
                            <span class="f-12 text-dark-grey">{{ $interview->starts_at->copy()->tz($tz)->format($fmt) }}</span>
                        </div>
                    @empty
                        <x-cards.no-record icon="calendar" message="No interviews in the next 7 days." />
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="f-15 font-weight-600 mb-2">Offers</h5>
                    @forelse ($offersOut as $offer)
                        <div class="d-flex justify-content-between py-2 border-bottom f-13">
                            <div>
                                <a href="{{ route('candidates.show', $offer->application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $offer->application->candidate->name }}</a>
                                <div class="text-dark-grey">{{ $offer->designation?->name }} · sent {{ $offer->sent_at?->tz($tz)->format($fmt) }}</div>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-primary">Awaiting reply</span>
                                @if ($offer->valid_until)
                                    <div class="{{ $offer->valid_until->isPast() ? 'text-danger' : 'text-dark-grey' }}">valid till {{ $offer->valid_until->format($fmt) }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="f-13 text-dark-grey">No offers waiting for a reply.</p>
                    @endforelse

                    <h5 class="f-15 font-weight-600 mt-3 mb-2">Accepted — to join</h5>
                    @forelse ($toJoin as $application)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom f-13">
                            <div>
                                <a href="{{ route('candidates.show', $application->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $application->candidate->name }}</a>
                                <div class="text-dark-grey">{{ $application->positionLabel() }}{{ $application->offer?->joining_date ? ' · joins ' . $application->offer->joining_date->format($fmt) : '' }}</div>
                            </div>
                            @if ($canManage)
                                <a href="{{ route('candidates.convert', $application->candidate_id) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-user-plus mr-1"></i>Add as employee</a>
                            @endif
                        </div>
                    @empty
                        <p class="f-13 text-dark-grey mb-0">Nobody waiting to join.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="f-15 font-weight-600 mb-0">Open Positions</h5>
                        <a href="{{ route('job-positions.index') }}" class="f-13">All positions</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm f-13 mb-0">
                            <thead><tr><th>Position</th><th class="text-center">Openings</th><th class="text-center">In pipeline</th><th class="text-center">Joined</th><th>Closes</th></tr></thead>
                            <tbody>
                            @forelse ($openPositions->take(10) as $position)
                                <tr>
                                    <td><a href="{{ route('job-positions.show', $position->id) }}" class="text-darkest-grey font-weight-500">{{ $position->title }}</a>
                                        <span class="text-lightest f-11">{{ $position->code }}</span></td>
                                    <td class="text-center">{{ $position->openings }}</td>
                                    <td class="text-center">{{ $position->active_count }}</td>
                                    <td class="text-center">{{ $position->joined_count }}</td>
                                    <td class="{{ $position->closing_date?->isPast() ? 'text-danger' : '' }}">{{ $position->closing_date?->format($fmt) ?? '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-cards.no-record icon="briefcase" message="No open positions." /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="f-15 font-weight-600 mb-2">Where applications came from <span class="f-12 text-dark-grey font-weight-normal">(30 days)</span></h5>
                    @php $totalSources = max(1, $sources->sum()); @endphp
                    @forelse (\App\Models\Candidate::SOURCES as $key => [$label, $icon])
                        @continue(!($sources[$key] ?? 0))
                        <div class="mb-2 f-13">
                            <div class="d-flex justify-content-between"><span><i class="fa {{ $icon }} mr-1 text-dark-grey"></i>{{ $label }}</span><strong>{{ $sources[$key] }}</strong></div>
                            <div class="progress" style="height: 6px"><div class="progress-bar" style="width: {{ round($sources[$key] * 100 / $totalSources) }}%"></div></div>
                        </div>
                    @empty
                    @endforelse
                    @if ($sources->isEmpty())
                        <p class="f-13 text-dark-grey">Nothing received in the last 30 days.</p>
                    @endif

                    @if ($setting)
                        <div class="border-top pt-3 mt-3 f-13">
                            <p class="mb-1"><i class="fa fa-envelope mr-1 text-dark-grey"></i><strong>Careers mailbox:</strong>
                                @if ($setting->mailboxReady())
                                    <span class="text-success">on</span> ({{ $setting->imap_username }})
                                @else
                                    <span class="text-dark-grey">off</span>
                                @endif
                            </p>
                            @if ($setting->mail_last_result)
                                <p class="text-dark-grey mb-1">{{ $setting->mail_last_result }}</p>
                            @endif
                            <p class="mb-0"><i class="fa fa-globe mr-1 text-dark-grey"></i><strong>Website API:</strong>
                                {!! $setting->api_enabled ? '<span class="text-success">on</span>' : '<span class="text-dark-grey">off</span>' !!}
                                · <a href="{{ route('recruitment-settings.index') }}">Settings</a> · <a href="{{ route('recruitment-settings.inbox') }}">Incoming log</a></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="f-15 font-weight-600 mb-3">Recent activity</h5>
            <ul class="list-unstyled rc-timeline mb-0">
                @forelse ($recent as $log)
                    @php [$label, $icon, $colour] = \App\Models\RecruitLog::ACTIONS[$log->action] ?? [ucfirst($log->action), 'fa-circle', 'text-dark-grey']; @endphp
                    <li>
                        <i class="fa {{ $icon }} {{ $colour }} rc-dot"></i>
                        <a href="{{ route('candidates.show', $log->candidate_id) }}" class="font-weight-600 text-darkest-grey">{{ $log->candidate_name }}</a> —
                        @if ($log->action === 'stage')
                            {!! \App\Models\JobApplication::badge($log->from_stage) !!} → {!! \App\Models\JobApplication::badge($log->to_stage) !!}
                        @else
                            {{ $label }}
                        @endif
                        @if ($log->notes) <span class="text-dark-grey">{{ \Illuminate\Support\Str::limit($log->notes, 120) }}</span> @endif
                        <div class="f-11 text-lightest">{{ \Carbon\Carbon::parse($log->created_at)->tz($tz)->format($fmt . ' h:i A') }}{{ $log->doneBy ? ' · ' . $log->doneBy->name : '' }}</div>
                    </li>
                @empty
                    <li class="pl-0 text-dark-grey">Nothing yet. Add a position, then candidates — or switch on the careers mailbox in Settings.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<style>
    /* Job Dashboard: the app's card look - white, 12px corners, one soft shadow,
       the colour on an icon chip; notices as slim strips; quiet section titles */
    .rd .card { border: 0 !important; border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06) !important; }
    .rd .rc-tile { border-left: 0 !important; transition: box-shadow .15s, transform .15s; }
    .rd .rc-tile:hover { box-shadow: 0 8px 22px rgba(16, 24, 40, .10) !important; transform: translateY(-1px); }
    .rd .rc-tile .card-body { padding: 18px 20px !important; align-items: flex-start !important; }
    .rd .rc-tile p { white-space: nowrap; font-size: 11.5px !important; letter-spacing: .05em; color: #667085 !important; margin-bottom: 6px !important; }
    .rd .rc-tile h3 { color: #101828 !important; font-size: 26px; line-height: 1.15; }
    .rd .rc-tile span.f-12 { color: #98a2b3 !important; }
    .rd .rc-tile .card-body > div { min-width: 0; }
    .rd .rd-chip { width: 38px; height: 38px; flex: none; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--rc) 12%, #fff); color: var(--rc); font-size: 17px; }
    .rd .alert { border: 0; border-left: 3px solid; border-radius: 10px; padding: 10px 16px; font-size: 13.5px; background: #fff;
        box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .rd .alert-warning { border-left-color: #f79009; color: #93370d; }
    .rd .alert-info { border-left-color: #2e90fa; color: #175cd3; }
    .rd .alert a { color: inherit; text-decoration: underline; }
    .rd h5.f-15 { font-size: 13px !important; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #475467 !important; }
    .rd h5.f-15 .fa, .rd h5.f-15 svg { color: var(--epic-blue, #cb6b4f) !important; }
    .rd .card-body { padding: 18px 20px; }
    .rd .border.rounded { border-color: #eaecf0 !important; border-radius: 10px !important; background: #fcfcfd; }
    .rd a.border.rounded:hover { border-color: var(--epic-blue, #cb6b4f) !important; background: #fff; }
    .rd .border-top, .rd .border-bottom { border-color: #f2f4f7 !important; }
    .rd .btn-sm { border-radius: 8px !important; height: 32px; display: inline-flex; align-items: center; padding: 0 12px !important; }
    .rd .badge-info { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); color: var(--epic-blue, #cb6b4f); }
</style>
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
