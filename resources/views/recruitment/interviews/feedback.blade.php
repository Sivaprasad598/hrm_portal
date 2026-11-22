@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $application = $interview->application;
    $candidate = $application->candidate;
    $tz = company()->timezone;
    $fmt = company()->date_format;
@endphp
<style>
    .iv-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 20px 22px; margin-bottom: 16px; }
    .iv-card h5 { font-size: 15px; font-weight: 600; margin: 0 0 14px; color: var(--epic-text, #1d2939); display: flex; align-items: center; gap: 8px; }
    .iv-hero { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
    .iv-avatar { flex: 0 0 56px; width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); font-weight: 600; font-size: 19px; }
    .iv-name { font-size: 21px; font-weight: 600; margin: 0; color: var(--epic-text, #1d2939); }
    .iv-sub { font-size: 13.5px; color: #667085; margin-top: 2px; }
    .iv-actions { margin-left: auto; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .iv-round { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 18px 0 14px; padding-top: 16px; border-top: 1px solid var(--epic-line, #eaecf0); }
    .iv-round strong { font-size: 16px; }
    .iv-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 991.98px) { .iv-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .iv-facts { grid-template-columns: 1fr; } .iv-actions { margin-left: 0; } }
    .iv-fact { background: #f9fafb; border: 1px solid #f2f4f7; border-radius: 10px; padding: 12px 14px; min-width: 0; }
    .iv-fact dt { font-size: 12px; font-weight: 500; color: #667085; margin: 0 0 4px; display: flex; align-items: center; gap: 6px; }
    .iv-fact dd { font-size: 14px; color: var(--epic-text, #1d2939); margin: 0; word-break: break-word; }
    .iv-note { margin-top: 12px; padding: 12px 14px; border-radius: 10px; background: #fffaeb; border: 1px solid #fef0c7; font-size: 13.5px; color: #7a2e0e; }
    .iv-note--hr { background: #f9fafb; border-color: #eaecf0; color: #475467; }
    .iv-wait { display: flex; gap: 14px; align-items: flex-start; }
    .iv-wait .ic { flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #f0f9ff; color: #0086c9; font-size: 16px; }
    .iv-wait p { margin: 2px 0 0; font-size: 13.5px; color: #667085; }
    .iv-dl { margin: 0; }
    .iv-dl > div { display: flex; gap: 12px; padding: 7px 0; border-bottom: 1px dashed #eaecf0; font-size: 13.5px; }
    .iv-dl > div:last-child { border-bottom: 0; }
    .iv-dl dt { flex: 0 0 110px; color: #667085; font-weight: 500; }
    .iv-dl dd { margin: 0; color: var(--epic-text, #1d2939); }
    .iv-score { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; background: #ecfdf3; color: #067647; font-weight: 600; font-size: 13.5px; }
    .iv-count { font-size: 12px; font-weight: 500; background: #f2f4f7; color: #475467; border-radius: 10px; padding: 1px 8px; }
    .iv-waiting { display: flex; align-items: center; gap: 6px; margin-top: 12px; font-size: 12.5px; color: #b54708; }
</style>
<div class="content-wrapper" data-swap-links>
    @php
        $canManageHere = \App\Services\Recruit\RecruitAccess::can('manage');
        $modeIcon = \App\Models\RecruitInterview::MODES[$interview->mode][1] ?? 'fa-circle';
    @endphp

    {{-- Who, which round, and what HR can do with it --}}
    <div class="iv-card">
        @if (\App\Services\Recruit\RecruitAccess::can('view'))
            <a href="{{ route('candidates.show', $candidate->id) }}" class="f-13 text-dark-grey d-inline-block mb-3"><i class="fa fa-arrow-left mr-1"></i>Candidate</a>
        @endif
        <div class="iv-hero">
            <span class="iv-avatar">{{ $candidate->initials() }}</span>
            <div style="min-width: 0">
                <p class="iv-name">{{ $candidate->name }}</p>
                <div class="iv-sub">{{ $application->positionLabel() }}
                    @if ($ref = $candidate->referrerLabel()) · <i class="fa fa-user-friends mx-1"></i>Referred by {{ $ref }}@endif
                </div>
            </div>
            <div class="iv-actions">
                @if ($candidate->resume)
                    <a href="{{ $candidate->resume->url }}" target="_blank" class="btn btn-sm btn-outline-secondary no-swap"><i class="fa fa-file-alt mr-1"></i>Resume</a>
                @endif
                @if ($canManageHere && $interview->status === 'scheduled')
                    <a href="{{ route('recruit-interviews.edit', $interview->id) }}" class="btn btn-sm btn-outline-secondary openRightModal"><i class="fa fa-calendar-alt mr-1"></i>Reschedule</a>
                @endif
                @if ($canManageHere && $interview->recruit_round_id && !in_array($interview->status, ['cancelled', 'proposed'], true))
                    <a href="{{ route('recruit-interviews.decide-form', $interview->id) }}" class="btn btn-sm btn-outline-primary openRightModal">
                        <i class="fa fa-gavel mr-1"></i>{{ $interview->result ? 'Change decision' : 'Decide / waive' }}</a>
                @endif
                @include('recruitment.partials.confirm-slot', ['interview' => $interview])
            </div>
        </div>

        <div class="iv-round">
            <strong>{{ $interview->round }}</strong> {!! $interview->statusBadge() !!} {!! $interview->resultBadge() !!}
            @if ($interview->score !== null || $interview->marks !== null)
                <span class="iv-score ml-auto"><i class="fa fa-star"></i>{{ $automation->scoreText($interview) }}</span>
            @endif
        </div>

        <dl class="iv-facts mb-0">
            <div class="iv-fact"><dt><i class="fa fa-calendar"></i>When</dt><dd>{{ $interview->whenText() }}</dd></div>
            <div class="iv-fact"><dt><i class="fa {{ $modeIcon }}"></i>Mode</dt><dd>{{ $interview->modeLabel() }}
                @if ($interview->meeting_link) · <a href="{{ $interview->meeting_link }}" target="_blank" class="no-swap">Join</a>@endif
                @if ($interview->location)<div class="text-dark-grey f-13">{{ $interview->location }}</div>@endif</dd></div>
            <div class="iv-fact"><dt><i class="fa fa-users"></i>Panel</dt><dd>{{ $interview->interviewers()->pluck('name')->implode(', ') ?: '--' }}</dd></div>
            <div class="iv-fact"><dt><i class="fa {{ $interview->isTest() ? 'fa-file-alt' : 'fa-comments' }}"></i>{{ $interview->isTest() ? 'Test' : 'Interview' }}</dt><dd>
                @if ($interview->isTest())
                    Out of {{ \App\Models\RecruitRound::num($interview->max_marks ?: 100) }}{{ $interview->cut_off !== null ? ' · cut-off ' . \App\Models\RecruitRound::num($interview->cut_off) : '' }}
                @elseif ($interview->cut_off !== null)
                    Pass mark {{ \App\Models\RecruitRound::num($interview->cut_off) }} / 5
                @else
                    Face-to-face · HR decides
                @endif</dd></div>
        </dl>
        @if ($interview->notes)<div class="iv-note rc-pre"><i class="fa fa-sticky-note mr-1"></i>{{ $interview->notes }}</div>@endif
        @if ($interview->result_note && $interview->result_by)<div class="iv-note iv-note--hr rc-pre"><i class="fa fa-gavel mr-1"></i>HR: {{ $interview->result_note }}</div>@endif
    </div>

    <div class="row">
        <div class="col-lg-8">
            @if ($canMarks)
                <div class="iv-card">
                    <h5><i class="fa fa-pen text-lightest"></i>Enter the marks</h5>
                    <x-form id="marks-form">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <x-forms.number fieldId="marks" :fieldLabel="'Marks (out of ' . \App\Models\RecruitRound::num($interview->max_marks ?: 100) . ')'" fieldName="marks" :fieldRequired="true" />
                            </div>
                            <div class="col-md-8">
                                <x-forms.text fieldId="marks_comments" fieldLabel="Remarks" fieldName="comments" fieldPlaceholder="Optional" />
                            </div>
                        </div>
                        <p class="f-12 text-dark-grey">{{ $interview->cut_off !== null ? 'At or over ' . \App\Models\RecruitRound::num($interview->cut_off) . ' clears the round and the next one is set up; under it, the candidate is not taken forward.' : 'There is no cut-off: HR decides this round.' }}</p>
                        <x-forms.button-primary id="save-marks" icon="check">Save marks</x-forms.button-primary>
                    </x-form>
                </div>
            @elseif ($interview->isTest() && $interview->marks === null && $isInterviewer)
                <div class="iv-card iv-wait"><span class="ic"><i class="fa fa-hourglass-half"></i></span>
                    <div><strong class="f-14">Marks open after the test</strong><p>You can enter the marks once the test has taken place ({{ $interview->whenText() }}).</p></div></div>
            @endif

            @if ($canGive)
                <div class="iv-card">
                    <h5><i class="fa fa-clipboard-check text-lightest"></i>Your scorecard</h5>
                    <x-form id="feedback-form">
                        @foreach ($questions as $i => $question)
                            <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom py-2">
                                <span class="f-14">{{ $question }}</span>
                                <span class="rc-stars f-13">
                                    @for ($n = 1; $n <= 5; $n++)
                                        <label><input type="radio" name="ratings[{{ $i }}]" value="{{ $n }}"> {{ $n }}</label>
                                    @endfor
                                </span>
                            </div>
                        @endforeach

                        <div class="d-flex flex-wrap justify-content-between align-items-center py-2">
                            <span class="f-14 font-weight-600">Overall <sup class="text-danger">*</sup></span>
                            <span class="rc-stars f-13">
                                @for ($n = 1; $n <= 5; $n++)
                                    <label><input type="radio" name="overall" value="{{ $n }}"> {{ $n }}</label>
                                @endfor
                            </span>
                        </div>

                        <div class="form-group my-3">
                            <label class="f-14 text-dark-grey">Recommendation <sup class="text-danger">*</sup></label>
                            <div>
                                @foreach (\App\Models\RecruitInterviewFeedback::RECOMMENDATIONS as $key => [$label, $class])
                                    <label class="mr-4 f-14"><input type="radio" name="recommendation" value="{{ $key }}" class="mr-1"><span class="text-{{ $class }} font-weight-600">{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </div>

                        <x-forms.textarea fieldId="comments" fieldLabel="Why — strengths, concerns" fieldName="comments" :fieldRequired="true" />

                        <p class="f-12 text-dark-grey">Once saved, the scorecard is kept as it is on the candidate's record.</p>
                        <x-forms.button-primary id="save-feedback" icon="check">Save scorecard</x-forms.button-primary>
                    </x-form>
                </div>
            @elseif ($isInterviewer && !$mine)
                <div class="iv-card iv-wait"><span class="ic"><i class="fa {{ $interview->status === 'cancelled' ? 'fa-ban' : 'fa-hourglass-half' }}"></i></span>
                    <div><strong class="f-14">{{ $interview->status === 'cancelled' ? 'This interview was cancelled' : 'Your scorecard opens when the interview starts' }}</strong>
                        @unless ($interview->status === 'cancelled')<p>{{ $interview->whenText() }} &mdash; you are reminded to give it afterwards.</p>@endunless</div></div>
            @endif

            @unless ($interview->isTest())
            <div class="iv-card">
                <h5><i class="fa fa-list-alt text-lightest"></i>Scorecards <span class="iv-count">{{ $interview->feedback->count() }} / {{ count($interview->interviewer_ids ?: []) }}</span></h5>
                @if (!$showScorecards)
                    <p class="f-13 text-dark-grey mb-0">The other scorecards show here once you have given yours.</p>
                @else
                @forelse ($interview->feedback as $feedback)
                    <div class="border rounded p-3 mb-2 f-13">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $feedback->user?->name }}</strong>
                            <span>{!! $feedback->recommendationBadge() !!} <span class="ml-1">Overall {{ $feedback->overall }}/5</span></span>
                        </div>
                        @if ($feedback->ratings)
                            <div class="mt-1">
                                @foreach ($feedback->ratings as $question => $value)
                                    <span class="rc-chip">{{ $question }}: {{ $value }}/5</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="rc-pre mt-1">{{ $feedback->comments }}</div>
                        <div class="f-11 text-lightest mt-1">{{ $feedback->created_at->tz($tz)->format($fmt . ' h:i A') }}</div>
                    </div>
                @empty
                    <p class="f-13 text-dark-grey mb-0">No scorecards yet.</p>
                @endforelse
                @endif

                @if ($missing = $interview->missingFeedbackIds())
                    <p class="iv-waiting mb-0"><i class="fa fa-clock"></i>Waiting for: {{ \App\Models\User::withoutGlobalScopes()->whereIn('id', $missing)->pluck('name')->implode(', ') }}</p>
                @endif
            </div>
            @endunless

        </div>

        {{-- The candidate, for the panel --}}
        <div class="col-lg-4">
            <div class="iv-card">
                <h5><i class="fa fa-user text-lightest"></i>Candidate</h5>
                <dl class="iv-dl">
                    @foreach ([
                        'Experience' => $candidate->experienceText(),
                        'Current role' => trim($candidate->current_designation . ($candidate->current_company ? ' at ' . $candidate->current_company : '')),
                        'Qualification' => $candidate->qualification,
                        'Location' => $candidate->current_location,
                        'Notice period' => $candidate->notice_period_days !== null ? ($candidate->notice_period_days ? $candidate->notice_period_days . ' days' : 'Immediate') : null,
                    ] as $label => $value)
                        @continue(!$value || $value === '--')
                        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($candidate->skills)
                    <div class="mt-3">@foreach ($candidate->skills as $skill)<span class="rc-chip">{{ $skill }}</span>@endforeach</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $('#save-marks').on('click', function () {
        $.easyAjax({
            url: '{{ route('recruit-interviews.marks', $interview->id) }}', container: '#marks-form', type: 'POST', blockUI: true,
            disableButton: true, buttonSelector: '#save-marks', data: $('#marks-form').serialize(),
            success: function (response) { if (response.status === 'success') { PageSwap.open(response.redirectUrl); } }
        });
    });

    $('#save-feedback').on('click', function () {
        $.easyAjax({
            url: '{{ route('recruit-interviews.feedback.store', $interview->id) }}', container: '#feedback-form', type: 'POST', blockUI: true,
            disableButton: true, buttonSelector: '#save-feedback', data: $('#feedback-form').serialize(),
            success: function (response) { if (response.status === 'success') { PageSwap.open(response.redirectUrl); } }
        });
    });
</script>
@endpush
