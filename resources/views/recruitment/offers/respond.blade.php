@php
    $labels = [
        'sent' => 'Sent to the candidate',
        'accepted' => 'Accepted by the candidate',
        'declined' => 'Declined by the candidate',
        'withdrawn' => 'Withdrawn by us',
    ];
    $fmt = company()->date_format;
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="respond-form" method="POST">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Offer {{ $offer->reference_no }}</h4>
                <div class="p-20">
                    <p class="f-14">{{ $offer->application->candidate->name }} · {{ $offer->designation?->name }} · CTC {{ number_format($offer->annual_ctc) }} / year
                        · joining {{ $offer->joining_date->format($fmt) }} {!! $offer->statusBadge() !!}</p>

                    <x-forms.select fieldId="status" fieldLabel="The offer is now" fieldName="status" :fieldRequired="true">
                        @foreach ($choices as $choice)
                            <option value="{{ $choice }}" @selected($preset === $choice)>{{ $labels[$choice] }}</option>
                        @endforeach
                    </x-forms.select>

                    <div id="proof-box" class="d-none">
                        <div class="form-group my-3">
                            <label class="f-14 text-dark-grey" for="signed_file">Signed offer (PDF or photo)</label>
                            <input type="file" name="signed_file" id="signed_file" class="form-control-file f-14" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Kept as the proof of acceptance and copied into the employee's documents when they join.
                                Without it, note below how the candidate accepted.</small>
                        </div>
                    </div>

                    <x-forms.textarea fieldId="response_note" fieldLabel="Note" fieldName="response_note"
                        fieldPlaceholder="e.g. Sent by email on 3 Nov · Accepted by reply email · Declined: took another offer" />
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-respond" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-respond">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        function sync() { $('#proof-box').toggleClass('d-none', $('#status').val() !== 'accepted'); }
        $('#status').on('change', sync);
        sync();

        $('#save-respond').on('click', function () { recruitSubmit('respond-form', '{{ route('job-offers.respond', $offer->id) }}', 'save-respond'); });
        $('#cancel-respond').on('click', function () { recruitClose('{{ route('candidates.show', $offer->application->candidate_id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
