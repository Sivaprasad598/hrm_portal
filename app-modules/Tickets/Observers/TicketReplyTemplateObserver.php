<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketReplyTemplate;

class TicketReplyTemplateObserver
{

    public function creating(TicketReplyTemplate $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
