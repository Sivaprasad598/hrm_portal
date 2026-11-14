<?php

namespace AppModules\Leads\Observers;

use AppModules\Leads\Models\UserLeadboardSetting;

class UserLeadboardSettingObserver
{

    public function creating(UserLeadboardSetting $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

}
