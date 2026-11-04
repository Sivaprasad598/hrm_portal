@php
    $canChangePosition = !in_array($application->stage, ['offered', 'offer_accepted', 'joined'], true);
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="move-form" method="POST">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $application->candidate->name }}</h4>
                <div class="p-20">
                    <p class="f-14 mb-3">Now: {!! $application->stageBadge() !!} for <strong>{{ $application->positionLabel() }}</strong></p>

                    @if ($canChangePosition)
                        <x-forms.select fieldId="job_position_id" fieldLabel="Position" fieldName="job_position_id" search="true">
                            <option value="">{{ $application->position ? 'Keep: ' . $application->position->title : '-- choose --' }}</option>
                            @foreach ($positions as $position)
                                @continue($position->id === $application->job_position_id)
                                <option value="{{ $position->id }}">{{ $position->title }} ({{ $position->code }})</option>
                            @endforeach
                        </x-forms.select>
                    @endif

                    <x-forms.select fieldId="stage" fieldLabel="Move to" fieldName="stage">
                        <option value="">-- stay at {{ $application->stageLabel() }} --</option>
                        @foreach ($choices as $to)
                            <option value="{{ $to }}" @selected($preset === $to)>{{ \App\Models\JobApplication::STAGES[$to][0] }}</option>
                        @endforeach
                    </x-forms.select>

                    <div id="reason-box" class="d-none">
                        <x-forms.select fieldId="reason_pick" fieldLabel="Reason" fieldName="reason_pick" :fieldRequired="true">
                            <option value="">--</option>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                            <option value="__other">Other…</option>
                        </x-forms.select>
                        <input type="text" name="reason" id="reason" class="form-control f-14 d-none" maxlength="191" placeholder="The reason">
                    </div>

                    <x-forms.textarea fieldId="notes" fieldLabel="Note" fieldName="notes" fieldPlaceholder="Kept on the candidate's timeline" />

                    <p class="f-12 text-dark-grey mb-0">
                        <strong>Selected</strong> needs a completed interview with every scorecard in. Offered and Offer Accepted follow the offer;
                        Joined follows "Convert to Employee". Rejected, On Hold and Withdrawn need a reason.
                    </p>
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="save-move" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-move">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        var closing = @json(\App\Models\JobApplication::CLOSING);

        function syncReason() {
            var needs = closing.indexOf($('#stage').val()) !== -1;
            $('#reason-box').toggleClass('d-none', !needs);
            var other = $('#reason_pick').val() === '__other';
            $('#reason').toggleClass('d-none', !other);
            if (!other) { $('#reason').val(needs ? $('#reason_pick').val() : ''); }
        }

        $('#stage, #reason_pick').on('change', syncReason);
        syncReason();

        $('#save-move').on('click', function () {
            syncReason();
            recruitSubmit('move-form', '{{ route('job-applications.move', $application->id) }}', 'save-move');
        });
        $('#cancel-move').on('click', function () { recruitClose('{{ route('candidates.show', $application->candidate_id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
