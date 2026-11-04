@php
    $editing = $offer->exists;
    $fmt = company()->date_format;
    $candidate = $application->candidate;
@endphp
<div class="row">
    <div class="col-sm-12">
        <x-form id="offer-form" method="POST">
            @if ($editing)
                <input type="hidden" name="_method" value="PUT">
            @else
                <input type="hidden" name="job_application_id" value="{{ $application->id }}">
            @endif

            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">{{ $editing ? 'Edit Offer' : 'Make Offer' }}</h4>

                <div class="row p-20">
                    <div class="col-12 f-14 mb-2">
                        <strong>{{ $candidate->name }}</strong> for {{ $application->positionLabel() }}
                        @if ($candidate->expected_ctc)<span class="text-dark-grey"> · expects {{ number_format($candidate->expected_ctc) }} / year</span>@endif
                        @if ($candidate->current_ctc)<span class="text-dark-grey"> · now {{ number_format($candidate->current_ctc) }} / year</span>@endif
                    </div>

                    <div class="col-md-4">
                        <x-forms.text fieldId="reference_no" fieldLabel="Reference No." fieldName="reference_no" :fieldValue="$offer->reference_no" />
                    </div>
                    <div class="col-md-8">
                        <x-forms.select fieldId="designation_id" fieldLabel="Designation" fieldName="designation_id" :fieldRequired="true" search="true">
                            <option value="">--</option>
                            @foreach ($designations as $designation)
                                <option value="{{ $designation->id }}" @selected($offer->designation_id == $designation->id)>{{ $designation->name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-6">
                        <x-forms.select fieldId="department_id" fieldLabel="Division" fieldName="department_id" search="true">
                            <option value="">--</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($offer->department_id == $department->id)>{{ $department->team_name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-6">
                        <x-forms.select fieldId="branch_id" fieldLabel="Branch" fieldName="branch_id">
                            <option value="">--</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($offer->branch_id == $branch->id)>{{ $branch->branch_name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>

                    <div class="col-md-6">
                        <x-forms.number fieldId="annual_ctc" fieldLabel="Annual CTC" fieldName="annual_ctc" :fieldRequired="true" :fieldValue="$offer->annual_ctc ? round($offer->annual_ctc) : null"
                            fieldHelp="Printed on the letter, and filled into Add Employee when the candidate joins." />
                    </div>
                    <div class="col-md-6">
                        <x-forms.datepicker fieldId="joining_date" fieldLabel="Joining Date" fieldName="joining_date" :fieldRequired="true"
                            :fieldValue="$offer->joining_date?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-6">
                        <x-forms.datepicker fieldId="offer_date" fieldLabel="Offer Date" fieldName="offer_date" :fieldRequired="true"
                            :fieldValue="$offer->offer_date?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-6">
                        <x-forms.datepicker fieldId="valid_until" fieldLabel="Accept By" fieldName="valid_until"
                            :fieldValue="$offer->valid_until?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="offer_notes" fieldLabel="Notes (internal)" fieldName="notes" :fieldValue="$offer->notes"
                            fieldPlaceholder="Negotiation, approvals, anything to remember" />
                    </div>
                    <div class="col-12 f-12 text-dark-grey">The offer is saved as a draft. Print the letter from the candidate's page, then mark it sent.</div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-offer" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-offer">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('recruitment.partials.submit-js')

<script>
    $(function () {
        ['#joining_date', '#offer_date', '#valid_until'].forEach(function (id) { datepicker(id, {position: 'bl', ...datepickerConfig}); });

        $('#save-offer').on('click', function () {
            recruitSubmit('offer-form', '{{ $editing ? route('job-offers.update', $offer->id) : route('job-offers.store') }}', 'save-offer');
        });
        $('#cancel-offer').on('click', function () { recruitClose('{{ route('candidates.show', $candidate->id) }}'); });
        init(RIGHT_MODAL);
    });
</script>
