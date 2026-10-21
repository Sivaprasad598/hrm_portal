<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Customer;
use App\Models\CustomerMeeting;
use App\Models\Lead;
use App\Models\LeadProject;
use App\Models\MeetingOption;
use App\Models\User;
use App\Services\CustomerMeetingNotifier;
use App\Services\Google;
use App\Services\GoogleMeetNotes;
use App\Services\MicrosoftTeams;
use Carbon\Carbon;
use Google_Service_Calendar_ConferenceData;
use Google_Service_Calendar_Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends AccountBaseController
{
    private const EMOJI_PATTERN = '/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u';

    private const CONTACT_MESSAGES = [
        'client_id.in'             => 'Select a valid client.',
        'lead_id.required_without' => 'Select a lead.',
    ];

    private const END_REASONS = ['Customer Not Interested', 'Budget Issue', 'Requirement Not Suitable', 'Price Too High', 'Project Cancelled', 'Project Postponed', 'Competitor Selected', 'Decision Pending', 'Other'];



    public function __construct()
    {
        parent::__construct();
        $this->pageTitle         = 'Customers';
        $this->activeSettingMenu = 'customers';
    }

    /**
     * Main page with tabs (Customers / Meetings).
     */
    public function index(Request $request)
    {
        // Opened as the Meetings tab of Lead Contacts or Clients: that page's title and breadcrumb and only its
        // meetings; from a lead's or client's own Meetings tab, Back and saving return to it
        $from = $request->get('from');
        $this->meetingWith = $from === 'client' ? 'client' : 'lead';
        $this->backUrl = null;
        $this->returnAfterSave = false;
        $this->contactListUrl = null; // set when shown as the Meetings tab of Lead Contacts / Clients
        $meetings = CustomerMeeting::with(['customer', 'lead', 'client', 'leadProject'])->orderByDesc('created_at');

        if ($from === 'lead-contact') {
            $this->pageTitle = __('modules.leadContact.leadContacts');
            $this->contactListUrl = route('lead-contact.index');
            $meetings->whereNotNull('lead_id');

            // From a lead's Meetings tab: Back (and saving) returns to that tab
            if ($request->get('lead_id')) {
                $this->backUrl = route('lead-contact.show', $request->get('lead_id')) . '?tab=meetings';
                $this->returnAfterSave = true;
            }
        } elseif ($from === 'client') {
            $this->pageTitle = __('app.menu.clients');
            $this->contactListUrl = route('clients.index');
            $meetings->whereNotNull('client_id');

            // From a client's Meetings tab: Back (and saving) returns to that tab
            if ($request->get('client_id')) {
                $this->backUrl = route('clients.show', $request->get('client_id')) . '?tab=meetings';
                $this->returnAfterSave = true;
            }
        }

        $this->customers = Customer::with('meetings')->orderByDesc('id')->get();
        $this->meetings  = $meetings->get();
        $this->leads     = Lead::orderBy('client_name')->get();
        $this->clients   = User::allClients();
        $this->leadProjects = LeadProject::orderBy('name')->get();
        $this->employees = User::allEmployees(null, true);
        $this->meetingPurposes = MeetingOption::ofType('purpose')->get();
        $this->meetingStatuses = MeetingOption::ofType('status')->get();

        return view('customers.index', $this->data);
    }

    /**
     * Create customer: its own page from "+ Add Customer", or the pop-up form (AJAX) from the meeting form's "+ Add".
     */
    public function createCustomer(Request $request)
    {
        $this->employees = User::allEmployees(null, true);

        if (!$request->ajax()) {
            $this->pageTitle = 'Add Customer';
            $this->fullPage  = true;

            return view('customers.create', $this->data);
        }

        $html = view('customers.ajax.create-customer', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Store new customer.
     */
    public function storeCustomer(Request $request)
    {
        $customer = Customer::create($this->validatedCustomer($request) + ['added_by' => user()->id]);

        return Reply::successWithData('Customer created successfully.', [
            'customer' => $customer->only(['id', 'customer_name', 'company_name']),
        ]);
    }

    /**
     * Edit customer page (same form as Add Customer, filled in).
     */
    public function editCustomer($id)
    {
        $this->customer  = Customer::findOrFail($id);
        $this->employees = User::allEmployees(null, true);
        $this->pageTitle = 'Edit Customer';
        $this->fullPage  = true;

        return view('customers.create', $this->data);
    }

    /**
     * Update customer.
     */
    public function updateCustomer(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->update($this->validatedCustomer($request));

        return Reply::successWithData('Customer updated successfully.', [
            'customer' => $customer->only(['id', 'customer_name', 'company_name']),
        ]);
    }

    /**
     * Shared validation for creating and updating a customer; returns the columns to save.
     */
    private function validatedCustomer(Request $request): array
    {
        $request->merge([
            'customer_name' => preg_replace('/\s+/', ' ', trim((string) $request->customer_name)),
            'company_name'  => trim((string) $request->company_name),
            'email'         => trim((string) $request->email) ?: null,
            // The form shows +91 as a fixed prefix; keep only the 10 digits typed after it
            'mobile'        => preg_replace('/^(\+?91)(?=\d{10}$)/', '', preg_replace('/[\s\-]+/', '', (string) $request->mobile)) ?: null,
        ]);

        $noEmoji = function ($attribute, $value, $fail) {
            if (preg_match(self::EMOJI_PATTERN, (string) $value)) {
                $fail('Emojis are not allowed.');
            }
        };

        $employeeIds = User::allEmployees(null, true)->pluck('id')->all();

        $request->validate([
            'customer_name' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z ]+$/'],
            'company_name'  => ['required', 'string', 'max:191', 'not_regex:/[0-9]/', 'regex:/[A-Za-z]/', $noEmoji],
            'email'         => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $noEmoji],
            'mobile'        => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'lead_owner'    => ['nullable', 'integer', Rule::in($employeeIds)],
        ], [
            'customer_name.regex' => 'Customer name can contain only letters and spaces.',
            'company_name.not_regex' => 'Company name cannot contain numbers.',
            'company_name.regex'     => 'Company name must contain letters.',
            'email.regex'         => 'Enter a valid email address, e.g. name@gmail.com.',
            'mobile.regex'        => 'Enter a valid 10-digit mobile number starting with 6, 7, 8 or 9.',
            'lead_owner.in'       => 'Select a valid lead owner.',
        ]);

        return [
            'customer_name' => $request->customer_name,
            'company_name'  => $request->company_name,
            'email'         => $request->email,
            'mobile'        => $request->mobile ? '+91 ' . $request->mobile : null,
            // lead_owner column stores the owner's name (existing data and the meetings form use names)
            'lead_owner'    => $request->lead_owner ? User::find($request->lead_owner)->name : null,
        ];
    }

    /**
     * Show create meeting modal (online or offline).
     */
    public function createMeeting(Request $request)
    {
        $this->customers   = Customer::orderBy('company_name')->get();
        $this->meetingType = $request->get('type', 'offline'); // online | offline

        $html = view('customers.ajax.create-meeting', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Store new meeting.
     */
    public function storeMeeting(Request $request)
    {
        $this->saveMeeting($request, new CustomerMeeting(['added_by' => user()->id]));

        return Reply::success('Meeting saved successfully.');
    }

    /**
     * Update an existing meeting.
     */
    public function updateMeeting(Request $request, $id)
    {
        $this->saveMeeting($request, CustomerMeeting::findOrFail($id));

        return Reply::success('Meeting updated successfully.');
    }

    private function saveMeeting(Request $request, CustomerMeeting $meeting): void
    {
        $noEmoji = function ($attribute, $value, $fail) {
            if (preg_match(self::EMOJI_PATTERN, (string) $value)) {
                $fail('Emojis are not allowed.');
            }
        };

        $employeeIds = User::allEmployees(null, true)->pluck('id')->all();

        $request->validate($this->contactRules() + [
            'meeting_type'   => 'required|in:online,offline',
            'title'          => ['nullable', 'string', 'max:191', $noEmoji],
            'lead_project_id' => ['nullable', Rule::exists('lead_projects', 'id')->where('company_id', company()->id)],
            'purpose'        => ['nullable', Rule::in(MeetingOption::ofType('purpose')->pluck('name')->all())],
            'status'         => ['nullable', Rule::in(MeetingOption::ofType('status')->pluck('name')->all())],
            'lead_owner'     => ['nullable', 'integer', Rule::in($employeeIds)],
            'meeting_date'   => 'required|date',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i|after:start_time',
            'provider'       => ['nullable', Rule::in(['Google Meet', 'Microsoft Teams'])],
            'meeting_link'   => ['nullable', 'string', 'max:500', $noEmoji],
            'google_event_id' => ['nullable', 'string', 'max:255'],
            'teams_meeting_id' => ['nullable', 'string', 'max:500'],
            'location'       => ['nullable', 'string', 'max:255', $noEmoji],
            'description'    => ['nullable', 'string', $noEmoji],
        ], [
            'lead_owner.in'         => 'Select a valid lead owner.',
            'end_time.after'        => 'End time must be after the start time.',
            'purpose.in'            => 'Select a valid meeting purpose.',
            'lead_project_id.exists' => 'Select a valid project.',
            'status.in'             => 'Select a valid meeting status.',
        ] + self::CONTACT_MESSAGES);

        $link = $request->meeting_link;
        $googleEventId = null;
        $previousEventId = $meeting->google_event_id;
        $previousTeamsId = $meeting->teams_meeting_id;
        $teamsMeetingId = null;
        $previousStart = $meeting->exists ? $meeting->startsAt()?->format('Y-m-d H:i') : null;
        $previousEnd = $meeting->exists ? $meeting->endsAt()?->format('Y-m-d H:i') : null;

        // Link made with "Generate Link" (or kept from before): update the event to the final details,
        // or drop it if the link was replaced
        if ($request->google_event_id) {
            $googleEventId = $this->syncGeneratedMeet($request);
        }

        // Auto-generate meeting link for online meetings
        if ($request->meeting_type === 'online' && empty($link)) {
            if ($request->provider === 'Google Meet') {
                [$link, $googleEventId] = $this->createGoogleMeet($request, true);
            } elseif ($request->provider === 'Microsoft Teams') {
                [$link, $teamsMeetingId] = $this->createTeamsMeeting($request);
            }
        } elseif ($request->meeting_type === 'online' && $request->provider === 'Microsoft Teams' && $request->teams_meeting_id) {
            // Link made with "Generate Link" (or kept from before): move the Teams meeting to the final time
            $teamsMeetingId = $this->syncTeamsMeeting($request, $link);
        }

        // Teams meeting no longer used (link replaced, other provider, offline): delete it in Teams
        if ($previousTeamsId && $previousTeamsId !== $teamsMeetingId) {
            $this->deleteTeamsMeeting($previousTeamsId);
        }

        // Edited to a new link: cancel the meeting's old calendar event
        if ($previousEventId && $previousEventId !== $googleEventId) {
            $this->deleteGoogleEvent($previousEventId, 'all');
        }

        $meeting->fill([
            // A meeting is with a client or a lead (customer_id is only on meetings made before leads)
            'client_id'      => $request->client_id ?: null,
            'lead_id'        => $request->client_id ? null : $request->lead_id,
            'customer_id'    => null,
            'meeting_type'   => $request->meeting_type,
            'title'          => $request->title,
            'lead_project_id' => $request->lead_project_id ?: null,
            'purpose'        => $request->purpose,
            'status'         => $request->status ?? 'Planned',
            // Stored as the owner's name, like customers.lead_owner
            'lead_owner'     => $request->lead_owner ? User::find($request->lead_owner)->name : null,
            'provider'       => $request->meeting_type === 'online' ? $request->provider : null,
            'meeting_link'   => $request->meeting_type === 'online' ? $link : null,
            'google_event_id' => $googleEventId,
            'teams_meeting_id' => $teamsMeetingId,
            'meeting_date'   => $request->meeting_date,
            'start_time'     => $request->start_time,
            'end_time'       => $request->end_time,
            'location'       => $request->meeting_type === 'offline' ? $request->location : null,
            'description'    => $request->description,
        ]);

        $isNew = !$meeting->exists;

        // Moved to another date/time: the 15-minute reminder and end-time notice go out again for the new slot
        // (times are compared as H:i because the database returns H:i:s)
        if (!$isNew && ($meeting->startsAt()?->format('Y-m-d H:i') !== $previousStart || $meeting->endsAt()?->format('Y-m-d H:i') !== $previousEnd)) {
            $meeting->reminder_sent_at = null;
            $meeting->ended_notified_at = null;
        }

        // A different Meet link is a different Meet space: auto Smart Notes must be set on it again
        if ($meeting->isDirty('meeting_link')) {
            $meeting->auto_notes_enabled = null;
        }

        $meeting->save();

        // Google Meet: turn on automatic Smart Notes for the meeting's space (needs Gemini on the company account)
        if ($meeting->meeting_type === 'online' && $meeting->provider === 'Google Meet'
            && GoogleMeetNotes::meetingCode($meeting->meeting_link) && !$meeting->auto_notes_enabled) {
            $meeting->forceFill(['auto_notes_enabled' => GoogleMeetNotes::enableAutoNotes($meeting->meeting_link)])->save();
        }

        // Email the customer and the lead owner (and the lead owner's bell) that the meeting is scheduled
        if ($isNew) {
            CustomerMeetingNotifier::send($meeting, 'scheduled');
        }
    }

    /**
     * Meeting Result page: "Follow-up Required" or "Discussion Ended".
     */
    public function meetingResult($id)
    {
        $meeting = CustomerMeeting::with(['customer', 'lead', 'client', 'followupMeeting'])->findOrFail($id);
        $followup = $meeting->followupMeeting;

        // Same lists as the Meetings tab form
        $this->leads           = Lead::orderBy('client_name')->get();
        $this->clients         = User::allClients();
        $this->leadProjects    = LeadProject::orderBy('name')->get();
        $this->meetingWith     = $meeting->client_id ? 'client' : 'lead';
        $this->employees       = User::allEmployees(null, true);
        $this->meetingPurposes = MeetingOption::ofType('purpose')->get();
        $this->meetingStatuses = MeetingOption::ofType('status')->get();

        // lead_owner is stored as a name; the form's select uses the user id
        $ownerId = fn ($name) => optional($this->employees->firstWhere('name', $name))->id;

        // Follow-up meeting form: the saved follow-up meeting, or a new one for the same customer
        $this->followupValues = $followup ? [
            'meeting_type'    => $followup->meeting_type,
            'title'           => $followup->title,
            'lead_project_id' => $followup->lead_project_id,
            'purpose'         => $followup->purpose,
            'status'          => $followup->status,
            'lead_id'         => $followup->lead_id,
            'client_id'       => $followup->client_id,
            'lead_owner'      => $ownerId($followup->lead_owner),
            'meeting_date'    => $followup->meeting_date ? $followup->meeting_date->format('Y-m-d') : '',
            'start_time'      => $followup->start_time ? substr($followup->start_time, 0, 5) : '',
            'end_time'        => $followup->end_time ? substr($followup->end_time, 0, 5) : '',
            'provider'        => $followup->provider,
            'meeting_link'    => $followup->meeting_link,
            'google_event_id' => $followup->google_event_id,
            'teams_meeting_id' => $followup->teams_meeting_id,
            'location'        => $followup->location,
            'description'     => $followup->description,
        ] : [
            'meeting_type' => 'online',
            'purpose'      => $meeting->purpose,
            'lead_id'      => $meeting->lead_id,
            'client_id'    => $meeting->client_id,
            'lead_project_id' => $meeting->lead_project_id,
            'lead_owner'   => $ownerId($meeting->lead_owner),
        ];

        $this->meeting         = $meeting;
        $this->endReasons      = self::END_REASONS;
        $this->meetingOutcomes = MeetingOption::ofType('result')->pluck('name')->all();
        $this->pageTitle       = 'Meeting Result';

        return view('customers.meeting-result', $this->data);
    }

    /**
     * Meet Notes pop-up (AJAX): the saved Google Meet Smart Notes for a meeting.
     */
    /**
     * Live status of Google Meet meetings for the Meetings tab (AJAX, polled every 30 seconds):
     * who is in the call now and whether Gemini notes are running.
     */
    public function meetingsLiveStatus()
    {
        $now = \Carbon\Carbon::now(CustomerMeeting::timezone());
        $status = [];

        $meetings = CustomerMeeting::where('meeting_type', 'online')
            ->where('provider', 'Google Meet')
            ->whereNotNull('meeting_link')
            ->whereBetween('meeting_date', [$now->copy()->subDay()->toDateString(), $now->copy()->addDay()->toDateString()])
            ->get();

        foreach ($meetings as $meeting) {
            $start = $meeting->startsAt();
            $end = $meeting->endsAt();

            // People can join early or stay late; outside this window there is nothing to check
            if (!$start || !$end || $now->lt($start->copy()->subHour()) || $now->gt($end->copy()->addHours(3))) {
                continue;
            }

            try {
                $status[$meeting->id] = cache()->remember('meet-live-' . $meeting->id, 20, fn () => GoogleMeetNotes::liveStatus($meeting->meeting_link));
            } catch (\Throwable $e) {
                Log::info('Meet live status failed for meeting ' . $meeting->id . ': ' . $e->getMessage());
            }
        }

        // CRM Notetaker bot: online if it checked in within the last 2 minutes (it checks every 30 seconds)
        $botSeen = cache('crm-notes-bot-last-seen');
        $botSeenAt = $botSeen ? \Carbon\Carbon::parse($botSeen)->timezone(CustomerMeeting::timezone()) : null;

        return Reply::dataOnly(['status' => 'success', 'meetings' => $status, 'bot' => [
            'online' => $botSeenAt && $botSeenAt->gt(now()->subMinutes(2)),
            'last_seen' => $botSeenAt ? $botSeenAt->format('d M Y, h:i A') : null,
        ]]);
    }

    public function meetingNotes($id)
    {
        return Reply::dataOnly($this->meetingNotesData(CustomerMeeting::with(['customer', 'lead', 'client'])->findOrFail($id)));
    }

    /**
     * Meet Notes "Check now": before the meeting ends, make sure auto Smart Notes are on;
     * after it ends, fetch the notes from Google.
     */
    public function refreshMeetingNotes($id)
    {
        $meeting = CustomerMeeting::with(['customer', 'lead', 'client'])->findOrFail($id);

        if (!GoogleMeetNotes::meetingCode($meeting->meeting_link)) {
            return response()->json(['status' => 'fail', 'message' => 'Notes are only available for Google Meet meetings.'], 422);
        }

        if (!GoogleMeetNotes::connected()) {
            return response()->json(['status' => 'fail', 'message' => 'Google Calendar is not connected. Connect it in Settings → Google Calendar.'], 422);
        }

        $ended = $meeting->endsAt() && $meeting->endsAt()->isPast();

        if (!$ended) {
            if (!$meeting->auto_notes_enabled) {
                $meeting->forceFill(['auto_notes_enabled' => GoogleMeetNotes::enableAutoNotes($meeting->meeting_link)])->save();
            }
        } else {
            try {
                $notes = GoogleMeetNotes::fetchNotes($meeting);
            } catch (\Throwable $e) {
                Log::error('Meet notes fetch failed for meeting ' . $meeting->id . ': ' . $e->getMessage());

                return response()->json(['status' => 'fail', 'message' => 'Could not read the notes from Google. Check that Google Calendar is connected with Meet and Drive access (Settings → Google Calendar → Disable, then Verify).'], 422);
            }

            $meeting->meet_notes_checked_at = now();

            if (!empty($notes['text'])) {
                $meeting->fill(['meet_notes' => $notes['text'], 'meet_notes_url' => $notes['url'], 'meet_notes_fetched_at' => now()]);
            } elseif (!empty($notes['url'])) {
                // The Doc exists but belongs to another Google account: keep its link
                $meeting->meet_notes_url = $notes['url'];
            }

            $meeting->save();
        }

        return Reply::dataOnly($this->meetingNotesData($meeting));
    }

    private function meetingNotesData(CustomerMeeting $meeting): array
    {
        $start = $meeting->startsAt();
        $end = $meeting->endsAt();
        $ended = $end && $end->isPast();

        if ($meeting->meet_notes) {
            $message = null;
        } elseif ($meeting->notes_recording_status === 'recording') {
            $message = 'The CRM Notes extension is recording this call. Notes appear here a minute or two after the call ends.';
        } elseif ($meeting->notes_recording_status === 'failed') {
            $message = 'The call was recorded, but no speech was heard in it, so no notes were written. Check that people had their microphones on, and see notetaker-bot\\bot.log for the audio level of each part.';
        } elseif ($meeting->notes_recording_status === 'processing') {
            $message = 'The call has ended. Writing the meeting notes now; check again in a minute.';
        } elseif (!GoogleMeetNotes::meetingCode($meeting->meeting_link)) {
            $message = 'Notes are only available for Google Meet meetings.';
        } elseif ($ended && $meeting->meet_notes_url) {
            $message = 'Google created notes for this meeting, but they were started in the call from a different Google account, so they are saved in that account\'s Drive and the CRM cannot read them. Use "Open in Google Docs" to view them. Notes created automatically under the company Google account are saved here.';
        } elseif (!$ended) {
            $message = $meeting->auto_notes_enabled
                ? 'Automatic notes are on. Notes appear here after the meeting ends (Google usually takes a few minutes).'
                : 'Automatic notes are not on for this meeting yet. Click "Check now" to turn them on (needs Meet access and a Gemini plan on the company Google account).';
        } else {
            $message = 'No notes from Google yet. Notes usually arrive within 10–30 minutes after the meeting ends; this page checks automatically every 10 minutes.'
                . ($meeting->auto_notes_enabled ? '' : ' Automatic notes were not on for this meeting, so notes exist only if someone started "Take notes for me" in the call.');
        }

        return [
            'status' => 'success',
            'title' => ($meeting->contact->company_name ?? 'Meeting') . ' · ' . ($start ? $start->format('d M Y, h:i A') : '') . ($end ? ' – ' . $end->format('h:i A') : ''),
            'notes' => $meeting->meet_notes,
            'url' => $meeting->meet_notes_url,
            'fetched_at' => $meeting->meet_notes_fetched_at ? $meeting->meet_notes_fetched_at->timezone(CustomerMeeting::timezone())->format('d M Y, h:i A') : null,
            'auto_notes_enabled' => (bool) $meeting->auto_notes_enabled,
            'ended' => $ended,
            'message' => $message,
            // gemini | ai (CRM Notes extension)
            'source' => $meeting->meet_notes ? ($meeting->meet_notes_source ?: 'gemini') : null,
            'transcript' => $meeting->meet_notes_source === 'ai' ? $meeting->meet_transcript : null,
            'recording_status' => $meeting->notes_recording_status,
        ];
    }

    /**
     * Save the meeting result: Discussion Ended, or Meeting Result (with an optional follow-up).
     * A follow-up creates (or, when saved again, updates) the follow-up meeting.
     */
    public function saveMeetingResult(Request $request, $id)
    {
        $meeting = CustomerMeeting::with('followupMeeting')->findOrFail($id);

        $noEmoji = function ($attribute, $value, $fail) {
            if (preg_match(self::EMOJI_PATTERN, (string) $value)) {
                $fail('Emojis are not allowed.');
            }
        };

        // The notes come from a rich text editor: count the words typed, not the formatting around them
        $hasText = function ($attribute, $value, $fail) {
            if (self::plainText($value) === '') {
                $fail($attribute === 'end_notes' ? 'Please enter additional notes.' : 'Enter the meeting result reason.');
            }
        };

        $request->validate(['result' => 'required|in:ended,outcome']);

        if ($request->result === 'ended') {
            $request->validate([
                'end_reason' => ['required', Rule::in(self::END_REASONS)],
                'end_notes'  => ['required', 'string', 'max:20000', $noEmoji, $hasText],
            ], [
                'end_reason.required' => 'Please select a reason.',
                'end_notes.required'  => 'Please enter additional notes.',
            ]);

            $meeting->fill([
                'result'     => 'ended',
                'end_reason' => $request->end_reason,
                'end_notes'  => self::cleanHtml($request->end_notes),
            ]);
            $message = 'Lead closed as Discussion Ended.';
        } elseif ($request->result === 'outcome') {
            $request->validate([
                'outcome'        => ['required', Rule::in(MeetingOption::ofType('result')->pluck('name')->all())],
                'outcome_reason' => ['required', 'string', 'max:20000', $noEmoji, $hasText],
            ], [
                'outcome.required'        => 'Select the meeting result.',
                'outcome_reason.required' => 'Enter the meeting result reason.',
            ]);

            $meeting->fill([
                'result'         => 'outcome',
                'outcome'        => $request->outcome,
                'outcome_reason' => self::cleanHtml($request->outcome_reason),
            ]);

            if ($request->boolean('followup_required')) {
                // The follow-up's description is plain text
                $followup = $this->saveFollowupMeeting($request, $meeting, 'Follow-up (' . $request->outcome . '): ' . self::plainText($request->outcome_reason));
                $meeting->fill(['followup_date' => $followup->meeting_date, 'followup_meeting_id' => $followup->id]);
                $message = 'Meeting result saved and the follow-up meeting is scheduled.';
            } else {
                $message = 'Meeting result saved.';
            }
        }

        // The meeting itself has taken place
        if (in_array('Completed', MeetingOption::ofType('status')->pluck('name')->all())) {
            $meeting->status = 'Completed';
        }

        $meeting->result_at = now();
        $meeting->save();

        return Reply::success($message);
    }

    /**
     * Create (or update) the follow-up meeting from the Meet with Customer form on the Meeting Result page.
     * Uses the same fields and save as the Meetings tab form (validation, Google Meet, invites).
     */
    /**
     * Keeps the formatting the meeting-result editor (jQuery TE) produces and drops everything else:
     * scripts, event handlers, javascript: links, iframes, images and any other markup.
     */
    private static function cleanHtml(?string $html): string
    {
        static $purifier = null;

        if ($purifier === null) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', 'p[style],br,div[style],span[style],b,strong,i,em,u,strike,s,sub,sup,'
                . 'ol,ul,li,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],pre,blockquote,hr,a[href]');
            $config->set('CSS.AllowedProperties', 'color,font-size,text-align,font-weight,font-style,text-decoration');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('HTML.TargetBlank', true);
            $purifier = new \HTMLPurifier($config);
        }

        return trim($purifier->purify((string) $html));
    }

    /** The text of an editor value without its formatting. */
    private static function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    private function saveFollowupMeeting(Request $request, CustomerMeeting $meeting, string $defaultDescription): CustomerMeeting
    {
        $followup = $meeting->followupMeeting ?? new CustomerMeeting(['added_by' => user()->id]);

        $followupRequest = Request::create('', 'POST', $request->only([
            'meeting_type', 'title', 'purpose', 'status', 'lead_id', 'client_id', 'lead_project_id', 'lead_owner', 'meeting_date', 'start_time',
            'end_time', 'provider', 'meeting_link', 'google_event_id', 'teams_meeting_id', 'location', 'description',
        ]));

        if (!trim((string) $followupRequest->description)) {
            $followupRequest->merge(['description' => $defaultDescription]);
        }

        $this->saveMeeting($followupRequest, $followup);

        return $followup;
    }

    /**
     * "Generate Link" on the meeting form: create the Google Meet for the chosen slot (AJAX).
     */
    public function generateMeetLink(Request $request)
    {
        $request->validate($this->contactRules() + [
            'lead_owner'      => ['nullable', 'integer', Rule::in(User::allEmployees(null, true)->pluck('id')->all())],
            'meeting_date'    => 'required|date',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'google_event_id' => ['nullable', 'string', 'max:255'],
            'teams_meeting_id' => ['nullable', 'string', 'max:500'],
        ], [
            'meeting_date.required' => 'Select the meeting date.',
            'start_time.required'   => 'Select the start time.',
            'end_time.required'     => 'Select the end time.',
            'end_time.after'        => 'End time must be after the start time.',
        ] + self::CONTACT_MESSAGES);

        // Regenerating: remove the event made by the previous click. A saved meeting's event is kept
        // until the edit is saved, so cancelling the edit leaves the meeting's link working.
        if ($request->provider === 'Microsoft Teams') {
            if ($request->teams_meeting_id && !CustomerMeeting::where('teams_meeting_id', $request->teams_meeting_id)->exists()) {
                $this->deleteTeamsMeeting($request->teams_meeting_id);
            }

            [$link, $teamsId] = $this->createTeamsMeeting($request);

            return Reply::dataOnly(['status' => 'success', 'meeting_link' => $link, 'teams_meeting_id' => $teamsId]);
        }

        if ($request->google_event_id && !CustomerMeeting::where('google_event_id', $request->google_event_id)->exists()) {
            $this->deleteGoogleEvent($request->google_event_id);
        }

        [$link, $eventId] = $this->createGoogleMeet($request, false);

        return Reply::dataOnly([
            'status'          => 'success',
            'meeting_link'    => $link,
            'google_event_id' => $eventId,
        ]);
    }

    /**
     * Show all meetings of a specific customer (AJAX).
     */
    public function customerMeetings($customerId)
    {
        $this->customer = Customer::with('meetings')->findOrFail($customerId);
        $html = view('customers.ajax.meetings-list', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Delete customer + all meetings.
     */
    public function destroyCustomer($id)
    {
        $customer = Customer::with('meetings')->findOrFail($id);

        // Cancel the Google Calendar events of the meetings that go with the customer
        foreach ($customer->meetings as $meeting) {
            if ($meeting->google_event_id) {
                $this->deleteGoogleEvent($meeting->google_event_id, 'all');
            }
        }

        $customer->delete();
        return Reply::success('Customer deleted successfully.');
    }

    /**
     * Delete a meeting.
     */
    public function destroyMeeting($id)
    {
        $meeting = CustomerMeeting::findOrFail($id);

        if ($meeting->google_event_id) {
            $this->deleteGoogleEvent($meeting->google_event_id, 'all');
        }

        if ($meeting->teams_meeting_id) {
            $this->deleteTeamsMeeting($meeting->teams_meeting_id);
        }

        $meeting->delete();
        return Reply::success('Meeting deleted successfully.');
    }

    /**
     * Teams meeting for the form's date/start/end (Microsoft Graph POST /me/onlineMeetings). Returns [join url, id].
     */
    private function createTeamsMeeting(Request $request): array
    {
        if (!MicrosoftTeams::connected()) {
            throw ValidationException::withMessages([
                'meeting_link' => 'Microsoft Teams is not connected. Connect it in Settings → Microsoft Teams, or paste a Teams link.',
            ]);
        }

        [$subject, $start, $end] = $this->teamsSlot($request);

        try {
            return MicrosoftTeams::createMeeting($subject, $start, $end);
        } catch (\Throwable $e) {
            Log::error('Teams meeting creation failed: ' . $e->getMessage());

            throw ValidationException::withMessages([
                'meeting_link' => 'Could not create the Microsoft Teams meeting. ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Saving a generated Teams link: keep the Teams meeting in step with the final date/time.
     * Returns its id, or null if the link in the form is no longer that meeting's link.
     */
    private function syncTeamsMeeting(Request $request, ?string $link): ?string
    {
        if (!MicrosoftTeams::connected()) {
            return null;
        }

        try {
            if (MicrosoftTeams::joinUrl($request->teams_meeting_id) !== $link) {
                return null;
            }

            [$subject, $start, $end] = $this->teamsSlot($request);
            MicrosoftTeams::updateMeeting($request->teams_meeting_id, $subject, $start, $end);
        } catch (\Throwable $e) {
            Log::warning('Teams meeting update failed: ' . $e->getMessage());
        }

        return $request->teams_meeting_id;
    }

    private function deleteTeamsMeeting(string $id): void
    {
        try {
            if (MicrosoftTeams::connected()) {
                MicrosoftTeams::deleteMeeting($id);
            }
        } catch (\Throwable $e) {
            Log::warning('Teams meeting delete failed: ' . $e->getMessage());
        }
    }

    /** The meeting form's client or lead: a client is chosen on meetings from the Clients page, a lead otherwise. */
    private function contactRules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', Rule::in(User::allClients()->pluck('id')->all())],
            'lead_id'   => ['required_without:client_id', 'nullable', 'exists:leads,id'],
        ];
    }

    /** Name, company and email of the form's client or lead. */
    private function requestContact(Request $request): object
    {
        return (new CustomerMeeting([
            'client_id' => $request->client_id ?: null,
            'lead_id'   => $request->client_id ? null : $request->lead_id,
        ]))->contact;
    }

    private function teamsSlot(Request $request): array
    {
        $timezone = CustomerMeeting::timezone();
        $contact = $this->requestContact($request);

        return [
            ($request->title ?: ($request->purpose ?: 'Meeting')) . ' - ' . ($contact->company_name ?? 'Meeting'),
            Carbon::parse($request->meeting_date . ' ' . $request->start_time, $timezone),
            Carbon::parse($request->meeting_date . ' ' . $request->end_time, $timezone),
        ];
    }

    private function googleCalendarConnected(): bool
    {
        $company = company();

        return $company
            && $company->google_calendar_status == 'active'
            && $company->google_calendar_verification_status == 'verified'
            && $company->token;
    }

    private function googleCalendar()
    {
        return (new Google())->connectUsing(company()->token)->service('Calendar');
    }

    /**
     * Calendar event for the meeting slot. Attendees are only added when the meeting is saved,
     * so "Generate Link" does not email the customer before the meeting exists.
     */
    private function meetingCalendarEvent(Request $request, bool $withAttendees): Google_Service_Calendar_Event
    {
        $timezone = company()->timezone ?: config('app.timezone');
        $contact = $this->requestContact($request);

        $data = [
            'summary' => ($request->title ?: ($request->purpose ?: 'Meeting')) . ' - ' . ($contact->company_name ?? 'Meeting'),
            'description' => $request->description,
            'start' => [
                'dateTime' => Carbon::parse($request->meeting_date . ' ' . $request->start_time, $timezone)->toRfc3339String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => Carbon::parse($request->meeting_date . ' ' . $request->end_time, $timezone)->toRfc3339String(),
                'timeZone' => $timezone,
            ],
        ];

        if ($withAttendees) {
            $owner = $request->lead_owner ? User::find($request->lead_owner) : null;
            $data['attendees'] = [];

            foreach ([$contact->email, $owner->email ?? null] as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $data['attendees'][] = ['email' => $email];
                }
            }
        }

        return new Google_Service_Calendar_Event($data);
    }

    private function meetLink($event): ?string
    {
        if ($event->getHangoutLink()) {
            return $event->getHangoutLink();
        }

        foreach ($event->getConferenceData() ? $event->getConferenceData()->getEntryPoints() : [] as $entryPoint) {
            if ($entryPoint->getEntryPointType() === 'video') {
                return $entryPoint->getUri();
            }
        }

        return null;
    }

    /**
     * Create a Google Calendar event with a Meet conference for the meeting slot.
     *
     * @return array [meet link, calendar event id]
     */
    private function createGoogleMeet(Request $request, bool $saving): array
    {
        if (!$this->googleCalendarConnected()) {
            throw ValidationException::withMessages([
                'meeting_link' => 'Google Calendar is not connected. Connect it in Settings or paste a meeting link.',
            ]);
        }

        // $saving: a meeting saved with an empty link creates and invites in one go;
        // "Generate Link" creates without invites, which go out when the meeting is saved
        $event = $this->meetingCalendarEvent($request, $saving);
        $event->setConferenceData(new Google_Service_Calendar_ConferenceData([
            'createRequest' => [
                'requestId' => uniqid('meet-', true),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ]));

        try {
            // conferenceDataVersion=1 is required for Google to create the Meet conference
            $result = $this->googleCalendar()->events->insert('primary', $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => $saving ? 'all' : 'none',
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Meet creation failed: ' . $e->getMessage());

            // Connected without ticking the Calendar permission on Google's consent screen
            if (str_contains($e->getMessage(), 'SERVICE_DISABLED')) {
                $message = 'Google Calendar API is not enabled in the Google Cloud project. Enable it in Google Cloud Console > APIs & Services > Library.';
            } elseif ($e->getCode() == 403 && str_contains($e->getMessage(), 'insufficient')) {
                $message = 'Google Calendar permission was not granted. In Settings > Google Calendar, click Disable, then Verify and tick the Calendar permission.';
            } else {
                $message = 'Could not create the Google Meet link. Please try again or paste a link.';
            }

            throw ValidationException::withMessages(['meeting_link' => $message]);
        }

        return [$this->meetLink($result), $result->getId()];
    }

    /**
     * On save of a generated link: update the event to the final date/time and invite the attendees.
     * If the link in the form is no longer the generated one, the event is removed.
     *
     * @return string|null calendar event id to store with the meeting
     */
    private function syncGeneratedMeet(Request $request): ?string
    {
        $eventId = $request->google_event_id;

        if (!$this->googleCalendarConnected()) {
            return null;
        }

        try {
            $existing = $this->googleCalendar()->events->get('primary', $eventId);
        } catch (\Throwable $e) {
            Log::warning('Generated Google Meet event not found: ' . $e->getMessage());
            return null;
        }

        if ($request->meeting_type !== 'online' || $this->meetLink($existing) !== $request->meeting_link) {
            $this->deleteGoogleEvent($eventId);
            return null;
        }

        try {
            $this->googleCalendar()->events->patch('primary', $eventId, $this->meetingCalendarEvent($request, true), [
                'sendUpdates' => 'all',
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Meet event update failed: ' . $e->getMessage());

            throw ValidationException::withMessages([
                'meeting_link' => 'Could not update the Google Calendar event. Please try again.',
            ]);
        }

        return $eventId;
    }

    private function deleteGoogleEvent(string $eventId, string $sendUpdates = 'none'): void
    {
        if (!$this->googleCalendarConnected()) {
            return;
        }

        try {
            $this->googleCalendar()->events->delete('primary', $eventId, ['sendUpdates' => $sendUpdates]);
        } catch (\Throwable $e) {
            // Already removed from the calendar, or access revoked
            Log::warning('Google Calendar event delete failed: ' . $e->getMessage());
        }
    }
}
