<?php

use Illuminate\Support\Facades\Route;
use AppModules\ClientNotes\Http\Controllers\ClientNoteController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('client-notes/ask-for-password/{id}', [ClientNoteController::class, 'askForPassword'])->name('client_notes.ask_for_password');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('client-notes/check-password', [ClientNoteController::class, 'checkPassword'])->name('client_notes.check_password');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('client-notes/apply-quick-action', [ClientNoteController::class, 'applyQuickAction'])->name('client-notes.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('client-notes/showVerified/{id}', [ClientNoteController::class, 'showVerified'])->name('client-notes.show_verified');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('client-notes', ClientNoteController::class);
});
});
