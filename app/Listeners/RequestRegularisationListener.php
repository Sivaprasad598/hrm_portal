<?php

namespace App\Listeners;

use App\Events\RequestRegularisationEvent;
use App\Models\User;
use App\Notifications\RequestRegularisationAccept;
use App\Notifications\RequestRegularisationReject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class RequestRegularisationListener
{

    public function handle(RequestRegularisationEvent $event)
    {
        $user = User::find($event->attendanceRegularisation->user_id);

        if ($event->attendanceRegularisation->status == 'accept') {

            if ($user) {
                Notification::send($user, new RequestRegularisationAccept($event->attendanceRegularisation));
            }

        } else {

            if ($user) {
                Notification::send($user, new RequestRegularisationReject($event->attendanceRegularisation));
            }
        }

    }

}
