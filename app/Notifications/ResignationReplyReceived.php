<?php

namespace App\Notifications;

use App\Models\Resignation;
use App\Models\User;

class ResignationReplyReceived extends BaseNotification
{

    private $resignation;
    private $admin;

    public function __construct(Resignation $resignation, User $admin)
    {
        $this->resignation = $resignation;
        $this->admin = $admin;
        $this->company = $this->resignation->company;
    }

    public function via($notifiable)
    {
        // Bell only, by design — the employee is actively watching their own
        // resignation page for HR's response, not waiting on email for it.
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'id' => $this->resignation->id,
            'user_id' => $this->admin->id,
            'user' => $this->admin,
        ];
    }

}
