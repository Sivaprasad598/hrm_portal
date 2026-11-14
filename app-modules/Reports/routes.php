<?php

use Illuminate\Support\Facades\Route;
use AppModules\Reports\Http\Controllers\AttendanceReportController;
use AppModules\Reports\Http\Controllers\ExpenseReportController;
use AppModules\Reports\Http\Controllers\FinanceReportController;
use AppModules\Reports\Http\Controllers\IncomeVsExpenseReportController;
use AppModules\Reports\Http\Controllers\LeadReportController;
use AppModules\Reports\Http\Controllers\LeaveReportController;
use AppModules\Reports\Http\Controllers\SalesReportController;
use AppModules\Reports\Http\Controllers\TaskReportController;
use AppModules\Reports\Http\Controllers\TimelogReportController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('task-report-chart', [TaskReportController::class, 'taskChartData'])->name('task-report.chart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('task-report/employee-wise-task-report', [TaskReportController::class, 'employeeWiseTaskReport'])->name('employee-wise-task-report');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('task-report/consolidated-task-report', [TaskReportController::class, 'consolidatedTaskReport'])->name('consolidated-task-report');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('task-report', TaskReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('time-log-report-chart', [TimelogReportController::class, 'timelogChartData'])->name('time-log-report.chart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('time-log-consolidated-report', [TimelogReportController::class,'consolidateIndex'])->name('time-log-consolidated.report');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('time-log-project-wise-report', [TimelogReportController::class,'projectWiseTimelog'])->name('project-wise-timelog.report');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('project-wise-timelog/report/export', [TimelogReportController::class,'exportProjectWiseTimeLog'])->name('project-wise-timelog.export');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('time-log-report', TimelogReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('time-log-report-time', [TimelogReportController::class, 'totalTime'])->name('time-log-report.time');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('finance-report-chart', [FinanceReportController::class, 'financeChartData'])->name('finance-report.chart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('finance-report', FinanceReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('income-expense-report', IncomeVsExpenseReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('leave-report/leave-quota', [LeaveReportController::class, 'leaveQuota'])->name('leave-report.leave_quota');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('leave-report/leave-quota/{id}/{year}/{month}', [LeaveReportController::class, 'employeeLeaveQuota'])->name('leave-report.employee-leave-quota');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('leave-report', LeaveReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('attendance-report', AttendanceReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('expense-report-chart', [ExpenseReportController::class, 'expenseChartData'])->name('expense-report.chart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('expense-report/expense-category-report', [ExpenseReportController::class, 'expenseCategoryReport'])->name('expense-report.expense_category_report');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('expense-report', ExpenseReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deal-report/lead', [LeadReportController::class, 'leadContact'])->name('lead-report.lead_contact');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('lead-report/total', [LeadReportController::class, 'totalContact'])->name('lead-report.total_contact');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deal-report/chart', [LeadReportController::class, 'averageDealSizeReport'])->name('lead-report.chart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deal-report/profile', [LeadReportController::class, 'profile'])->name('lead-report.profile');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deal-report/export/{year}/{pipeline}/{category}', [LeadReportController::class, 'exportDealReport'])->name('deal-report.export');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('lead-report', LeadReportController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('sales-report', SalesReportController::class);
});
});
