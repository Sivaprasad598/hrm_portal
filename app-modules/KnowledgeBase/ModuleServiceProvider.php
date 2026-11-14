<?php

namespace AppModules\KnowledgeBase;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Knowledge Base, split out of core (branch hrm_v1_module_knowledge-base).
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
            'App\\Models\\KnowledgeBase' => Models\KnowledgeBase::class,
            'App\\Models\\KnowledgeBaseCategory' => Models\KnowledgeBaseCategory::class,
            'App\\Models\\KnowledgeBaseFile' => Models\KnowledgeBaseFile::class,
        ]);

        Models\KnowledgeBase::observe(Observers\KnowledgeBaseObserver::class);
        Models\KnowledgeBaseCategory::observe(Observers\KnowledgeBaseCategoriesObserver::class);
    }
}
