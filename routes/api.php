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

// The old /api/v1/attendances endpoints are gone: they took any user id
// with no sign-in. Attendance from outside comes in through the mobile API
// below (signed in, own records only) or the biometric sync.

/*
| Employee mobile app: /api/mobile/v1/...  (docs: "mobile API resources/")
| Sign in with e-mail and password for a bearer token; everything else needs it
| and works on the signed-in employee only.
*/
Route::prefix('mobile/v1')->middleware('mobile.api')->namespace('App\Http\Controllers\Api\Mobile')->group(function () {
    Route::post('auth/login', 'AuthController@login');
    Route::post('auth/forgot-password', 'AuthController@forgotPassword');
    Route::post('auth/verify-reset-code', 'AuthController@verifyResetCode');
    Route::post('auth/reset-password', 'AuthController@resetPassword');

    Route::middleware(['auth:sanctum', 'mobile.employee'])->group(function () {
        Route::post('auth/logout', 'AuthController@logout');
        Route::post('auth/change-password', 'AuthController@changePassword');
        Route::get('me', 'ProfileController@show');
        Route::post('me', 'ProfileController@update');   // POST: it can carry a photo

        Route::get('assets', 'AssetController@index');
        Route::get('assets/{asset}', 'AssetController@show')->whereNumber('asset');

        Route::get('payslips', 'PayslipController@index');
        Route::get('payslips/{payslip}', 'PayslipController@show')->whereNumber('payslip');
        Route::get('payslips/{payslip}/download', 'PayslipController@download')->whereNumber('payslip');

        Route::get('attendance', 'AttendanceController@index');
        Route::get('attendance/today', 'AttendanceController@today');
        Route::post('attendance/clock-in', 'AttendanceController@clockIn');
        Route::post('attendance/clock-out', 'AttendanceController@clockOut');
        Route::post('attendance/breaks/start', 'AttendanceController@startBreak');
        Route::post('attendance/breaks/end', 'AttendanceController@endBreak');
        Route::get('attendance/calendar', 'AttendanceController@calendar');
        Route::get('attendance/day/{date}', 'AttendanceController@day');

        Route::get('calendar', 'CalendarController@index');

        Route::get('leaves/types', 'LeaveController@types');
        Route::get('leaves', 'LeaveController@index');
        Route::post('leaves', 'LeaveController@store');
        Route::get('leaves/{leave}', 'LeaveController@show')->whereNumber('leave');
    });
});
