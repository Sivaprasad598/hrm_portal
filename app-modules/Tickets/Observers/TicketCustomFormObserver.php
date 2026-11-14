<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketCustomForm;

class TicketCustomFormObserver
{

    public function creating(TicketCustomForm $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
