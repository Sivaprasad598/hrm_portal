<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketType;

class TicketTypeObserver
{

    public function creating(TicketType $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
