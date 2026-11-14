<?php

use Illuminate\Support\Facades\Route;
use AppModules\Clients\Http\Controllers\ClientCategoryController;
use AppModules\Clients\Http\Controllers\ClientContactController;
use AppModules\Clients\Http\Controllers\ClientController;
use AppModules\Clients\Http\Controllers\ClientDocController;
use AppModules\Clients\Http\Controllers\ClientSubCategoryController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('approve/{id}', [ClientController::class, 'approve'])->name('clients.approve');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('save-consent-purpose-data/{client}', [ClientController::class, 'saveConsentLeadData'])->name('clients.save_consent_purpose_data');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('clients/gdpr-consent', [ClientController::class, 'consent'])->name('clients.gdpr_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('clients/save-client-consent/{lead}', [ClientController::class, 'saveClientConsent'])->name('clients.save_client_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('clients/apply-quick-action', [ClientController::class, 'applyQuickAction'])->name('clients.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('clients/import', [ClientController::class, 'importClient'])->name('clients.import');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('clients/import', [ClientController::class, 'importStore'])->name('clients.import.store');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('clients/import/process', [ClientController::class, 'importProcess'])->name('clients.import.process');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('clients/finance-count/{id}', [ClientController::class, 'financeCount'])->name('clients.finance_count');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('clients', ClientController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('client-contacts/apply-quick-action', [ClientContactController::class, 'applyQuickAction'])->name('client-contacts.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('client-contacts', ClientContactController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('client-docs/download/{id}', [ClientDocController::class, 'download'])->name('client-docs.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('client-docs', ClientDocController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('clientCategory', ClientCategoryController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('getClientSubCategories/{id}', [ClientSubCategoryController::class, 'getSubCategories'])->name('get_client_sub_categories');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('clientSubCategory', ClientSubCategoryController::class);
});
});
