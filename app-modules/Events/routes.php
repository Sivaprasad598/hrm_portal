<?php

use Illuminate\Support\Facades\Route;
use AppModules\Events\Http\Controllers\EventCalendarController;
use AppModules\Events\Http\Controllers\EventFileController;
use AppModules\Events\Http\Controllers\RecurringEventController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'events'], function () {
        Route::post('recurring-event/event-monthly-on', [RecurringEventController::class, 'monthlyOn'])->name('recurring-event.monthly_on');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'events'], function () {
        Route::post('recurring-event/apply-quick-action', [RecurringEventController::class, 'applyQuickAction'])->name('recurring-event.apply_quick_action');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'events'], function () {
        Route::resource('recurring-event', RecurringEventController::class);
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'events'], function () {
        Route::post('recurring-event/updateStatus/{id}', [RecurringEventController::class, 'updateStatus'])->name('recurring-event.update_status');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'events'], function () {
        Route::get('recurring-event/event-status-note/{id}', [RecurringEventController::class, 'eventStatusNote'])->name('recurring-event.event_status_note');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('event-monthly-on', [EventCalendarController::class, 'monthlyOn'])->name('events.monthly_on');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('events/table-view', [EventCalendarController::class, 'tableView'])->name('events.table_view');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('events/apply-quick-action', [EventCalendarController::class, 'applyQuickAction'])->name('events.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('events', EventCalendarController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('updateStatus/{id}', [EventCalendarController::class, 'updateStatus'])->name('events.update_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('events/event-status-note/{id}', [EventCalendarController::class, 'eventStatusNote'])->name('events.event_status_note');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('event-files/download/{id}', [EventFileController::class, 'download'])->name('event-files.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('event-files', EventFileController::class);
});
});
