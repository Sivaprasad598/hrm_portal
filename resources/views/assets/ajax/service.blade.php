@php
    $fmt = company()->date_format;
    $open = $asset->openService;
@endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="asset-service-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Log Service / Repair: {{ $asset->name }}</h4>

                <div class="row p-20">
                    @if ($open)
                        <div class="col-12">
                            <div class="alert alert-warning f-13 mb-0">
                                A {{ strtolower($open->kindLabel()) }} sent on {{ $open->sent_on->format($fmt) }} is still open.
                                You can still log a finished job here; to close the open one use
                                <a href="{{ route('assets.service-complete-form', $open->id) }}" class="openRightModal font-weight-600">Complete it</a>.
                            </div>
                        </div>
                    @endif

                    <div class="col-md-4">
                        <x-forms.select fieldId="service_kind" fieldLabel="Type" fieldName="kind" :fieldRequired="true">
                            @foreach (\App\Models\AssetService::KINDS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-4">
                        <x-forms.text fieldId="service_vendor" fieldLabel="Vendor / Service Centre" fieldName="vendor" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.text fieldId="service_reference" fieldLabel="Job / Ticket No." fieldName="reference" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="service_sent_on" fieldLabel="Sent / Started On" fieldName="sent_on" :fieldRequired="true"
                            :fieldValue="now(company()->timezone)->format($fmt)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="service_completed_on" fieldLabel="Completed On" fieldName="completed_on"
                            :fieldPlaceholder="__('placeholders.date')" />
                        <small class="text-dark-grey">Leave empty if it is still with the vendor.</small>
                    </div>
                    <div class="col-md-4">
                        <x-forms.number fieldId="service_cost" fieldLabel="Cost" fieldName="cost" />
                    </div>
                    <div class="col-md-4 next-service">
                        <x-forms.datepicker fieldId="service_next_on" fieldLabel="Next Service Due" fieldName="next_service_on"
                            :fieldPlaceholder="__('placeholders.date')" />
                        <small class="text-dark-grey">
                            @if ($asset->service_interval_months)
                                Empty = completed date + {{ $asset->service_interval_months }} month(s).
                            @else
                                Set "Service every" on the asset to work this out automatically.
                            @endif
                        </small>
                    </div>
                    <div class="col-md-8">
                        <x-forms.file fieldId="service_bill" fieldLabel="Bill / Job Sheet" fieldName="bill" fieldHeight="90"
                            allowedFileExtensions="pdf png jpg jpeg webp" />
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="service_notes" fieldLabel="Notes" fieldName="notes"
                            fieldPlaceholder="What was done, parts replaced, the fault found" />
                    </div>
                    <div class="col-12">
                        <p class="f-12 text-dark-grey mb-0"><i class="fa fa-info-circle mr-1"></i>
                            An open repair on an asset in stock marks it Under Repair until it is completed.</p>
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-service" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-service">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        ['#service_sent_on', '#service_completed_on', '#service_next_on'].forEach(function (id) {
            datepicker(id, {position: 'bl', ...datepickerConfig});
        });

        $('#save-service').on('click', function () {
            assetPanelSubmit('asset-service-form', '{{ route('assets.service', $asset->id) }}', 'save-service');
        });
        $('#cancel-service').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
