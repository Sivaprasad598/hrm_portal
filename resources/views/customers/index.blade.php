@extends('layouts.app')

@section('content')

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 hide-while-editing">
        <div>
            <h1 class="mb-1">Customers</h1>
            <p class="text-muted mb-0">Manage customers and customer meetings</p>
        </div>
    </div>

    {{-- ==================== TABS ==================== --}}
    <div class="d-flex mb-3 hide-while-editing" style="gap: 8px;">
        <button type="button" class="btn btn-primary tab-btn active" data-tab="customers">Customers</button>
        <button type="button" class="btn btn-outline-primary tab-btn" data-tab="meetings">Meetings</button>
    </div>

    {{-- ==================== CUSTOMERS PANEL ==================== --}}
    <section id="customersPanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-0">Customers</h2>
                <div class="text-muted small">Created customers</div>
            </div>
            <a href="{{ route('customers.create') }}" class="btn btn-primary" id="topAddCustomerBtn">+ Add Customer</a>
        </div>

        <div id="customerList">
            @foreach ($customers as $customer)
                <div class="card mb-3 customer-card" data-customer-id="{{ $customer->id }}">
                    <div class="card-body d-flex justify-content-between align-items-start flex-wrap" style="gap: 20px;">
                        <div>
                            <h3 class="h6 mb-2 font-weight-bold">{{ $customer->company_name }}</h3>
                            <p class="mb-1 text-muted">Contact: {{ $customer->customer_name }}</p>
                            <p class="mb-2 text-muted">
                                Email: {{ $customer->email ?? '-' }} &nbsp;|&nbsp; Mobile: {{ $customer->mobile ?? '-' }}
                            </p>
                            <span class="badge badge-success">Customer</span>
                        </div>
                        <div class="d-flex" style="gap: 8px; align-items: center;">
                            <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-outline-primary">Edit</a>
                            <button type="button" class="btn btn-outline-danger delete-customer-btn"
                                    data-customer-id="{{ $customer->id }}">Delete</button>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($customers->isEmpty())
                <div class="text-center text-muted py-4">No customers yet.</div>
            @endif
        </div>
    </section>

    {{-- ==================== MEETINGS PANEL ==================== --}}
    <section id="meetingsPanel" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-0">Meetings</h2>
                <div class="text-muted small">Created customer meetings</div>
            </div>
            <div class="d-flex align-items-center" style="gap: 8px;">
            <div class="d-flex hide-while-editing" style="gap: 8px;">
                <span class="badge badge-light border align-self-center px-2 py-2" id="botStatusBadge" title="CRM Notetaker bot: joins every Google Meet meeting automatically and takes notes">Bot: checking…</span>
                <button type="button" class="btn btn-outline-secondary" id="crmNotesSetupBtn" title="Connect the CRM Notes Chrome extension">CRM Notes extension</button>
                <button type="button" class="btn btn-primary" id="topAddMeetingBtn">+ Add Meeting</button>
            </div>
            {{-- Back: only while the Add/Edit Meeting form is open; closes it --}}
            <button type="button" class="btn btn-outline-secondary show-while-editing" id="meetingsBackBtn">&larr; Back</button>
            </div>
        </div>

        {{-- Chooser + form: hidden until "+ Add Meeting" or "Edit" --}}
        <div id="meetingEditor" style="display: none;">
            <div class="card mb-3" id="meetingChooser">
                <div class="card-body">
                    <h3 class="h6 mb-1">Meet with Customer</h3>
                    <p class="text-muted small mb-3">Choose Online Meeting or Offline Meeting</p>
                    <div class="d-flex" style="gap: 10px;">
                        <button type="button" class="btn btn-outline-primary" id="onlineBtn">Online Meeting</button>
                        <button type="button" class="btn btn-primary" id="offlineBtn">Offline Meeting</button>
                    </div>
                </div>
            </div>

            @include('customers.partials.meeting-form')
        </div>

        {{-- Meetings list --}}
        <div id="meetingList" class="hide-while-editing">
            @forelse ($meetings as $meeting)
                @php
                    $meetingOwner = $employees->firstWhere('name', $meeting->lead_owner);
                    $meetingData = [
                        'id'              => $meeting->id,
                        'meeting_type'    => $meeting->meeting_type,
                        'purpose'         => $meeting->purpose,
                        'status'          => $meeting->status,
                        'customer_id'     => $meeting->customer_id,
                        'lead_owner'      => $meetingOwner->id ?? '',
                        'meeting_date'    => $meeting->meeting_date ? $meeting->meeting_date->format('Y-m-d') : '',
                        'start_time'      => $meeting->start_time ? substr($meeting->start_time, 0, 5) : '',
                        'end_time'        => $meeting->end_time ? substr($meeting->end_time, 0, 5) : '',
                        'provider'        => $meeting->provider ?: 'Google Meet',
                        'meeting_link'    => $meeting->meeting_link,
                        'google_event_id' => $meeting->google_event_id,
                        'teams_meeting_id' => $meeting->teams_meeting_id,
                        'location'        => $meeting->location,
                        'description'     => $meeting->description,
                    ];
                @endphp
                <div class="card mb-2 meeting-card" data-meeting-id="{{ $meeting->id }}">
                    <div class="card-body d-flex justify-content-between align-items-start flex-wrap" style="gap: 16px;">
                        <div>
                            <h3 class="h6 mb-1 font-weight-bold">
                                {{ ucfirst($meeting->meeting_type) }} Meeting
                                @if ($meeting->status)
                                    <span class="badge badge-light border ml-1">{{ $meeting->status }}</span>
                                @endif
                            </h3>
                            {{-- Filled by the live status poll while people are in the Google Meet call --}}
                            <div class="meet-live-panel mb-2" data-meeting-id="{{ $meeting->id }}" style="display: none;"></div>
                            <p class="mb-1"><b>Customer:</b> {{ $meeting->customer->company_name ?? '-' }}</p>
                            <p class="mb-1 text-muted">
                                {{ $meeting->purpose }} ·
                                {{ $meeting->meeting_date ? $meeting->meeting_date->format('d M Y') : '-' }} ·
                                {{ $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') : '' }}
                                - {{ $meeting->end_time ? \Carbon\Carbon::parse($meeting->end_time)->format('h:i A') : '' }}
                            </p>
                            @if ($meeting->lead_owner)
                                <p class="mb-1 text-muted small">Lead Owner: {{ $meeting->lead_owner }}</p>
                            @endif
                            @if ($meeting->meeting_link)
                                <p class="mb-1 text-muted small">Link: <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener">{{ $meeting->meeting_link }}</a></p>
                            @endif
                            @if ($meeting->location)
                                <p class="mb-0 text-muted small">Location: {{ $meeting->location }}</p>
                            @endif
                            @if ($meeting->result === 'followup')
                                <p class="mb-0 mt-1 small"><span class="badge badge-info">Follow-up</span>
                                    {{ $meeting->followup_action }} on {{ $meeting->followup_date ? $meeting->followup_date->format('d M Y') : '-' }}</p>
                            @elseif ($meeting->result === 'outcome')
                                <p class="mb-0 mt-1 small"><span class="badge badge-success">Result</span>
                                    {{ $meeting->outcome }}@if ($meeting->followup_date) · Follow-up on {{ $meeting->followup_date->format('d M Y') }}@endif</p>
                            @elseif ($meeting->result === 'ended')
                                <p class="mb-0 mt-1 small"><span class="badge badge-danger">Discussion Ended</span>
                                    {{ $meeting->end_reason }}</p>
                            @endif
                        </div>
                        <div class="d-flex" style="gap: 8px;">
                            <button type="button" class="btn btn-outline-primary btn-sm edit-meeting-btn"
                                    data-meeting='@json($meetingData)'>Edit</button>
                            @if ($meeting->meeting_type === 'online' && $meeting->provider === 'Google Meet' && $meeting->meeting_link)
                                <button type="button" class="btn btn-outline-info btn-sm meet-notes-btn" data-meeting-id="{{ $meeting->id }}">
                                    Meet Notes @if ($meeting->meet_notes)<i class="fa fa-check-circle ml-1"></i>@endif
                                </button>
                            @endif
                            <a href="{{ route('customers.meetings.result', $meeting->id) }}" class="btn btn-outline-success btn-sm">Meeting Result</a>
                            <button type="button" class="btn btn-outline-danger btn-sm delete-meeting-btn"
                                    data-meeting-id="{{ $meeting->id }}">Delete</button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-muted mb-3" id="noMeetings">No meetings created yet.</div>
            @endforelse
        </div>
    </section>
</div>

{{-- ==================== MODAL CONTAINER ==================== --}}
<div class="modal fade" id="customerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">Create Customer</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="customerModalBody"></div>
        </div>
    </div>
</div>


@include('customers.partials.meeting-options-modal')

{{-- CRM Notes Chrome extension: create the per-user key --}}
<div class="modal fade" id="crmNotesSetupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">CRM Notes extension</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body small">
                <p class="mb-2">The CRM Notes Chrome extension records the Google Meet call from your browser and saves AI meeting notes in <b>Meet Notes</b> after the call.</p>
                <ol class="pl-3 mb-3">
                    <li>Install the extension (folder <code>crm-notes-extension</code>, see its README).</li>
                    <li>Click <b>Create my key</b> below and paste the Portal URL and key into the extension's options.</li>
                    <li>In a CRM meeting, click the CRM Notes icon once after joining.</li>
                </ol>
                <div id="crmNotesKeyBox" style="display: none;">
                    <label class="mb-1">Portal URL</label>
                    <input type="text" class="form-control form-control-sm mb-2" id="crmNotesPortalUrl" readonly>
                    <label class="mb-1">Your key <span class="text-danger">(shown only once)</span></label>
                    <input type="text" class="form-control form-control-sm" id="crmNotesKey" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="crmNotesCreateKeyBtn">Create my key</button>
            </div>
        </div>
    </div>
</div>

{{-- Google Meet automatic Smart Notes --}}
<div class="modal fade" id="meetNotesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Meet Notes</h5>
                    <div class="text-muted small" id="meetNotesTitle"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="meetNotesStatus" class="small mb-2"></div>
                <div id="meetNotesMessage" class="alert alert-light border small mb-0" style="display: none;"></div>
                <div id="meetNotesText" class="border rounded p-3 bg-white" style="display: none; white-space: pre-wrap; font-size: 14px; line-height: 1.6;"></div>
                <details id="meetTranscriptBox" class="mt-3" style="display: none;">
                    <summary class="small text-muted" style="cursor: pointer;">Show full transcript</summary>
                    <div id="meetTranscriptText" class="border rounded p-3 mt-2 bg-light small" style="white-space: pre-wrap; max-height: 300px; overflow: auto;"></div>
                </details>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener" class="btn btn-outline-secondary" id="meetNotesDocLink" style="display: none;">Open in Google Docs</a>
                <button type="button" class="btn btn-primary" id="meetNotesRefreshBtn">Check now</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<style>
    /* !important needed: the header and tabs use Bootstrap .d-flex, which is display:flex !important */
    .meeting-editing .hide-while-editing { display: none !important; }
    .show-while-editing { display: none !important; }
    .meeting-editing .show-while-editing { display: inline-block !important; }
</style>
<script>
$(document).ready(function () {

    // ----------------------------------------------------
    // TAB SWITCHING
    // ----------------------------------------------------
    $('.tab-btn').on('click', function () {
        var tab = $(this).data('tab');

        // Remember the tab in the URL so a reload stays on it
        history.replaceState(null, '', tab === 'meetings' ? '#meetings' : location.pathname + location.search);

        $('.tab-btn').removeClass('btn-primary active').addClass('btn-outline-primary');

        if (tab === 'customers') {
            $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
            $('#customersPanel').show();
            $('#meetingsPanel').hide();
        } else {
            $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
            $('#customersPanel').hide();
            $('#meetingsPanel').show();
        }
    });

    // ----------------------------------------------------
    // OPEN CUSTOMER MODAL
    // ----------------------------------------------------
    function openCustomerModal() {
        $('#customerModalLabel').text('Create Customer');
        $('#customerModalBody').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
        $('#customerModal').modal('show');

        $.ajax({
            url: "{{ route('customers.create') }}",
            type: 'GET',
            success: function (r) {
                if (r.status === 'success') {
                    $('#customerModalBody').html(r.html);
                }
            }
        });
    }


    // ----------------------------------------------------
    // INLINE MEETING FORM (ONLINE / OFFLINE)
    // ----------------------------------------------------
    var meetingForm = $('#createMeetingForm');
    var emojiPattern = /[\u{1F000}-\u{1FAFF}\u{2300}-\u{23FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}\u{20E3}]/u;

    function setMeetingType(type) {
        var online = type === 'online';

        $('#meetingType').val(type);
        $('#meetingFormTitle').text(($('#meetingId').val() ? 'Edit ' : 'Create ') + (online ? 'Online' : 'Offline') + ' Meeting');

        $('#onlineBtn').toggleClass('btn-primary', online).toggleClass('btn-outline-primary', !online);
        $('#offlineBtn').toggleClass('btn-primary', !online).toggleClass('btn-outline-primary', online);

        // Hidden fields are disabled so they are not submitted
        $('.online-only').toggle(online).find(':input').prop('disabled', !online);
        $('.offline-only').toggle(!online).find(':input').prop('disabled', online);
        updateGenerateMeetBtn();
    }

    // "Generate Link" is only for Google Meet (Microsoft Teams links are pasted until Teams is integrated)
    function updateGenerateMeetBtn() {
        var online = $('#meetingType').val() === 'online';
        $('#generateMeetBtn').prop('disabled', !online);
    }

    $('#meetingProvider').on('change', updateGenerateMeetBtn);

    $('#generateMeetBtn').on('click', function () {
        var errors = {};
        var start = $('#meetingStart').val();
        var end = $('#meetingEnd').val();

        if (!$('#meetingCustomer').val()) errors.customer_id = 'Select a customer.';
        if (!$('#meetingDate').val()) errors.meeting_date = 'Select the meeting date.';
        if (!start) errors.start_time = 'Select the start time.';
        if (!end) errors.end_time = 'Select the end time.';
        if (start && end && end <= start) errors.end_time = 'End time must be after the start time.';

        ['customer_id', 'meeting_date', 'start_time', 'end_time', 'meeting_link'].forEach(function (name) {
            showMeetingError(name, errors[name] || '');
        });

        if (Object.keys(errors).length) {
            meetingForm.find('.is-invalid').first().focus();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Generating...');

        $.ajax({
            url: "{{ route('customers.meetings.generate-meet') }}",
            type: 'POST',
            data: meetingForm.serialize(),
            success: function (r) {
                if (r.status === 'success' && r.meeting_link) {
                    $('#meetingLink').val(r.meeting_link);
                    $('#meetingGoogleEventId').val(r.google_event_id || '');
                    $('#meetingTeamsMeetingId').val(r.teams_meeting_id || '');
                    btn.text('Regenerate');
                } else {
                    btn.text('Generate Link');
                    showMeetingError('meeting_link', 'Could not create the Google Meet link. Please try again.');
                }
            },
            error: function (xhr) {
                btn.text($('#meetingGoogleEventId').val() || $('#meetingTeamsMeetingId').val() ? 'Regenerate' : 'Generate Link');
                var body = xhr.responseJSON;

                if (!body) {
                    try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
                }

                var errors = body && body.errors;

                if (errors) {
                    Object.keys(errors).forEach(function (name) {
                        showMeetingError(name, errors[name][0]);
                    });
                    meetingForm.find('.is-invalid').first().focus();
                } else {
                    showMeetingError('meeting_link', (body && body.message) || 'Could not create the Google Meet link. Please try again.');
                }
            },
            complete: function () {
                updateGenerateMeetBtn();
            }
        });
    });

    // Back to an empty "Create" form (also used before filling it for an edit)
    function resetMeetingForm() {
        meetingForm[0].reset();
        $('#meetingId').val('');
        $('#meetingGoogleEventId').val('');
        $('#meetingTeamsMeetingId').val('');
        meetingForm.find('.is-invalid').removeClass('is-invalid');
        meetingForm.find('[data-error-for]').text('');
        $('#generateMeetBtn').text('Generate Link');
        $('#saveMeetingBtn').prop('disabled', false).text('Create Meeting');
        setMeetingType('offline');
    }

    function openMeetingEditor(scrollTo) {
        $('.tab-btn[data-tab="meetings"]').trigger('click');
        // Only the chooser + form while adding/editing; header, tabs and list come back on Cancel
        $('.content-wrapper').addClass('meeting-editing');
        $('#meetingEditor').show();
        $('html, body').animate({ scrollTop: $(scrollTo).offset().top - 80 }, 300);
    }

    function showMeetingForm(customerId) {
        resetMeetingForm();

        if (customerId) {
            $('#meetingCustomer').val(String(customerId));
        }

        openMeetingEditor('#meetingChooser');
    }

    function closeMeetingForm() {
        resetMeetingForm();
        $('#meetingEditor').hide();
        $('.content-wrapper').removeClass('meeting-editing');
    }

    $('#meetingsBackBtn').on('click', function () {
        closeMeetingForm();
        $('html, body').animate({ scrollTop: 0 }, 200);
    });

    function editMeeting(meeting) {
        resetMeetingForm();

        $('#meetingId').val(meeting.id);
        $('#meetingPurpose').val(meeting.purpose || $('#meetingPurpose option:first').val());
        $('#meetingStatus').val(meeting.status || $('#meetingStatus option:first').val());
        $('#meetingCustomer').val(String(meeting.customer_id));
        $('#meetingLeadOwner').val(meeting.lead_owner ? String(meeting.lead_owner) : '');
        $('#meetingDate').val(meeting.meeting_date);
        $('#meetingStart').val(meeting.start_time);
        $('#meetingEnd').val(meeting.end_time);
        $('#meetingProvider').val(meeting.provider);
        $('#meetingLink').val(meeting.meeting_link || '');
        $('#meetingGoogleEventId').val(meeting.google_event_id || '');
        $('#meetingTeamsMeetingId').val(meeting.teams_meeting_id || '');
        $('#meetingLocation').val(meeting.location || '');
        $('#meetingDescription').val(meeting.description || '');

        setMeetingType(meeting.meeting_type);
        $('#generateMeetBtn').text(meeting.google_event_id || meeting.teams_meeting_id ? 'Regenerate' : 'Generate Link');
        $('#saveMeetingBtn').text('Update Meeting');

        openMeetingEditor('#meetingFormCard');
    }

    $('#onlineBtn').on('click', function () { setMeetingType('online'); });
    $('#offlineBtn').on('click', function () { setMeetingType('offline'); });

    $('#topAddMeetingBtn').on('click', function () { showMeetingForm(null); });
    $('#cancelMeetingBtn').on('click', closeMeetingForm);

    $(document).on('click', '.add-meeting-btn', function () {
        showMeetingForm($(this).data('customer-id'));
    });

    // ----------------------------------------------------
    // MEET NOTES (Google Meet automatic Smart Notes)
    // ----------------------------------------------------
    var notesMeetingId = null;

    function renderMeetNotes(r) {
        $('#meetNotesTitle').text(r.title || '');
        $('#meetNotesStatus').html(r.source === 'ai'
            ? '<span class="badge badge-primary">AI notes · CRM Notes extension</span>'
            : (r.auto_notes_enabled
                ? '<span class="badge badge-success">Automatic notes on</span>'
                : '<span class="badge badge-secondary">Automatic notes off</span>'));

        if (r.fetched_at) {
            $('#meetNotesStatus').append($('<span class="text-muted ml-2">').text('Saved ' + r.fetched_at));
        }

        $('#meetNotesText').text(r.notes || '').toggle(!!r.notes);
        $('#meetNotesMessage').text(r.message || '').toggle(!!r.message);
        $('#meetNotesDocLink').attr('href', r.url || '#').toggle(!!r.url);
        $('#meetNotesRefreshBtn').toggle(!r.notes);
        $('#meetTranscriptText').text(r.transcript || '');
        $('#meetTranscriptBox').toggle(!!r.transcript);
    }

    // CRM Notes extension key
    $('#crmNotesSetupBtn').on('click', function () {
        $('#crmNotesKeyBox').hide();
        $('#crmNotesCreateKeyBtn').text('Create my key');
        $('#crmNotesSetupModal').modal('show');
    });

    $('#crmNotesCreateKeyBtn').on('click', function () {
        if ($('#crmNotesKeyBox').is(':visible') && !confirm('Create a new key? The old key stops working.')) return;
        var btn = $(this).prop('disabled', true);

        $.ajax({
            url: "{{ route('crm-notes.key') }}",
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (r) {
                $('#crmNotesPortalUrl').val(r.portal_url);
                $('#crmNotesKey').val(r.key);
                $('#crmNotesKeyBox').show();
                btn.text('Create a new key');
            },
            complete: function () { btn.prop('disabled', false); }
        });
    });

    $('#crmNotesKey, #crmNotesPortalUrl').on('focus click', function () { this.select(); });

    function meetNotesError(xhr) {
        var body = xhr.responseJSON;
        if (!body) {
            try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
        }
        $('#meetNotesMessage').text((body && body.message) || 'Something went wrong. Please try again.').show();
    }

    $(document).on('click', '.meet-notes-btn', function () {
        notesMeetingId = $(this).data('meeting-id');
        $('#meetNotesTitle, #meetNotesStatus').text('');
        $('#meetNotesText, #meetNotesDocLink').hide();
        $('#meetNotesMessage').html('<i class="fa fa-spinner fa-spin"></i> Loading...').show();
        $('#meetNotesModal').modal('show');

        $.ajax({
            url: "{{ route('customers.meetings.notes', ':id') }}".replace(':id', notesMeetingId),
            type: 'GET',
            success: renderMeetNotes,
            error: meetNotesError
        });
    });

    $('#meetNotesRefreshBtn').on('click', function () {
        var btn = $(this).prop('disabled', true).text('Checking...');

        $.ajax({
            url: "{{ route('customers.meetings.notes.refresh', ':id') }}".replace(':id', notesMeetingId),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (r) {
                renderMeetNotes(r);
                // Show the tick on the card once notes are saved
                if (r.notes) {
                    $('.meet-notes-btn[data-meeting-id="' + notesMeetingId + '"]').html('Meet Notes <i class="fa fa-check-circle ml-1"></i>');
                }
            },
            error: meetNotesError,
            complete: function () { btn.prop('disabled', false).text('Check now'); }
        });
    });

    // ----------------------------------------------------
    // LIVE GOOGLE MEET STATUS (who is in the call, notes running)
    // ----------------------------------------------------
    function renderLiveStatus(meetings) {
        $('.meet-live-panel').each(function () {
            var panel = $(this);
            var status = meetings[panel.data('meeting-id')];

            if (!status || !status.live) {
                panel.hide().empty();
                return;
            }

            var card = panel.closest('.meeting-card');
            var link = card.find('a[href*="meet.google.com"]').attr('href');
            var people = status.count + (status.count === 1 ? ' person' : ' people') + ' in the call';

            panel.empty().append(
                $('<div class="d-flex align-items-center flex-wrap p-2 rounded" style="gap: 8px; background: #fff5f5; border: 1px solid #fecaca;">').append(
                    '<span class="badge badge-danger"><i class="fa fa-circle mr-1" style="font-size: 8px;"></i>LIVE</span>',
                    $('<span class="small font-weight-bold">').text(people),
                    $('<span class="small text-muted">').text(status.names.join(', ')),
                    status.notes_running
                        ? '<span class="badge badge-success">Gemini notes on</span>'
                        : '<span class="badge badge-warning" title="Notes start when the company Google account joins the call">Notes not running</span>',
                    link ? $('<a target="_blank" rel="noopener" class="btn btn-sm btn-danger ml-auto">Join</a>').attr('href', link) : ''
                )
            ).show();
        });
    }

    function pollLiveStatus() {
        // Only while the Meetings tab is open and the page is visible
        if (!$('#meetingsPanel').is(':visible') || document.hidden) return;

        $.get("{{ route('customers.meetings.live-status') }}", function (r) {
            renderLiveStatus(r.meetings || {});
            renderBotStatus(r.bot || {});
        });
    }

    // CRM Notetaker bot badge: green while the bot is checking in, red with the last time it was seen
    function renderBotStatus(bot) {
        var badge = $('#botStatusBadge').removeClass('badge-light badge-success badge-danger');

        if (bot.online) {
            badge.addClass('badge-success').html('&#9679; Bot online')
                .attr('title', 'CRM Notetaker bot is running and will join Google Meet meetings automatically.');
        } else {
            badge.addClass('badge-danger').html('&#9679; Bot offline')
                .attr('title', (bot.last_seen ? 'Last seen ' + bot.last_seen + '. ' : '')
                    + 'Start it on the bot PC: double-click notetaker-bot/start-bot.vbs (it also starts at Windows login).');
        }
    }

    pollLiveStatus();
    setInterval(pollLiveStatus, 30000);
    $('.tab-btn[data-tab="meetings"]').on('click', function () { setTimeout(pollLiveStatus, 100); });

    $(document).on('click', '.edit-meeting-btn', function () {
        editMeeting($(this).data('meeting'));
    });

    $(document).on('click', '.delete-meeting-btn', function () {
        var id = $(this).data('meeting-id');

        if (!confirm('Delete this meeting? Its Google Calendar event (if any) will be cancelled.')) return;

        $.easyAjax({
            url: "{{ route('customers.meetings.destroy', ':id') }}".replace(':id', id),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'DELETE' },
            success: function (r) {
                if (r.status === 'success') reloadOnMeetings();
            }
        });
    });

    // After saving or deleting a meeting, come back to the Meetings tab
    function reloadOnMeetings() {
        location.hash = 'meetings';
        location.reload();
    }

    if (location.hash === '#meetings') {
        $('.tab-btn[data-tab="meetings"]').trigger('click');
    }

    // "+ Add" beside Customer: create the customer in the pop-up, then select it here
    $('#meetingAddCustomerBtn').on('click', function () {
        window.onCustomerCreated = function (customer) {
            var option = $('<option>').val(customer.id).text(customer.company_name + ' (' + customer.customer_name + ')');
            $('#meetingCustomer').append(option).val(String(customer.id));
            showMeetingError('customer_id', '');
        };
        openCustomerModal();
    });

    $('#customerModal').on('hidden.bs.modal', function () {
        window.onCustomerCreated = null;
    });

    function showMeetingError(name, message) {
        meetingForm.find('[name="' + name + '"]').toggleClass('is-invalid', !!message);
        meetingForm.find('[data-error-for="' + name + '"]').text(message || '');
    }

    function validateMeeting() {
        var errors = {};
        var start = $('#meetingStart').val();
        var end = $('#meetingEnd').val();

        if (!$('#meetingCustomer').val()) errors.customer_id = 'Select a customer.';
        if (!$('#meetingDate').val()) errors.meeting_date = 'Select the meeting date.';
        if (!start) errors.start_time = 'Select the start time.';
        if (!end) errors.end_time = 'Select the end time.';
        if (start && end && end <= start) errors.end_time = 'End time must be after the start time.';

        ['location', 'meeting_link'].forEach(function (name) {
            var field = meetingForm.find('[name="' + name + '"]');
            if (!field.prop('disabled') && emojiPattern.test(field.val())) errors[name] = 'Emojis are not allowed.';
        });

        ['customer_id', 'lead_owner', 'meeting_date', 'start_time', 'end_time', 'location', 'meeting_link'].forEach(function (name) {
            showMeetingError(name, errors[name] || '');
        });

        return Object.keys(errors).length === 0;
    }

    meetingForm.on('change input', ':input', function () {
        if (meetingForm.find('.is-invalid').length) validateMeeting();
    });

    meetingForm.on('submit', function (e) {
        e.preventDefault();

        if (!validateMeeting()) {
            meetingForm.find('.is-invalid').first().focus();
            return;
        }

        var btn = $('#saveMeetingBtn');
        var meetingId = $('#meetingId').val();
        var btnText = meetingId ? 'Update Meeting' : 'Create Meeting';
        var data = meetingForm.serialize() + (meetingId ? '&_method=PUT' : '');

        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: meetingId
                ? "{{ route('customers.meetings.update', ':id') }}".replace(':id', meetingId)
                : "{{ route('customers.meetings.store') }}",
            type: 'POST',
            data: data,
            success: function (r) {
                if (r.status === 'success') {
                    reloadOnMeetings();
                } else {
                    btn.prop('disabled', false).text(btnText);
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text(btnText);
                var errors = xhr.responseJSON && xhr.responseJSON.errors;

                if (errors) {
                    Object.keys(errors).forEach(function (name) {
                        showMeetingError(name, errors[name][0]);
                    });
                    meetingForm.find('.is-invalid').first().focus();
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to save the meeting. Please try again.' });
                }
            }
        });
    });

    setMeetingType('offline');

    // ----------------------------------------------------
    // DELETE CUSTOMER
    // ----------------------------------------------------
    $(document).on('click', '.delete-customer-btn', function () {
        var id = $(this).data('customer-id');

        if (!confirm('Delete this customer and all their meetings?')) return;

        $.easyAjax({
            url: "{{ route('customers.destroy', ':id') }}".replace(':id', id),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'DELETE' },
            success: function (r) {
                if (r.status === 'success') location.reload();
            }
        });
    });
});
</script>
@endpush
