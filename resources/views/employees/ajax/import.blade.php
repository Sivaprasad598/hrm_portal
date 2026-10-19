<div class="row" id="import_table">
    <div class="col-sm-12">
        <x-form id="import-employee-data-form">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal  border-bottom-grey">
                    @lang('app.importEmployee')</h4>
                <div class="col-sm-12 pt-2">
                    <div class="alert alert-warning mb-0" role="alert">
                        Upload the HR master sheet (<strong>Active Employees</strong> layout). Dates can be Excel dates or
                        dd/mm/yyyy. Departments, parent departments, designations and branches that do not exist yet are
                        created. <strong>Nothing is saved on upload</strong>: every row is shown for review first.
                    </div>
                </div>
                <div class="row py-20">
                    <div class="col-md-12">
                        <x-forms.link-secondary :link="route('employees.import.sample')" icon="download">@lang('app.downloadSampleImport')</x-forms.link-secondary>

                        <x-forms.file :fieldLabel="__('modules.import.file') . ' (xlsx, xls)'" fieldName="import_file"
                                      fieldId="employee_import"/>
                    </div>
                </div>
                <x-form-actions>
                    <x-forms.button-primary id="import-employee-form" class="mr-3"
                                            icon="arrow-right">@lang('app.uploadNext')
                    </x-forms.button-primary>
                    <x-forms.button-cancel :link="route('employees.index')" class="border-0">@lang('app.back')
                    </x-forms.button-cancel>
                </x-form-actions>
            </div>
        </x-form>

    </div>
</div>

<script>

    $(document).ready(function () {

        $("#employee_import").dropify({
            messages: dropifyMessages
        });

        $('body').off('click', '#import-employee-form').on('click', '#import-employee-form', function () {
            $.easyAjax({
                url: "{{ route('employees.import.review') }}",
                container: '#import-employee-data-form',
                type: "POST",
                disableButton: true,
                blockUI: true,
                buttonSelector: "#import-employee-form",
                file: true,
                data: $('#import-employee-data-form').serialize(),
                success: function (response) {
                    if (response.status == 'success') {
                        $('#import_table').html(response.view);
                    }
                }
            });
        });
    });
</script>
