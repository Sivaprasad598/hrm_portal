<?php

namespace AppModules\Leads\Listeners;

use AppModules\Leads\Events\RemovalRequestApprovedRejectLeadEvent;
use AppModules\Leads\Notifications\RemovalRequestApprovedRejectLead;
use Illuminate\Support\Facades\Notification;

class RemovalRequestApprovedRejectLeadListener
{

    /**
     * Handle the event.
     *
     * @param RemovalRequestApprovedRejectLeadEvent $event
     * @return void
     */

    public function handle(RemovalRequestApprovedRejectLeadEvent $event)
    {
        Notification::send($event->removal->lead, new RemovalRequestApprovedRejectLead($event->removal->status));
    }

}
