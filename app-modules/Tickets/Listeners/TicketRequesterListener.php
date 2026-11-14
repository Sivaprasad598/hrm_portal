<?php

namespace AppModules\Tickets\Listeners;

use AppModules\Tickets\Events\TicketRequesterEvent;
use AppModules\Tickets\Notifications\NewTicketRequester;
use Illuminate\Support\Facades\Notification;

class TicketRequesterListener
{

    /**
     * @param TicketRequesterEvent $event
     */

    public function handle(TicketRequesterEvent $event)
    {
        if (!is_null($event->notifyUser)) {
            Notification::send($event->notifyUser, new NewTicketRequester($event->ticket));
        }
    }

}
