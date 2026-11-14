<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketChannel;

class TicketChannelObserver
{

    public function creating(TicketChannel $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
