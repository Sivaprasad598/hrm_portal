{{-- Inline Create Meeting form (Meetings tab). Online/Offline is switched by the chooser above it. --}}
<div class="card mb-3 meeting-form-card" id="meetingFormCard">
    <div class="card-body">
        @php $contactWord = ($meetingWith ?? 'lead') === 'client' ? 'Client' : 'Lead'; @endphp
        <h3 class="h5 mb-1 font-weight-bold" id="meetingFormTitle">Create Offline {{ $contactWord }} Meeting</h3>
        <p class="text-muted small mb-4">Schedule the meeting with this <span class="meeting-contact-word-lower">{{ strtolower($contactWord) }}</span></p>

        <form id="createMeetingForm" novalidate>
            @csrf
            <input type="hidden" name="meeting_type" id="meetingType" value="offline">
            {{-- Set when editing an existing meeting --}}
            <input type="hidden" id="meetingId" value="">

            @include('customers.partials.meeting-fields')

            {{-- Lead meetings: who is invited (lead, lead owner, lead team). Filled when a lead is picked;
                 people ticked on the lead's "Lead Team" tab arrive pre-selected. --}}
            <div class="form-group mb-4" id="meetingInviteBox" style="display: none;">
                <label class="d-block mb-1">Invite</label>
                <small class="text-muted d-block mb-2">Ticked people are saved with the meeting; for Google Meet they also get the calendar invite.</small>
                <input type="hidden" name="attendees_sent" value="1" id="meetingAttendeesSent" disabled>
                <div id="meetingInviteList" class="border rounded p-2" style="max-height: 260px; overflow-y: auto;"></div>
                <div class="text-danger small mt-1" data-error-for="attendees"></div>
            </div>

            <button type="submit" class="btn btn-primary" id="saveMeetingBtn">Create Meeting</button>
            <button type="button" class="btn btn-outline-secondary ml-2" id="cancelMeetingBtn">Cancel</button>
        </form>
    </div>
</div>
