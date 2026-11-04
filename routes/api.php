<?php


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

ApiRoute::group(['namespace' => 'App\Http\Controllers'], function () {
    ApiRoute::get('purchased-module', ['as' => 'api.purchasedModule', 'uses' => 'HomeController@installedModule']);
});

ApiRoute::group(['namespace' => 'App\Http\Controllers\Api'], function () {
    ApiRoute::post('attendances', ['as' => 'attendances.store', 'uses' => 'AttendanceController@store']);
    ApiRoute::get('attendances', ['as' => 'attendances.index', 'uses' => 'AttendanceController@index']);
    ApiRoute::get('attendances/{id}', ['as' => 'attendances.show', 'uses' => 'AttendanceController@show']);
});

// Lead Finder API: the Search Projects search as JSON. Authenticated by an administrator's key
// ("Authorization: Bearer <key>", made in Lead Generation Bot → API access); 10 searches a minute (each one is a ChatGPT search).
Route::get('lead-finder/projects/search', [\App\Http\Controllers\LeadFinderApiController::class, 'searchProjects'])
    ->middleware('throttle:10,1')->name('api.lead-finder.projects.search');
Route::get('lead-finder/companies/search', [\App\Http\Controllers\LeadFinderApiController::class, 'searchCompanies'])
    ->middleware('throttle:10,1')->name('api.lead-finder.companies.search');

// CRM Notes Chrome extension (meeting notes bot). Authenticated by the per-user key ("Authorization: Bearer ..."),
// not by session, so these are API routes without CSRF.
Route::prefix('crm-notes')->group(function () {
    Route::get('lookup', [\App\Http\Controllers\CrmNotesController::class, 'lookup']);
    Route::get('bot/due', [\App\Http\Controllers\CrmNotesController::class, 'botDue']);
    Route::post('meetings/{id}/segments', [\App\Http\Controllers\CrmNotesController::class, 'uploadSegment'])->whereNumber('id');
    Route::post('meetings/{id}/finish', [\App\Http\Controllers\CrmNotesController::class, 'finish'])->whereNumber('id');
});
