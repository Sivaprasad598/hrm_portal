<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4 ">
    @method('POST')
    <div class="row">
        <div class="col-lg-12">
            <x-forms.checkbox :checked="($invoiceSetting->show_estimate_item_selection_column ?? 'yes') === 'yes'"
                              :fieldLabel="__('modules.invoiceSettings.showEstimateItemSelectionColumn')"
                              fieldName="show_estimate_item_selection_column"
                              fieldId="show_estimate_item_selection_column"
                              :popover="__('modules.invoiceSettings.showEstimateItemSelectionColumnInfo')"/>
        </div>
    </div>
</div>

<!-- Buttons Start -->
<div class="w-100 border-top-grey">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-form" class="mr-3" icon="check">@lang('app.save')
        </x-forms.button-primary>
    </x-setting-form-actions>
</div>
<!-- Buttons End -->

<script>
    $('#save-form').click(function () {
        $.easyAjax({
            url: "{{ route('invoice_settings.update_estimate_setting', $invoiceSetting->id) }}",
            container: '#editSettings',
            type: "POST",
            redirect: true,
            file: true,
            data: $('#editSettings').serialize(),
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-form",
        });
    });
</script>
