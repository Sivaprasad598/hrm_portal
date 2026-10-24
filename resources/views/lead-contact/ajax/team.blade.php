{{--
    Lead page, "Lead Team" tab: the lead contact, the lead owner and the lead team (Assign Team on the lead form).
    Tick who should attend and "Create Meeting" opens the meeting form for this lead with them in its Invite list.
    "Lead Team Meetings" (beside Manage team): this lead's meetings saved with an Invite list; ?view=meetings opens it.
    "Team Chat": group and one-to-one chats of the lead team (lead-contact.partials.team-chat); ?view=chat opens it.
    $leadContact, $teamPeople (LeadTeamController::people), $teamMeetings, $teamView ('team' | 'chat' | 'meetings')
--}}
@php
    $editLeadPermission = user()->permission('edit_lead');
@endphp

<div class="row pb-5">
    <div class="col-12">
        <div class="card bg-white border-0 b-shadow-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap p-20" style="gap: 10px;">
                <div>
                    <h4 class="f-18 f-w-500 mb-1">Lead Team</h4>
                    <p class="text-lightest f-13 mb-0">The lead, its owner and the employees assigned to it. Tick who should attend, then create the meeting.</p>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                    {{-- Lead Team / Lead Team Meetings switch --}}
                    <div class="lt-switch" role="tablist" aria-label="Lead Team views">
                        <button type="button" class="lt-switch-btn lead-team-view {{ $teamView === 'team' ? 'active' : '' }}" data-view="team" role="tab">
                            <i class="fa fa-users"></i> Lead Team</button>
                        <button type="button" class="lt-switch-btn lead-team-view {{ $teamView === 'chat' ? 'active' : '' }}" data-view="chat" role="tab">
                            <i class="fa fa-comments"></i> Team Chat
                            <span class="lt-count" id="ltChatUnread" title="Unread messages"></span></button>
                        <button type="button" class="lt-switch-btn lead-team-view {{ $teamView === 'meetings' ? 'active' : '' }}" data-view="meetings" role="tab">
                            <i class="fa fa-calendar-alt"></i> Lead Team Meetings
                            <span class="lt-count">{{ $teamMeetings->count() }}</span></button>
                    </div>
                    {{-- Manage team: not on the Lead Team page (lead_team_view passes hideManageTeam) --}}
                    @if ($editLeadPermission != 'none' && empty($hideManageTeam))
                        <a href="{{ route('lead-contact.edit', $leadContact->id) }}" class="lt-manage-btn openRightModal">
                            <i class="fa fa-user-edit"></i> Manage team</a>
                    @endif
                </div>
            </div>

            {{-- Lead Team view: the people and Create Meeting --}}
            <div id="leadTeamBody" @if ($teamView !== 'team') style="display: none;" @endif>

            <div class="table-responsive">
                <table class="table mb-0" id="leadTeamTable">
                    <thead class="thead-light">
                        <tr>
                            {{-- Tick boxes (who attends a new meeting): not on the Lead Team page (hideAttendeeSelect) --}}
                            @if (empty($hideAttendeeSelect))
                                <th style="width: 40px;">
                                    <input type="checkbox" id="leadTeamAll" checked title="Select everyone" aria-label="Select everyone">
                                </th>
                            @endif
                            <th>Name</th>
                            <th>Role</th>
                            <th>Designation</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamPeople as $person)
                            <tr>
                                @if (empty($hideAttendeeSelect))
                                    <td>
                                        <input type="checkbox" class="lead-team-check" value="{{ $person['key'] }}" checked
                                               id="lead-team-{{ $person['key'] }}" aria-label="Include {{ $person['name'] }}">
                                    </td>
                                @endif
                                <td>
                                    <label for="lead-team-{{ $person['key'] }}" class="mb-0 text-dark-grey f-14" style="cursor: pointer;">
                                        {{ $person['name'] }}
                                    </label>
                                    @unless ($person['active'])
                                        <span class="badge badge-secondary ml-1">Inactive</span>
                                    @endunless
                                </td>
                                <td>
                                    <span class="badge badge-light border">{{ $person['role'] }}</span>
                                </td>
                                <td class="f-14">{{ $person['designation'] }}</td>
                                <td class="f-14">
                                    @if ($person['email'] !== '--')
                                        <a href="mailto:{{ $person['email'] }}">{{ $person['email'] }}</a>
                                    @else
                                        <span class="text-lightest" title="No email: saved with the meeting but not sent an invite">--</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (count($teamPeople) === 1)
                <p class="text-lightest f-13 px-20 pt-3 mb-0">No team members yet. Use <b>Manage team</b> (Assign Team on the lead form) to add employees.</p>
            @endif

            {{-- Create Meeting and "3 of 3 selected": the whole bar goes when both are off --}}
            @if (empty($hideCreateMeeting) || empty($hideAttendeeSelect))
            <div class="d-flex align-items-center flex-wrap p-20 border-top-grey mt-3" style="gap: 12px;" id="leadTeamActions">
                {{-- Create Meeting: not on the Lead Team page (hideCreateMeeting) --}}
                @if (empty($hideCreateMeeting))
                    <button type="button" class="btn btn-primary" id="leadTeamCreateMeeting">
                        <i class="fa fa-calendar-plus mr-1"></i> Create Meeting
                    </button>
                @endif
                @if (empty($hideAttendeeSelect))
                    <span class="text-lightest f-13" id="leadTeamCount"></span>
                @endif
            </div>
            @endif
            </div>
        </div>
    </div>
