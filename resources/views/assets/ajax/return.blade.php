@php
    $fmt = company()->date_format;
    $asset = $assignment->asset;
@endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="return-asset-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Take Return</h4>

                <div class="row p-20">
                    <div class="col-12">
                        <div class="p-3 rounded d-flex justify-content-between flex-wrap" style="background:#f8f9fa">
                            <div class="mr-3">
                                <p class="f-12 text-dark-grey mb-1">Asset</p>
                                <p class="f-14 mb-0"><strong>{{ $asset->name }}</strong> ({{ $asset->asset_code }})</p>
                            </div>
                            <div class="mr-3">
                                <p class="f-12 text-dark-grey mb-1">Held by</p>
                                <p class="f-14 mb-0"><strong>{{ $assignment->user?->name }}</strong></p>
                            </div>
                            <div>
                                <p class="f-12 text-dark-grey mb-1">Since</p>
                                <p class="f-14 mb-0">{{ $assignment->assigned_on->format($fmt) }}
                                    · handed over {{ strtolower(\App\Models\Asset::CONDITIONS[$assignment->condition_out] ?? (string) $assignment->condition_out) }}</p>
                            </div>
                        </div>
                        @if ($assignment->assign_notes)
                            <p class="f-12 text-dark-grey mt-2 mb-0">Hand-over notes: {{ $assignment->assign_notes }}</p>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="returned_on" fieldLabel="Returned On" fieldName="returned_on" :fieldRequired="true"
                            :fieldValue="now(company()->timezone)->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.select fieldId="back_to" fieldLabel="Outcome" fieldName="back_to" :fieldRequired="true">
                            <option value="available">Returned, back in stock</option>
                            <option value="repair">Returned, needs repair</option>
                            <option value="lost">Not returned, lost</option>
                        </x-forms.select>
                    </div>
                    <div class="col-md-4 return-condition">
                        <x-forms.select fieldId="return_condition" fieldLabel="Condition on Return" fieldName="condition" :fieldRequired="true">
                            @foreach (\App\Models\Asset::CONDITIONS as $key => $label)
                                <option value="{{ $key }}" @selected($assignment->condition_out === $key)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-4 recovery d-none">
                        <x-forms.number fieldId="recovery_amount" fieldLabel="Amount to Recover" fieldName="recovery_amount"
                            fieldHelp="From the employee, for the loss or damage. Leave empty for none." />
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="return_notes" fieldLabel="Notes" fieldName="notes"
                            fieldPlaceholder="What came back, damage seen, accessories missing" />
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-return" class="mr-3" icon="check">Save Return</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-return">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        datepicker('#returned_on', {position: 'bl', ...datepickerConfig});

        function toggleFields() {
            var outcome = $('#back_to').val();
            var damaged = $('#return_condition').val() === 'damaged';
            $('.return-condition').toggleClass('d-none', outcome === 'lost');
            $('.recovery').toggleClass('d-none', !(outcome === 'lost' || outcome === 'repair' || damaged));
        }

        $('#back_to, #return_condition').on('change', toggleFields);
        toggleFields();

        $('#save-return').on('click', function () {
            assetPanelSubmit('return-asset-form', '{{ route('assets.return', $assignment->id) }}', 'save-return');
        });
        $('#cancel-return').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
