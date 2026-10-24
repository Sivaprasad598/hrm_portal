{{--
    Lead contact form, "Assign Team" panel (shown while the Assign Team checkbox is ticked).
    Department -> Designation -> Employee -> "Add Employee" adds a row to the team table. Each row posts
    team_members[]; the team is saved with the lead (LeadTeamController::sync).
    - Duplicates: an employee already in the table is shown disabled as "(added)" and cannot be added again;
      the server also saves each employee once.
    - Remove: the row's Remove button takes the employee off the list; it is saved with the lead.
    - Editing: the edit form loads the saved team; add or remove members there. Designation, department,
      email and phone come from the employee's profile, so they are changed in HR, not here.
    Expects $departments, and $teamMembers on the edit form.
--}}
@php
    $teamMembers = $teamMembers ?? [];
    // Edit form: Add Employee / Remove save straight away. Create form: the lead does not exist yet, so the
    // team is saved with it when the form is saved.
    $teamLeadId = isset($leadContact) && $leadContact->exists ? $leadContact->id : null;
@endphp

<div class="row px-20 pb-20 {{ count($teamMembers) ? '' : 'd-none' }}" id="assign-team-panel">
    <div class="col-12">
        <h5 class="f-16 font-weight-bold text-dark-grey mb-0">Assign Team</h5>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.label class="my-3" fieldId="team_department" fieldLabel="Department" />
        <select class="form-control select-picker" id="team_department" data-live-search="true">
            <option value="">--</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}">{{ $department->team_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.label class="my-3" fieldId="team_designation" fieldLabel="Designation" />
        <select class="form-control select-picker" id="team_designation" data-live-search="true" disabled
                title="Select a department first"></select>
    </div>
    <div class="col-lg-4 col-md-6">
        <x-forms.label class="my-3" fieldId="team_employee" fieldLabel="Employee" />
        <select class="form-control select-picker" id="team_employee" data-live-search="true" disabled
                title="Select a designation first"></select>
    </div>
    <div class="col-lg-2 col-md-6 d-flex align-items-end">
        <button type="button" class="btn btn-primary w-100 mb-0" id="team_add_employee" disabled style="height: 39px;">
            <i class="fa fa-plus mr-1"></i> Add Employee
        </button>
    </div>
    <div class="col-12">
        <small class="text-danger d-none" id="team_message"></small>
    </div>

    <div class="col-12 mt-3">
        <div class="table-responsive">
            <table class="table table-bordered mb-0" id="team_table">
                <thead class="thead-light">
                    <tr>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <small class="text-lightest">{{ $teamLeadId ? 'Adding or removing an employee saves the team straight away.' : 'The team is saved with the lead when you click Save.' }}</small>
    </div>
</div>

