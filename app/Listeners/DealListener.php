<?php

namespace App\Listeners;

use App\Events\DealEvent;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\DealStageUpdated;
use App\Notifications\LeadAgentAssigned;
use Illuminate\Support\Facades\Notification;

class DealListener
{

    /**
     * Handle the event.
     *
     * @param DealEvent $event
     * @return void
     */

    public function handle(DealEvent $event)
    {
        $lead = Deal::with('leadAgent', 'leadAgent.user', 'contact')->findOrFail($event->deal->id);

        $companyId = $lead->company_id;

        $adminUsers = User::allAdmins($companyId);
        $usersToNotify = collect($adminUsers);

        if ($lead->deal_watcher) {
            $dealWatcher = User::find($lead->deal_watcher);
            if ($dealWatcher) {
                $usersToNotify->push($dealWatcher);
            }
        }

        if ($lead->contact->lead_owner) {
            $leadOwner = User::find($lead->contact->lead_owner);
            if ($leadOwner) {
                $usersToNotify->push($leadOwner);
            }
        }

        if ($lead->leadAgent && $lead->leadAgent->user) {
            $leadAgent = User::find($lead->leadAgent->user->id);
            if ($leadAgent) {
                $usersToNotify->push($leadAgent);
            }
        }

        if (user()) {
            $createdBy = User::find(user()->id);
            if ($createdBy) {
                $usersToNotify->push($createdBy);
            }
        }

        // Remove duplicate users by id
        $usersToNotify = $usersToNotify->unique('id');

        if ($event->notificationName == 'LeadAgentAssigned') {
            if ($lead->leadAgent && $lead->leadAgent->user) {
                Notification::send($usersToNotify, new LeadAgentAssigned($lead));
            }
        }

        if ($event->notificationName == 'StageUpdated') {
            if ($lead->leadAgent && $lead->leadAgent->user) {
                Notification::send($usersToNotify, new DealStageUpdated($lead));
            }
        }
    }

}
