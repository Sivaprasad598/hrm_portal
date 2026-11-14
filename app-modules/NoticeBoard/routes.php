<?php

use Illuminate\Support\Facades\Route;
use AppModules\NoticeBoard\Http\Controllers\NoticeController;
use AppModules\NoticeBoard\Http\Controllers\NoticeFileController;

// Same URLs and route names as before the split (/account/notices...).
Route::middleware(['web', 'auth'])->prefix('account')->group(function () {
    Route::post('notices/apply-quick-action', [NoticeController::class, 'applyQuickAction'])->name('notices.apply_quick_action');
    Route::resource('notices', NoticeController::class);

    Route::get('notice-files/download/{id}', [NoticeFileController::class, 'download'])->name('notice_files.download');
    Route::resource('notice-files', NoticeFileController::class);
});
