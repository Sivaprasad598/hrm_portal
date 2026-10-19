{{-- Emergency contact rows on the employee wizard (Add and Edit): add,
     remove, renumber, and the check that each row is complete. Included
     inside the page's $(document).ready(), after setFieldState(). --}}
    function validateEmergencySection() {
        var rows    = $('#emergencyContactRows tr');
        var isValid = true;
        rows.each(function (i) {
            var row   = $(this);
            var label = 'Contact ' + (i + 1);
            var name  = row.find('.ec-name')[0];
            var rel   = row.find('.ec-relation')[0];
            var num   = row.find('.ec-number')[0];
            if (!name || !name.value.trim()) { setFieldState(name, false, label + ' Name is required.');     isValid = false; } else { setFieldState(name, true); }
            if (!rel  || !rel.value)         { setFieldState(rel,  false, label + ' Relation is required.'); isValid = false; } else { setFieldState(rel,  true); }
            if (!num  || !num.value.trim())  { setFieldState(num,  false, label + ' Number is required.');   isValid = false; } else { setFieldState(num,  true); }
        });
        return isValid;
    }

    // ── Dynamic Emergency Contact rows ───────────────────────
    var ecRelationOptions =
        '<option value="">-- Select --</option>' +
        '<option value="Spouse">Spouse</option>' +
        '<option value="Father">Father</option>' +
        '<option value="Mother">Mother</option>' +
        '<option value="Brother">Brother</option>' +
        '<option value="Sister">Sister</option>' +
        '<option value="Friend">Friend</option>' +
        '<option value="Colleague">Colleague</option>' +
        '<option value="Other">Other</option>';

    function renumberEcRows() {
        $('#emergencyContactRows tr').each(function (i) {
            $(this).find('.ec-serial').text((i + 1) + '.');
        });
    }

    $('#ecAddRow').on('click', function () {
        var row = '<tr>' +
            '<td class="align-middle text-center ec-serial"></td>' +
            '<td><input type="text" name="emergency_name[]" class="form-control ec-name" maxlength="100"></td>' +
            '<td><select name="emergency_relation[]" class="form-control ec-relation">' + ecRelationOptions + '</select></td>' +
            '<td><input type="text" name="emergency_number[]" class="form-control ec-number" maxlength="20"></td>' +
            '<td class="align-middle text-center"><button type="button" class="btn btn-sm btn-outline-danger ec-remove-row" title="Remove row"><i class="fa fa-trash"></i></button></td>' +
            '</tr>';
        $('#emergencyContactRows').append(row);
        renumberEcRows();
    });

    $(document).on('click', '.ec-remove-row', function () {
        if ($('#emergencyContactRows tr').length <= 1) {
            alert('At least one emergency contact is required.');
            return;
        }
        $(this).closest('tr').remove();
        renumberEcRows();
    });

