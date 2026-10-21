@php
    $editing = $asset->exists;
    $dateFormat = company()->date_format;
    $types = \App\Models\AssetType::orderBy('name')->get();
    $branches = \App\Models\Branch::orderBy('branch_name')->get();
@endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="asset-form" method="POST">
            @if ($editing)
                <input type="hidden" name="_method" value="PUT">
            @endif
            <input type="hidden" name="image_delete" id="image_delete" value="no">

            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal text-capitalize border-bottom-grey">
                    {{ $editing ? 'Edit Asset' : 'Add Asset' }}
                </h4>

                <div class="row p-20">
                    <div class="col-lg-8">
                        <div class="row">
                            <div class="col-md-6">
                                <x-forms.text fieldId="asset_name" fieldLabel="Asset Name" fieldName="name" :fieldRequired="true"
                                    fieldPlaceholder="e.g. Dell Latitude 5420" :fieldValue="$asset->name" />
                            </div>
                            <div class="col-md-6">
                                <x-forms.text fieldId="asset_code" fieldLabel="Asset Code / Tag" fieldName="asset_code" :fieldRequired="true"
                                    :fieldValue="$asset->asset_code" fieldHelp="Unique; printed on the asset label." />
                            </div>
                            <div class="col-md-6">
                                <x-forms.select fieldId="asset_type_id" fieldLabel="Asset Type" fieldName="asset_type_id" search="true">
                                    <option value="">--</option>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->id }}" @selected($asset->asset_type_id == $type->id)>{{ $type->name }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                            <div class="col-md-6">
                                <x-forms.text fieldId="serial_number" fieldLabel="Serial Number" fieldName="serial_number"
                                    :fieldValue="$asset->serial_number" />
                            </div>
                            <div class="col-md-6">
                                <x-forms.text fieldId="brand" fieldLabel="Brand" fieldName="brand" :fieldValue="$asset->brand" />
                            </div>
                            <div class="col-md-6">
                                <x-forms.text fieldId="model" fieldLabel="Model" fieldName="model" :fieldValue="$asset->model" />
                            </div>
                            <div class="col-md-6">
                                <x-forms.select fieldId="branch_id" fieldLabel="Branch / Location" fieldName="branch_id">
                                    <option value="">--</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected($asset->branch_id == $branch->id)>{{ $branch->branch_name }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                            <div class="col-md-6">
                                <x-forms.select fieldId="condition" fieldLabel="Condition" fieldName="condition" :fieldRequired="true">
                                    @foreach (\App\Models\Asset::CONDITIONS as $key => $label)
                                        <option value="{{ $key }}" @selected($asset->condition === $key)>{{ $label }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <x-forms.file allowedFileExtensions="png jpg jpeg webp" fieldLabel="Photo" fieldName="image" fieldId="asset_image"
                            :fieldValue="$asset->image_url ?? ''" fieldHeight="190" />
                    </div>

                    <div class="col-12">
                        <h5 class="f-14 font-weight-600 text-dark-grey mt-4 mb-0 border-top-grey pt-3">Purchase &amp; Warranty</h5>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <x-forms.datepicker fieldId="purchase_date" fieldLabel="Purchase Date" fieldName="purchase_date"
                            :fieldValue="$asset->purchase_date?->format($dateFormat)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <x-forms.number fieldId="purchase_cost" fieldLabel="Purchase Cost" fieldName="purchase_cost"
                            :fieldValue="$asset->purchase_cost" />
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <x-forms.text fieldId="vendor" fieldLabel="Vendor" fieldName="vendor" :fieldValue="$asset->vendor" />
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <x-forms.datepicker fieldId="warranty_until" fieldLabel="Warranty Until" fieldName="warranty_until"
                            :fieldValue="$asset->warranty_until?->format($dateFormat)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-12">
                        <h5 class="f-14 font-weight-600 text-dark-grey mt-4 mb-0 border-top-grey pt-3">Servicing</h5>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <x-forms.number fieldId="service_interval_months" fieldLabel="Service Every (months)" fieldName="service_interval_months"
                            :fieldValue="$asset->service_interval_months" fieldHelp="e.g. 3 for an AC, 6 for a vehicle. Empty = no regular service." />
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <x-forms.datepicker fieldId="next_service_on" fieldLabel="Next Service Due" fieldName="next_service_on"
                            :fieldValue="$asset->next_service_on?->format($dateFormat)" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-lg-6 col-md-12 d-flex align-items-end">
                        <p class="f-12 text-dark-grey mb-2">Each completed service moves the next date forward by the interval.
                            AMC, insurance, RC / PUC and licences are added from the asset's page (Renewals).</p>
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="asset_notes" fieldLabel="Notes" fieldName="notes" :fieldValue="$asset->notes"
                            fieldPlaceholder="Accessories included, configuration, anything worth knowing" />
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-asset" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-asset">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        ['#purchase_date', '#warranty_until', '#next_service_on'].forEach(function (id) {
            datepicker(id, {position: 'bl', ...datepickerConfig});
        });

        $('#asset_image').on('dropify.afterClear', function () {
            $('#image_delete').val('yes');
        });

        $('#save-asset').on('click', function () {
            assetPanelSubmit('asset-form', '{{ $editing ? route('assets.update', $asset->id) : route('assets.store') }}', 'save-asset');
        });

        $('#cancel-asset').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
