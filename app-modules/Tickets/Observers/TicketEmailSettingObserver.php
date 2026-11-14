<?php

namespace AppModules\Tickets\Observers;

use AppModules\Tickets\Models\TicketEmailSetting;

class TicketEmailSettingObserver
{

    public function creating(TicketEmailSetting $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
