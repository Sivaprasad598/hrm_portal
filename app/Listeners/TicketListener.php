<?php

namespace App\Listeners;

use App\Events\TicketEvent;
use App\Notifications\NewTicket;
use App\Notifications\TicketAgent;
use App\Models\User;
use App\Models\TicketGroup;
use App\Notifications\MentionTicketAgent;
use Illuminate\Support\Facades\Notification;

class TicketListener
{

    /**
     * Handle the event.
     *
     * @param TicketEvent $event
     * @return void
     */

    public function handle(TicketEvent $event)
    {

        if ($event->notificationName == 'NewTicket') {
            $group = TicketGroup::with('enabledAgents', 'enabledAgents.user')
                        ->where('id', $event->ticket->group_id)
                        ->first();
            if ($group && count($group->enabledAgents) > 0) {
                $usersToNotify = [];
                foreach ($group->enabledAgents as $agent) {
                    // Don't notify the user if they are the one who created the ticket
                    if ($agent->user->id != $event->ticket->added_by) {
                        $usersToNotify[] = $agent->user;
                    }
                }
                if (count($usersToNotify) > 0) {
                    Notification::send($usersToNotify, new NewTicket($event->ticket));
                }
            }
            // Don't notify admins who created the ticket
            $admins = User::allAdmins($event->ticket->company->id);
            $adminsToNotify = $admins->filter(function($admin) use ($event) {
                return $admin->id != $event->ticket->added_by;
            });
            if ($adminsToNotify->count() > 0) {
                Notification::send($adminsToNotify, new NewTicket($event->ticket));
            }
        }
        elseif ($event->notificationName == 'TicketAgent') {
            // Don't notify the agent if they are the one who assigned themselves or updated the ticket
            if ($event->ticket->agent_id != $event->ticket->last_updated_by) {
                Notification::send($event->ticket->agent, new TicketAgent($event->ticket));
            }
        }
        elseif ($event->notificationName == 'MentionTicketAgent') {
            // Filter out the current user from mentions
            $usersToNotify = $event->mentionUser->filter(function($user) use ($event) {
                return $user->id != $event->ticket->last_updated_by;
            });
            
            if ($usersToNotify->count() > 0) {
                Notification::send($usersToNotify, new MentionTicketAgent($event->ticket));
            }
        }
    }

}
