@php $fmt = company()->date_format; @endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="asset-renewal-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">
                    {{ $previous ? 'Renew ' . $previous->kindLabel() : 'Add Renewal' }}: {{ $asset->name }}
                </h4>

                <div class="row p-20">
                    @if ($previous)
                        <input type="hidden" name="previous_id" value="{{ $previous->id }}">
                        <div class="col-12">
                            <div class="p-3 rounded f-13" style="background:#f8f9fa">
                                Current period: {{ $previous->starts_on?->format($fmt) ?? '--' }} to <strong>{{ $previous->expires_on->format($fmt) }}</strong>
                                @if ($previous->provider) · {{ $previous->provider }}@endif
                                @if ($previous->reference_no) · {{ $previous->reference_no }}@endif
                                @if ($previous->cost !== null) · cost {{ number_format((float) $previous->cost, 2) }}@endif
                                <br><span class="text-dark-grey">It stays in the history; the new period below becomes the current one.</span>
                            </div>
                        </div>
                    @else
                        <div class="col-md-4">
                            <x-forms.select fieldId="renewal_kind" fieldLabel="What" fieldName="kind" :fieldRequired="true">
                                @foreach (\App\Models\AssetRenewal::KINDS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                    @endif

                    <div class="col-md-4">
                        <x-forms.text fieldId="renewal_provider" fieldLabel="Provider / Insurer / Vendor" fieldName="provider"
                            :fieldValue="$previous?->provider" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.text fieldId="renewal_reference" fieldLabel="Policy / Contract / Licence No." fieldName="reference_no"
                            :fieldValue="$previous?->reference_no" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="renewal_starts_on" fieldLabel="Starts On" fieldName="starts_on"
                            :fieldValue="$suggestStart?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="renewal_expires_on" fieldLabel="Expires On" fieldName="expires_on" :fieldRequired="true"
                            :fieldValue="$suggestEnd?->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.number fieldId="renewal_cost" fieldLabel="Cost" fieldName="cost" />
                    </div>
                    <div class="col-md-8">
                        <x-forms.file fieldId="renewal_document" fieldLabel="Policy / Contract Document" fieldName="document" fieldHeight="90"
                            allowedFileExtensions="pdf png jpg jpeg webp" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.textarea fieldId="renewal_notes" fieldLabel="Notes" fieldName="notes" />
                    </div>
                    <div class="col-12">
                        <p class="f-12 text-dark-grey mb-0"><i class="fa fa-bell mr-1"></i>
                            HR gets a bell reminder 30 and 7 days before it expires, and when it has expired.</p>
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-renewal" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-renewal">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        datepicker('#renewal_starts_on', {position: 'bl', ...datepickerConfig});
        datepicker('#renewal_expires_on', {position: 'bl', ...datepickerConfig});

        $('#save-renewal').on('click', function () {
            assetPanelSubmit('asset-renewal-form', '{{ route('assets.renewal', $asset->id) }}', 'save-renewal');
        });
        $('#cancel-renewal').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
