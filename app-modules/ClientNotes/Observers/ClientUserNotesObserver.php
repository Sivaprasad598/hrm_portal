<?php

namespace AppModules\ClientNotes\Observers;

use AppModules\ClientNotes\Models\ClientUserNote;

class ClientUserNotesObserver
{

    public function creating(ClientUserNote $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
