<?php

namespace AppModules\Leads\Observers;

use AppModules\Leads\Events\LeadEvent;
use AppModules\Leads\Models\Lead;
use App\Models\UniversalSearch;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use AppModules\Leads\Notifications\LeadImported;


class LeadObserver
{

    public function saving(Lead $lead)
    {
        if (!isRunningInConsoleOrSeeding()) {
            $userID = (!is_null(user())) ? user()->id : null;
            $lead->last_updated_by = $userID;
        }

    }

    public function creating(Lead $leadContact)
    {
        $leadContact->hash = md5(microtime());

        if (!isRunningInConsoleOrSeeding()) {
            if (request()->has('added_by')) {
                $leadContact->added_by = request('added_by');

            }
            else {
                $userID = (!is_null(user())) ? user()->id : null;
                $leadContact->added_by = $userID;
            }
        }

        if (company()) {
            $leadContact->company_id = company()->id;
        }
    }

    public function created(Lead $leadContact)
    {
        if (!isRunningInConsoleOrSeeding()) {

            if (!session()->has('is_imported')) {

                event(new LeadEvent($leadContact, 'NewLeadCreated'));
            }else{



                if (session('leads_count') == (session('total_leads'))) {

                    info('check');
                    $admins = User::allAdmins(company()->id);
                    Notification::send($admins, new LeadImported());
                }

            }
        }
    }

    public function deleting(Lead $leadContact)
    {
        $notifyData = ['AppModules\Leads\Notifications\LeadAgentAssigned', 'App\Notifications\LeadAgentAssigned', 'App\Notifications\NewDealCreated', 'AppModules\Leads\Notifications\NewLeadCreated', 'App\Notifications\NewLeadCreated', 'AppModules\Leads\Notifications\LeadImported', 'App\Notifications\LeadImported'];
        \App\Models\Notification::deleteNotification($notifyData, $leadContact->id);
    }

    public function deleted(Lead $leadContact)
    {
        UniversalSearch::where('searchable_id', $leadContact->id)->where('module_type', 'lead')->delete();
    }

}
