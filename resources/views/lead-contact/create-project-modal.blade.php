<x-form id="addLeadProject" method="POST" class="ajax-form">
    <div class="modal-header">
        <h5 class="modal-title" id="modelHeading">Add Project Name</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                aria-hidden="true">×</span></button>
    </div>
    <div class="modal-body">
        <div class="portlet-body">
            <div class="form-body">
                <div class="row">
                    <div class="col-lg-12">
                        <x-forms.text fieldId="lead_project_name" fieldLabel="Project Name" fieldName="name"
                            fieldPlaceholder="Enter the project name" fieldRequired="true">
                        </x-forms.text>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
        <x-forms.button-primary id="save-lead-project" icon="check">@lang('app.add')</x-forms.button-primary>
    </div>
</x-form>

<script>
    $('#addLeadProject').on('submit', function (e) {
        e.preventDefault();
        $('#save-lead-project').trigger('click');
    });

    $('#save-lead-project').click(function () {
        $.easyAjax({
            url: "{{ route('lead-projects.store') }}",
            container: '#addLeadProject',
            type: "POST",
            blockUI: true,
            disableButton: true,
            buttonSelector: "#save-lead-project",
            data: $('#addLeadProject').serialize(),
            success: function (response) {
                if (response.status == "success") {
                    // Fill the lead form's Project Name list and select the new project
                    $('#lead_project_id').html(response.data).selectpicker('refresh');
                    $(MODAL_LG).modal('hide');
                }
            }
        })
    });
</script>
