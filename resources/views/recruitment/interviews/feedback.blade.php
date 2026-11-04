@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $application = $interview->application;
    $candidate = $application->candidate;
    $tz = company()->timezone;
    $fmt = company()->date_format;
@endphp
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="bg-white rounded b-shadow-4 p-20">
                @if (\App\Services\Recruit\RecruitAccess::can('view'))
                    <a href="{{ route('candidates.show', $candidate->id) }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Candidate</a>
                @endif
                <h4 class="f-21 font-weight-normal mt-2 mb-1">{{ $candidate->name }}</h4>
                <div class="f-13 text-dark-grey mb-3">{{ $application->positionLabel() }}</div>

                <div class="f-13">
                    <div class="mb-1"><strong>{{ $interview->round }}</strong> {!! $interview->statusBadge() !!}</div>
                    <div class="mb-1"><i class="fa fa-calendar mr-1 text-dark-grey"></i>{{ $interview->whenText() }}</div>
                    <div class="mb-1"><i class="fa {{ \App\Models\RecruitInterview::MODES[$interview->mode][1] ?? 'fa-circle' }} mr-1 text-dark-grey"></i>{{ $interview->modeLabel() }}
                        @if ($interview->meeting_link) · <a href="{{ $interview->meeting_link }}" target="_blank">Join</a> @endif
                        @if ($interview->location) · {{ $interview->location }} @endif</div>
                    <div class="mb-1"><i class="fa fa-users mr-1 text-dark-grey"></i>{{ $interview->interviewers()->pluck('name')->implode(', ') }}</div>
                    @if ($interview->notes)<div class="rc-pre border rounded p-2 mt-2 bg-light">{{ $interview->notes }}</div>@endif
                </div>

                <h6 class="f-13 font-weight-600 text-dark-grey mt-3">Candidate</h6>
                <div class="f-13">
                    {{ $candidate->experienceText() }}{{ $candidate->current_designation ? ' · ' . $candidate->current_designation : '' }}{{ $candidate->current_company ? ' at ' . $candidate->current_company : '' }}
                    @if ($candidate->qualification)<div>{{ $candidate->qualification }}</div>@endif
                    @if ($candidate->skills)<div class="mt-1">@foreach ($candidate->skills as $skill)<span class="rc-chip">{{ $skill }}</span>@endforeach</div>@endif
                    @if ($candidate->resume)
                        <a href="{{ $candidate->resume->url }}" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="fa fa-file-alt mr-1"></i>Open resume</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if ($canGive)
                <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                    <h5 class="f-16 font-weight-600 mb-3">Your scorecard</h5>
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
                <div class="alert alert-info f-14">
                    {{ $interview->status === 'cancelled' ? 'This interview was cancelled.' : 'You can give your scorecard once the interview has started.' }}
                </div>
            @endif

            <div class="bg-white rounded b-shadow-4 p-20 mb-3">
                <h5 class="f-16 font-weight-600 mb-3">Scorecards ({{ $interview->feedback->count() }}/{{ count($interview->interviewer_ids ?: []) }})</h5>
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
                    <p class="f-12 text-warning mt-2 mb-0">Waiting for: {{ \App\Models\User::withoutGlobalScopes()->whereIn('id', $missing)->pluck('name')->implode(', ') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $('#save-feedback').on('click', function () {
        $.easyAjax({
            url: '{{ route('recruit-interviews.feedback.store', $interview->id) }}', container: '#feedback-form', type: 'POST', blockUI: true,
            disableButton: true, buttonSelector: '#save-feedback', data: $('#feedback-form').serialize(),
            success: function (response) { if (response.status === 'success') { window.location.href = response.redirectUrl; } }
        });
    });
</script>
@endpush
