<?php

namespace App\Console\Commands;

use App\Models\CustomerMeeting;
use App\Notifications\CustomerMeetingNotification;
use App\Services\CrmNotesRecorder;
use App\Services\CustomerMeetingNotifier;
use App\Services\GoogleMeetNotes;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Customers → Meetings: 15 minutes before a meeting, remind the customer and the lead owner;
 * at the end time, ask the lead owner to update the meeting result; after Google Meet meetings,
 * fetch the automatic Smart Notes. Runs every minute.
 */
class SendCustomerMeetingReminders extends Command
{
    protected $signature = 'send-customer-meeting-reminders';

    protected $description = 'Send customer meeting reminders (15 minutes before) and end-time notices, and fetch Google Meet notes';

    // End notices are skipped for meetings that ended longer ago than this (e.g. the scheduler was off)
    private const ENDED_GRACE_MINUTES = 60;

    // Google Meet notes: check every 10 minutes after the meeting ends, for up to 2 days
    private const NOTES_CHECK_MINUTES = 10;
    private const NOTES_GIVE_UP_HOURS = 48;

    public function handle()
    {
        $timezone = CustomerMeeting::timezone();
        $now = Carbon::now($timezone);
        $reminders = 0;
        $ended = 0;

        // Only meetings around today can be due; the time checks below are exact
        $meetings = CustomerMeeting::with('customer')
            ->whereBetween('meeting_date', [$now->copy()->subDay()->toDateString(), $now->copy()->addDay()->toDateString()])
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'Cancelled');
            })
            ->where(function ($query) {
                $query->whereNull('reminder_sent_at')->orWhereNull('ended_notified_at');
            })
            ->get();

        foreach ($meetings as $meeting) {
            $start = $meeting->startsAt();
            $end = $meeting->endsAt();

            if (!$start || !$end) {
                continue;
            }

            // 15 minutes before the start (sent as soon as the window opens, never after the start)
            if (!$meeting->reminder_sent_at
                && $now->gte($start->copy()->subMinutes(CustomerMeetingNotification::REMINDER_MINUTES))
                && $now->lt($start)) {
                CustomerMeetingNotifier::send($meeting, 'reminder');
                $meeting->forceFill(['reminder_sent_at' => now()])->save();
                $reminders++;
            }

            // At the end time, unless a result has already been saved
            if (!$meeting->ended_notified_at
                && !$meeting->result
                && $now->gte($end)
                && $now->lt($end->copy()->addMinutes(self::ENDED_GRACE_MINUTES))) {
                CustomerMeetingNotifier::send($meeting, 'ended');
                $meeting->forceFill(['ended_notified_at' => now()])->save();
                $ended++;
            }
        }

        $notes = $this->fetchMeetNotes($now);

        // CRM Notes extension: finish recordings whose browser stopped sending (tab closed mid-call)
        try {
            $notes += CrmNotesRecorder::finishStale();
        } catch (\Throwable $e) {
            \Log::error('CRM Notes stale recordings: ' . $e->getMessage());
        }

        $this->info("Customer meeting reminders sent: {$reminders}, end notices sent: {$ended}, notes saved: {$notes}");

        return Command::SUCCESS;
    }

    /**
     * Save Google Meet Smart Notes for meetings that have ended (Google adds them a few minutes after the call).
     */
    private function fetchMeetNotes(Carbon $now): int
    {
        if (!GoogleMeetNotes::connected()) {
            return 0;
        }

        $saved = 0;

        $meetings = CustomerMeeting::where('meeting_type', 'online')
            ->where('provider', 'Google Meet')
            ->whereNotNull('meeting_link')
            ->whereNull('meet_notes')
            ->whereBetween('meeting_date', [$now->copy()->subHours(self::NOTES_GIVE_UP_HOURS)->subDay()->toDateString(), $now->toDateString()])
            ->where(function ($query) {
                $query->whereNull('meet_notes_checked_at')
                    ->orWhere('meet_notes_checked_at', '<', now()->subMinutes(self::NOTES_CHECK_MINUTES));
            })
            ->get();

        foreach ($meetings as $meeting) {
            $end = $meeting->endsAt();

            if (!$end || $now->lt($end) || $now->gt($end->copy()->addHours(self::NOTES_GIVE_UP_HOURS))) {
                continue;
            }

            try {
                $found = GoogleMeetNotes::fetchNotes($meeting);
            } catch (\Throwable $e) {
                $found = null;
                \Log::warning('Meet notes fetch failed for meeting ' . $meeting->id . ': ' . $e->getMessage());
            }

            $meeting->meet_notes_checked_at = now();

            if (!empty($found['text'])) {
                $meeting->fill(['meet_notes' => $found['text'], 'meet_notes_url' => $found['url'], 'meet_notes_fetched_at' => now()]);
                $saved++;
            } elseif (!empty($found['url'])) {
                // The Doc belongs to another Google account and cannot be read: keep its link
                $meeting->meet_notes_url = $found['url'];
            }

            $meeting->save();
        }

        return $saved;
    }
}
