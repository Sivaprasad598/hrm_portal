@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="f-21 font-weight-normal mb-2">Job Dashboard</h4>
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
                        <i class="fa {{ $icon }} f-21" style="color: {{ $colour }}; opacity: .8"></i>
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
            <i class="fa fa-clipboard-check mr-1"></i> <strong>{{ $awaitingFeedback->count() }}</strong> completed interview(s) are waiting for scorecards.
            <a href="{{ route('recruit-interviews.index', ['when' => 'feedback']) }}" class="font-weight-600">See them</a>
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
@endsection
