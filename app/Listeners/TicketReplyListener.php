<?php

namespace App\Listeners;

use App\Events\TicketReplyEvent;
use App\Notifications\NewTicketReply;
use App\Models\User;
use App\Notifications\NewTicketNote;
use Illuminate\Support\Facades\Notification;

class TicketReplyListener
{

    /**
     * Handle the event.
     *
     * @param TicketReplyEvent $event
     * @return void
     */

    public function handle(TicketReplyEvent $event)
    {
        if ($event?->ticketReply?->type != 'note') {
            if (!is_null($event->notifyUser) && ($event->ticketReply->type != 'note')) {
                // Don't send notification if the notify user is the one who created the reply
                if ($event->notifyUser->id != $event->ticketReply->user_id) {
                    Notification::send($event->notifyUser, new NewTicketReply($event->ticketReply));
                }
            }
            else {
                // Get all admins but exclude the user who created the reply
                $admins = User::allAdmins($event->ticketReply->ticket->company->id);
                $adminsToNotify = $admins->filter(function($admin) use ($event) {
                    return $admin->id != $event->ticketReply->user_id;
                });
                
                if ($adminsToNotify->count() > 0) {
                    Notification::send($adminsToNotify, new NewTicketReply($event->ticketReply));
                }
            }
        }

        if (!is_null($event->ticketReplyUsers)) {
            // Filter out the current user from note notifications
            $usersToNotify = $event->ticketReplyUsers->filter(function($user) use ($event) {
                return $user->id != $event->ticketReply->user_id;
            });
            
            if ($usersToNotify->count() > 0) {
                Notification::send($usersToNotify, new NewTicketNote($event->ticketReply));
            }
        }

    }

}
