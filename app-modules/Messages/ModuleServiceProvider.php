<?php

namespace AppModules\Messages;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Messages, split out of core (branch hrm_v1_module_messages).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Same tables, URLs, route names and view names as before the split.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        View::addLocation(__DIR__ . '/views');

        // rows already stored under the old class names (morph types) keep resolving,
        // and new rows are written the same way in every branch
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\\Models\\MessageSetting' => Models\MessageSetting::class,
            'App\\Models\\UserChat' => Models\UserChat::class,
            'App\\Models\\UserchatFile' => Models\UserchatFile::class,
        ]);

        Models\UserChat::observe(Observers\NewChatObserver::class);
        Models\MessageSetting::observe(Observers\MessageSettingObserver::class);
        Models\UserchatFile::observe(Observers\UserchatFileObserver::class);
        Event::listen(Events\NewChatEvent::class, Listeners\NewChatListener::class);
        Event::listen(Events\NewMentionChatEvent::class, Listeners\NewMentionChatListener::class);
    }
}
