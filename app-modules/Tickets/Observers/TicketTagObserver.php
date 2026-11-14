<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketTag;

class TicketTagObserver
{

    public function creating(TicketTag $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
