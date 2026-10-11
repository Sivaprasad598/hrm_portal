<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('modules.employees.addDocumentExpiry')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">×</span>
    </button>
</div>

<x-form id="save-document-expiry-form" method="POST" class="ajax-form">
    <div class="modal-body">
        <input type="hidden" name="user_id" value="{{ $user->id }}">
        
        <div class="row">
            <div class="col-md-6">
                <x-forms.text 
                    :fieldLabel="__('modules.employees.documentName')" 
                    fieldName="document_name"
                    fieldRequired="true" 
                    fieldId="document_name"
                    :fieldPlaceholder="__('modules.employees.documentName')"
                />
            </div>
            <div class="col-md-6">
                <x-forms.text 
                    :fieldLabel="__('modules.employees.documentNumber')" 
                    fieldName="document_number"
                    fieldRequired="false" 
                    fieldId="document_number"
                    :fieldPlaceholder="__('modules.employees.documentNumber')"
                />
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <x-forms.datepicker 
                    :fieldLabel="__('modules.employees.issueDate')" 
                    fieldName="issue_date"
                    fieldRequired="true" 
                    fieldId="issue_date"
                    :fieldPlaceholder="__('modules.employees.issueDate')"
                    :fieldValue="now(company()->timezone)->translatedFormat(company()->date_format)"
                />
            </div>
            <div class="col-md-6">
                <x-forms.datepicker 
                    :fieldLabel="__('modules.employees.expiryDate')" 
                    fieldName="expiry_date"
                    fieldRequired="true" 
                    fieldId="expiry_date"
                    :fieldPlaceholder="__('modules.employees.expiryDate')"
                    :fieldValue="now(company()->timezone)->addDays(30)->translatedFormat(company()->date_format)"
                />
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <x-forms.number 
                    :fieldLabel="__('modules.employees.alertBefore')" 
                    fieldName="alert_before_days"
                    fieldRequired="true" 
                    fieldId="alert_before_days"
                    :fieldPlaceholder="__('modules.employees.days')"
                    fieldValue="30"
                />
            </div>
            <div class="col-md-6">
                <x-forms.select 
                    :fieldLabel="__('modules.employees.alertEnabled')" 
                    fieldName="alert_enabled"
                    fieldRequired="true" 
                    fieldId="alert_enabled"
                >
                    <option value="1">@lang('app.yes')</option>
                    <option value="0">@lang('app.no')</option>
                </x-forms.select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <x-forms.file 
                    :fieldLabel="__('modules.projects.uploadFile')" 
                    fieldName="file"
                    fieldRequired="false" 
                    fieldId="document_file"
                    {{-- allowedFileExtensions="pdf doc docx xls xlsx txt rtf png jpg jpeg gif svg" --}}
                    :popover="__('messages.fileFormat.multipleImageFile')"
                />
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <x-forms.button-cancel id="cancel-document-expiry" class="border-0 mr-3">
            @lang('app.cancel')
        </x-forms.button-cancel>
        <x-forms.button-primary id="submit-document-expiry" icon="check">
            @lang('app.submit')
        </x-forms.button-primary>
    </div>
</x-form>

<script>
    $(document).ready(function() {
        // Initialize date pickers with restrictions
        datepicker('#issue_date', {
            position: 'bl',
            maxDate: new Date(), // Issue date cannot be in the future
            dateSelected: new Date(),
            ...datepickerConfig
        });

        datepicker('#expiry_date', {
            position: 'bl',
            minDate: new Date(), // Expiry date cannot be in the past
            dateSelected: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // Default to 1 month from now
            ...datepickerConfig
        });

        // Initialize form
        init(MODAL_XL);
    });
</script>
