{{-- Inline Create Meeting form (Meetings tab). Online/Offline is switched by the chooser above it. --}}
<div class="card mb-3 meeting-form-card" id="meetingFormCard">
    <div class="card-body">
        <h3 class="h5 mb-1 font-weight-bold" id="meetingFormTitle">Create Offline Meeting</h3>
        <p class="text-muted small mb-4">Schedule the meeting with this lead</p>

        <form id="createMeetingForm" novalidate>
            @csrf
            <input type="hidden" name="meeting_type" id="meetingType" value="offline">
            {{-- Set when editing an existing meeting --}}
            <input type="hidden" id="meetingId" value="">

            @include('customers.partials.meeting-fields')

            <button type="submit" class="btn btn-primary" id="saveMeetingBtn">Create Meeting</button>
            <button type="button" class="btn btn-outline-secondary ml-2" id="cancelMeetingBtn">Cancel</button>
        </form>
    </div>
</div>
