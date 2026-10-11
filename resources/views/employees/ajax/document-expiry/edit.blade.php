<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('app.edit') @lang('modules.employees.employeeDocumentExpiry')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">×</span>
    </button>
</div>

<x-form id="update-document-expiry-form" method="POST" class="ajax-form">
    <div class="modal-body">
        <input type="hidden" name="_method" value="PUT">

        
        <div class="row">
            <div class="col-md-6">
                <x-forms.text 
                    :fieldLabel="__('modules.employees.documentName')" 
                    fieldName="document_name"
                    fieldRequired="true" 
                    fieldId="document_name"
                    :fieldPlaceholder="__('modules.employees.documentName')"
                    :fieldValue="$document->document_name"
                />
            </div>
            <div class="col-md-6">
                <x-forms.text 
                    :fieldLabel="__('modules.employees.documentNumber')" 
                    fieldName="document_number"
                    fieldRequired="false" 
                    fieldId="document_number"
                    :fieldPlaceholder="__('modules.employees.documentNumber')"
                    :fieldValue="$document->document_number"
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
                    :fieldValue="$document->issue_date->format('Y-m-d')"
                />
            </div>
            <div class="col-md-6">
                <x-forms.datepicker 
                    :fieldLabel="__('modules.employees.expiryDate')" 
                    fieldName="expiry_date"
                    fieldRequired="true" 
                    fieldId="expiry_date"
                    :fieldPlaceholder="__('modules.employees.expiryDate')"
                    :fieldValue="$document->expiry_date->format('Y-m-d')"
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
                    :fieldValue="$document->alert_before_days"
                />
            </div>
            <div class="col-md-6">
                <x-forms.select 
                    :fieldLabel="__('modules.employees.alertEnabled')" 
                    fieldName="alert_enabled"
                    fieldRequired="true" 
                    fieldId="alert_enabled"
                >
                    <option value="1" {{ $document->alert_enabled ? 'selected' : '' }}>@lang('app.yes')</option>
                    <option value="0" {{ !$document->alert_enabled ? 'selected' : '' }}>@lang('app.no')</option>
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
                    allowedFileExtensions="pdf doc docx xls xlsx txt rtf png jpg jpeg gif svg"
                    :popover="__('messages.fileFormat.multipleImageFile')"
                />
                @if($document->filename)
                    <div class="mt-2">
                        <small class="text-muted">
                            @lang('modules.employees.currentFile'): {{ $document->filename }}
                        </small>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <x-forms.button-cancel id="cancel-document-expiry-edit" class="border-0 mr-3">
            @lang('app.cancel')
        </x-forms.button-cancel>
        <x-forms.button-primary id="update-document-expiry" icon="check">
            @lang('app.update')
        </x-forms.button-primary>
    </div>
</x-form>

<script>
    $(document).ready(function() {
        // Initialize date pickers with restrictions
        var issueDate = new Date('{{ $document->issue_date->format('Y-m-d') }}');
        var expiryDate = new Date('{{ $document->expiry_date->format('Y-m-d') }}');
        var today = new Date();
        
        // For edit, we need to be more flexible with date restrictions
        datepicker('#issue_date', {
            position: 'bl',
            dateSelected: issueDate,
            ...datepickerConfig
        });

        datepicker('#expiry_date', {
            position: 'bl',
            minDate: issueDate, // Expiry date should be after issue date
            dateSelected: expiryDate,
            ...datepickerConfig
        });

        // Initialize form
        init(MODAL_XL);
    });
</script>
