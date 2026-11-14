<?php

namespace AppModules\NoticeBoard;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Notice Board, split out of core (branch hrm_v1_module_notice-board).
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
            'App\\Models\\Notice' => Models\Notice::class,
            'App\\Models\\NoticeBoardUser' => Models\NoticeBoardUser::class,
            'App\\Models\\NoticeFile' => Models\NoticeFile::class,
            'App\\Models\\NoticeView' => Models\NoticeView::class,
        ]);

        Models\Notice::observe(Observers\NoticeObserver::class);
        Models\NoticeFile::observe(Observers\NoticeFileObserver::class);
        Models\NoticeView::observe(Observers\NoticeViewObserver::class);
        Event::listen(Events\NewNoticeEvent::class, Listeners\NewNoticeListener::class);
    }
}
