<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('modules.branch.addTitle')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">

    <x-form id="save-branch-data-form">
        <div class="add-client bg-white rounded">

            <div class="row p-20">
                <div class="col-md-12">
                    <x-forms.text fieldId="branch_name" :fieldLabel="__('app.branch')" fieldName="branch_name"
                        fieldRequired="true" :fieldPlaceholder="__('placeholders.branch')"
                        pattern="[A-Za-z\s]+" title="{{ __('messages.branchAlpha') }}">
                    </x-forms.text>
                </div>
            </div>


        </div>
    </x-form>


</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-branch-form" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $(document).ready(function() {
        $(".select-picker").selectpicker();
    });

    $('#branch_name').on('input', function() {
        this.value = this.value.replace(/[^A-Za-z\s]/g, '');
    });

    $('#save-branch-form').click(function() {
        var url = "{{ route('branches.store') }}";
        $.easyAjax({
            url: url,
            container: '#save-branch-data-form',
            type: "POST",
            data: $('#save-branch-data-form').serialize(),
            disableButton: true,
            blockUI: true,
            success: function(response) {
                if (response.status == 'success') {
                    var options = [];
                    var rData = [];
                    rData = response.branches;

                    $.each(rData, function(index, value) {
                        var selectData = '<option value="">--</option>';
                        selectData = '<option value="' + value.id + '">' + value.branch_name + '</option>';
                        options.push(selectData);
                    });

                    $('#employee_branch').html(options);
                    $('#employee_branch').selectpicker('refresh');
                    $(MODAL_LG).modal('hide');

                }
            }
        })
    });
</script>
