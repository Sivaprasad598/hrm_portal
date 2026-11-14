<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketGroup;

class TicketGroupObserver
{

    public function creating(TicketGroup $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
