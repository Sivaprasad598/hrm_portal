<?php

namespace App\Events;

use App\Models\Resignation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResignationEvent
{

    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $resignation;
    public $status;

    public function __construct(Resignation $resignation, $status)
    {
        $this->resignation = $resignation;
        $this->status = $status;
    }

}
