<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketTagList;

class TicketTagListObserver
{

    public function creating(TicketTagList $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
