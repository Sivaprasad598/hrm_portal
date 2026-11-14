<?php

use Illuminate\Support\Facades\Route;
use AppModules\Gdpr\Http\Controllers\GdprController;
use AppModules\Gdpr\Http\Controllers\GdprSettingsController;
use AppModules\Gdpr\Http\Controllers\PublicLeadGdprController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('gdpr/lead/approve-reject/{id}/{type}', [GdprSettingsController::class, 'approveRejectLead'])->name('gdpr.lead.approve_reject');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('gdpr/customer/approve-reject/{id}/{type}', [GdprSettingsController::class, 'approveRejectClient'])->name('gdpr.customer.approve_reject');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('gdpr-settings/apply-quick-action', [GdprSettingsController::class, 'applyQuickAction'])->name('gdpr_settings.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::put('gdpr-settings.update-general', [GdprSettingsController::class, 'updateGeneral'])->name('gdpr_settings.update_general');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('gdpr/store-consent', [GdprSettingsController::class, 'storeConsent'])->name('gdpr.store_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('gdpr/add-consent', [GdprSettingsController::class, 'AddConsent'])->name('gdpr.add_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('gdpr/edit-consent/{id}', [GdprSettingsController::class, 'editConsent'])->name('gdpr.edit_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::put('gdpr/update-consent/{id}', [GdprSettingsController::class, 'updateConsent'])->name('gdpr.update_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::delete('gdpr-settings/purpose-delete/{id}', [GdprSettingsController::class, 'purposeDelete'])->name('gdpr_settings.purpose_delete');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('gdpr-settings', GdprSettingsController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('gdpr/update-client-consent', [GdprController::class, 'updateClientConsent'])->name('gdpr.update_client_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('gdpr/export-data', [GdprController::class, 'downloadJson'])->name('gdpr.export_data');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('gdpr/update-consent-block', [GdprController::class, 'updateConsentBlock'])->name('gdpr.update_consent_block');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('gdpr', GdprController::class);
});

Route::get('/consent/l/{hash}', [PublicLeadGdprController::class, 'consent'])->name('front.gdpr.consent');

Route::post('/consent/remove-lead-request', [PublicLeadGdprController::class, 'learemoveLeadRequestd'])->name('front.gdpr.remove_lead_request');

Route::post('/consent/l/update/{lead}', [PublicLeadGdprController::class, 'updateConsent'])->name('front.gdpr.consent.update');
});
