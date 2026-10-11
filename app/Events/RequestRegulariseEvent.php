<?php

namespace App\Events;

use App\Models\AttendanceRegularisation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestRegulariseEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $requestRegularisation;
    public $users;

    public function __construct(AttendanceRegularisation $requestRegularisation, $users)
    {
        $this->requestRegularisation = $requestRegularisation;
        $this->users = $users;
    }

}
