<?php

namespace AppModules\Gdpr\Observers;

use AppModules\Gdpr\Models\GdprSetting;

class GdprSettingObserver
{

    //phpcs:ignore
    public function creating(GdprSetting $doc)
    {
        //
    }

}
