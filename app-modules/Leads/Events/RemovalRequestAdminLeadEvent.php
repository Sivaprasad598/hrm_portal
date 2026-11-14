<?php

namespace AppModules\Leads\Events;

use AppModules\Leads\Models\RemovalRequestLead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RemovalRequestAdminLeadEvent
{

    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $removalRequestLead;

    public function __construct(RemovalRequestLead $removalRequestLead)
    {
        $this->removalRequestLead = $removalRequestLead;
    }

}
