<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use AppModules\Tickets\Http\Controllers\TicketAgentController;
use AppModules\Tickets\Http\Controllers\TicketChannelController;
use AppModules\Tickets\Http\Controllers\TicketController;
use AppModules\Tickets\Http\Controllers\TicketCustomFormController;
use AppModules\Tickets\Http\Controllers\TicketEmailSettingController;
use AppModules\Tickets\Http\Controllers\TicketFileController;
use AppModules\Tickets\Http\Controllers\TicketGroupController;
use AppModules\Tickets\Http\Controllers\TicketReplyController;
use AppModules\Tickets\Http\Controllers\TicketReplyTemplatesController;
use AppModules\Tickets\Http\Controllers\TicketSettingController;
use AppModules\Tickets\Http\Controllers\TicketTypeController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::get('/ticket-form/{id}', [HomeController::class, 'ticketForm'])->name('front.ticket_form');
Route::post('/lead-form/ticket-store', [HomeController::class, 'ticketStore'])->name('front.ticket_store');

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('get-contacts', [TicketController::class, 'getContacts'])->name('get.contacts');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('tickets/apply-quick-action', [TicketController::class, 'applyQuickAction'])->name('tickets.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('tickets/updateOtherData/{id}', [TicketController::class, 'updateOtherData'])->name('tickets.update_other_data');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('tickets/change-status', [TicketController::class, 'changeStatus'])->name('tickets.change-status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('tickets/refreshCount', [TicketController::class, 'refreshCount'])->name('tickets.refresh_count');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('tickets/agent-group/{id}/{exceptThis?}', [TicketController::class, 'agentGroup'])->name('tickets.agent_group');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('tickets/edit-details/{id}', [TicketController::class, 'editDetail'])->name('tickets.edit_detail');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::put('tickets/update-details/{id}', [TicketController::class, 'updateDetail'])->name('tickets.update_detail');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('tickets', TicketController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('ticket-form/sort-fields', [TicketCustomFormController::class, 'sortFields'])->name('ticket-form.sort_fields');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('ticket-form', TicketCustomFormController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('ticket-files/download/{id}', [TicketFileController::class, 'download'])->name('ticket-files.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('ticket-files', TicketFileController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('ticket-replies', TicketReplyController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('ticket-replies/edit-note/{id}', [TicketReplyController::class, 'editNote'])->name('ticket-replies.edit_note');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::post('ticket-agents/update-group/{id}', [TicketAgentController::class, 'updateGroup'])->name('ticket_agents.update_group');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('ticket-agents', TicketAgentController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::get('agent-groups', [TicketAgentController::class, 'agentGroups'])->name('ticket_agents.agent_groups');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('ticket-settings', TicketSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::post('ticket-settings-status/update-status/{companyId}', [TicketSettingController::class, 'updateTicketSettingStatus'])->name('ticket-setting.update_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::post('/ticket-agent-settings/{companyId}', [TicketSettingController::class, 'updateTicketSettingForAgent'])->name('ticket-agent-settings.update');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('ticket-groups', TicketGroupController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('ticketTypes', TicketTypeController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('ticketChannels', TicketChannelController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('ticket-email-settings', TicketEmailSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::get('replyTemplates/fetch-template', [TicketReplyTemplatesController::class, 'fetchTemplate'])->name('replyTemplates.fetchTemplate');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('replyTemplates', TicketReplyTemplatesController::class);
});
});