</div>

{{-- ==================== LEAD TEAM MEETINGS ==================== --}}
<div id="leadTeamMeetingsPanel" @if ($teamView !== 'meetings') style="display: none;" @endif>
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap: 8px;">
        <div>
            <h4 class="f-18 f-w-500 mb-1">Lead Team Meetings</h4>
            <p class="text-lightest f-13 mb-0">Meetings with this lead created from the Lead Team, with who was invited.</p>
        </div>
        <div class="d-flex align-items-center" style="gap: 10px;">
            <span class="badge badge-light border align-self-stretch d-inline-flex align-items-center px-3 bot-status-badge"
                  style="font-size: 13px; border-radius: 8px;"
                  title="CRM Notetaker bot: joins every Google Meet meeting automatically and takes notes">Bot: checking…</span>
            @if (empty($hideCreateMeeting))
                <button type="button" class="btn btn-primary btn-sm" id="leadTeamNewMeeting"><i class="fa fa-calendar-plus mr-1"></i> Create Meeting</button>
            @endif
        </div>
    </div>
    @include('customers.partials.contact-meetings', [
        'meetings' => $teamMeetings,
        'from' => 'lead-contact',
        'contactParam' => 'lead_id',
        'contactId' => $leadContact->id,
        'contactLabel' => 'Lead',
        'withLeadMeetingNotes' => true,
        'hideMeetingActions' => !empty($hideMeetingActions),
    ])
</div>

{{-- ==================== CREATE MEETING (opens below the table) ====================
     A lead meeting for this lead, saved like one made on the Meetings page. The ticks in the table above are
     the Invite list: those people are saved with the meeting and get the invite. --}}
<div class="row pb-5" id="leadTeamMeeting" style="display: none;">
    <div class="col-12">
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body">
                <h4 class="f-16 f-w-500 mb-1">Meet with Lead Team Members</h4>
                <p class="text-lightest f-13 mb-3">Choose Online Meeting or Offline Meeting</p>
                <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                    <button type="button" class="btn btn-outline-primary" id="onlineBtn">Online Meeting</button>
                    <button type="button" class="btn btn-primary" id="offlineBtn">Offline Meeting</button>
                    {{-- CRM Notetaker bot: joins Google Meet meetings and takes notes (same badge as the Meetings page) --}}
                    <span class="badge badge-light border align-self-stretch d-inline-flex align-items-center px-3 bot-status-badge" id="botStatusBadge"
                          style="font-size: 13px; border-radius: 8px;"
                          title="CRM Notetaker bot: joins every Google Meet meeting automatically and takes notes">Bot: checking…</span>
                </div>
            </div>
        </div>

        <div class="card bg-white border-0 b-shadow-4">
            <div class="card-body">
                <h3 class="h5 mb-1 font-weight-bold" id="meetingFormTitle">Create Offline Lead Team Meeting</h3>
                <p class="text-muted small mb-4">Schedule the meeting with this lead and its team members</p>

                <form id="leadTeamMeetingForm" novalidate>
                    @csrf
                    <input type="hidden" name="meeting_type" id="meetingType" value="offline">
                    <input type="hidden" name="attendees_sent" value="1">
                    {{-- Saved as its own Lead Team Meeting record (lead_team_meetings), linked to this lead --}}
                    <input type="hidden" name="lead_team_meeting" value="1">
                    {{-- A meeting with this lead's whole team: the lead and its owner are fixed, so no Leads / Lead Owner fields --}}
                    <input type="hidden" name="lead_id" value="{{ $leadContact->id }}">
                    <input type="hidden" name="lead_owner" value="{{ $leadContact->lead_owner }}">

                    @include('customers.partials.meeting-fields', ['values' => $meetingValues, 'meetingWith' => 'lead', 'withAddButtons' => false, 'hideContactFields' => true])

                    <div class="form-group mb-4">
                        <label class="d-block mb-1">Invite</label>
                        <div id="leadTeamInviteSummary" class="f-14"></div>
                        <small class="text-muted">Change who is invited with the ticks in the Lead Team table above. For Google Meet they also get the calendar invite.</small>
                        <div class="invalid-feedback d-block" data-error-for="attendees"></div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="saveMeetingBtn">Create Meeting</button>
                    <button type="button" class="btn btn-outline-secondary ml-2" id="cancelMeetingBtn">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ==================== TEAM CHAT ==================== --}}
