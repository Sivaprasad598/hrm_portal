<?php

namespace App\Listeners;

use App\Events\NewEmployeeAddedEvent;
use App\Models\User;
use App\Notifications\NewEmployeeAdmin;
use Illuminate\Support\Facades\Notification;

class NewEmployeeAddedListener
{

    public function handle(NewEmployeeAddedEvent $event)
    {
        $company = $event->user->company;

        if (!$company) {
            return;
        }

        Notification::send(User::allAdmins($company->id), new NewEmployeeAdmin($event->user));
    }

}
