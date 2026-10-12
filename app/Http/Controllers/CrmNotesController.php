<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\CustomerMeeting;
use App\Services\CrmNotesRecorder;
use App\Services\GoogleMeetNotes;
use App\Services\OpenAiMeetingNotes;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "CRM Notes" Chrome extension API (routes/api.php, prefix api/crm-notes).
 * The extension sends "Authorization: Bearer <key>"; keys are created per user on the Meetings tab.
 */
class CrmNotesController extends Controller
{
    /**
     * Is this Google Meet code a CRM meeting? The extension only offers to record CRM meetings.
     */
    public function lookup(Request $request)
    {
        $this->userId($request);
        $code = strtolower((string) $request->query('code'));

        $meeting = $code ? CustomerMeeting::with('customer')
            ->where('provider', 'Google Meet')
            ->where('meeting_link', 'like', '%' . $code . '%')
            ->orderByDesc('meeting_date')
            ->first() : null;

        if (!$meeting || GoogleMeetNotes::meetingCode($meeting->meeting_link) !== $code) {
            return response()->json(['status' => 'success', 'meeting' => null]);
        }

        return response()->json([
            'status' => 'success',
            'meeting' => [
                'id' => $meeting->id,
                'title' => ($meeting->customer->company_name ?? 'Customer') . ' · ' . ($meeting->purpose ?: 'Meeting'),
                'has_notes' => (bool) $meeting->meet_notes,
            ],
        ]);
    }

    public function uploadSegment(Request $request, $id)
    {
        $userId = $this->userId($request);
        $meeting = CustomerMeeting::findOrFail($id);

        $request->validate([
            'index' => 'required|integer|min:0|max:10000',
            // ~5 minutes of compressed audio is a few MB; 25 MB is the transcription limit
            'audio' => 'required|file|max:25000',
        ]);

        // Two people in the same call with the extension: only the first one records
        if ($meeting->notes_recording_status === 'recording'
            && $meeting->notes_recorded_by && (int) $meeting->notes_recorded_by !== $userId
            && $meeting->notes_last_upload_at && $meeting->notes_last_upload_at->gt(now()->subMinutes(CrmNotesRecorder::STALE_MINUTES))) {
            return response()->json(['status' => 'fail', 'message' => 'This call is already being recorded by another user.'], 409);
        }

        if (!OpenAiMeetingNotes::configured()) {
            return response()->json(['status' => 'fail', 'message' => 'OpenAI is not configured on the portal.'], 503);
        }

        try {
            CrmNotesRecorder::addSegment($meeting, (int) $request->input('index'), $request->file('audio'), $userId);
        } catch (\Throwable $e) {
            Log::error('CRM Notes: segment ' . $request->input('index') . ' of meeting ' . $meeting->id . ' failed: ' . $e->getMessage());

            return response()->json(['status' => 'fail', 'message' => 'Could not transcribe this part of the call.'], 502);
        }

        return response()->json(['status' => 'success']);
    }

    public function finish(Request $request, $id)
    {
        $this->userId($request);
        $meeting = CustomerMeeting::findOrFail($id);

        $ok = CrmNotesRecorder::finish($meeting);

        return response()->json($ok
            ? ['status' => 'success', 'message' => 'Meeting notes saved in the CRM.']
            : ['status' => 'fail', 'message' => 'No notes could be made from this recording.'], $ok ? 200 : 422);
    }

    /**
     * Meetings tab: create (or replace) the logged-in user's extension key. Shown once.
     */
    public function createKey()
    {
        $key = 'crmn_' . Str::random(40);

        DB::table('crm_notes_tokens')->updateOrInsert(
            ['user_id' => user()->id],
            ['token_hash' => hash('sha256', $key), 'last_used_at' => null, 'created_at' => now(), 'updated_at' => now()]
        );

        return Reply::dataOnly([
            'status' => 'success',
            'key' => $key,
            'portal_url' => rtrim(config('app.url'), '/'),
        ]);
    }

    /**
     * CRM Notetaker bot: Google Meet meetings to be in right now (from 2 minutes before the start to the end time).
     */
    public function botDue(Request $request)
    {
        abort_unless($this->userId($request) === self::BOT_USER, 403);

        // Heartbeat for the "Bot online/offline" badge on the Meetings tab (the bot calls this every 30 seconds)
        cache()->forever('crm-notes-bot-last-seen', now()->toIso8601String());

        $now = now(CustomerMeeting::timezone());

        $meetings = CustomerMeeting::with('customer')
            ->where('meeting_type', 'online')
            ->where('provider', 'Google Meet')
            ->whereNotNull('meeting_link')
            ->whereDate('meeting_date', $now->toDateString())
            ->get()
            ->filter(function (CustomerMeeting $meeting) use ($now) {
                $start = $meeting->startsAt();
                $end = $meeting->endsAt();

                return $start && $end && $now->gte($start->copy()->subMinutes(2)) && $now->lt($end)
                    && GoogleMeetNotes::meetingCode($meeting->meeting_link);
            })
            ->map(fn (CustomerMeeting $meeting) => [
                'id' => $meeting->id,
                'title' => ($meeting->customer->company_name ?? 'Customer') . ' · ' . ($meeting->purpose ?: 'Meeting'),
                'link' => $meeting->meeting_link,
                // Same meeting moved to a new time is a new session for the bot
                'session' => $meeting->id . '@' . $meeting->startsAt()->format('Y-m-d H:i'),
                'ends_at' => $meeting->endsAt()->toIso8601String(),
            ])
            ->values();

        return response()->json(['status' => 'success', 'meetings' => $meetings]);
    }

    private const BOT_USER = 0;

    private function userId(Request $request): int
    {
        $token = $request->bearerToken();
        $botKey = (string) config('services.openai.bot_key');

        if ($token && $botKey !== '' && hash_equals($botKey, $token)) {
            return self::BOT_USER;
        }

        $row = $token ? DB::table('crm_notes_tokens')->where('token_hash', hash('sha256', $token))->first() : null;

        if (!$row) {
            throw new HttpResponseException(response()->json([
                'status' => 'fail',
                'message' => 'Invalid CRM Notes key. Create a new key on the Meetings tab.',
            ], 401));
        }

        DB::table('crm_notes_tokens')->where('id', $row->id)->update(['last_used_at' => now()]);

        return (int) $row->user_id;
    }
}
