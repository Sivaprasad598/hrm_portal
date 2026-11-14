<?php

namespace AppModules\Tickets;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Tickets, split out of core (branch hrm_v1_module_tickets).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Same tables, URLs, route names and view names as before the split.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->commands([Console\Commands\FetchTicketEmails::class]);
        View::addLocation(__DIR__ . '/views');

        // rows already stored under the old class names (morph types) keep resolving,
        // and new rows are written the same way in every branch
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\\Models\\Ticket' => Models\Ticket::class,
            'App\\Models\\TicketActivity' => Models\TicketActivity::class,
            'App\\Models\\TicketAgentGroups' => Models\TicketAgentGroups::class,
            'App\\Models\\TicketChannel' => Models\TicketChannel::class,
            'App\\Models\\TicketCustomForm' => Models\TicketCustomForm::class,
            'App\\Models\\TicketEmailSetting' => Models\TicketEmailSetting::class,
            'App\\Models\\TicketFile' => Models\TicketFile::class,
            'App\\Models\\TicketGroup' => Models\TicketGroup::class,
            'App\\Models\\TicketReply' => Models\TicketReply::class,
            'App\\Models\\TicketReplyTemplate' => Models\TicketReplyTemplate::class,
            'App\\Models\\TicketReplyUser' => Models\TicketReplyUser::class,
            'App\\Models\\TicketSettingForAgents' => Models\TicketSettingForAgents::class,
            'App\\Models\\TicketTag' => Models\TicketTag::class,
            'App\\Models\\TicketTagList' => Models\TicketTagList::class,
            'App\\Models\\TicketType' => Models\TicketType::class,
        ]);

        Models\Ticket::observe(Observers\TicketObserver::class);
        Models\TicketEmailSetting::observe(Observers\TicketEmailSettingObserver::class);
        Models\TicketReply::observe(Observers\TicketReplyObserver::class);
        Models\TicketFile::observe(Observers\TicketFileObserver::class);
        Models\TicketReplyTemplate::observe(Observers\TicketReplyTemplateObserver::class);
        Models\TicketGroup::observe(Observers\TicketGroupObserver::class);
        Models\TicketAgentGroups::observe(Observers\TicketAgentGroupsObserver::class);
        Models\TicketChannel::observe(Observers\TicketChannelObserver::class);
        Models\TicketCustomForm::observe(Observers\TicketCustomFormObserver::class);
        Models\TicketType::observe(Observers\TicketTypeObserver::class);
        Models\TicketTag::observe(Observers\TicketTagObserver::class);
        Models\TicketTagList::observe(Observers\TicketTagListObserver::class);
        Event::listen(Events\TicketEvent::class, Listeners\TicketListener::class);
        Event::listen(Events\TicketReplyEvent::class, Listeners\TicketReplyListener::class);
        Event::listen(Events\TicketRequesterEvent::class, Listeners\TicketRequesterListener::class);
        Event::listen(Events\MailTicketReplyEvent::class, Listeners\MailTicketReplyListener::class);
    }
}
