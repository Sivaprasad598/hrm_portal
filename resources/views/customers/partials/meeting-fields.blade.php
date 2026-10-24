{{--
    Meeting fields shared by the Meetings tab form and the Meeting Result follow-up.
    Optional: $values (field => value) to pre-fill, $hideContactFields (no Client/Leads and Lead Owner fields), $meetingWith ('lead' default | 'client') for the contact dropdown, $withAddButtons (default true) for the Purpose/Status "+ Add" buttons,
    $withCustomerAddButton (default: same as $withAddButtons) for the Customer "+ Add" button.
--}}
@php
    $values         = $values ?? [];
    $withAddButtons = $withAddButtons ?? true;
    $withCustomerAddButton = $withCustomerAddButton ?? $withAddButtons;
    // Lead Team meetings: the lead and its owner are fixed by the page (hidden inputs), so no Leads / Lead Owner fields
    $hideContactFields = $hideContactFields ?? false;
    $fieldType      = $values['meeting_type'] ?? 'offline';
    $isOnline       = $fieldType === 'online';
    $val            = fn ($key) => $values[$key] ?? '';
    // Client or lead dropdown: the saved meeting's, else the page's ($meetingWith)
    $withClient     = !empty($values['client_id']) || (empty($values['lead_id']) && ($meetingWith ?? 'lead') === 'client');
@endphp
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label for="meetingTitle">Meeting Title</label>
            <input type="text" name="title" id="meetingTitle" class="form-control"
                   placeholder="e.g. Project kick-off with Ai Adhyapak" maxlength="191" autocomplete="off"
                   value="{{ $val('title') }}">
            <div class="invalid-feedback d-block" data-error-for="title"></div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingPurpose">Meeting Purpose</label>
            <div class="d-flex" style="gap: 8px;">
                <select name="purpose" id="meetingPurpose" class="form-control meeting-picker" data-live-search="true" data-size="8" data-option-type="purpose">
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
                <select name="status" id="meetingStatus" class="form-control meeting-picker" data-live-search="true" data-size="8" data-option-type="status">
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

    {{-- Meeting with a client (from the Clients page) or a lead; only the shown one is submitted --}}
    @unless ($hideContactFields)
    <div class="col-md-6 contact-group" data-contact="client" @if (!$withClient) style="display: none;" @endif>
        <div class="form-group">
            <label for="meetingClient">Clients <span class="text-danger">*</span></label>
            <div class="d-flex" style="gap: 8px;">
                <select name="client_id" id="meetingClient" class="form-control meeting-picker" data-live-search="true" data-size="8" @disabled(!$withClient)>
                    <option value="">Select Client</option>
                    @foreach ($clients as $clientOption)
                        <option value="{{ $clientOption->id }}" @selected((string) $val('client_id') === (string) $clientOption->id)>
                            {{ $clientOption->name }}{{ $clientOption->company_name ? ' (' . $clientOption->company_name . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @if ($withCustomerAddButton)
                    {{-- New client on the Clients page (new tab); reload this page to see it in the list --}}
                    <a href="{{ route('clients.create') }}" target="_blank" class="btn btn-outline-secondary text-nowrap" title="Add a client">+ Add</a>
                @endif
            </div>
            <div class="invalid-feedback d-block" data-error-for="client_id"></div>
        </div>
    </div>
    <div class="col-md-6 contact-group" data-contact="lead" @if ($withClient) style="display: none;" @endif>
        <div class="form-group">
            <label for="meetingLead">Leads <span class="text-danger">*</span></label>
            <div class="d-flex" style="gap: 8px;">
                <select name="lead_id" id="meetingLead" class="form-control meeting-picker" data-live-search="true" data-size="8" @disabled($withClient)>
                    <option value="">Select Lead</option>
                    @foreach ($leads as $leadOption)
                        <option value="{{ $leadOption->id }}" data-project-id="{{ $leadOption->lead_project_id }}" data-owner-id="{{ $leadOption->lead_owner }}" @selected((string) $val('lead_id') === (string) $leadOption->id)>
                            {{ $leadOption->client_name }}{{ $leadOption->company_name ? ' (' . $leadOption->company_name . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @if ($withCustomerAddButton)
                    {{-- New lead on the Lead Contacts page (new tab); reload this page to see it in the list --}}
                    <a href="{{ route('lead-contact.create') }}" target="_blank" class="btn btn-outline-secondary text-nowrap" title="Add a lead contact">+ Add</a>
                @endif
            </div>
            <div class="invalid-feedback d-block" data-error-for="lead_id"></div>
        </div>
    </div>
    @endunless
    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingProject">Select Project</label>
            <select name="lead_project_id" id="meetingProject" class="form-control meeting-picker" data-live-search="true" data-size="8">
                <option value="">Select Project</option>
                @foreach ($leadProjects as $projectOption)
                    <option value="{{ $projectOption->id }}" @selected((string) $val('lead_project_id') === (string) $projectOption->id)>{{ $projectOption->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-error-for="lead_project_id"></div>
        </div>
    </div>
    @unless ($hideContactFields)
    <div class="col-md-6">
        <div class="form-group">
            <label for="meetingLeadOwner">Lead Owner</label>
            <select name="lead_owner" id="meetingLeadOwner" class="form-control meeting-picker" data-live-search="true" data-size="8">
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

    @endunless
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

@once
    @push('scripts')
        <style>
            /* Searchable dropdowns next to a "+ Add" button take the remaining width */
            .d-flex > .bootstrap-select.meeting-picker { flex: 1 1 auto; min-width: 0; }
        </style>
        <script>
            // Searchable meeting dropdowns (bootstrap-select). Call after changing one of them in code
            // (.val(), new options, enabled/disabled) so the visible dropdown shows the change.
            window.refreshMeetingPickers = function () {
                if (!$.fn.selectpicker) return;
                $('select.meeting-picker').each(function () {
                    var select = $(this);
                    if (select.data('selectpicker')) {
                        select.selectpicker('refresh');
                    } else {
                        select.selectpicker();
                    }
                });
            };
            $(function () { refreshMeetingPickers(); });
        </script>
    @endpush
@endonce
