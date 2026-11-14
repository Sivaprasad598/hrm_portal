<?php

namespace AppModules\Leads\Listeners;

use AppModules\Leads\Events\RemovalRequestAdminLeadEvent;
use App\Notifications\RemovalRequestAdminNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class RemovalRequestAdminLeadListener
{

    /**
     * Handle the event.
     *
     * @param RemovalRequestAdminLeadEvent $event
     * @return void
     */
    // phpcs:ignore
    public function handle(RemovalRequestAdminLeadEvent $event)
    {
        Notification::send(User::allAdmins($event->removalRequestLead->company->id), new RemovalRequestAdminNotification());
    }

}
