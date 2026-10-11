<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('modules.payslipSettings.addNewBranch')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>

<x-form id="save-payslip-branch-form">
    <div class="modal-body">
        <div class="row">
            <div class="col-md-12">
                <x-forms.text fieldId="branch_name" :fieldLabel="__('app.branch')" fieldName="branch_name"
                              fieldRequired="true" :fieldPlaceholder="__('placeholders.branch')"
                              pattern="[A-Za-z\s]+" title="{{ __('messages.branchAlpha') }}">
                </x-forms.text>
            </div>

            <div class="col-md-12">
                <x-forms.textarea fieldId="address" :fieldLabel="__('modules.payslipSettings.branchAddress')"
                                  fieldName="address" :fieldRequired="true"
                                  :fieldPlaceholder="__('modules.payslipSettings.branchAddressPlaceholder')"
                                  :fieldHelp="__('modules.payslipSettings.branchAddressHelp')">
                </x-forms.textarea>
            </div>

            <div class="col-md-12 mt-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="f-14 text-dark-grey mb-0">@lang('modules.payslipSettings.branchEmail')
                        <span class="text-danger">*</span></label>
                    <button type="button" class="btn btn-secondary btn-sm f-12" id="addEmailField">
                        <i class="fa fa-plus mr-1"></i> @lang('modules.payslipSettings.addEmail')
                    </button>
                </div>
                <div id="emailFields"></div>
                <small class="form-text text-muted">@lang('modules.payslipSettings.branchEmailHelp')</small>
            </div>

            <div class="col-md-12 mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="f-14 text-dark-grey mb-0">@lang('modules.payslipSettings.branchPhone')</label>
                    <button type="button" class="btn btn-secondary btn-sm f-12" id="addPhoneField">
                        <i class="fa fa-plus mr-1"></i> @lang('modules.payslipSettings.addPhone')
                    </button>
                </div>
                <div id="phoneFields"></div>
                <small class="form-text text-muted">@lang('modules.payslipSettings.branchPhoneHelp')</small>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
        <x-forms.button-primary id="save-payslip-branch" icon="check">@lang('app.save')</x-forms.button-primary>
    </div>
</x-form>

<script>
    function addBranchEmailRow(value) {
        var row = $('<div class="input-group mb-2"></div>');
        var input = $('<input>', {
            type: 'email',
            class: 'form-control height-35 f-14',
            name: 'branch_email[]',
            placeholder: 'example@gmail.com',
            title: "@lang('messages.branchEmailInvalid')"
        }).val(value || '');
        var removeWrap = $('<div class="input-group-append"></div>');
        var removeBtn = $('<button type="button" class="btn btn-secondary remove-email"><i class="fa fa-times"></i></button>');

        input.on('input', function () {
            this.value = this.value.trim();
        });

        removeWrap.append(removeBtn);
        row.append(input).append(removeWrap);
        $('#emailFields').append(row);
    }

    function addBranchPhoneRow(value) {
        var row = $('<div class="input-group mb-2"></div>');
        var prepend = $('<div class="input-group-prepend"><span class="input-group-text">+91</span></div>');
        var digits = (value || '').replace(/\D/g, '').slice(-10);
        var input = $('<input>', {
            type: 'text',
            class: 'form-control height-35 f-14',
            name: 'branch_phone[]',
            placeholder: '9876543210',
            maxlength: 10,
            inputmode: 'numeric',
            autocomplete: 'off',
            title: "@lang('messages.branchPhoneDigits')"
        }).val(digits);
        var removeWrap = $('<div class="input-group-append"></div>');
        var removeBtn = $('<button type="button" class="btn btn-secondary remove-phone"><i class="fa fa-times"></i></button>');

        input.on('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });

        removeWrap.append(removeBtn);
        row.append(prepend).append(input).append(removeWrap);
        $('#phoneFields').append(row);
    }

    $(document).ready(function () {
        addBranchEmailRow();
        addBranchPhoneRow();

        $('#addEmailField').click(function () {
            addBranchEmailRow();
        });

        $('#addPhoneField').click(function () {
            addBranchPhoneRow();
        });

        $('#emailFields').on('click', '.remove-email', function () {
            if ($('#emailFields .input-group').length <= 1) {
                toastr.error("@lang('modules.payslipSettings.branchEmailRequiredMinOne')");
                return;
            }
            $(this).closest('.input-group').remove();
        });

        $('#phoneFields').on('click', '.remove-phone', function () {
            $(this).closest('.input-group').remove();
        });

        $('#branch_name').on('input', function () {
            this.value = this.value.replace(/[^A-Za-z\s]/g, '');
        });

        $('#save-payslip-branch').click(function () {
            $.easyAjax({
                url: "{{ route('payslip-settings.store') }}",
                container: '#save-payslip-branch-form',
                type: "POST",
                disableButton: true,
                blockUI: true,
                buttonSelector: "#save-payslip-branch",
                data: $('#save-payslip-branch-form').serialize(),
                success: function (response) {
                    if (response.status === 'success') {
                        window.location.reload();
                    }
                }
            });
        });

        init('#save-payslip-branch-form');
    });
</script>
