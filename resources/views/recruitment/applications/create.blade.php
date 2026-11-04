<div class="row">
    <div class="col-sm-12">
        <x-form id="application-form" method="POST">
            <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Add {{ $candidate->name }} to a Position</h4>
                <div class="p-20">
                    @if ($positions->isEmpty())
                        <p class="f-14 text-dark-grey">There is no open position this candidate is not already being considered for.</p>
                    @else
                        <x-forms.select fieldId="job_position_id" fieldLabel="Position" fieldName="job_position_id" :fieldRequired="true" search="true">
                            <option value="">--</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}">{{ $position->title }} ({{ $position->code }})</option>
                            @endforeach
                        </x-forms.select>
                        <p class="f-13 text-dark-grey">The application starts at <strong>New</strong>. An application that came in without a position is given this one instead of opening a second.</p>
                    @endif
                </div>
                <x-form-actions>
                    @if ($positions->isNotEmpty())
                        <x-forms.button-primary id="save-application" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    @endif
                    <x-forms.button-secondary id="cancel-application">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        $('#save-application').on('click', function () { recruitSubmit('application-form', '{{ route('job-applications.store') }}', 'save-application'); });
        $('#cancel-application').on('click', function () { recruitClose('{{ route('candidates.show', $candidate->id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
