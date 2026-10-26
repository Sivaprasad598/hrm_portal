<link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-colorpicker.css') }}" />

<div class="modal-header">
    <h5 class="modal-title">@lang('app.updateShift')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>

<div class="modal-body">
    <div class="portlet-body">
        <x-form id="createTicket" method="PUT" class="ajax-form">
            @include('employee-shifts.partials.form', ['shift' => $employeeShift])
        </x-form>
    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-employee-shift" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $('#save-employee-shift').click(function () {
        $.easyAjax({
            url: "{{ route('employee-shifts.update', $employeeShift->id) }}",
            container: '#createTicket',
            type: "POST",
            blockUI: '#save-employee-shift',
            disableButton: true,
            buttonSelector: '#save-employee-shift',
            data: $('#createTicket').serialize(),
            success: function (response) {
                if (response.status == "success") {
                    window.location.reload();
                }
            }
        })
    });
</script>
