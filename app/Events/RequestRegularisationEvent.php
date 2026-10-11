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

class RequestRegularisationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $attendanceRegularisation;

    public function __construct(AttendanceRegularisation $attendanceRegularisation)
    {
        $this->attendanceRegularisation = $attendanceRegularisation;
    }

}
