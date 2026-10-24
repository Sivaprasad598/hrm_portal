<?php

namespace App\Services;

use App\Models\CustomerMeeting;
use App\Notifications\CustomerMeetingNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the customer meeting notices: "scheduled" and "reminder" go to the customer, the lead owner and any
 * invited lead team members; "ended" (update the meeting result) goes to the lead owner only.
 */
class CustomerMeetingNotifier
{
    public static function send(CustomerMeeting $meeting, string $kind): void
    {
        $meeting->loadMissing(['customer', 'lead', 'client']);

        // A failed email must never stop the meeting from being saved
        try {
            $owner = $meeting->leadOwnerUser();

            if ($owner) {
                $owner->notify(new CustomerMeetingNotification($meeting, $kind));
            }

            // Lead meetings with an Invite list (Lead Team): the customer only if the lead was ticked, plus the
            // ticked team members. Meetings without one: customer and owner, as before.
            $attendees = $meeting->attendees()->get();
            $customerInvited = $attendees->isEmpty() || $attendees->contains('is_lead', true);
            $customerEmail = $meeting->contact->email;

            if ($kind !== 'ended' && $customerInvited && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $customerEmail)->notify(new CustomerMeetingNotification($meeting, $kind));
            }

            if ($kind !== 'ended') {
                $teamIds = $attendees->pluck('user_id')->filter()->reject(fn ($id) => $owner && $id == $owner->id);

                foreach (\App\Models\User::whereIn('id', $teamIds)->get() as $member) {
                    $member->notify(new CustomerMeetingNotification($meeting, $kind));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Customer meeting ' . $kind . ' notification failed for meeting ' . $meeting->id . ': ' . $e->getMessage());
        }
    }
}
