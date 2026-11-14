<?php

namespace AppModules\Leads\Observers;

use AppModules\Leads\Models\LeadCategory;

class LeadCategoryObserver
{

    public function saving(LeadCategory $leadCategory)
    {
        if (!isRunningInConsoleOrSeeding()) {
            $leadCategory->last_updated_by = user()->id;
        }
    }

    public function creating(LeadCategory $leadCategory)
    {
        if (!isRunningInConsoleOrSeeding()) {
            $leadCategory->added_by = user()->id;
        }

        if (company()) {
            $leadCategory->company_id = company()->id;
        }
    }

}
