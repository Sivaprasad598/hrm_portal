{{-- "+ Add" pop-up for Meeting Purpose / Meeting Status / Meeting Result dropdowns.
     Used by the Customers → Meetings form and the Meeting Result page. Edit/delete is in Settings → Lead Settings. --}}
<div class="modal fade" id="meetingOptionsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="meetingOptionsTitle">Manage Options</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="meetingOptionAddForm" class="d-flex mb-3" style="gap: 8px;" autocomplete="off">
                    <input type="text" id="meetingOptionNewName" class="form-control" maxlength="100" placeholder="Enter a new option">
                    <button type="submit" class="btn btn-primary text-nowrap" id="meetingOptionAddBtn">+ Add</button>
                </form>
                <ul class="list-group" id="meetingOptionsList"></ul>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fa fa-info-circle"></i>
                    To edit or delete options, go to
                    <a href="#" id="meetingOptionsSettingsLink">Settings → Lead Settings</a>.
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
    // ----------------------------------------------------
    // MANAGE MEETING PURPOSE / STATUS / RESULT OPTIONS ("+ Add" buttons)
    // ----------------------------------------------------
    var optionLabels = { purpose: 'Meeting Purpose', status: 'Meeting Status', result: 'Meeting Result' };
    var optionType = null;
    var optionUrls = {
        index: "{{ route('meeting-options.index', ':type') }}",
        store: "{{ route('meeting-options.store', ':type') }}"
    };

    function optionUrl(name, id) {
        return optionUrls[name].replace(':type', optionType).replace(':id', id || '');
    }

    function alertMessage(icon, title, text) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: icon, title: title, text: text || '' });
        } else {
            alert(title + (text ? '\n' + text : ''));
        }
    }

    function firstError(xhr, fallback) {
        var json = xhr.responseJSON || {};
        if (json.errors) {
            var key = Object.keys(json.errors)[0];
            return json.errors[key][0];
        }
        return json.message || fallback;
    }

    // Keep the form's dropdown in step with the list, preserving the current choice where possible
    function refreshOptionSelect(options, renamedFrom, renamedTo) {
        var select = $('select[data-option-type="' + optionType + '"]');
        var current = select.val();
        // Keep a "Select ..." placeholder option if the dropdown has one
        var placeholder = select.find('option[value=""]').first().clone();

        if (renamedFrom && current === renamedFrom) current = renamedTo;

        select.empty().append(placeholder);
        options.forEach(function (option) {
            select.append($('<option>').val(option.name).text(option.name));
        });

        if (current && select.find('option').filter(function () { return this.value === current; }).length) {
            select.val(current);
        }
    }

    function renderOptionList(options) {
        var list = $('#meetingOptionsList').empty();

        if (!options.length) {
            list.append('<li class="list-group-item text-muted">No options yet.</li>');
        }

        options.forEach(function (option) {
            list.append($('<li class="list-group-item">').text(option.name));
        });
    }

    function loadOptions(renamedFrom, renamedTo) {
        return $.get(optionUrl('index'), function (r) {
            renderOptionList(r.options || []);
            refreshOptionSelect(r.options || [], renamedFrom, renamedTo);
        });
    }

    $(document).on('click', '.manage-meeting-options-btn', function () {
        optionType = $(this).data('option-type');
        $('#meetingOptionsTitle').text('Add ' + optionLabels[optionType]);
        $('#meetingOptionsSettingsLink').attr('href', "{{ route('lead-settings.index') }}?tab=meeting-" + optionType);
        $('#meetingOptionNewName').val('').attr('placeholder', 'Enter a new ' + optionLabels[optionType].toLowerCase());
        $('#meetingOptionsList').html('<li class="list-group-item text-center"><i class="fa fa-spinner fa-spin"></i></li>');
        $('#meetingOptionsModal').modal('show');
        loadOptions();
    });

    $('#meetingOptionsModal').on('shown.bs.modal', function () {
        $('#meetingOptionNewName').trigger('focus');
    });

    // Add
    $('#meetingOptionAddForm').on('submit', function (e) {
        e.preventDefault();
        var name = $.trim($('#meetingOptionNewName').val());

        if (!name) {
            alertMessage('warning', 'Enter a name', 'Type the ' + optionLabels[optionType].toLowerCase() + ' to add.');
            return;
        }

        var btn = $('#meetingOptionAddBtn').prop('disabled', true);

        $.ajax({
            url: optionUrl('store'),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', name: name },
            success: function (r) {
                $('#meetingOptionNewName').val('');
                loadOptions().done(function () {
                    // Select the newly added option in the form
                    $('select[data-option-type="' + optionType + '"]').val(r.option.name);
                });
            },
            error: function (xhr) {
                var message = firstError(xhr, 'Unable to add this option.');
                alertMessage('error', /already exists/i.test(message) ? 'Already exists' : 'Not added', message);
            },
            complete: function () { btn.prop('disabled', false); }
        });
    });

});
</script>
@endpush
