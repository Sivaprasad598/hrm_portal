<form id="createMeetingForm">
    @csrf
    <input type="hidden" name="meeting_type" value="{{ $meetingType }}">

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Customer <span class="text-danger">*</span></label>
                <select name="customer_id" class="form-control" required>
                    <option value="">Select Customer</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Meeting Purpose</label>
                <select name="purpose" class="form-control">
                    <option>Project Discussion</option>
                    <option>Requirement Discussion</option>
                    <option>Price Discussion</option>
                    <option>Negotiation</option>
                    <option>Follow-up</option>
                    <option>Other</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option>Planned</option>
                    <option>Completed</option>
                    <option>Cancelled</option>
                    <option>Rescheduled</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Lead Owner</label>
                <select name="lead_owner" class="form-control">
                    <option value="">Select Lead Owner</option>
                    <option>UMA DEVI</option>
                    <option>Ravi Kumar</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" class="form-control">
            </div>
        </div>

        @if ($meetingType === 'online')
            <div class="col-md-6">
                <div class="form-group">
                    <label>Meeting Type</label>
                    <select name="provider" class="form-control">
                        <option>Google Meet</option>
                        <option>Microsoft Teams</option>
                    </select>
                </div>
            </div>
        @endif

        <div class="col-md-4">
            <div class="form-group">
                <label>Date <span class="text-danger">*</span></label>
                <input type="date" name="meeting_date" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>Start Time <span class="text-danger">*</span></label>
                <input type="time" name="start_time" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>End Time <span class="text-danger">*</span></label>
                <input type="time" name="end_time" class="form-control" required>
            </div>
        </div>

        @if ($meetingType === 'offline')
            <div class="col-md-12">
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control">
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
        @endif

        @if ($meetingType === 'online')
            <div class="col-md-12">
                <div class="form-group">
                    <label>Meeting Link</label>
                    <input type="text" name="meeting_link" class="form-control" placeholder="Leave empty to auto-generate">
                    <small class="text-muted">Leave empty — the system will auto-generate a link.</small>
                </div>
            </div>
        @endif
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="saveMeetingBtn">Create Meeting</button>
    </div>
</form>

<script>
    $(document).ready(function () {
        $('#createMeetingForm').off('submit').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#saveMeetingBtn');
            btn.prop('disabled', true).text('Saving...');

            $.easyAjax({
                url: "{{ route('customers.meetings.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function (r) {
                    if (r.status === 'success') {
                        $('#meetingModal').modal('hide');
                        location.reload();
                    } else {
                        btn.prop('disabled', false).text('Create Meeting');
                    }
                },
                error: function () { btn.prop('disabled', false).text('Create Meeting'); }
            });
        });
    });
</script>
