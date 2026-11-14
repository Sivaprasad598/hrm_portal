<?php

namespace AppModules\Messages\Observers;

use AppModules\Messages\Models\MessageSetting;

class MessageSettingObserver
{

    public function creating(MessageSetting $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
