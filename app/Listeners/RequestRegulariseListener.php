<?php

namespace App\Listeners;

use App\Events\RequestRegulariseEvent;
use App\Models\AttendanceSetting;
use App\Models\User;
use App\Notifications\RequestRegularise;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class RequestRegulariseListener
{

    public function handle(RequestRegulariseEvent $event)
    {
        Notification::send($event->users, new RequestRegularise($event->requestRegularisation));
    }

}
