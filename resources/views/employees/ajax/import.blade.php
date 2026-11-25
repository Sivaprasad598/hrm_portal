{{-- Import Employee, step 1 of 3: the HR master sheet is chosen here; nothing is saved until the review. --}}
<style>
    .ie-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .ie-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 20px 24px; border-bottom: 1px solid #f2f4f7; }
    .ie-head h4 { font-size: 20px; font-weight: 600; color: #101828; margin: 0; }
    .ie-head p { margin: 2px 0 0; font-size: 13px; color: #667085; }
    .ie-steps { display: flex; align-items: center; gap: 8px; list-style: none; margin: 0; padding: 0; }
    .ie-steps li { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #98a2b3; }
    .ie-steps li b { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; background: #f2f4f7; color: #667085; }
    .ie-steps li.on { color: #101828; font-weight: 600; }
    .ie-steps li.on b { background: var(--epic-blue, #cb6b4f); color: #fff; }
    .ie-steps li + li::before { content: ''; width: 22px; height: 1px; background: #d0d5dd; margin-right: 2px; }
    /* two rows, two columns: the upload box and the sample card share a top and a bottom,
       the note and the tips share the row under them */
    .ie-body { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 14px 20px; padding: 20px 24px; align-items: stretch; }
    @media (max-width: 991px) { .ie-body { grid-template-columns: 1fr; } .ie-body .ie-note { order: 2; } .ie-body .ie-tips { order: 4; } .ie-body .ie-sample { order: 3; } }
    .ie-drop { display: flex; flex-direction: column; min-height: 100%; }
    .ie-drop .form-group { margin: 0 !important; display: flex; flex-direction: column; flex: 1; }
    /* no label above the box (it pushed the box below the sample card): the box says what to drop */
    .ie-drop .form-group > label { display: none !important; }
    .ie-drop .dropify-wrapper .dropify-message p { font-size: 15px; color: #475467; }
    .ie-drop .dropify-wrapper .dropify-message p small { display: block; margin-top: 4px; font-size: 12.5px; color: #98a2b3; }
    .ie-drop .dropify-wrapper { flex: 1; height: auto !important; min-height: 220px; border: 2px dashed #d0d5dd; border-radius: 12px; background: #fcfcfd; }
    .ie-drop .dropify-wrapper:hover { border-color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 4%, #fff); }
    .ie-note { display: flex; gap: 8px; margin: 0; font-size: 12.5px; color: #475467; align-self: start; }
    .ie-note i { color: #12b76a; margin-top: 2px; }
    .ie-sample { display: flex; flex-direction: column; justify-content: center; border: 1px solid #eaecf0; border-radius: 12px; padding: 18px; background: linear-gradient(180deg, color-mix(in srgb, var(--epic-blue, #cb6b4f) 5%, #fff), #fff 60%); }
    .ie-sample .ic { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
        background: #ecfdf3; color: #067647; font-size: 20px; margin-bottom: 10px; }
    .ie-sample h5 { font-size: 15px; font-weight: 600; color: #101828; margin: 0 0 4px; }
    .ie-sample p { font-size: 12.5px; color: #667085; margin: 0 0 12px; }
    .ie-cols { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .ie-cols span { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; background: #f2f4f7; color: #344054; }
    .ie-dl { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 40px; border-radius: 8px;
        border: 1px solid var(--epic-blue, #cb6b4f); color: var(--epic-blue, #cb6b4f) !important; background: #fff; font-size: 14px; font-weight: 600; }
    .ie-dl:hover { background: var(--epic-blue, #cb6b4f); color: #fff !important; text-decoration: none; }
    .ie-tips { list-style: none; padding: 0; margin: 0; align-self: start; }
    .ie-tips li { display: flex; gap: 8px; font-size: 12.5px; color: #475467; margin-bottom: 6px; }
    .ie-tips li i { color: #98a2b3; width: 14px; margin-top: 3px; text-align: center; }
</style>

<div class="row" id="import_table">
    <div class="col-sm-12">
        <x-form id="import-employee-data-form">
            <div class="ie-card">
                <div class="ie-head">
                    <div>
                        <h4>@lang('app.importEmployee')</h4>
                        <p>Add new employees and update existing ones from the HR master sheet.</p>
                    </div>
                    <ol class="ie-steps">
                        <li class="on"><b>1</b> Upload</li>
                        <li><b>2</b> Review changes</li>
                        <li><b>3</b> Import</li>
                    </ol>
                </div>

                <div class="ie-body">
                    <div class="ie-drop">
                        <x-forms.file :fieldLabel="'HR master sheet (.xlsx, .xls)'" fieldName="import_file" fieldId="employee_import"/>
                    </div>

                        <div class="ie-sample">
                            <span class="ic"><i class="fa fa-file-excel"></i></span>
                            <h5>Sample file</h5>
                            <p>The master sheet layout (Active Employees), with the headings the import reads.</p>
                            <div class="ie-cols">
                                @foreach (['EMP ID', 'Employee Name', 'Department', 'Designation', 'DOJ', 'CTC', 'Mobile', 'Mail ID', '+ more'] as $col)
                                    <span>{{ $col }}</span>
                                @endforeach
                            </div>
                            <a href="{{ route('employees.import.sample') }}" class="ie-dl no-swap" download>
                                <i class="fa fa-download"></i> @lang('app.downloadSampleImport')
                            </a>
                        </div>

                    <div class="ie-note"><i class="fa fa-shield-alt"></i>
                        <span><strong>Nothing is saved on upload.</strong> Every row is shown for review first: new employees, and for
                            existing ones exactly what changes (now → from the sheet).</span></div>

                        <ul class="ie-tips">
                            <li><i class="fa fa-calendar-alt"></i> Dates as Excel dates or dd/mm/yyyy.</li>
                            <li><i class="fa fa-sitemap"></i> Divisions, designations and branches not in the system yet are created.</li>
                            <li><i class="fa fa-eraser"></i> A blank cell never erases what is already saved.</li>
                            <li><i class="fa fa-id-badge"></i> A changed EMP ID is shown with everything it affects, biometric included.</li>
                        </ul>
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
            messages: $.extend({}, dropifyMessages, {
                'default': 'Drop the HR master sheet here or click to choose<small>.xlsx or .xls</small>'
            })
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