@include('lead-contact.partials.team-chat')

{{-- Lead Team Meeting Notes on the cards: styles and Analyse / Edit --}}
@include('lead-contact.partials.lead-meeting-notes-assets')

<style>
    /* Lead Team / Lead Team Meetings switch and Manage team: same height, one rounded group */
    .lt-switch { display: inline-flex; padding: 3px; background: #f1f3f9; border: 1px solid #e3e7f1; border-radius: 8px; }
    .lt-switch-btn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 14px; border: 0; border-radius: 6px;
        background: transparent; color: #5a6276; font-size: 13px; font-weight: 500; line-height: 1; cursor: pointer; transition: background .15s, color .15s; }
    .lt-switch-btn:hover { color: var(--header_color, #1d82f5); }
    .lt-switch-btn.active { background: #fff; color: var(--header_color, #1d82f5); box-shadow: 0 1px 3px rgba(23, 43, 77, .14); }
    .lt-switch-btn:focus { outline: none; }
    .lt-switch-btn:focus-visible { box-shadow: 0 0 0 2px rgba(29, 130, 245, .35); }
    .lt-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 18px; padding: 0 6px;
        border-radius: 9px; background: #e3e7f1; color: #5a6276; font-size: 11px; font-weight: 600; }
    .lt-switch-btn.active .lt-count { background: var(--header_color, #1d82f5); color: #fff; }
    .lt-manage-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 14px; border: 1px solid #e3e7f1;
        border-radius: 8px; background: #fff; color: #5a6276; font-size: 13px; font-weight: 500; text-decoration: none; }
    /* The meeting just created, after the redirect */
    .lead-meeting-card.lt-new-meeting { box-shadow: 0 0 0 3px rgba(79, 70, 229, .45), 0 8px 24px rgba(79, 70, 229, .18); transition: box-shadow .3s; }
    .lt-manage-btn:hover { color: var(--header_color, #1d82f5); border-color: var(--header_color, #1d82f5); text-decoration: none; }
</style>
<script>
    $(function () {
        const STORE_URL = "{{ route('customers.meetings.store') }}";
        const GENERATE_URL = "{{ route('customers.meetings.generate-meet') }}";
        // After saving: the new meeting in Lead Team Meetings
        // After saving: Lead Team Meetings, on the lead page or on the Lead Team page (lead_team_view)
        const MEETINGS_TAB = @json(isset($teamPageUrl) ? $teamPageUrl . '?view=meetings' : route('lead-contact.show', $leadContact->id) . '?tab=team&view=meetings');
        const form = $('#leadTeamMeetingForm');

        if (window.refreshMeetingPickers) refreshMeetingPickers();

        function toast(text, icon = 'success') {
            Swal.fire({icon: icon, text: text, toast: true, position: 'top-end', timer: 3000,
                timerProgressBar: true, showConfirmButton: false});
        }

        function checkedKeys() {
            return $('.lead-team-check:checked').map((i, el) => el.value).get();
        }

        function updateCount() {
            let n = $('.lead-team-check:checked').length, total = $('.lead-team-check').length;
            $('#leadTeamCount').text(`${n} of ${total} selected to attend`);
            $('#leadTeamAll').prop('checked', n === total).prop('indeterminate', n > 0 && n < total);

            // The form's Invite line follows the ticks
            let names = $('.lead-team-check:checked').map((i, el) => $(el).closest('tr').find('label').text().trim()).get();
            $('#leadTeamInviteSummary').html(names.length
                ? names.map(n => `<span class="badge badge-light border mr-1 mb-1 f-13">${$('<span>').text(n).html()}</span>`).join('')
                : '<span class="text-danger">Nobody is ticked.</span>');
        }

        $('#leadTeamAll').on('change', function () {
            $('.lead-team-check').prop('checked', this.checked);
            updateCount();
        });
        $('.lead-team-check').on('change', updateCount);

        // ---------- Online / Offline ----------
        function setMeetingType(type) {
            let online = type === 'online';
            $('#meetingType').val(type);
            $('#meetingFormTitle').text(`Create ${online ? 'Online' : 'Offline'} Lead Team Meeting`);
            $('#onlineBtn').toggleClass('btn-primary', online).toggleClass('btn-outline-primary', !online);
            $('#offlineBtn').toggleClass('btn-primary', !online).toggleClass('btn-outline-primary', online);
            // Hidden fields are disabled so they are not submitted
            form.find('.online-only').toggle(online).find(':input').prop('disabled', !online);
            form.find('.offline-only').toggle(!online).find(':input').prop('disabled', online);
        }

        $('#onlineBtn').on('click', () => setMeetingType('online'));
        $('#offlineBtn').on('click', () => setMeetingType('offline'));

        // ---------- Errors ----------
        function showErrors(errors) {
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('[data-error-for]').text('');
            $.each(errors || {}, function (name, messages) {
                let field = name.replace(/\.\d+$/, '');
                form.find('[name="' + field + '"]').addClass('is-invalid');
                form.find('[data-error-for="' + field + '"]').text([].concat(messages)[0]);
            });
            let first = form.find('.is-invalid, [data-error-for]:not(:empty)').first();
            if (first.length) $('html, body').animate({scrollTop: first.offset().top - 120}, 200);
        }

        function payload() {
            return form.serialize() + '&' + $.param({attendees: checkedKeys()});
        }

        // ---------- Generate Link (online) ----------
        $('#generateMeetBtn').on('click', function () {
            let btn = $(this).prop('disabled', true).text('Generating...');
            $.ajax({url: GENERATE_URL, type: 'POST', data: payload()})
                .done(r => {
                    if (r.meeting_link) {
                        $('#meetingLink').val(r.meeting_link);
                        $('#meetingGoogleEventId').val(r.google_event_id || '');
                        $('#meetingTeamsMeetingId').val(r.teams_meeting_id || '');
                        toast('Meeting link generated.');
                    }
                })
                .fail(xhr => showErrors(xhr.responseJSON?.errors || {meeting_link: xhr.responseJSON?.message || 'Could not generate the link.'}))
                .always(() => btn.prop('disabled', false).text('Generate Link'));
        });

        // ---------- Open / close / save ----------
        $('#leadTeamCreateMeeting').on('click', function () {
            if (!checkedKeys().length) return toast('Select at least one person to attend.', 'warning');
            $('#leadTeamActions').removeClass('d-flex').addClass('d-none');
            $('#leadTeamMeeting').slideDown(200, function () {
                if (window.refreshMeetingPickers) refreshMeetingPickers();
                $('html, body').animate({scrollTop: $('#leadTeamMeeting').offset().top - 80}, 250);
            });
        });

        $('#cancelMeetingBtn').on('click', function () {
            $('#leadTeamMeeting').slideUp(200);
            $('#leadTeamActions').removeClass('d-none').addClass('d-flex');
            showErrors({});
        });

        form.on('submit', function (e) {
            e.preventDefault();
            if (!checkedKeys().length) return showErrors({attendees: 'Select at least one person to invite.'});

            let btn = $('#saveMeetingBtn').prop('disabled', true).text('Saving...');
            $.ajax({url: STORE_URL, type: 'POST', data: payload(), headers: {Accept: 'application/json'}})
                .done(r => {
                    let emailed = $('.lead-team-check:checked').filter((i, el) => $(el).closest('tr').find('a[href^="mailto:"]').length).length;
                    toast(`Lead team meeting created. Invitation email sent to ${emailed} ${emailed === 1 ? 'person' : 'people'}.`);
                    // Lead Team Meetings, on the new meeting's card
                    setTimeout(() => location.href = MEETINGS_TAB + (r.meeting_id ? '#meeting-' + r.meeting_id : ''), 800);
                })
                .fail(xhr => {
                    btn.prop('disabled', false).text('Create Meeting');
                    showErrors(xhr.responseJSON?.errors || {title: xhr.responseJSON?.message || 'Could not save the meeting.'});
                });
        });

        // ---------- Notetaker bot status (polled while the meeting form or Lead Team Meetings is open) ----------
        function pollBotStatus() {
            if (!$('.bot-status-badge:visible').length || document.hidden) return;
            $.get("{{ route('customers.meetings.live-status') }}", function (r) {
                renderLiveStatus(r.meetings || {});
                let bot = r.bot || {};
                let badge = $('.bot-status-badge').removeClass('badge-light badge-success badge-danger');
                if (bot.online) {
                    badge.addClass('badge-success').html('&#9679; Bot online')
                        .attr('title', 'CRM Notetaker bot is running and will join Google Meet meetings automatically.');
                } else {
                    badge.addClass('badge-danger').html('&#9679; Bot offline')
                        .attr('title', (bot.last_seen ? 'Last seen ' + bot.last_seen + '. ' : '')
                            + 'Start it on the bot PC: double-click notetaker-bot/start-bot.vbs (it also starts at Windows login).');
                }
            });
        }

        // LIVE panel on the Lead Team Meetings cards: who is in the Google Meet call, notes running, Join
        function renderLiveStatus(meetings) {
            $('#leadTeamMeetingsPanel .meet-live-panel').each(function () {
                let panel = $(this);
                let status = meetings[panel.data('meeting-id')];

                if (!status || !status.live) return panel.hide().empty();

                let link = panel.closest('.meeting-card').find('a[href*="meet.google.com"]').attr('href');
                let people = status.count + (status.count === 1 ? ' person' : ' people') + ' in the call';

                panel.empty().append(
                    $('<div class="d-flex align-items-center flex-wrap p-2 rounded" style="gap: 8px; background: #fff5f5; border: 1px solid #fecaca;">').append(
                        '<span class="badge badge-danger"><i class="fa fa-circle mr-1" style="font-size: 8px;"></i>LIVE</span>',
                        $('<span class="small font-weight-bold">').text(people),
                        $('<span class="small text-muted">').text((status.names || []).join(', ')),
                        status.notes_running
                            ? '<span class="badge badge-success">Gemini notes on</span>'
                            : '<span class="badge badge-warning" title="Notes start when the company Google account joins the call">Notes not running</span>',
                        link ? $('<a target="_blank" rel="noopener" class="btn btn-sm btn-danger ml-auto">Join</a>').attr('href', link) : ''
                    )
                ).show();
            });
        }

        $('#leadTeamCreateMeeting').on('click', () => setTimeout(pollBotStatus, 300));
        setInterval(pollBotStatus, 30000);

        // ---------- Lead Team / Lead Team Meetings ----------
        function showView(view) {
            $('.lead-team-view').each(function () {
                $(this).toggleClass('active', $(this).data('view') === view);
            });
            $('#leadTeamBody').toggle(view === 'team');
            $('#leadTeamMeetingsPanel').toggle(view === 'meetings');
            $('#leadTeamChatPanel').toggle(view === 'chat');
            if (view !== 'team') $('#leadTeamMeeting').hide();
            history.replaceState(null, '', location.pathname + '?tab=team' + (view !== 'team' ? '&view=' + view : ''));
            $(document).trigger('lead-team-view', [view]);
        }

        $('.lead-team-view').on('click', function () {
            showView($(this).data('view'));
            setTimeout(pollBotStatus, 100);
        });

        // From Lead Team Meetings: back to the people and straight into the form
        $('#leadTeamNewMeeting').on('click', function () {
            showView('team');
            $('#leadTeamCreateMeeting').trigger('click');
        });

        // Arrived from saving (#meeting-<id>): open its project, slide the carousel to its card and highlight it
        function focusMeeting() {
            let id = (location.hash.match(/^#meeting-(\d+)$/) || [])[1];
            let card = id && $('#leadTeamMeetingsPanel .lead-meeting-card[data-meeting-id="' + id + '"]');
            if (!card || !card.length) return;

            let header = card.closest('.project-meetings-group').find('.project-group-header');
            if (header.hasClass('collapsed')) header.trigger('click');

            setTimeout(function () {
                let track = card.closest('.meeting-track')[0];
                let slide = card.closest('.meeting-slide')[0];
                if (track && slide) track.scrollTo({left: slide.offsetLeft - track.offsetLeft, behavior: 'auto'});
                if (window.updateMeetingCarousels) updateMeetingCarousels();
                $('html, body').animate({scrollTop: card.offset().top - 140}, 300);
                card.addClass('lt-new-meeting');
                setTimeout(() => card.removeClass('lt-new-meeting'), 4000);
            }, 300);
        }

        setMeetingType('offline');
        updateCount();
        focusMeeting();
        pollBotStatus();
    });
</script>
