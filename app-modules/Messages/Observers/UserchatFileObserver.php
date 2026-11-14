<?php

namespace AppModules\Messages\Observers;

use AppModules\Messages\Models\UserchatFile;

class UserchatFileObserver
{

    public function creating(UserchatFile $model)
    {
        $model->company_id = $model->chat->company_id;
    }

}
