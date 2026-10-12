{{--
    Meeting fields shared by the Meetings tab form and the Meeting Result follow-up.
    Optional: $values (field => value) to pre-fill, $withAddButtons (default true) for the Purpose/Status "+ Add" buttons,
    $withCustomerAddButton (default: same as $withAddButtons) for the Customer "+ Add" button.
--}}
@php
    $values         = $values ?? [];
    $withAddButtons = $withAddButtons ?? true;
    $withCustomerAddButton = $withCustomerAddButton ?? $withAddButtons;
    $fieldType      = $values['meeting_type'] ?? 'offline';
    $isOnline       = $fieldType === 'online';
    $val            = fn ($key) => $values[$key] ?? '';
@endphp
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingPurpose">Meeting Purpose</label>
            <div class="d-flex" style="gap: 8px;">
                <select name="purpose" id="meetingPurpose" class="form-control" data-option-type="purpose">
                    @foreach ($meetingPurposes as $purposeOption)
                        <option value="{{ $purposeOption->name }}" @selected($val('purpose') === $purposeOption->name)>{{ $purposeOption->name }}</option>
                    @endforeach
                </select>
                @if ($withAddButtons)
                    <button type="button" class="btn btn-outline-secondary text-nowrap manage-meeting-options-btn"
                            data-option-type="purpose" title="Add, edit or delete meeting purposes">+ Add</button>
                @endif
            </div>
            <div class="invalid-feedback d-block" data-error-for="purpose"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingStatus">Meeting Status</label>
            <div class="d-flex" style="gap: 8px;">
                <select name="status" id="meetingStatus" class="form-control" data-option-type="status">
                    @foreach ($meetingStatuses as $statusOption)
                        <option value="{{ $statusOption->name }}" @selected($val('status') === $statusOption->name)>{{ $statusOption->name }}</option>
                    @endforeach
                </select>
                @if ($withAddButtons)
                    <button type="button" class="btn btn-outline-secondary text-nowrap manage-meeting-options-btn"
                            data-option-type="status" title="Add, edit or delete meeting statuses">+ Add</button>
                @endif
            </div>
            <div class="invalid-feedback d-block" data-error-for="status"></div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingCustomer">Customer <span class="text-danger">*</span></label>
            <div class="d-flex" style="gap: 8px;">
                <select name="customer_id" id="meetingCustomer" class="form-control">
                    <option value="">Select Customer</option>
                    @foreach ($customers as $customerOption)
                        <option value="{{ $customerOption->id }}" @selected((string) $val('customer_id') === (string) $customerOption->id)>
                            {{ $customerOption->company_name }} ({{ $customerOption->customer_name }})
                        </option>
                    @endforeach
                </select>
                @if ($withCustomerAddButton)
                    <button type="button" class="btn btn-outline-secondary text-nowrap" id="meetingAddCustomerBtn">+ Add</button>
                @endif
            </div>
            <div class="invalid-feedback d-block" data-error-for="customer_id"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingLeadOwner">Lead Owner</label>
            <select name="lead_owner" id="meetingLeadOwner" class="form-control">
                <option value="">Select Lead Owner</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) $val('lead_owner') === (string) $employee->id)>
                        {{ $employee->name }}{{ $employee->designation_name ? ' (' . $employee->designation_name . ')' : '' }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-error-for="lead_owner"></div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label for="meetingDate">Date <span class="text-danger">*</span></label>
            <input type="date" name="meeting_date" id="meetingDate" class="form-control" value="{{ $val('meeting_date') }}">
            <div class="invalid-feedback d-block" data-error-for="meeting_date"></div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label for="meetingStart">Start Time <span class="text-danger">*</span></label>
            <input type="time" name="start_time" id="meetingStart" class="form-control" value="{{ $val('start_time') }}">
            <div class="invalid-feedback d-block" data-error-for="start_time"></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="meetingEnd">End Time <span class="text-danger">*</span></label>
            <input type="time" name="end_time" id="meetingEnd" class="form-control" value="{{ $val('end_time') }}">
            <div class="invalid-feedback d-block" data-error-for="end_time"></div>
        </div>
    </div>

    {{-- Offline only --}}
    <div class="col-md-12 offline-only" @if ($isOnline) style="display: none;" @endif>
        <div class="form-group">
            <label for="meetingLocation">Location</label>
            <input type="text" name="location" id="meetingLocation" class="form-control"
                   placeholder="Enter meeting location" maxlength="255" autocomplete="off"
                   value="{{ $val('location') }}" @disabled($isOnline)>
            <div class="invalid-feedback d-block" data-error-for="location"></div>
        </div>
    </div>

    {{-- Online only --}}
    <div class="col-md-6 online-only" @if (!$isOnline) style="display: none;" @endif>
        <div class="form-group">
            <label for="meetingProvider">Meeting Type</label>
            <div class="d-flex" style="gap: 8px;">
                <select name="provider" id="meetingProvider" class="form-control" @disabled(!$isOnline)>
                    <option @selected($val('provider') !== 'Microsoft Teams')>Google Meet</option>
                    <option @selected($val('provider') === 'Microsoft Teams')>Microsoft Teams</option>
                </select>
                <button type="button" class="btn btn-outline-primary text-nowrap" id="generateMeetBtn"
                        title="Create a Google Meet or Microsoft Teams link for the selected date and time"
                        @disabled(!$isOnline)>{{ $val('google_event_id') || $val('teams_meeting_id') ? 'Regenerate' : 'Generate Link' }}</button>
            </div>
            {{-- Calendar event behind a generated link; updated with the final details on save --}}
            <input type="hidden" name="google_event_id" id="meetingGoogleEventId" value="{{ $val('google_event_id') }}" @disabled(!$isOnline)>
            {{-- Microsoft Teams meeting behind a generated Teams link; moved to the final time on save --}}
            <input type="hidden" name="teams_meeting_id" id="meetingTeamsMeetingId" value="{{ $val('teams_meeting_id') }}" @disabled(!$isOnline)>
        </div>
    </div>
    <div class="col-md-6 online-only" @if (!$isOnline) style="display: none;" @endif>
        <div class="form-group">
            <label for="meetingLink">Meeting Link</label>
            <input type="text" name="meeting_link" id="meetingLink" class="form-control"
                   placeholder="Leave empty to auto-generate" maxlength="500" autocomplete="off"
                   value="{{ $val('meeting_link') }}" @disabled(!$isOnline)>
            <div class="invalid-feedback d-block" data-error-for="meeting_link"></div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            <label for="meetingDescription">Description</label>
            <textarea name="description" id="meetingDescription" class="form-control" rows="3"
                      placeholder="What is the aim of the meeting?">{{ $val('description') }}</textarea>
        </div>
    </div>
</div>
