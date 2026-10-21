<div class="row">
    <div class="col-sm-12">
        <div class="bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Import Assets</h4>

            <div class="p-20" id="import-upload">
                <ol class="f-14 text-dark-grey pl-3 mb-3">
                    <li><a href="{{ route('assets.import.template') }}" class="font-weight-600"><i class="fa fa-download mr-1"></i>Download the template</a>
                        and fill one row per asset. The second sheet explains each column.</li>
                    <li>To load assets that people already hold, put their <strong>Employee ID</strong> and the <strong>Assigned On</strong> date.</li>
                    <li>Upload it. Every row is checked and shown to you first; nothing is saved until you press Import.</li>
                </ol>

                <x-form id="asset-import-form">
                    <x-forms.file allowedFileExtensions="xlsx xls csv" fieldLabel="Spreadsheet" fieldName="import_file" fieldId="import_file"
                        :fieldRequired="true" fieldHeight="110" />
                </x-form>

                <x-forms.button-primary id="check-import" class="mr-3" icon="search">Check the File</x-forms.button-primary>
                <x-forms.button-secondary id="cancel-import">@lang('app.cancel')</x-forms.button-secondary>
            </div>

            <div id="import-review" class="px-20 pb-20"></div>
        </div>
    </div>
</div>

@include('assets.ajax.submit-js')

<script>
    $(function () {
        $('#check-import').on('click', function () {
            $.easyAjax({
                url: '{{ route('assets.import.preview') }}',
                container: '#asset-import-form',
                type: 'POST',
                file: true,
                blockUI: true,
                disableButton: true,
                buttonSelector: '#check-import',
                success: function (response) {
                    if (response.status === 'success') {
                        $('#import-upload').addClass('d-none');
                        $('#import-review').html(response.html);
                    }
                }
            });
        });

        $('#cancel-import').on('click', assetPanelClose);

        init(RIGHT_MODAL);
    });
</script>
