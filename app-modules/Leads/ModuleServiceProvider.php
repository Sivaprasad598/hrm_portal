<?php

namespace AppModules\Leads;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Leads, split out of core (branch hrm_v1_module_leads).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Same tables, URLs, route names and view names as before the split.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->commands([Console\Commands\SendAutoFollowUpReminder::class]);
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command('send-auto-followup-reminder')->everyMinute();
        });
        View::addLocation(__DIR__ . '/views');

        // rows already stored under the old class names (morph types) keep resolving,
        // and new rows are written the same way in every branch
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\\Models\\Deal' => Models\Deal::class,
            'App\\Models\\DealFile' => Models\DealFile::class,
            'App\\Models\\DealFollowUp' => Models\DealFollowUp::class,
            'App\\Models\\DealHistory' => Models\DealHistory::class,
            'App\\Models\\DealNote' => Models\DealNote::class,
            'App\\Models\\Lead' => Models\Lead::class,
            'App\\Models\\LeadAgent' => Models\LeadAgent::class,
            'App\\Models\\LeadCategory' => Models\LeadCategory::class,
            'App\\Models\\LeadCustomForm' => Models\LeadCustomForm::class,
            'App\\Models\\LeadNote' => Models\LeadNote::class,
            'App\\Models\\LeadPipeline' => Models\LeadPipeline::class,
            'App\\Models\\LeadPipelineStages' => Models\LeadPipelineStages::class,
            'App\\Models\\LeadProduct' => Models\LeadProduct::class,
            'App\\Models\\LeadSource' => Models\LeadSource::class,
            'App\\Models\\LeadStatus' => Models\LeadStatus::class,
            'App\\Models\\LeadUserNote' => Models\LeadUserNote::class,
            'App\\Models\\PipelineStage' => Models\PipelineStage::class,
            'App\\Models\\Proposal' => Models\Proposal::class,
            'App\\Models\\ProposalItem' => Models\ProposalItem::class,
            'App\\Models\\ProposalItemImage' => Models\ProposalItemImage::class,
            'App\\Models\\ProposalSign' => Models\ProposalSign::class,
            'App\\Models\\ProposalTemplate' => Models\ProposalTemplate::class,
            'App\\Models\\ProposalTemplateItem' => Models\ProposalTemplateItem::class,
            'App\\Models\\ProposalTemplateItemImage' => Models\ProposalTemplateItemImage::class,
            'App\\Models\\RemovalRequestLead' => Models\RemovalRequestLead::class,
            'App\\Models\\UserLeadboardSetting' => Models\UserLeadboardSetting::class,
        ]);

        Models\Deal::observe(Observers\DealObserver::class);
        Models\LeadAgent::observe(Observers\LeadAgentObserver::class);
        Models\LeadCategory::observe(Observers\LeadCategoryObserver::class);
        Models\LeadCustomForm::observe(Observers\LeadCustomFormObserver::class);
        Models\DealFile::observe(Observers\LeadFileObserver::class);
        Models\DealFollowUp::observe(Observers\LeadFollowUpObserver::class);
        Models\LeadNote::observe(Observers\LeadNoteObserver::class);
        Models\DealNote::observe(Observers\DealNoteObserver::class);
        Models\LeadSource::observe(Observers\LeadSourceObserver::class);
        Models\LeadStatus::observe(Observers\LeadStatusObserver::class);
        Models\Proposal::observe(Observers\ProposalObserver::class);
        Models\ProposalTemplate::observe(Observers\ProposalTemplateObserver::class);
        Models\UserLeadboardSetting::observe(Observers\UserLeadboardSettingObserver::class);
        Models\PipelineStage::observe(Observers\LeadStageObserver::class);
        Models\LeadPipeline::observe(Observers\LeadPipelineObserver::class);
        Models\Lead::observe(Observers\LeadObserver::class);
        Event::listen(Events\RemovalRequestAdminLeadEvent::class, Listeners\RemovalRequestAdminLeadListener::class);
        Event::listen(Events\RemovalRequestApprovedRejectLeadEvent::class, Listeners\RemovalRequestApprovedRejectLeadListener::class);
        Event::listen(Events\LeadEvent::class, Listeners\LeadListener::class);
        Event::listen(Events\NewProposalEvent::class, Listeners\NewProposalListener::class);
        Event::listen(Events\AutoFollowUpReminderEvent::class, Listeners\AutoFollowUpReminderListener::class);
        Event::listen(Events\DealEvent::class, Listeners\DealListener::class);
    }
}
