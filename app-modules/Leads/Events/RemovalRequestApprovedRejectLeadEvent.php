<?php

namespace AppModules\Leads\Events;

use AppModules\Leads\Models\RemovalRequestLead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RemovalRequestApprovedRejectLeadEvent
{

    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $removal;

    public function __construct(RemovalRequestLead $removal)
    {
        $this->removal = $removal;
    }

}
