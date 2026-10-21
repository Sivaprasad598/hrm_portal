@php $fmt = company()->date_format; @endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="asset-service-complete-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Complete {{ $service->kindLabel() }}: {{ $asset->name }}</h4>

                <div class="row p-20">
                    <div class="col-12">
                        <div class="p-3 rounded f-13" style="background:#f8f9fa">
                            Sent on <strong>{{ $service->sent_on->format($fmt) }}</strong>
                            @if ($service->vendor) to <strong>{{ $service->vendor }}</strong>@endif
                            @if ($service->reference) · Job {{ $service->reference }}@endif
                            @if ($service->notes)<br><span class="text-dark-grey">{{ $service->notes }}</span>@endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="complete_on" fieldLabel="Completed On" fieldName="completed_on" :fieldRequired="true"
                            :fieldValue="now(company()->timezone)->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.number fieldId="complete_cost" fieldLabel="Cost" fieldName="cost" :fieldValue="$service->cost" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="complete_next_on" fieldLabel="Next Service Due" fieldName="next_service_on"
                            :fieldPlaceholder="__('placeholders.date')" />
                        @if ($service->kind === 'service' && $asset->service_interval_months)
                            <small class="text-dark-grey">Empty = completed date + {{ $asset->service_interval_months }} month(s).</small>
                        @endif
                    </div>
                    <div class="col-md-8">
                        <x-forms.file fieldId="complete_bill" fieldLabel="Bill / Job Sheet" fieldName="bill" fieldHeight="90"
                            allowedFileExtensions="pdf png jpg jpeg webp" :fieldValue="$service->billUrl() ?? ''" />
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="complete_notes" fieldLabel="Notes" fieldName="notes"
                            fieldPlaceholder="What was done, parts replaced" />
                    </div>
                    @if ($service->kind === 'repair' && $asset->status === 'repair')
                        <div class="col-12">
                            <p class="f-12 text-dark-grey mb-0"><i class="fa fa-info-circle mr-1"></i>The asset goes back in stock (Available).</p>
                        </div>
                    @endif
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-complete" class="mr-3" icon="check">Complete</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-complete">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        datepicker('#complete_on', {position: 'bl', ...datepickerConfig});
        datepicker('#complete_next_on', {position: 'bl', ...datepickerConfig});

        $('#save-complete').on('click', function () {
            assetPanelSubmit('asset-service-complete-form', '{{ route('assets.service-complete', $service->id) }}', 'save-complete');
        });
        $('#cancel-complete').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