<script>
    $(function () {
        const DESIGNATIONS_URL = "{{ route('lead-contact.team.designations') }}";
        const EMPLOYEES_URL = "{{ route('lead-contact.team.employees') }}";
        // Edit form only: save each add / remove at once
        const SAVE_URL = @json($teamLeadId ? route('lead-contact.team.add', $teamLeadId) : null);
        const REMOVE_URL = @json($teamLeadId ? route('lead-contact.team.remove', [$teamLeadId, ':user']) : null);
        let team = @json($teamMembers);   // rows in the table: {id, name, designation, department, email, phone, active}
        let employees = [];                // employees of the selected designation

        const esc = v => $('<div>').text(v ?? '').html();
        const isAdded = id => team.some(m => +m.id === +id);

        function message(text) {
            $('#team_message').text(text || '').toggleClass('d-none', !text);
        }

        // Same top-right toast the portal uses elsewhere (e.g. "URL copied")
        function toast(text, icon = 'success') {
            Swal.fire({
                icon: icon,
                text: text,
                toast: true,
                position: 'top-end',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                showClass: {
                    popup: 'swal2-noanimation',
                    backdrop: 'swal2-noanimation'
                },
            });
        }

        function resetSelect($select, title) {
            $select.html('').prop('disabled', true).attr('title', title).selectpicker('refresh');
        }

        function renderTable() {
            $('#team_table tbody').html(team.length ? team.map(m => `
                <tr>
                    <td>${esc(m.name)}${m.active === false ? ' <span class="badge badge-secondary">Inactive</span>' : ''}
                        <input type="hidden" name="team_members[]" value="${+m.id}"></td>
                    <td>${esc(m.designation)}</td>
                    <td>${esc(m.department)}</td>
                    <td>${esc(m.email)}</td>
                    <td>${esc(m.phone)}</td>
                    <td class="text-right">
                        <button type="button" class="btn btn-sm btn-outline-danger team-remove" data-id="${+m.id}">
                            <i class="fa fa-times mr-1"></i>Remove</button>
                    </td>
                </tr>`).join('')
                : '<tr><td colspan="6" class="text-center text-lightest">No employees added yet.</td></tr>');
        }

        // Employees of the designation; anyone already in the team is disabled and marked "(added)"
        function renderEmployees() {
            $('#team_employee').html('<option value="">--</option>' + employees.map(e =>
                `<option value="${+e.id}" ${isAdded(e.id) ? 'disabled' : ''}>${esc(e.name)}${isAdded(e.id) ? ' (added)' : ''}</option>`
            ).join('')).prop('disabled', !employees.length)
                .attr('title', employees.length ? '--' : 'No employees with this designation')
                .selectpicker('refresh');
            $('#team_add_employee').prop('disabled', true);
        }

        $('#assign_team').on('change', function () {
            $('#assign-team-panel').toggleClass('d-none', !this.checked);
            message('');
        });

        $('#team_department').on('change', function () {
            message('');
            employees = [];
            resetSelect($('#team_designation'), 'Loading…');
            resetSelect($('#team_employee'), 'Select a designation first');
            $('#team_add_employee').prop('disabled', true);
            if (!this.value) return resetSelect($('#team_designation'), 'Select a department first');

            $.get(DESIGNATIONS_URL, {department_id: this.value}, function (r) {
                $('#team_designation').html('<option value="">--</option>' + r.designations.map(d =>
                    `<option value="${+d.id}">${esc(d.name)}</option>`).join(''))
                    .prop('disabled', !r.designations.length)
                    .attr('title', r.designations.length ? '--' : 'No designations in this department')
                    .selectpicker('refresh');
            });
        });

        $('#team_designation').on('change', function () {
            message('');
            employees = [];
            resetSelect($('#team_employee'), 'Loading…');
            $('#team_add_employee').prop('disabled', true);
            if (!this.value) return resetSelect($('#team_employee'), 'Select a designation first');

            $.get(EMPLOYEES_URL, {department_id: $('#team_department').val(), designation_id: this.value}, function (r) {
                employees = r.employees;
                renderEmployees();
            });
        });

        $('#team_employee').on('change', function () {
            message('');
            $('#team_add_employee').prop('disabled', !this.value);
        });

        $('#team_add_employee').on('click', function () {
            let id = +$('#team_employee').val();
            let employee = employees.find(e => +e.id === id);
            if (!employee) return message('Select an employee.');
            if (isAdded(id)) return message(`${employee.name} is already in the team.`);

            function added() {
                team.push(employee);
                renderTable();
                renderEmployees();
            }

            if (!SAVE_URL) {
                added();
                return toast(`${employee.name} added. The team is saved with the lead when you click Save.`);
            }

            let $btn = $(this).prop('disabled', true);
            $.ajax({url: SAVE_URL, type: 'POST', data: {user_id: id, _token: '{{ csrf_token() }}'}})
                .done(() => { added(); toast(`${employee.name} added to the lead team and saved.`); })
                .fail(xhr => { $btn.prop('disabled', false); message(xhr.responseJSON?.message || 'Could not add the employee. Please try again.'); });
        });

        $('#team_table').on('click', '.team-remove', function () {
            let removed = team.find(m => +m.id === +$(this).data('id'));
            if (!removed) return;
            message('');

            function done() {
                team = team.filter(m => m !== removed);
                renderTable();
                renderEmployees();
            }

            if (!REMOVE_URL) {
                done();
                return toast(`${removed.name} removed from the team.`);
            }

            $(this).prop('disabled', true);
            $.ajax({url: REMOVE_URL.replace(':user', removed.id), type: 'POST', data: {_method: 'DELETE', _token: '{{ csrf_token() }}'}})
                .done(() => { done(); toast(`${removed.name} removed from the lead team.`); })
                .fail(() => { $(this).prop('disabled', false); message('Could not remove the employee. Please try again.'); });
        });

        renderTable();
    });
</script>
