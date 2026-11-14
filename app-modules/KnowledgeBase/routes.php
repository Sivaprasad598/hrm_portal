<?php

use Illuminate\Support\Facades\Route;
use AppModules\KnowledgeBase\Http\Controllers\KnowledgeBaseController;
use AppModules\KnowledgeBase\Http\Controllers\KnowledgeBaseFileController;
use AppModules\KnowledgeBase\Http\Controllers\KnowledgeBaseCategoryController;

// Same URLs and route names as before the split (/account/knowledgebase...)
Route::middleware(['web', 'auth'])->prefix('account')->group(function () {
    /* KnowledgeBase */
    Route::get('knowledgebase/create/{id?}', [KnowledgeBaseController::class, 'create'])->name('knowledgebase.create');
    Route::post('knowledgebase/apply-quick-action', [KnowledgeBaseController::class, 'applyQuickAction'])->name('knowledgebase.apply_quick_action');
    Route::get('knowledgebase/searchquery/{query?}', [KnowledgeBaseController::class, 'searchQuery'])->name('knowledgebase.searchQuery');
    Route::resource('knowledgebase', KnowledgeBaseController::class)->except(['create']);

    Route::get('knowledgebase-files/download/{id}', [KnowledgeBaseFileController::class, 'download'])->name('knowledgebase-files.download');
    Route::resource('knowledgebase-files', KnowledgeBaseFileController::class);

    /* KnowledgeBase category */
    Route::resource('knowledgebasecategory', KnowledgeBaseCategoryController::class);
});
