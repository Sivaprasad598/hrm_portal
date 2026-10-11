<?php

namespace App\Listeners;

use App\Events\ResignationEvent;
use App\Models\Resignation;
use App\Notifications\NewResignationRequest;
use Illuminate\Support\Facades\Notification;

class ResignationListener
{
    public function handle(ResignationEvent $event)
    {
        if ($event->status !== 'created') {
            return;
        }

        $recipients = Resignation::adminRecipients($event->resignation->company_id);

        Notification::send($recipients, new NewResignationRequest($event->resignation));
    }
}
