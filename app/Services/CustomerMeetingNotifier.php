<?php

namespace App\Services;

use App\Models\CustomerMeeting;
use App\Notifications\CustomerMeetingNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the customer meeting notices: "scheduled" and "reminder" go to the customer and the lead owner,
 * "ended" (update the meeting result) goes to the lead owner only.
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

            $customerEmail = $meeting->contact->email;

            if ($kind !== 'ended' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $customerEmail)->notify(new CustomerMeetingNotification($meeting, $kind));
            }
        } catch (\Throwable $e) {
            Log::error('Customer meeting ' . $kind . ' notification failed for meeting ' . $meeting->id . ': ' . $e->getMessage());
        }
    }
}
