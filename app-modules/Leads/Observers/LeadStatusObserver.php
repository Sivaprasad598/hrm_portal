<?php

namespace AppModules\Leads\Observers;

use AppModules\Leads\Models\Deal;
use AppModules\Leads\Models\LeadStatus;
use App\Models\User;
use AppModules\Leads\Models\UserLeadboardSetting;

class LeadStatusObserver
{

    public function created(LeadStatus $leadStatus)
    {
        if (!isRunningInConsoleOrSeeding() && user()) {
            $employees = User::allEmployees();

            foreach ($employees as $item) {
                UserLeadboardSetting::create([
                    'user_id' => $item->id,
                    'board_column_id' => $leadStatus->id
                ]);
            }
        }
    }

    public function deleting(LeadStatus $leadStatus)
    {
        $defaultStatus = LeadStatus::where('default', 1)->first();
        abort_403($defaultStatus->id == $leadStatus->id);

        Deal::where('status_id', $leadStatus->id)->update(['status_id' => $defaultStatus->id]);
    }

    public function creating(LeadStatus $leadStatus)
    {
        if (company()) {
            $leadStatus->company_id = company()->id;
        }
    }

}
