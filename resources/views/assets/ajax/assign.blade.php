@php $today = now(company()->timezone)->format(company()->date_format); @endphp

<div class="row">
    <div class="col-sm-12">
        <x-form id="assign-asset-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Assign Asset</h4>

                <div class="row p-20">
                    <div class="col-md-6">
                        @if ($asset)
                            <x-forms.label fieldId="asset_label" fieldLabel="Asset" class="mt-3" />
                            <p class="f-14 mb-0 mt-2"><strong>{{ $asset->name }}</strong> <span class="text-dark-grey">({{ $asset->asset_code }})</span></p>
                            <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                        @else
                            <x-forms.select fieldId="assign_asset_id" fieldLabel="Asset" fieldName="asset_id" :fieldRequired="true" search="true">
                                <option value="">-- Choose an available asset --</option>
                                @foreach ($availableAssets as $a)
                                    <option value="{{ $a->id }}" data-condition="{{ $a->condition }}">{{ $a->name }} ({{ $a->asset_code }}){{ $a->type ? ' · ' . $a->type->name : '' }}</option>
                                @endforeach
                            </x-forms.select>
                            @if ($availableAssets->isEmpty())
                                <small class="text-danger">No asset is available. Add one under Assets → Asset List.</small>
                            @endif
                        @endif
                    </div>
                    <div class="col-md-6">
                        <x-forms.select fieldId="assign_user_id" fieldLabel="Employee" fieldName="user_id" :fieldRequired="true" search="true">
                            <option value="">-- Choose the employee --</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected($employeeId == $e->id)>{{ $e->name }}{{ $e->employee_id ? ' (' . $e->employee_id . ')' : '' }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="assigned_on" fieldLabel="Assigned On" fieldName="assigned_on" :fieldRequired="true"
                            :fieldValue="$today" :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.datepicker fieldId="expected_return_on" fieldLabel="Expected Return (optional)" fieldName="expected_return_on"
                            :fieldPlaceholder="__('placeholders.date')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.select fieldId="assign_condition" fieldLabel="Condition at Hand-over" fieldName="condition" :fieldRequired="true">
                            @foreach (\App\Models\Asset::CONDITIONS as $key => $label)
                                <option value="{{ $key }}" @selected(($asset->condition ?? 'good') === $key)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-12">
                        <x-forms.textarea fieldId="assign_notes" fieldLabel="Notes" fieldName="notes"
                            fieldPlaceholder="Accessories handed over (charger, bag, case), purpose, anything to remember" />
                    </div>
                    <div class="col-12">
                        <p class="f-12 text-dark-grey mb-0 mt-2"><i class="fa fa-info-circle mr-1"></i>The employee is notified by email and in the app.</p>
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-assign" class="mr-3" icon="check">Assign</x-forms.button-primary>
                    <x-forms.button-secondary id="cancel-assign">@lang('app.cancel')</x-forms.button-secondary>
                </x-form-actions>
            </div>
        </x-form>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        datepicker('#assigned_on', {position: 'bl', ...datepickerConfig});
        datepicker('#expected_return_on', {position: 'bl', ...datepickerConfig});

        $('#assign_asset_id').on('change', function () {
            var condition = $(this).find(':selected').data('condition');
            if (condition) {
                $('#assign_condition').val(condition).selectpicker('refresh');
            }
        });

        $('#save-assign').on('click', function () {
            assetPanelSubmit('assign-asset-form', '{{ route('assets.assign') }}', 'save-assign');
        });
        $('#cancel-assign').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
