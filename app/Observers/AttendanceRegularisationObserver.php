<?php

namespace App\Observers;

use App\Models\AttendanceRegularisation;

class AttendanceRegularisationObserver
{
    public function saving(AttendanceRegularisation $requestRegulisation)
    {
        //
    }

    public function creating(AttendanceRegularisation $requestRegulisation)
    {
        $requestRegulisation->company_id = $requestRegulisation->user->company_id;
    }
}
