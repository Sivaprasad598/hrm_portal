<?php

namespace App\Observers;

use App\Events\ResignationEvent;
use App\Models\Resignation;

class ResignationObserver
{
    public function created(Resignation $resignation)
    {
        if (!isRunningInConsoleOrSeeding()) {
            event(new ResignationEvent($resignation, 'created'));
        }
    }
}
