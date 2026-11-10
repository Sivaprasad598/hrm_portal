@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
    $flow = ['new', 'shortlisted', 'interview', 'selected', 'offered', 'offer_accepted', 'joined'];
    $canConvert = $canManage && !$candidate->user_id && $applications->contains('stage', 'offer_accepted');
@endphp
<div class="content-wrapper">

    @if (session('recruit_error'))
        <div class="alert alert-danger f-14">{{ session('recruit_error') }}</div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded b-shadow-4 p-20 mb-3">
        <a href="{{ route('candidates.index') }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Resumes</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start mt-2">
            <div class="d-flex align-items-center mb-2">
                <span class="rc-avatar lg mr-3">{{ $candidate->initials() }}</span>
                <div>
                    <h4 class="f-21 font-weight-normal mb-1">{{ $candidate->name }}
                        @if ($candidate->user_id)
                            <a href="{{ route('employees.show', $candidate->user_id) }}" class="badge badge-dark f-12 ml-1">Employee: {{ $candidate->employee?->name }}</a>
                        @endif
                    </h4>
                    <div class="f-13 text-dark-grey">
                        @if ($candidate->email) <span class="mr-3"><i class="fa fa-envelope mr-1"></i><a href="mailto:{{ $candidate->email }}">{{ $candidate->email }}</a></span> @endif
                        @if ($candidate->mobile) <span class="mr-3"><i class="fa fa-phone mr-1"></i><a href="tel:{{ $candidate->mobile }}">{{ $candidate->mobile }}</a></span> @endif
                        <span class="mr-3"><i class="fa {{ \App\Models\Candidate::SOURCES[$candidate->source][1] ?? 'fa-circle' }} mr-1"></i>{{ $candidate->sourceLabel() }}{{ $candidate->source_detail ? ' — ' . $candidate->source_detail : '' }}</span>
                        <span>Added {{ $candidate->created_at->tz($tz)->format($fmt) }}</span>
                    </div>
                </div>
            </div>

            @if ($canManage)
                <div class="mb-2">
                    @if ($canConvert)
                        @if ($conversionProblem)
                            <span class="d-inline-block" title="{{ $conversionProblem }}"><button class="btn btn-success rounded f-14" disabled><i class="fa fa-user-plus mr-1"></i>Convert to Employee</button></span>
                        @else
                            <a href="{{ route('candidates.convert', $candidate->id) }}" class="btn btn-success rounded f-14"><i class="fa fa-user-plus mr-1"></i>Convert to Employee</a>
                        @endif
                    @endif
                    <a href="{{ route('candidates.edit', $candidate->id) }}" class="btn btn-secondary rounded f-14 openRightModal ml-1"><i class="fa fa-edit mr-1"></i>Edit</a>
                    <div class="btn-group ml-1">
                        <button type="button" class="btn btn-secondary rounded f-14 dropdown-toggle" data-toggle="dropdown">More</button>
                        <div class="dropdown-menu dropdown-menu-right">
                            @if ($openPositions->isNotEmpty() && !$candidate->user_id)
                                <a class="dropdown-item openRightModal" href="{{ route('job-applications.create', ['candidate_id' => $candidate->id]) }}"><i class="fa fa-briefcase mr-2"></i>Add to a position</a>
                            @endif
                            @if ($aiReady && $candidate->resume?->isPdf())
                                <a class="dropdown-item" href="javascript:;" id="ai-fill"><i class="fa fa-magic mr-2"></i>Read resume with AI</a>
                            @endif
                            <a class="dropdown-item" href="javascript:;" data-toggle="collapse" data-target="#upload-box"><i class="fa fa-paperclip mr-2"></i>Add a file</a>
                            <a class="dropdown-item text-danger" href="javascript:;" id="delete-candidate"><i class="fa fa-trash mr-2"></i>Delete</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if ($canConvert && $conversionProblem)
            <div class="alert alert-warning f-13 mt-2 mb-0">{{ $conversionProblem }}</div>
        @endif

        @if ($canManage)
            <div class="collapse mt-3" id="upload-box">
                <x-form id="upload-form" class="border rounded p-3">
                    <div class="row align-items-end">
                        <div class="col-md-4"><label class="f-13 mb-1">File (PDF, Word, image; 5 MB)</label><input type="file" name="file" class="form-control-file f-13"></div>
                        <div class="col-md-3"><label class="f-13 mb-1">Kind</label>
                            <select name="category" class="form-control f-14"><option value="document">Document</option><option value="resume">Resume</option></select></div>
                        <div class="col-md-3"><label class="f-13 mb-1">Name</label><input type="text" name="name" class="form-control f-14" placeholder="e.g. Degree certificate"></div>
                        <div class="col-md-2"><button type="button" class="btn btn-primary rounded f-14 w-100" id="upload-file">Upload</button></div>
                    </div>
                </x-form>
            </div>
        @endif
    </div>

    <div class="row">
        {{-- Applications --}}
        <div class="col-lg-8">
            @forelse ($applications as $application)
                @php
                    $at = array_search($application->stage, $flow, true);
                    $offer = $application->offers->firstWhere('status', '<>', 'withdrawn');
                @endphp
                <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start">
                        <div class="mb-2">
                            <h5 class="f-16 font-weight-600 mb-1">
                                @if ($application->position)
                                    <a href="{{ route('job-positions.show', $application->job_position_id) }}" class="text-darkest-grey">{{ $application->position->title }}</a>
                                    <span class="f-12 text-dark-grey font-weight-normal">{{ $application->position->code }}</span>
                                @else
                                    <span class="text-warning">No position yet</span>
                                @endif
                                {!! $application->stageBadge() !!}
                            </h5>
                            <div class="f-12 text-dark-grey">Applied {{ $application->created_at->tz($tz)->format($fmt) }} · in this stage since {{ ($application->stage_changed_at ?? $application->created_at)->tz($tz)->format($fmt) }}
                                @if ($application->stage_reason) · <span class="text-danger">{{ $application->stage_reason }}</span> @endif
                            </div>
                        </div>
                        @if ($canManage && $application->stage !== 'joined')
                            <div class="mb-2">
                                @if ($application->stage === 'new' && $application->job_position_id)
                                    <a href="javascript:;" class="btn btn-sm btn-primary shortlist-app" data-id="{{ $application->id }}"><i class="fa fa-star mr-1"></i>Shortlist</a>
                                @endif
                                @if (in_array($application->stage, ['new', 'shortlisted', 'interview', 'on_hold']))
                                    <a href="{{ route('recruit-interviews.create', ['application' => $application->id]) }}" class="btn btn-sm btn-outline-primary openRightModal"><i class="fa fa-calendar-plus mr-1"></i>{{ $application->job_position_id ? 'Extra interview' : 'Schedule interview' }}</a>
                                @endif
                                @if ($application->stage === 'selected')
                                    <a href="{{ route('job-offers.create', ['application' => $application->id]) }}" class="btn btn-sm btn-primary openRightModal"><i class="fa fa-file-signature mr-1"></i>Make offer</a>
                                @endif
                                <a href="{{ route('job-applications.move-form', $application->id) }}" class="btn btn-sm btn-outline-secondary openRightModal"><i class="fa fa-exchange-alt mr-1"></i>{{ $application->position ? 'Move' : 'Set position / move' }}</a>
                            </div>
                        @endif
                    </div>

                    <div class="rc-steps my-2">
                        @foreach ($flow as $i => $step)
                            <span class="{{ $application->stage === $step ? 'now' : ($at !== false && $i < $at ? 'done' : '') }}">{{ \App\Models\JobApplication::STAGES[$step][0] }}</span>
                        @endforeach
                    </div>

                    @if ($application->cover_note)
                        <details class="mb-2">
                            <summary class="f-13 text-primary cursor-pointer">What the candidate sent</summary>
                            <div class="rc-pre border rounded p-2 mt-1 bg-light">{{ $application->cover_note }}</div>
                        </details>
                    @endif

                    {{-- Hiring rounds: the position's plan, run by the system --}}
                    @php $planRounds = $automation->rounds($application); @endphp
                    @if ($planRounds->isNotEmpty())
                        <h6 class="f-13 font-weight-600 text-dark-grey mt-3 mb-2">Hiring rounds</h6>
                        @if ($application->stage === 'new')
                            <p class="f-12 text-dark-grey mb-2">Shortlist the candidate and the system suggests the first round's slot.</p>
                        @endif
                        @foreach ($planRounds as $item)
                            @php
                                $iv = $item->interview;
                                $round = $item->round;
                                $state = $item->state;
                            @endphp
                            <div class="rc-round border rounded p-2 mb-2 f-13 {{ in_array($state, ['passed', 'waived']) ? 'is-done' : '' }} {{ $state === 'failed' ? 'is-failed' : '' }}">
                                <div class="d-flex flex-wrap justify-content-between align-items-start">
                                    <div class="d-flex align-items-start mr-2">
                                        <span class="rc-round-no mr-2">{{ $item->no }}</span>
                                        <div>
                                            <strong>{{ $round->name }}</strong>
                                            <span class="text-dark-grey">· {{ $round->isTest() ? 'Test' : 'Interview' }} · {{ $round->passText() }}</span>
                                            {!! \App\Services\Recruit\RecruitAutomation::stateBadge($state) !!}
                                            @if ($iv)
                                                <div class="text-dark-grey">
                                                    <i class="fa {{ \App\Models\RecruitInterview::MODES[$iv->mode][1] ?? 'fa-circle' }} mr-1"></i>{{ $iv->modeLabel() }} · {{ $iv->whenText() }}
                                                    @if ($iv->meeting_link) · <a href="{{ $iv->meeting_link }}" target="_blank">Meeting link</a> @endif
                                                    @if ($iv->location) · {{ $iv->location }} @endif
                                                </div>
                                                <div class="text-dark-grey">{{ $round->isTest() ? 'Conducted by' : 'Panel' }}:
                                                    {{ $iv->interviewers()->pluck('name')->implode(', ') ?: 'not assigned — choose at Change slot' }}
                                                    @if (!$round->isTest() && in_array($iv->status, ['scheduled', 'completed']))
                                                        · scorecards {{ $iv->feedback->count() }}/{{ count($iv->interviewer_ids ?: []) }}
                                                    @endif
                                                </div>
                                                @if ($iv->score !== null || $iv->marks !== null)
                                                    <div><strong>Score: {{ $automation->scoreText($iv) }}</strong></div>
                                                @endif
                                                @if ($iv->result_note && $iv->result_by)
                                                    <div class="text-dark-grey"><em>HR: {{ $iv->result_note }}</em></div>
                                                @endif
                                            @elseif ($state === 'not_started')
                                                <div class="text-lightest">{{ $item->no === 1 ? 'Starts when the candidate is shortlisted.' : 'After the round before it is cleared.' }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($canManage && $iv && !in_array($application->stage, ['offered', 'offer_accepted', 'joined']))
                                        <div class="text-right">
                                            @if ($state === 'proposed')
                                                <label class="f-12 text-dark-grey mr-1 mb-0"><input type="checkbox" class="mr-1 confirm-email" data-id="{{ $iv->id }}" @checked($candidate->email)>email candidate</label>
                                                <a href="javascript:;" class="btn btn-sm btn-primary confirm-slot" data-id="{{ $iv->id }}"><i class="fa fa-check mr-1"></i>Confirm</a>
                                                <a href="{{ route('recruit-interviews.edit', $iv->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Change slot</a>
                                            @elseif ($iv->status === 'scheduled')
                                                <a href="{{ route('recruit-interviews.edit', $iv->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Reschedule</a>
                                                <a href="{{ route('recruit-interviews.close-form', $iv->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">No-show / cancel</a>
                                            @endif
                                            @if (in_array($iv->status, ['scheduled', 'completed']))
                                                <a href="{{ route('recruit-interviews.feedback', $iv->id) }}" class="btn btn-sm btn-link">{{ $round->isTest() ? ($iv->marks === null ? 'Enter marks' : 'Marks') : 'Scorecards' }}</a>
                                            @endif
                                            @if (in_array($state, ['awaiting', 'to_decide', 'passed', 'failed', 'proposed', 'scheduled']))
                                                <a href="{{ route('recruit-interviews.decide-form', $iv->id) }}" class="btn btn-sm btn-link openRightModal">{{ in_array($state, ['passed', 'failed']) ? 'Change decision' : ($state === 'to_decide' ? 'Decide' : 'Decide / waive') }}</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Interviews outside the plan (scheduled by hand) --}}
                    @php $extraInterviews = $planRounds->isNotEmpty() ? $application->interviews->whereNull('recruit_round_id') : $application->interviews; @endphp
                    @if ($extraInterviews->isNotEmpty())
                        <h6 class="f-13 font-weight-600 text-dark-grey mt-3 mb-2">{{ $planRounds->isNotEmpty() ? 'Other interviews' : 'Interviews' }}</h6>
                        @foreach ($extraInterviews as $interview)
                            <div class="border rounded p-2 mb-2 f-13">
                                <div class="d-flex flex-wrap justify-content-between">
                                    <div>
                                        <strong>{{ $interview->round }}</strong> {!! $interview->statusBadge() !!}
                                        <div class="text-dark-grey">
                                            <i class="fa {{ \App\Models\RecruitInterview::MODES[$interview->mode][1] ?? 'fa-circle' }} mr-1"></i>{{ $interview->modeLabel() }} · {{ $interview->whenText() }}
                                            @if ($interview->meeting_link) · <a href="{{ $interview->meeting_link }}" target="_blank">Meeting link</a> @endif
                                            @if ($interview->location) · {{ $interview->location }} @endif
                                        </div>
                                        <div class="text-dark-grey">Panel: {{ $interview->interviewers()->pluck('name')->implode(', ') }}
                                            @if ($interview->candidate_notified) · <i class="fa fa-envelope text-success" title="Candidate emailed"></i> @endif
                                        </div>
                                        @if ($interview->status_note)<div class="text-dark-grey"><em>{{ $interview->status_note }}</em></div>@endif
                                    </div>
                                    <div class="text-right">
                                        <a href="{{ route('recruit-interviews.feedback', $interview->id) }}" class="btn btn-sm btn-link">Scorecards ({{ $interview->feedback->count() }}/{{ count($interview->interviewer_ids ?: []) }})</a>
                                        @if ($canManage && $interview->status === 'scheduled')
                                            <a href="{{ route('recruit-interviews.edit', $interview->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Reschedule</a>
                                            <a href="{{ route('recruit-interviews.close-form', $interview->id) }}" class="btn btn-sm btn-outline-primary openRightModal">Outcome</a>
                                        @endif
                                    </div>
                                </div>
                                @foreach ($interview->feedback as $feedback)
                                    <div class="mt-2 pt-2 border-top">
                                        <strong>{{ $feedback->user?->name }}</strong> {!! $feedback->recommendationBadge() !!} <span class="text-dark-grey">{{ $feedback->overall }}/5</span>
                                        <div class="text-dark-grey">{{ \Illuminate\Support\Str::limit($feedback->comments, 220) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endif

                    {{-- Offers --}}
                    @foreach ($application->offers as $o)
                        <div class="border rounded p-2 mb-2 f-13 {{ $o->status === 'withdrawn' ? 'text-dark-grey' : '' }}" style="{{ $o->status === 'accepted' ? 'border-color: #16a34a !important' : '' }}">
                            <div class="d-flex flex-wrap justify-content-between">
                                <div>
                                    <strong>Offer {{ $o->reference_no }}</strong> {!! $o->statusBadge() !!}
                                    <div>{{ $o->designation?->name }} · CTC {{ number_format($o->annual_ctc) }} / year · joining {{ $o->joining_date->format($fmt) }}
                                        @if ($o->valid_until) · valid till {{ $o->valid_until->format($fmt) }} @endif</div>
                                    <div class="text-dark-grey">
                                        Made {{ $o->offer_date->format($fmt) }}
                                        @if ($o->sent_at) · sent {{ $o->sent_at->tz($tz)->format($fmt) }} @endif
                                        @if ($o->responded_at) · {{ strtolower(\App\Models\JobOffer::STATUSES[$o->status][0]) }} {{ $o->responded_at->tz($tz)->format($fmt) }} @endif
                                    </div>
                                    @if ($o->response_note)<div class="text-dark-grey"><em>{{ $o->response_note }}</em></div>@endif
                                    @if ($o->signedFile)<div><i class="fa fa-file-signature text-success mr-1"></i><a href="{{ $o->signedFile->url }}" target="_blank">Signed copy</a></div>@endif
                                </div>
                                <div class="text-right">
                                    @if ($o->status !== 'withdrawn')
                                        <a href="{{ route('job-offers.letter', $o->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-print mr-1"></i>Letter</a>
                                    @endif
                                    @if ($canManage)
                                        @if ($o->status === 'draft')
                                            <a href="{{ route('job-offers.edit', $o->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">Edit</a>
                                            <a href="{{ route('job-offers.respond-form', [$o->id, 'to' => 'sent']) }}" class="btn btn-sm btn-primary openRightModal">Mark sent</a>
                                        @elseif ($o->status === 'sent')
                                            <a href="{{ route('job-offers.respond-form', [$o->id, 'to' => 'accepted']) }}" class="btn btn-sm btn-success openRightModal">Accepted</a>
                                            <a href="{{ route('job-offers.respond-form', [$o->id, 'to' => 'declined']) }}" class="btn btn-sm btn-outline-danger openRightModal">Declined</a>
                                        @elseif ($o->status === 'accepted' && $application->stage !== 'joined')
                                            <a href="{{ route('job-offers.respond-form', [$o->id, 'to' => 'withdrawn']) }}" class="btn btn-sm btn-outline-secondary openRightModal">Withdraw</a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if ($application->stage === 'offer_accepted' && $canConvert && !$conversionProblem)
                        <div class="alert alert-success f-13 mb-0 mt-2 d-flex justify-content-between align-items-center">
                            <span><i class="fa fa-handshake mr-1"></i>Offer accepted. Add {{ $candidate->name }} as an employee — the form opens filled from this record.</span>
                            <a href="{{ route('candidates.convert', $candidate->id) }}" class="btn btn-sm btn-success">Convert to Employee</a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                    <p class="f-14 text-dark-grey mb-2">In the talent pool — not being considered for a position.</p>
                    @if ($canManage && $openPositions->isNotEmpty())
                        <a href="{{ route('job-applications.create', ['candidate_id' => $candidate->id]) }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-briefcase mr-1"></i>Add to a position</a>
                    @endif
                </div>
            @endforelse

            {{-- Timeline --}}
            <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                <h5 class="f-15 font-weight-600 mb-3">Timeline</h5>
                @if ($canManage)
                    <x-form id="note-form" class="mb-3">
                        <div class="d-flex">
                            <input type="text" name="note" class="form-control f-14 mr-2" placeholder="Add a note (call made, what was discussed …)">
                            @if ($applications->count() > 1)
                                <select name="job_application_id" class="form-control f-14 mr-2" style="max-width: 200px">
                                    <option value="">General</option>
                                    @foreach ($applications as $application)
                                        <option value="{{ $application->id }}">{{ $application->positionLabel() }}</option>
                                    @endforeach
                                </select>
                            @elseif ($applications->count() === 1)
                                <input type="hidden" name="job_application_id" value="{{ $applications->first()->id }}">
                            @endif
                            <button type="button" class="btn btn-primary rounded f-14" id="add-note">Add</button>
                        </div>
                    </x-form>
                @endif
                <ul class="list-unstyled rc-timeline mb-0">
                    @foreach ($candidate->logs as $log)
                        @php [$label, $icon, $colour] = \App\Models\RecruitLog::ACTIONS[$log->action] ?? [ucfirst($log->action), 'fa-circle', 'text-dark-grey']; @endphp
                        <li>
                            <i class="fa {{ $icon }} {{ $colour }} rc-dot"></i>
                            <span class="font-weight-600">{{ $label }}</span>
                            @if ($log->action === 'stage')
                                {!! \App\Models\JobApplication::badge($log->from_stage) !!} → {!! \App\Models\JobApplication::badge($log->to_stage) !!}
                            @endif
                            @if ($log->application && $applications->count() > 1)
                                <span class="text-dark-grey">({{ $log->application->positionLabel() }})</span>
                            @endif
                            @if ($log->notes)<div class="rc-pre text-dark-grey">{{ $log->notes }}</div>@endif
                            <div class="f-11 text-lightest">{{ \Carbon\Carbon::parse($log->created_at)->tz($tz)->format($fmt . ' h:i A') }}{{ $log->doneBy ? ' · ' . $log->doneBy->name : ' · system' }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Profile --}}
        <div class="col-lg-4">
            <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                <h5 class="f-15 font-weight-600 mb-3">Profile
                    @if ($candidate->ai_filled_at)<span class="f-11 text-info font-weight-normal ml-1"><i class="fa fa-magic"></i> read with AI</span>@endif
                </h5>
                @foreach ([
                    'Experience' => $candidate->experienceText(),
                    'Current role' => trim($candidate->current_designation . ($candidate->current_company ? ' at ' . $candidate->current_company : '')),
                    'Location' => $candidate->current_location,
                    'Qualification' => $candidate->qualification,
                    'Current CTC' => $candidate->current_ctc ? number_format($candidate->current_ctc) . ' / year' : null,
                    'Expected CTC' => $candidate->expected_ctc ? number_format($candidate->expected_ctc) . ' / year' : null,
                    'Notice period' => $candidate->notice_period_days !== null ? $candidate->notice_period_days . ' days' : null,
                    'Gender' => $candidate->gender ? ucfirst($candidate->gender) : null,
                    'Date of birth' => $candidate->date_of_birth?->format($fmt),
                    'Address' => $candidate->address,
                ] as $label => $value)
                    @continue(!$value || $value === '--')
                    <div class="d-flex f-13 mb-2"><span class="text-dark-grey" style="min-width: 110px">{{ $label }}</span><span>{{ $value }}</span></div>
                @endforeach

                @if ($candidate->skills)
                    <div class="mt-2">@foreach ($candidate->skills as $skill)<span class="rc-chip">{{ $skill }}</span>@endforeach</div>
                @endif

                @if ($candidate->work_experience)
                    <h6 class="f-13 font-weight-600 text-dark-grey mt-3">Work history</h6>
                    @foreach ($candidate->work_experience as $job)
                        <div class="f-13 mb-2">
                            <strong>{{ $job['designation'] ?? '' }}</strong>{{ !empty($job['designation']) ? ', ' : '' }}{{ $job['company_name'] ?? '' }}
                            <div class="text-dark-grey f-12">{{ $job['joining_date'] ?? '?' }} – {{ $job['leaving_date'] ?? 'now' }}{{ !empty($job['location']) ? ' · ' . $job['location'] : '' }}</div>
                        </div>
                    @endforeach
                @endif

                @if ($candidate->notes)
                    <h6 class="f-13 font-weight-600 text-dark-grey mt-3">Notes</h6>
                    <div class="rc-pre">{{ $candidate->notes }}</div>
                @endif
            </div>

            <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                <h5 class="f-15 font-weight-600 mb-3">Files</h5>
                @forelse ($candidate->files as $file)
                    <div class="d-flex justify-content-between align-items-center f-13 mb-2">
                        <div class="text-truncate mr-2">
                            <i class="fa fa-{{ $file->isPdf() ? 'file-pdf text-danger' : 'file' }} mr-1"></i>
                            <a href="{{ $file->url }}" target="_blank" title="{{ $file->filename }}">{{ $file->name }}</a>
                            <div class="f-11 text-lightest">{{ \App\Models\CandidateFile::CATEGORIES[$file->category] ?? $file->category }} · {{ $file->created_at->tz($tz)->format($fmt) }}</div>
                        </div>
                        @if ($canManage && $file->category !== 'signed_offer')
                            <a href="javascript:;" class="text-danger delete-file" data-id="{{ $file->id }}" title="Remove"><i class="fa fa-times"></i></a>
                        @endif
                    </div>
                @empty
                    <p class="f-13 text-dark-grey mb-0">No files.</p>
                @endforelse
            </div>

            @if ($inbound->isNotEmpty())
                <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                    <h5 class="f-15 font-weight-600 mb-3">As received</h5>
                    @foreach ($inbound as $item)
                        <div class="f-13 mb-2">
                            <i class="fa fa-{{ $item->channel === 'email' ? 'envelope' : 'globe' }} mr-1 text-dark-grey"></i>
                            @if ($canSettings)
                                <a href="{{ route('recruitment-settings.inbox-show', $item->id) }}" class="openRightModal">{{ $item->subject ?: '(no subject)' }}</a>
                            @else
                                {{ $item->subject ?: '(no subject)' }}
                            @endif
                            <div class="f-11 text-lightest">{{ $item->from_email }} · {{ ($item->received_at ?? $item->created_at)->tz($tz)->format($fmt . ' h:i A') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        // HR shortlists; the system then suggests round 1's slot
        $('body').on('click', '.shortlist-app', function () {
            var id = $(this).data('id');
            $.easyAjax({
                url: '{{ route('job-applications.move', ':id') }}'.replace(':id', id), type: 'POST', blockUI: true,
                data: {_token: '{{ csrf_token() }}', stage: 'shortlisted'},
                success: function (response) { if (response.status === 'success') { window.location.reload(); } }
            });
        });

        // One click: the suggested slot becomes the interview
        $('body').on('click', '.confirm-slot', function () {
            var id = $(this).data('id');
            var email = $('.confirm-email[data-id="' + id + '"]').is(':checked') ? 1 : 0;
            $.easyAjax({
                url: '{{ route('recruit-interviews.confirm', ':id') }}'.replace(':id', id), type: 'POST', blockUI: true,
                data: {_token: '{{ csrf_token() }}', email_candidate: email},
                success: function (response) { if (response.status === 'success') { window.location.reload(); } }
            });
        });

        $('#add-note').on('click', function () {
            $.easyAjax({
                url: '{{ route('candidates.note', $candidate->id) }}', container: '#note-form', type: 'POST', blockUI: true,
                disableButton: true, buttonSelector: '#add-note', data: $('#note-form').serialize(),
                success: function (response) { if (response.status === 'success') { window.location.reload(); } }
            });
        });

        $('#upload-file').on('click', function () {
            $.easyAjax({
                url: '{{ route('candidates.files.store', $candidate->id) }}', container: '#upload-form', type: 'POST', blockUI: true, file: true,
                disableButton: true, buttonSelector: '#upload-file', data: $('#upload-form').serialize(),
                success: function (response) { if (response.status === 'success') { window.location.reload(); } }
            });
        });

        $('#ai-fill').on('click', function () {
            $.easyAjax({
                url: '{{ route('candidates.ai', $candidate->id) }}', type: 'POST', blockUI: true, data: {_token: '{{ csrf_token() }}'},
                success: function (response) { if (response.status === 'success') { window.location.reload(); } }
            });
        });

        $('body').on('click', '.delete-file', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')", text: 'The file is removed from this candidate.', icon: 'warning', showCancelButton: true,
                confirmButtonText: "@lang('messages.confirmDelete')", cancelButtonText: "@lang('app.cancel')",
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.easyAjax({
                        url: '{{ route('candidates.files.destroy', ':id') }}'.replace(':id', id), type: 'POST', blockUI: true,
                        data: {_token: '{{ csrf_token() }}', _method: 'DELETE'},
                        success: function (response) { if (response.status === 'success') { window.location.reload(); } }
                    });
                }
            });
        });

        $('#delete-candidate').on('click', function () {
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: 'The candidate and their files are deleted. Anyone with interviews or offers is kept on record instead.',
                icon: 'warning', showCancelButton: true, confirmButtonText: "@lang('messages.confirmDelete')", cancelButtonText: "@lang('app.cancel')",
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.easyAjax({
                        url: '{{ route('candidates.destroy', $candidate->id) }}', type: 'POST', blockUI: true,
                        data: {_token: '{{ csrf_token() }}', _method: 'DELETE'},
                        success: function (response) { if (response.status === 'success') { window.location.href = response.redirectUrl; } }
                    });
                }
            });
        });
    });
</script>
@endpush
