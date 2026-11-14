<?php

use Illuminate\Support\Facades\Route;
use AppModules\Messages\Http\Controllers\MessageController;
use AppModules\Messages\Http\Controllers\MessageFileController;
use AppModules\Messages\Http\Controllers\MessageSettingController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('messages/fetch-user-list', [MessageController::class, 'fetchUserListView'])->name('messages.fetch_user_list');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('messages/fetch_messages/{id}', [MessageController::class, 'fetchUserMessages'])->name('messages.fetch_messages');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('messages/check_messages', [MessageController::class, 'checkNewMessages'])->name('messages.check_new_message');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::delete('messages/destroy-chat/{id}', [MessageController::class, 'destroyAll'])->name('messages.destroy_all');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('messages', MessageController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('message-file/download/{id}', [MessageFileController::class, 'download'])->name('message_file.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('message-file', MessageFileController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('message-settings', MessageSettingController::class);
});
});
