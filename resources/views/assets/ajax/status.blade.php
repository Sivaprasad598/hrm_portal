<div class="row">
    <div class="col-sm-12">
        <x-form id="status-asset-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Change Status: {{ $asset->name }}</h4>

                <div class="row p-20">
                    <div class="col-12">
                        <p class="f-14 mb-0">Now: {!! $asset->statusBadge() !!}</p>
                    </div>

                    @if ($choices->isEmpty())
                        <div class="col-12">
                            <p class="f-14 text-dark-grey mt-3 mb-0">There is no status this asset can be moved to by hand.</p>
                        </div>
                    @else
                        <div class="col-md-6">
                            <x-forms.select fieldId="new_status" fieldLabel="New Status" fieldName="status" :fieldRequired="true">
                                @foreach ($choices as $status)
                                    <option value="{{ $status }}">{{ $status === 'available' && $asset->status === 'repair' ? 'Available (repaired)' : \App\Models\Asset::STATUSES[$status][0] }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        {{-- Into repair: who has it. Out of repair: when it came back and what it cost. --}}
                        <div class="col-md-6 st-into-repair d-none">
                            <x-forms.text fieldId="st_vendor" fieldLabel="Repair Vendor" fieldName="vendor" />
                        </div>
                        <div class="col-md-6 st-into-repair d-none">
                            <x-forms.datepicker fieldId="st_sent_on" fieldLabel="Sent On" fieldName="sent_on"
                                :fieldValue="now(company()->timezone)->format(company()->date_format)" :fieldPlaceholder="__('placeholders.date')" />
                        </div>
                        @if ($asset->status === 'repair')
                            <div class="col-md-6 st-out-repair">
                                <x-forms.datepicker fieldId="st_completed_on" fieldLabel="Repair Completed On" fieldName="completed_on"
                                    :fieldValue="now(company()->timezone)->format(company()->date_format)" :fieldPlaceholder="__('placeholders.date')" />
                            </div>
                            <div class="col-md-6 st-out-repair">
                                <x-forms.number fieldId="st_cost" fieldLabel="Repair Cost" fieldName="cost" />
                            </div>
                        @endif
                        @if ($asset->status === 'assigned')
                            <div class="col-12">
                                <p class="f-13 text-danger mt-3 mb-0"><i class="fa fa-exclamation-triangle mr-1"></i>
                                    The asset is with an employee: marking it lost closes their hand-over as lost.
                                    To record an amount to recover from them, use Take Return, "Not returned, lost" instead.</p>
                            </div>
                        @endif
                        <div class="col-12">
                            <x-forms.textarea fieldId="status_notes" fieldLabel="Notes" fieldName="notes"
                                fieldPlaceholder="Repair vendor and cost, how it was lost, disposal details" />
                        </div>
                    @endif
                </div>

                <x-form-actions>
                    @if ($choices->isNotEmpty())
                        <x-forms.button-primary id="save-status" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    @endif
                    <x-forms.button-secondary id="cancel-status">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        if ($('#st_sent_on').length) { datepicker('#st_sent_on', {position: 'bl', ...datepickerConfig}); }
        if ($('#st_completed_on').length) { datepicker('#st_completed_on', {position: 'bl', ...datepickerConfig}); }

        function toggleRepairFields() {
            var to = $('#new_status').val();
            $('.st-into-repair').toggleClass('d-none', to !== 'repair');
            $('.st-out-repair').toggleClass('d-none', to !== 'available');
        }
        $('#new_status').on('change', toggleRepairFields);
        toggleRepairFields();

        $('#save-status').on('click', function () {
            assetPanelSubmit('status-asset-form', '{{ route('assets.status', $asset->id) }}', 'save-status');
        });
        $('#cancel-status').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
