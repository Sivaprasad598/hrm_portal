@php
    $candidate = $interview->application->candidate;
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="decide-form" method="POST">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Decide Round</h4>
                <div class="p-20">
                    <p class="f-14 mb-1">{{ $candidate->name }} · <strong>{{ $interview->round }}</strong> {!! $interview->resultBadge() !!}</p>
                    <p class="f-13 text-dark-grey">
                        {{ $interview->isTest() ? 'Test / exam' : 'Face-to-face interview' }} · {{ $interview->whenText() }}<br>
                        Score: {{ $scoreText }}
                        @if (!$interview->isTest() && $missing = $interview->missingFeedbackIds())
                            <br><span class="text-warning">Scorecards not in from: {{ \App\Models\User::withoutGlobalScopes()->whereIn('id', $missing)->pluck('name')->implode(', ') }}</span>
                        @endif
                    </p>

                    <x-forms.select fieldId="result" fieldLabel="Decision" fieldName="result" :fieldRequired="true">
                        <option value="">--</option>
                        @if ($interview->status !== 'proposed')
                            <option value="passed" @selected($interview->result === 'passed')>Cleared — take the candidate to the next round</option>
                            <option value="failed" @selected($interview->result === 'failed')>Not cleared — reject the candidate</option>
                        @endif
                        <option value="waived" @selected($interview->result === 'waived')>Waive — skip this round (or a scorecard that will not come)</option>
                    </x-forms.select>

                    <x-forms.textarea fieldId="note" fieldLabel="Why" fieldName="note" :fieldRequired="true"
                        fieldPlaceholder="e.g. Interviewer left the company; strong practical test despite marks; candidate already assessed" />

                    <p class="f-12 text-dark-grey mt-3 mb-0">
                        This overrides the cut-off. Cleared or waived moves the candidate on (the next round's slot is suggested, or Selected after the last one);
                        not cleared rejects them. A candidate the system rejected on this round comes back when cleared here.
                    </p>
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-decide" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-decide">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        $('#save-decide').on('click', function () { recruitSubmit('decide-form', '{{ route('recruit-interviews.decide', $interview->id) }}', 'save-decide'); });
        $('#cancel-decide').on('click', function () { recruitClose('{{ route('candidates.show', $interview->application->candidate_id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
