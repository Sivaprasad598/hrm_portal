<div class="row">
    <div class="col-sm-12">
        <x-form id="close-form" method="POST">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Interview Outcome</h4>
                <div class="p-20">
                    <p class="f-14">{{ $interview->application->candidate->name }} · {{ $interview->round }} · {{ $interview->whenText() }}</p>

                    <x-forms.select fieldId="status" fieldLabel="What happened" fieldName="status" :fieldRequired="true">
                        <option value="completed" @selected($preset === 'completed')>Completed — the interview took place</option>
                        <option value="no_show" @selected($preset === 'no_show')>No-show — the candidate did not come</option>
                        <option value="cancelled" @selected($preset === 'cancelled')>Cancelled</option>
                    </x-forms.select>

                    <x-forms.textarea fieldId="note" fieldLabel="Note" fieldName="note"
                        fieldPlaceholder="Needed for a no-show or a cancellation; kept on the candidate's record" />

                    <div id="email-box" class="d-none">
                        <x-forms.checkbox fieldId="email_candidate" fieldName="email_candidate" fieldValue="1" :checked="(bool) $interview->application->candidate->email"
                            fieldLabel="Email the candidate that it is cancelled" />
                    </div>

                    <p class="f-12 text-dark-grey mt-3 mb-0">Completing it asks every interviewer who has not given a scorecard yet to do so.</p>
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-close" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-close">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        function sync() { $('#email-box').toggleClass('d-none', $('#status').val() !== 'cancelled'); }
        $('#status').on('change', sync);
        sync();

        $('#save-close').on('click', function () { recruitSubmit('close-form', '{{ route('recruit-interviews.close', $interview->id) }}', 'save-close'); });
        $('#cancel-close').on('click', function () { recruitClose('{{ route('candidates.show', $interview->application->candidate_id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
