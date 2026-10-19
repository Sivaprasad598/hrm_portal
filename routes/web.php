<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\TaskBlockerController;
use App\Http\Controllers\TaskMeetingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SubTaskController;
use App\Http\Controllers\TimelogController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeExitController;
use App\Http\Controllers\ResignationController;
use App\Http\Controllers\ResignationManagementController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\LeadFileController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\PassportController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskFileController;
use App\Http\Controllers\TaskNoteController;
use App\Http\Controllers\ClientDocController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventFileController;
use App\Http\Controllers\LeadBoardController;
use App\Http\Controllers\LeaveFileController;
use App\Http\Controllers\QuickbookController;
use App\Http\Controllers\TaskBoardController;
use App\Http\Controllers\TaskLabelController;
use App\Http\Controllers\ProjectLabelController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClientNoteController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\LeadReportController;
use App\Http\Controllers\StickyNoteController;
use App\Http\Controllers\TaskReportController;
use App\Http\Controllers\TicketFileController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeDocController;
use App\Http\Controllers\EmployeeDocumentExpiryController;
use App\Http\Controllers\LeadCategoryController;
use App\Http\Controllers\LeaveReportController;
use App\Http\Controllers\LeavesQuotaController;
use App\Http\Controllers\MessageFileController;
use App\Http\Controllers\ProductFileController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProjectNoteController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SubTaskFileController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TicketReplyController;
use App\Http\Controllers\AppreciationController;
use App\Http\Controllers\ContractFileController;
use App\Http\Controllers\ContractTypeController;
use App\Http\Controllers\EmployeeVisaController;
use App\Http\Controllers\GdprSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskCalendarController;
use App\Http\Controllers\TaskCategoryController;
use App\Http\Controllers\InvoiceFilesController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ContractRenewController;
use App\Http\Controllers\EventCalendarController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectRatingController;
use App\Http\Controllers\TimelogReportController;
use App\Http\Controllers\ClientCategoryController;
use App\Http\Controllers\LeadCustomFormController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\DiscussionFilesController;
use App\Http\Controllers\DiscussionReplyController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProjectCalendarController;
use App\Http\Controllers\ProjectCategoryController;
use App\Http\Controllers\ProjectTemplateController;
use App\Http\Controllers\TimelogCalendarController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\RecurringEventController;
use App\Http\Controllers\RecurringTaskController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\EstimateTemplateController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\ProposalTemplateController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\TicketCustomFormController;
use App\Http\Controllers\ClientSubCategoryController;
use App\Http\Controllers\KnowledgeBaseFileController;
use App\Http\Controllers\ContractDiscussionController;
use App\Http\Controllers\DealNoteController;
use App\Http\Controllers\DiscussionCategoryController;
use App\Http\Controllers\ProductSubCategoryController;
use App\Http\Controllers\ProjectTemplateTaskController;
use App\Http\Controllers\ProjectTimelogBreakController;
use App\Http\Controllers\EmployeeShiftScheduleController;
use App\Http\Controllers\IncomeVsExpenseReportController;
use App\Http\Controllers\KnowledgeBaseCategoryController;
use App\Http\Controllers\ProjectTemplateMemberController;
use App\Http\Controllers\ProjectTemplateSubTaskController;
use App\Http\Controllers\EmployeeShiftChangeRequestController;
use App\Http\Controllers\EstimateRequestController;
use App\Http\Controllers\GanttLinkController;
use App\Http\Controllers\LeadContactController;
use App\Http\Controllers\NoticeFileController;
use App\Http\Controllers\InvoicePaymentDetailController;
use App\Http\Controllers\MyCalendarController;
use App\Http\Controllers\ProjectSubCategoryController;
use App\Http\Controllers\ProjectTemplateMilestoneController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\TimelogWeeklyApprovalController;
use App\Http\Controllers\WeeklyTimesheetController;
use App\Http\Controllers\Auth\LogoutController;


Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('logout', LogoutController::class)->name('logout');
    Route::post('image/upload', [ImageController::class, 'store'])->name('image.store');


    Route::get('account-unverified', [DashboardController::class, 'accountUnverified'])->name('account_unverified');
    Route::get('checklist', [DashboardController::class, 'checklist'])->name('checklist');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/universal-bundle-alert-dismiss', [DashboardController::class, 'dismissUniversalBundleAlert'])->name('dashboard.universal_bundle_alert_dismiss');
    Route::get('dashboard-advanced', [DashboardController::class, 'advancedDashboard'])->name('dashboard.advanced');
    Route::post('dashboard/widget/{dashboardType}', [DashboardController::class, 'widget'])->name('dashboard.widget');
    Route::post('dashboard/week-timelog', [DashboardController::class, 'weekTimelog'])->name('dashboard.week_timelog');
    Route::get('dashboard/lead-data/{id}', [DashboardController  ::class, 'getLeadStage'])->name('dashboard.deal-stage-data');

    Route::get('attendances/clock-in-modal', [DashboardController::class, 'clockInModal'])->name('attendances.clock_in_modal');
    Route::post('attendances/store-clock-in', [DashboardController::class, 'storeClockIn'])->name('attendances.store_clock_in');
    Route::get('attendances/update-clock-in', [DashboardController::class, 'updateClockIn'])->name('attendances.update_clock_in');
    // The same handler over POST. The GET above cannot carry a photograph —
    // a data URL runs to tens of kilobytes and query strings do not. Left in
    // place rather than converted so nothing already pointing at it breaks.
    Route::post('attendances/store-clock-out', [DashboardController::class, 'updateClockIn'])->name('attendances.store_clock_out');
    Route::get('attendances/show_clocked_hours', [DashboardController::class, 'showClockedHours'])->name('attendances.show_clocked_hours');
    Route::get('dashboard/private_calendar', [DashboardController::class, 'privateCalendar'])->name('dashboard.private_calendar');
    Route::get('/pusher/beams-auth', [DashboardController::class, 'beamAuth'])->name('dashboard.beam_auth');

    Route::get('settings/change-language', [SettingsController::class, 'changeLanguage'])->name('settings.change_language');
    Route::resource('settings', SettingsController::class)->only(['edit', 'update', 'index', 'change_language']);


    Route::post('approve/{id}', [ClientController::class, 'approve'])->name('clients.approve');
    Route::post('save-consent-purpose-data/{client}', [ClientController::class, 'saveConsentLeadData'])->name('clients.save_consent_purpose_data');
    Route::get('clients/gdpr-consent', [ClientController::class, 'consent'])->name('clients.gdpr_consent');
    Route::post('clients/save-client-consent/{lead}', [ClientController::class, 'saveClientConsent'])->name('clients.save_client_consent');
    Route::post('clients/ajax-details/{id}', [ClientController::class, 'ajaxDetails'])->name('clients.ajax_details');
    Route::get('clients/client-details/{id}', [ClientController::class, 'clientDetails'])->name('clients.client_details');
    Route::post('clients/project-list/{id}', [ClientController::class, 'projectList'])->name('clients.project_list');
    Route::post('clients/apply-quick-action', [ClientController::class, 'applyQuickAction'])->name('clients.apply_quick_action');
    Route::get('clients/import', [ClientController::class, 'importClient'])->name('clients.import');
    Route::post('clients/import', [ClientController::class, 'importStore'])->name('clients.import.store');
    Route::post('clients/import/process', [ClientController::class, 'importProcess'])->name('clients.import.process');
    Route::get('clients/finance-count/{id}', [ClientController::class, 'financeCount'])->name('clients.finance_count');
    Route::resource('clients', ClientController::class);

    Route::post('client-contacts/apply-quick-action', [ClientContactController::class, 'applyQuickAction'])->name('client-contacts.apply_quick_action');
    Route::get('get-contacts', [TicketController::class, 'getContacts'])->name('get.contacts');
    Route::resource('client-contacts', ClientContactController::class);

    Route::get('client-notes/ask-for-password/{id}', [ClientNoteController::class, 'askForPassword'])->name('client_notes.ask_for_password');
    Route::post('client-notes/check-password', [ClientNoteController::class, 'checkPassword'])->name('client_notes.check_password');
    Route::post('client-notes/apply-quick-action', [ClientNoteController::class, 'applyQuickAction'])->name('client-notes.apply_quick_action');
    Route::post('client-notes/showVerified/{id}', [ClientNoteController::class, 'showVerified'])->name('client-notes.show_verified');
    Route::resource('client-notes', ClientNoteController::class);

    Route::get('client-docs/download/{id}', [ClientDocController::class, 'download'])->name('client-docs.download');
    Route::resource('client-docs', ClientDocController::class);

    // client category & subcategory
    Route::resource('clientCategory', ClientCategoryController::class);

    Route::get('getClientSubCategories/{id}', [ClientSubCategoryController::class, 'getSubCategories'])->name('get_client_sub_categories');
    Route::resource('clientSubCategory', ClientSubCategoryController::class);

    // employee Promotion
    Route::resource('promotions', PromotionController::class);

    // employee routes
    Route::post('employees/apply-quick-action', [EmployeeController::class, 'applyQuickAction'])->name('employees.apply_quick_action');
    Route::post('employees/assignRole', [EmployeeController::class, 'assignRole'])->name('employees.assign_role');
    Route::get('employees/byDepartment/{id}', [EmployeeController::class, 'byDepartment'])->name('employees.by_department');
    Route::get('employees/invite-member', [EmployeeController::class, 'inviteMember'])->name('employees.invite_member');
    Route::get('employees/import', [EmployeeController::class, 'importMember'])->name('employees.import');
    Route::post('employees/import', [EmployeeController::class, 'importStore'])->name('employees.import.store');
    Route::post('employees/import/process', [EmployeeController::class, 'importProcess'])->name('employees.import.process');
    // The HR master sheet: upload, review, then import (EmployeeMasterImportController).
    Route::get('employees/import/sample', [App\Http\Controllers\EmployeeMasterImportController::class, 'sample'])->name('employees.import.sample');
    Route::post('employees/import/review', [App\Http\Controllers\EmployeeMasterImportController::class, 'review'])->name('employees.import.review');
    Route::post('employees/import/apply', [App\Http\Controllers\EmployeeMasterImportController::class, 'apply'])->name('employees.import.apply');
    Route::get('employees/import/report', [App\Http\Controllers\EmployeeMasterImportController::class, 'report'])->name('employees.import.report');
    Route::get('import/process/{name}/{id}', [ImportController::class, 'getImportProgress'])->name('import.process.progress');

    Route::get('employees/import/exception/{name}', [ImportController::class, 'getQueueException'])->name('import.process.exception');
    Route::post('employees/send-invite', [EmployeeController::class, 'sendInvite'])->name('employees.send_invite');
    Route::post('employees/create-link', [EmployeeController::class, 'createLink'])->name('employees.create_link');
    Route::post('/get-exit-date-message', [EmployeeController::class, 'getExitDateMessage'])->name('getExitDateMessage');

    // Helpers the Add / Edit Employee form calls while it is filled in
    Route::post('employees/onboarding/resume', [App\Http\Controllers\EmployeeOnboardingController::class, 'resume'])->name('employees.onboarding.resume');
    Route::get('employees/onboarding/ifsc/{code}', [App\Http\Controllers\EmployeeOnboardingController::class, 'ifsc'])->name('employees.onboarding.ifsc');
    Route::get('employees/onboarding/salary', [App\Http\Controllers\EmployeeOnboardingController::class, 'salary'])->name('employees.onboarding.salary');

    // Rejoin: a former employee comes back as the same employee
    Route::get('employees/{id}/rejoin', [App\Http\Controllers\EmployeeRejoinController::class, 'create'])->name('employees.rejoin.create');
    Route::post('employees/{id}/rejoin', [App\Http\Controllers\EmployeeRejoinController::class, 'store'])->name('employees.rejoin.store');
    Route::post('employee-rejoins/{rejoin}/cancel', [App\Http\Controllers\EmployeeRejoinController::class, 'cancel'])->name('employees.rejoin.cancel');
    Route::post('employment-periods/{period}/eligibility', [App\Http\Controllers\EmployeeRejoinController::class, 'eligibility'])->name('employees.rejoin.eligibility');

    Route::resource('employees', EmployeeController::class);

    // Employee Exit Management
    Route::get('employee-exit/initiate/{userId}', [EmployeeExitController::class, 'initiate'])->name('employee-exit.initiate');
    Route::post('employee-exit/store', [EmployeeExitController::class, 'store'])->name('employee-exit.store');
    Route::get('employee-exit/{id}/checklist', [EmployeeExitController::class, 'checklist'])->name('employee-exit.checklist');
    Route::patch('employee-exit/{id}/checklist', [EmployeeExitController::class, 'updateChecklist'])->name('employee-exit.update-checklist');
    Route::get('employee-exit/{id}/interview', [EmployeeExitController::class, 'interview'])->name('employee-exit.interview');
    Route::post('employee-exit/{id}/interview', [EmployeeExitController::class, 'storeInterview'])->name('employee-exit.store-interview');
    Route::get('employee-exit/history/{userId}', [EmployeeExitController::class, 'history'])->name('employee-exit.history');
    Route::post('employee-exit/confirm', [EmployeeExitController::class, 'confirmEmployee'])->name('employee-exit.confirm');
    Route::patch('employee-exit/{id}/offboard', [EmployeeExitController::class, 'offboard'])->name('employee-exit.offboard');
    Route::get('employee-exit/{id}', [EmployeeExitController::class, 'show'])->name('employee-exit.show');
    Route::get('employee-exit/{id}/edit', [EmployeeExitController::class, 'edit'])->name('employee-exit.edit');
    Route::put('employee-exit/{id}', [EmployeeExitController::class, 'update'])->name('employee-exit.update');
    Route::get('employee-exit', [EmployeeExitController::class, 'index'])->name('employee-exit.index');

    // Employee self-service resignation
    Route::get('resignation', [ResignationController::class, 'index'])->name('resignations.index');
    Route::post('resignation', [ResignationController::class, 'store'])->name('resignations.store');
    Route::put('resignation/{id}', [ResignationController::class, 'update'])->name('resignations.update');
    Route::delete('resignation/{id}', [ResignationController::class, 'destroy'])->name('resignations.destroy');
    Route::post('resignation/{id}/reply', [ResignationController::class, 'reply'])->name('resignations.reply');

    // Admin / HR resignation management
    Route::get('resignation-management', [ResignationManagementController::class, 'index'])->name('resignation-management.index');
    Route::get('resignation-management/notice-periods', [ResignationManagementController::class, 'noticePeriods'])->name('resignation-management.notice_periods');
    Route::get('resignation-management/{id}', [ResignationManagementController::class, 'show'])->name('resignation-management.show');
    Route::post('resignation-management/{id}/approve', [ResignationManagementController::class, 'approve'])->name('resignation-management.approve');
    Route::post('resignation-management/{id}/reject', [ResignationManagementController::class, 'reject'])->name('resignation-management.reject');
    Route::post('resignation-management/{id}/undo', [ResignationManagementController::class, 'undo'])->name('resignation-management.undo');
    Route::post('resignation-management/{id}/reply', [ResignationManagementController::class, 'reply'])->name('resignation-management.reply');

    Route::resource('passport', PassportController::class);
    Route::resource('employee-visa', EmployeeVisaController::class);

    Route::resource('emergency-contacts', EmergencyContactController::class);

    Route::get('employee-docs/download/{id}', [EmployeeDocController::class, 'download'])->name('employee-docs.download');
    Route::resource('employee-docs', EmployeeDocController::class);

    Route::get('employee-document-expiries/download/{id}', [EmployeeDocumentExpiryController::class, 'download'])->name('employee-document-expiries.download');
    Route::resource('employee-document-expiries', EmployeeDocumentExpiryController::class);

    Route::get('employee-leaves/employeeLeaveTypes/{id}', [LeavesQuotaController::class, 'employeeLeaveTypes'])->name('employee-leaves.employee_leave_types');
    Route::resource('employee-leaves', LeavesQuotaController::class);

    Route::get('designations/designation-hierarchy', [DesignationController::class, 'hierarchyData'])->name('designation.hierarchy');
    Route::post('designations/changeParent', [DesignationController::class, 'changeParent'])->name('designation.changeParent');
    Route::post('designations/search-filter', [DesignationController::class, 'searchFilter'])->name('designation.srchFilter');
    Route::post('designations/apply-quick-action', [DesignationController::class, 'applyQuickAction'])->name('designations.apply_quick_action');
    Route::resource('designations', DesignationController::class);

    Route::post('departments/apply-quick-action', [DepartmentController::class, 'applyQuickAction'])->name('departments.apply_quick_action');
    Route::get('departments/department-hierarchy', [DepartmentController::class, 'hierarchyData'])->name('department.hierarchy');
    Route::post('department/changeParent', [DepartmentController::class, 'changeParent'])->name('department.changeParent');
    Route::get('department/search', [DepartmentController::class, 'searchDepartment'])->name('departments.search');
    Route::get('department/{id}', [DepartmentController::class, 'getMembers'])->name('departments.members');
    Route::resource('departments', DepartmentController::class);

    Route::resource('branches', BranchController::class)->only(['create', 'store']);

    Route::post('user-permissions/customPermissions/{id}', [UserPermissionController::class, 'customPermissions'])->name('user-permissions.custom_permissions');
    Route::post('user-permissions/resetPermissions/{id}', [UserPermissionController::class, 'resetPermissions'])->name('user-permissions.reset_permissions');
    Route::resource('user-permissions', UserPermissionController::class);

    // =========================================================
    // SIDEBAR MENU MANAGEMENT — CRUD over `sidebar_menus`
    // The two bare GETs are declared before the resource, or /get-submenus
    // would be captured by menu-management/{menu}.
    // =========================================================
    Route::get('menu-management/get-submenus', [App\Http\Controllers\MenuController::class, 'getSubmenus'])->name('menu-management.get-submenus');
    Route::get('menu-management/next-priority', [App\Http\Controllers\MenuController::class, 'getNextPriority'])->name('menu-management.next-priority');
    Route::resource('menu-management', App\Http\Controllers\MenuController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    // =========================================================
    // ASSIGN MENUS — which roles may see which menus
    // =========================================================
    Route::get('assign-menus', [App\Http\Controllers\AssignMenuController::class, 'index'])->name('assign-menus.index');
    Route::get('assign-menus/{roleId}/access', [App\Http\Controllers\AssignMenuController::class, 'getRoleAccess'])->name('assign-menus.access');
    Route::post('assign-menus/{roleId}/access', [App\Http\Controllers\AssignMenuController::class, 'updateRoleAccess'])->name('assign-menus.update-access');
    Route::post('assign-menus/{roleId}/assign-all', [App\Http\Controllers\AssignMenuController::class, 'assignAllMenus'])->name('assign-menus.assign-all');
    Route::post('assign-menus/{roleId}/revoke-all', [App\Http\Controllers\AssignMenuController::class, 'revokeAllMenus'])->name('assign-menus.revoke-all');

    // =========================================================
    // APP LAUNCHER SETTINGS — the tiles in the topbar launcher
    // =========================================================
    Route::get('app-launcher-settings', [App\Http\Controllers\AppLauncherController::class, 'index'])->name('app-launcher-settings.index');
    Route::get('app-launcher-settings/create', [App\Http\Controllers\AppLauncherController::class, 'create'])->name('app-launcher-settings.create');
    Route::post('app-launcher-settings', [App\Http\Controllers\AppLauncherController::class, 'store'])->name('app-launcher-settings.store');
    Route::get('app-launcher-settings/{id}/edit', [App\Http\Controllers\AppLauncherController::class, 'edit'])->name('app-launcher-settings.edit');
    Route::put('app-launcher-settings/{id}', [App\Http\Controllers\AppLauncherController::class, 'update'])->name('app-launcher-settings.update');
    Route::delete('app-launcher-settings/{id}', [App\Http\Controllers\AppLauncherController::class, 'destroy'])->name('app-launcher-settings.destroy');
    Route::post('app-launcher-settings/{id}/toggle-active', [App\Http\Controllers\AppLauncherController::class, 'toggleActive'])->name('app-launcher-settings.toggle-active');

    /* PROJECTS */
    Route::resource('projectCategory', ProjectCategoryController::class);
    Route::post('projects/change-status', [ProjectController::class, 'changeProjectStatus'])->name('projects.change_status');

    Route::resource('ProjectSubCategory', ProjectSubCategoryController::class);
    Route::get('get_project_sub_category/{id}', [ProjectSubCategoryController::class, 'getSubCategories'])->name('project.get_project_sub_category');


    Route::group(
        ['prefix' => 'projects'],
        function () {

            Route::get('import', [ProjectController::class, 'importProject'])->name('projects.import');
            Route::post('import', [ProjectController::class, 'importStore'])->name('projects.import.store');
            Route::post('import/process', [ProjectController::class, 'importProcess'])->name('projects.import.process');

            Route::post('assignProjectAdmin', [ProjectController::class, 'assignProjectAdmin'])->name('projects.assign_project_admin');
            Route::post('archive-restore/{id}', [ProjectController::class, 'archiveRestore'])->name('projects.archive_restore');
            Route::post('archive-delete/{id}', [ProjectController::class, 'archiveDestroy'])->name('projects.archive_delete');
            Route::get('archive', [ProjectController::class, 'archive'])->name('projects.archive');
            Route::post('apply-quick-action', [ProjectController::class, 'applyQuickAction'])->name('projects.apply_quick_action');
            Route::post('updateStatus/{id}', [ProjectController::class, 'updateStatus'])->name('projects.update_status');
            Route::post('store-pin', [ProjectController::class, 'storePin'])->name('projects.store_pin');
            Route::post('destroy-pin/{id}', [ProjectController::class, 'destroyPin'])->name('projects.destroy_pin');
            Route::post('gantt-data', [ProjectController::class, 'ganttData'])->name('projects.gantt_data');
            Route::post('invoiceList/{id}', [ProjectController::class, 'invoiceList'])->name('projects.invoice_list');
            Route::get('duplicate-project/{id}', [ProjectController::class, 'duplicateProject'])->name('projects.duplicate_project');

            Route::get('members/{id}', [ProjectController::class, 'members'])->name('projects.members');
            Route::get('pendingTasks/{id}', [ProjectController::class, 'pendingTasks'])->name('projects.pendingTasks');
            Route::get('labels/{id}', [TaskLabelController::class, 'labels'])->name('projects.labels');
            Route::resource('project-label', ProjectLabelController::class);

            Route::post('project-members/save-group', [ProjectMemberController::class, 'storeGroup'])->name('project-members.store_group');
            Route::resource('project-members', ProjectMemberController::class);

            Route::post('files/store-link', [ProjectFileController::class, 'storeLink'])->name('files.store_link');
            Route::get('files/download/{id}', [ProjectFileController::class, 'download'])->name('files.download');
            Route::get('files/thumbnail', [ProjectFileController::class, 'thumbnailShow'])->name('files.thumbnail');
            Route::post('files/multiple-upload', [ProjectFileController::class, 'storeMultiple'])->name('files.multiple_upload');
            Route::resource('files', ProjectFileController::class);

            Route::get('milestones/byProject/{id}', [ProjectMilestoneController::class, 'byProject'])->name('milestones.by_project');
            Route::post('/milestones/{id}/update-status', [ProjectMilestoneController::class, 'updateStatus'])->name('milestones.updateStatus');
            Route::resource('milestones', ProjectMilestoneController::class);


            // Discussion category routes
            Route::resource('discussion-category', DiscussionCategoryController::class);
            Route::post('discussion/setBestAnswer', [DiscussionController::class, 'setBestAnswer'])->name('discussion.set_best_answer');
            Route::resource('discussion', DiscussionController::class);
            Route::get('discussion-reply/get-replies/{id}', [DiscussionReplyController::class, 'getReplies'])->name('discussion-reply.get_replies');
            Route::resource('discussion-reply', DiscussionReplyController::class);

            // Discussion Files
            Route::get('discussion-files/download/{id}', [DiscussionFilesController::class, 'download'])->name('discussion_file.download');
            Route::resource('discussion-files', DiscussionFilesController::class);

            // Rating routes
            Route::resource('project-ratings', ProjectRatingController::class);

            Route::get('projects/burndown/{projectId?}', [ProjectController::class, 'burndown'])->name('projects.burndown');

            /* PROJECT TEMPLATE */
            Route::post('project-template/apply-quick-action', [ProjectTemplateController::class, 'applyQuickAction'])->name('project_template.apply_quick_action');
            Route::resource('project-template', ProjectTemplateController::class);
            Route::post('project-template-members/save-group', [ProjectTemplateMemberController::class, 'storeGroup'])->name('project_template_members.store_group');
            Route::resource('project-template-member', ProjectTemplateMemberController::class);
            Route::get('project-template-task/data/{templateId?}', [ProjectTemplateTaskController::class, 'data'])->name('project_template_task.data');
            Route::get('project-template-milestones/byProject/{id}', [ProjectTemplateMilestoneController::class, 'byProject'])->name('project-template-milestone.by_project');
            Route::post('/project-template-milestones/{id}/update-status', [ProjectTemplateMilestoneController::class, 'updateStatus'])->name('project-template-milestone.updateStatus');
            Route::resource('project-template-task', ProjectTemplateTaskController::class);
            Route::resource('project-template-milestone', ProjectTemplateMilestoneController::class);
            Route::resource('project-template-sub-task', ProjectTemplateSubTaskController::class);
            Route::resource('project-calendar', ProjectCalendarController::class);

        }
    );

    Route::get('project-notes/ask-for-password/{id}', [ProjectNoteController::class, 'askForPassword'])->name('project_notes.ask_for_password');
    Route::post('project-notes/check-password', [ProjectNoteController::class, 'checkPassword'])->name('project_notes.check_password');
    Route::post('project-notes/apply-quick-action', [ProjectNoteController::class, 'applyQuickAction'])->name('project_notes.apply_quick_action');
    Route::resource('project-notes', ProjectNoteController::class);
    Route::get('projects-ajax', [ProjectController::class, 'ajaxLoadProject'])->name('get.projects-ajax');
    Route::get('get-projects', [ProjectController::class, 'getProjects'])->name('get.projects');
    Route::resource('projects', ProjectController::class);

    /* PRODUCTS */
    Route::post('products/apply-quick-action', [ProductController::class, 'applyQuickAction'])->name('products.apply_quick_action');
    Route::post('products/remove-cart-item/{id}', [ProductController::class, 'removeCartItem'])->name('products.remove_cart_item');
    Route::get('products/options', [ProductController::class, 'allProductOption'])->name('products.options');


    Route::post('products/add-cart-item', [ProductController::class, 'addCartItem'])->name('products.add_cart_item');
    Route::get('products/cart', [ProductController::class, 'cart'])->name('products.cart');
    Route::get('products/empty-cart', [ProductController::class, 'emptyCart'])->name('products.empty_cart');

    /* Product Import */
    Route::group(
        ['prefix' => 'products'],
        function () {
            Route::get('import', [ProductController::class, 'importProduct'])->name('products.import');
            Route::post('import', [ProductController::class, 'importStore'])->name('products.import.store');
            Route::post('import/process', [ProductController::class, 'importProcess'])->name('products.import.process');
        }
    );

    Route::resource('products', ProductController::class);
    Route::resource('productCategory', ProductCategoryController::class);
    Route::get('getProductSubCategories/{id}', [ProductSubCategoryController::class, 'getSubCategories'])->name('get_product_sub_categories');
    Route::resource('productSubCategory', ProductSubCategoryController::class);

    /* PRODUCT FILES */
    Route::get('product-files/download/{id}', [ProductFileController::class, 'download'])->name('product-files.download');
    Route::post('product-files/delete-image/{id}', [ProductFileController::class, 'deleteImage'])->name('product-files.delete_image');
    Route::post('product-files/update-images', [ProductFileController::class, 'updateImages'])->name('product-files.update_images');
    Route::resource('product-files', ProductFileController::class);

    /* INVOICE FILES */
    Route::get('invoice-files/download/{id}', [InvoiceFilesController::class, 'download'])->name('invoice-files.download');
    Route::resource('invoice-files', InvoiceFilesController::class);


    /* Payments */
    Route::get('orders/offline-payment-modal', [OrderController::class, 'offlinePaymentModal'])->name('orders.offline_payment_modal');
    Route::get('orders/add-item', [OrderController::class, 'addItem'])->name('orders.add_item');
    Route::get('orders/stripe-modal', [OrderController::class, 'stripeModal'])->name('orders.stripe_modal');
    Route::post('orders/make-invoice/{orderId}', [OrderController::class, 'makeInvoice'])->name('orders.make_invoice');

    Route::post('orders/payment-failed/{orderId}', [OrderController::class, 'paymentFailed'])->name('orders.payment_failed');
    Route::post('orders/save-stripe-detail/', [OrderController::class, 'saveStripeDetail'])->name('orders.save_stripe_detail');
    Route::post('orders/change-status/', [OrderController::class, 'changeStatus'])->name('orders.change_status');
    /* Payments */
    Route::get('orders/download/{id}', [OrderController::class, 'download'])->name('orders.download');
    Route::post('orders/store-quantity/', [OrderController::class, 'storeQuantity'])->name('orders.store_quantity');


    /* Orders */
    Route::resource('orders', OrderController::class);


    /* NOTICE */
    Route::post('notices/apply-quick-action', [NoticeController::class, 'applyQuickAction'])->name('notices.apply_quick_action');
    Route::resource('notices', NoticeController::class);

    /* Notice files */
    Route::get('notice-files/download/{id}', [NoticeFileController::class, 'download'])->name('notice_files.download');
    Route::resource('notice-files', NoticeFileController::class);

    /* User Appreciation */
    Route::group(
        ['prefix' => 'appreciations'],
        function () {
            Route::post('awards/apply-quick-action', [AwardController::class, 'applyQuickAction'])->name('awards.apply_quick_action');
            Route::post('awards/change-status/{id?}', [AwardController::class, 'changeStatus'])->name('awards.change-status');
            Route::get('awards/quick-create', [AwardController::class, 'quickCreate'])->name('awards.quick-create');
            Route::post('awards/quick-store', [AwardController::class, 'quickStore'])->name('awards.quick-store');
            Route::resource('awards', AwardController::class);
        });
    Route::post('appreciations/apply-quick-action', [AppreciationController::class, 'applyQuickAction'])->name('appreciations.apply_quick_action');
    Route::resource('appreciations', AppreciationController::class);

    /* KnowledgeBase */
    Route::get('knowledgebase/create/{id?}', [KnowledgeBaseController::class, 'create'])->name('knowledgebase.create');
    Route::post('knowledgebase/apply-quick-action', [KnowledgeBaseController::class, 'applyQuickAction'])->name('knowledgebase.apply_quick_action');
    Route::get('knowledgebase/searchquery/{query?}', [KnowledgeBaseController::class, 'searchQuery'])->name('knowledgebase.searchQuery');
    Route::resource('knowledgebase', KnowledgeBaseController::class)->except(['create']);

    Route::get('knowledgebase-files/download/{id}', [KnowledgeBaseFileController::class, 'download'])->name('knowledgebase-files.download');
    Route::resource('knowledgebase-files', KnowledgeBaseFileController::class);

    /* KnowledgeBase category */
    Route::resource('knowledgebasecategory', KnowledgeBaseCategoryController::class);


    Route::group(['prefix' => 'events'], function () {
        Route::post('recurring-event/event-monthly-on', [RecurringEventController::class, 'monthlyOn'])->name('recurring-event.monthly_on');
        Route::post('recurring-event/apply-quick-action', [RecurringEventController::class, 'applyQuickAction'])->name('recurring-event.apply_quick_action');
        Route::resource('recurring-event', RecurringEventController::class);
        Route::post('recurring-event/updateStatus/{id}', [RecurringEventController::class, 'updateStatus'])->name('recurring-event.update_status');
        Route::get('recurring-event/event-status-note/{id}', [RecurringEventController::class, 'eventStatusNote'])->name('recurring-event.event_status_note');
    });

    /* EVENTS */
    Route::post('event-monthly-on', [EventCalendarController::class, 'monthlyOn'])->name('events.monthly_on');
    Route::get('events/table-view', [EventCalendarController::class, 'tableView'])->name('events.table_view');
    Route::post('events/apply-quick-action', [EventCalendarController::class, 'applyQuickAction'])->name('events.apply_quick_action');
    Route::resource('events', EventCalendarController::class);
    Route::post('updateStatus/{id}', [EventCalendarController::class, 'updateStatus'])->name('events.update_status');
    Route::get('events/event-status-note/{id}', [EventCalendarController::class, 'eventStatusNote'])->name('events.event_status_note');

    /* My Calendar */
    Route::get('my-calendar', [MyCalendarController::class, 'index'])->name('my-calendar.index');


    /* Event Files */
    Route::get('event-files/download/{id}', [EventFileController::class, 'download'])->name('event-files.download');
    Route::resource('event-files', EventFileController::class);

    /* TASKS */
    Route::get('tasks/client-detail', [TaskController::class, 'clientDetail'])->name('tasks.clientDetail');
    Route::post('tasks/change-status', [TaskController::class, 'changeStatus'])->name('tasks.change_status');
    Route::post('tasks/change-milestone', [TaskController::class, 'milestoneChange'])->name('tasks.change_milestone');

    Route::post('tasks/apply-quick-action', [TaskController::class, 'applyQuickAction'])->name('tasks.apply_quick_action');
    Route::post('tasks/store-pin', [TaskController::class, 'storePin'])->name('tasks.store_pin');
    Route::post('tasks/reminder', [TaskController::class, 'reminder'])->name('tasks.reminder');
    Route::post('tasks/destroy-pin/{id}', [TaskController::class, 'destroyPin'])->name('tasks.destroy_pin');
    Route::post('tasks/check-task/{taskID}', [TaskController::class, 'checkTask'])->name('tasks.check_task');
    Route::post('tasks/send-approval', [TaskController::class, 'sendApproval'])->name('tasks.send_approval');
    Route::post('tasks/gantt-task-update/{id}', [TaskController::class, 'updateTaskDuration'])->name('tasks.gantt_task_update');
    Route::get('tasks/members/{id}', [TaskController::class, 'members'])->name('tasks.members');
    Route::get('tasks/project_tasks/{id}', [TaskController::class, 'projectTasks'])->name('tasks.project_tasks');
    Route::get('tasks/check-leaves', [TaskController::class, 'checkLeaves'])->name('tasks.checkLeaves');
    Route::get('tasks/waiting-approval', [TaskController::class, 'waitingApproval'])->name('tasks.waiting-approval');
    Route::get('tasks/import', [TaskController::class, 'importTask'])->name('tasks.import');
    Route::post('tasks/import', [TaskController::class, 'importStore'])->name('tasks.import.store');
    Route::post('tasks/import/process', [TaskController::class, 'importProcess'])->name('tasks.import.process');
    Route::get('tasks/show-waiting-approval-change-status-modal', [TaskController::class, 'statusReason'])->name('tasks.show_status_reason_modal');
    Route::post('tasks/store-status-reason', [TaskController::class, 'storeStatusReason'])->name('tasks.store_comment_on_change_status');

    /* Task roadblocks */
    Route::get('task-blockers/user-tasks', [TaskBlockerController::class, 'userTasks'])->name('task-blockers.user_tasks');
    Route::resource('task-blockers', TaskBlockerController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    /* Task meetings */
    Route::post('task-meetings/{id}/respond', [TaskMeetingController::class, 'respond'])->name('task-meetings.respond');
    Route::post('task-meetings/{id}/cancel', [TaskMeetingController::class, 'cancel'])->name('task-meetings.cancel');
    Route::get('task-meetings/{id}/minutes', [TaskMeetingController::class, 'minutes'])->name('task-meetings.minutes');
    Route::post('task-meetings/{id}/minutes', [TaskMeetingController::class, 'storeMinutes'])->name('task-meetings.store_minutes');
    Route::resource('task-meetings', TaskMeetingController::class)->only(['create', 'store', 'edit', 'update']);

    Route::group(['prefix' => 'tasks'], function () {

        Route::post('recurring-task/apply-quick-action', [RecurringTaskController::class, 'applyQuickAction'])->name('recurring-task.apply_quick_action');
        Route::resource('recurring-task', RecurringTaskController::class);

        Route::resource('task-label', TaskLabelController::class);
        Route::resource('taskCategory', TaskCategoryController::class);
        Route::post('taskComment/save-comment-like', [TaskCommentController::class, 'saveCommentLike'])->name('taskComment.save_comment_like');
        Route::post('taskComment/bulk-delete', [TaskCommentController::class, 'bulkDestroy'])->name('taskComment.bulk_delete');
        Route::resource('taskComment', TaskCommentController::class);
        Route::resource('task-note', TaskNoteController::class);

        // task files routes
        Route::get('task-files/download/{id}', [TaskFileController::class, 'download'])->name('task_files.download');
        Route::post('task-files/bulk-delete', [TaskFileController::class, 'bulkDestroy'])->name('task-files.bulk_delete');
        Route::resource('task-files', TaskFileController::class);

        // Sub task routes
        Route::post('sub-task/change-status', [SubTaskController::class, 'changeStatus'])->name('sub_tasks.change_status');
        Route::resource('sub-tasks', SubTaskController::class);

        // Task files routes
        Route::get('sub-task-files/download/{id}', [SubTaskFileController::class, 'download'])->name('sub-task-files.download');
        Route::resource('sub-task-files', SubTaskFileController::class);

        // Taskboard routes
        Route::post('taskboards/collapseColumn', [TaskBoardController::class, 'collapseColumn'])->name('taskboards.collapse_column');
        Route::post('taskboards/updateIndex', [TaskBoardController::class, 'updateIndex'])->name('taskboards.update_index');
        Route::get('taskboards/loadMore', [TaskBoardController::class, 'loadMore'])->name('taskboards.load_more');
        Route::resource('taskboards', TaskBoardController::class);

        Route::resource('task-calendar', TaskCalendarController::class);
    });

    Route::resource('tasks', TaskController::class);

    // Holidays
    Route::get('holidays/mark-holiday', [HolidayController::class, 'markHoliday'])->name('holidays.mark_holiday');
    Route::post('holidays/mark-holiday-store', [HolidayController::class, 'markDayHoliday'])->name('holidays.mark_holiday_store');
    Route::get('holidays/table-view', [HolidayController::class, 'tableView'])->name('holidays.table_view');
    Route::post('holidays/apply-quick-action', [HolidayController::class, 'applyQuickAction'])->name('holidays.apply_quick_action');
    Route::resource('holidays', HolidayController::class);

    // Lead Files
    Route::get('deal-files/download/{id}', [LeadFileController::class, 'download'])->name('deal-files.download');
    Route::get('deal-files/layout', [LeadFileController::class, 'layout'])->name('deal-files.layout');
    Route::resource('deal-files', LeadFileController::class);

    // Follow up
    Route::get('deals/follow-up/{leadID}', [DealController::class, 'followUpCreate'])->name('deals.follow_up');
    Route::post('deals/follow-up-store', [DealController::class, 'followUpStore'])->name('deals.follow_up_store');
    Route::get('deals/follow-up-edit/{id?}', [DealController::class, 'editFollow'])->name('deals.follow_up_edit');
    Route::post('deals/follow-up-update', [DealController::class, 'updateFollow'])->name('deals.follow_up_update');
    Route::post('deals/follow-up-delete/{id}', [DealController::class, 'deleteFollow'])->name('deals.follow_up_delete');

    // Change status
    Route::get('stage-change/{id}', [DealController::class, 'stageChange'])->name('deals.stage_change');
    Route::post('save-stage-change', [DealController::class, 'saveStageChange'])->name('deals.save_stage_change');
    Route::post('deals/change-stage', [DealController::class, 'changeStage'])->name('deals.change_stage');
    Route::post('deals/apply-quick-action', [DealController::class, 'applyQuickAction'])->name('deals.apply_quick_action');

    Route::get('deals/gdpr-consent', [DealController::class, 'consent'])->name('deals.gdpr_consent');
    Route::post('deals/save-deal-consent/{deal}', [DealController::class, 'saveLeadConsent'])->name('deals.save_lead_consent');
    Route::post('deals/change-follow-up-status', [DealController::class, 'changeFollowUpStatus'])->name('deals.change_follow_up_status');

    // Lead Category
    Route::post('/update-lead-category', [LeadCategoryController::class, 'updateLeadCategory'])->name('category.updateDefault');
    Route::resource('leadCategory', LeadCategoryController::class);

    // Lead Note
    Route::get('lead-notes/ask-for-password/{id}', [LeadNoteController::class, 'askForPassword'])->name('lead-notes.ask_for_password');
    Route::post('lead-notes/check-password', [LeadNoteController::class, 'checkPassword'])->name('lead-notes.check_password');
    Route::post('lead-notes/apply-quick-action', [LeadNoteController::class, 'applyQuickAction'])->name('lead-notes.apply_quick_action');

    Route::resource('lead-notes', LeadNoteController::class);

    // Deal Note
    Route::post('deal-notes/apply-quick-action', [DealNoteController::class, 'applyQuickAction'])->name('deal-notes.apply_quick_action');
    Route::resource('deal-notes', DealNoteController::class);

    // deal board routes
    Route::post('leadboards/get-stage-slug', [LeadBoardController::class, 'getStageSlug'])->name('leadboards.get_stage_slug');
    Route::post('leadboards/collapseColumn', [LeadBoardController::class, 'collapseColumn'])->name('leadboards.collapse_column');
    Route::post('leadboards/updateIndex', [LeadBoardController::class, 'updateIndex'])->name('leadboards.update_index');
    Route::get('leadboards/loadMore', [LeadBoardController::class, 'loadMore'])->name('leadboards.load_more');
    Route::resource('leadboards', LeadBoardController::class);

    Route::post('lead-form/sortFields', [LeadCustomFormController::class, 'sortFields'])->name('lead-form.sortFields');
    Route::resource('lead-form', LeadCustomFormController::class);

    Route::group(['prefix' => 'deals'], function () {
        Route::get('import', [DealController::class, 'importLead'])->name('deals.import');
        Route::post('import', [DealController::class, 'importStore'])->name('deals.import.store');
        Route::post('import/process', [DealController::class, 'importProcess'])->name('deals.import.process');
    });

    Route::group(['prefix' => 'lead-contact'], function () {
        Route::get('import', [LeadContactController::class, 'importLead'])->name('lead-contact.import');
        Route::post('import', [LeadContactController::class, 'importStore'])->name('lead-contact.import.store');
        Route::post('import/process', [LeadContactController::class, 'importProcess'])->name('lead-contact.import.process');
    });

    // deals route

    Route::resource('lead-contact', LeadContactController::class);
    Route::post('lead-contact/apply-quick-action', [LeadContactController::class, 'applyQuickAction'])->name('lead-contact.apply_quick_action');

    Route::get('deals/get-stage/{id}', [DealController::class, 'getStages'])->name('deals.get-stage');
    Route::get('deals/get-deals/{id}', [DealController::class, 'getDeals'])->name('deals.get-deals');
    Route::get('deals/get-agent/{id}', [DealController::class, 'getAgents'])->name('deals.get_agents');
    Route::resource('deals', DealController::class);

    // leaves files routes
    Route::get('leave-files/download/{id}', [LeaveFileController::class, 'download'])->name('leave-files.download');
    Route::resource('leave-files', LeaveFileController::class);

    /* LEAVES */
    Route::get('leaves/leaves-date', [LeaveController::class, 'getDate'])->name('leaves.date');
    Route::get('leaves/personal', [LeaveController::class, 'personalLeaves'])->name('leaves.personal');
    Route::get('leaves/calendar', [LeaveController::class, 'leaveCalendar'])->name('leaves.calendar');
    Route::post('leaves/data', [LeaveController::class, 'data'])->name('leaves.data');
    Route::post('leaves/leaveAction', [LeaveController::class, 'leaveAction'])->name('leaves.leave_action');
    Route::get('leaves/show-reject-modal', [LeaveController::class, 'rejectLeave'])->name('leaves.show_reject_modal');
    Route::get('leaves/show-approved-modal', [LeaveController::class, 'approveLeave'])->name('leaves.show_approved_modal');
    Route::post('leaves/pre-approve-leave', [LeaveController::class, 'preApprove'])->name('leaves.pre_approve_leave');
    Route::post('leaves/apply-quick-action', [LeaveController::class, 'applyQuickAction'])->name('leaves.apply_quick_action');
    Route::get('leaves/view-related-leave/{id}', [LeaveController::class, 'viewRelatedLeave'])->name('leaves.view_related_leave');
    Route::get('leaves/export-all-leave', [LeaveController::class, 'exportAllLeaves'])->name('leaves.export_all_leave');
    Route::resource('leaves', LeaveController::class);

    // Messages
    Route::get('messages/fetch-user-list', [MessageController::class, 'fetchUserListView'])->name('messages.fetch_user_list');
    Route::post('messages/fetch_messages/{id}', [MessageController::class, 'fetchUserMessages'])->name('messages.fetch_messages');
    Route::post('messages/check_messages', [MessageController::class, 'checkNewMessages'])->name('messages.check_new_message');
    Route::delete('messages/destroy-chat/{id}', [MessageController::class, 'destroyAll'])->name('messages.destroy_all');
    Route::resource('messages', MessageController::class);

    // Chat Files
    Route::get('message-file/download/{id}', [MessageFileController::class, 'download'])->name('message_file.download');
    Route::resource('message-file', MessageFileController::class);

    // Invoices
    Route::get('invoices/offline-method-description', [InvoiceController::class, 'offlineDescription'])->name('invoices.offline_method_description');
    Route::get('invoices/offline-payment-modal', [InvoiceController::class, 'offlinePaymentModal'])->name('invoices.offline_payment_modal');
    Route::get('invoices/stripe-modal', [InvoiceController::class, 'stripeModal'])->name('invoices.stripe_modal');
    Route::post('invoices/save-stripe-detail/', [InvoiceController::class, 'saveStripeDetail'])->name('invoices.save_stripe_detail');
    Route::get('invoices/delete-image', [InvoiceController::class, 'deleteInvoiceItemImage'])->name('invoices.delete_image');
    Route::post('invoices/store-offline-payment', [InvoiceController::class, 'storeOfflinePayment'])->name('invoices.store_offline_payment');
    Route::post('invoices/store_file', [InvoiceController::class, 'storeFile'])->name('invoices.store_file');
    Route::get('invoices/file-upload', [InvoiceController::class, 'fileUpload'])->name('invoices.file_upload');
    Route::post('invoices/delete-applied-credit/{id}', [InvoiceController::class, 'deleteAppliedCredit'])->name('invoices.delete_applied_credit');
    Route::get('invoices/applied-credits/{id}', [InvoiceController::class, 'appliedCredits'])->name('invoices.applied_credits');
    Route::get('invoices/payment-reminder/{invoiceID}', [InvoiceController::class, 'remindForPayment'])->name('invoices.payment_reminder');
    Route::post('invoices/send-invoice/{invoiceID}', [InvoiceController::class, 'sendInvoice'])->name('invoices.send_invoice');
    Route::post('invoices/approve-offline-invoice/{invoiceID}', [InvoiceController::class, 'approveOfflineInvoice'])->name('invoices.approve_offline_invoice');
    Route::post('invoices/apply-quick-action', [InvoiceController::class, 'applyQuickAction'])->name('invoices.apply_quick_action');
    Route::get('invoices/download/{id}', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('invoices/add-item', [InvoiceController::class, 'addItem'])->name('invoices.add_item');
    Route::get('invoices/update-status/{invoiceID}', [InvoiceController::class, 'cancelStatus'])->name('invoices.update_status');
    Route::get('invoices/get-client-company/{projectID?}', [InvoiceController::class, 'getClientOrCompanyName'])->name('invoices.get_client_company');
    Route::post('invoices/fetchTimelogs', [InvoiceController::class, 'fetchTimelogs'])->name('invoices.fetch_timelogs');
    Route::get('invoices/check-shipping-address', [InvoiceController::class, 'checkShippingAddress'])->name('invoices.check_shipping_address');
    Route::get('invoices/product-category/{id}', [InvoiceController::class, 'productCategory'])->name('invoices.product_category');

    Route::get('invoices/toggle-shipping-address/{invoice}', [InvoiceController::class, 'toggleShippingAddress'])->name('invoices.toggle_shipping_address');
    Route::get('invoices/shipping-address-modal/{invoice}', [InvoiceController::class, 'shippingAddressModal'])->name('invoices.shipping_address_modal');
    Route::post('invoices/add-shipping-address/{clientId}', [InvoiceController::class, 'addShippingAddress'])->name('invoices.add_shipping_address');
    Route::get('invoices/get-exchange-rate/{id}', [InvoiceController::class, 'getExchangeRate'])->name('invoices.get_exchange_rate');

    Route::get('invoices/committed-modal', [InvoiceController::class, 'committedModal'])->name('invoices.committed_modal');

    Route::group(['prefix' => 'invoices'], function () {
        // Invoice recurring
        Route::post('recurring-invoice/change-status', [RecurringInvoiceController::class, 'changeStatus'])->name('recurring_invoice.change_status');
        Route::get('recurring-invoice/export/{startDate}/{endDate}/{status}/{employee}', [RecurringInvoiceController::class, 'export'])->name('recurring_invoice.export');
        Route::get('recurring-invoice/recurring-invoice/{id}', [RecurringInvoiceController::class, 'recurringInvoices'])->name('recurring_invoice.recurring_invoice');
        Route::delete('recurring-invoice/delete-repeat-invoices/{id}', [RecurringInvoiceController::class, 'deleteInvoices'])->name('recurring_invoice.delete_repeat_invoices');
        Route::resource('recurring-invoices', RecurringInvoiceController::class);

        // Subscriptions — admin
        Route::get('subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::patch('subscriptions/{id}/cancel', [\App\Http\Controllers\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::patch('subscriptions/{id}/reactivate', [\App\Http\Controllers\SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');
        Route::get('subscriptions/{id}/logs', [\App\Http\Controllers\SubscriptionController::class, 'logs'])->name('subscriptions.logs');

        // Subscriptions — client-facing
        Route::get('my-subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'clientIndex'])->name('client.subscriptions.index');
        Route::patch('my-subscriptions/{id}/cancel', [\App\Http\Controllers\SubscriptionController::class, 'clientCancel'])->name('client.subscriptions.cancel');
        Route::get('my-subscriptions/{id}/logs', [\App\Http\Controllers\SubscriptionController::class, 'clientLogs'])->name('client.subscriptions.logs');
    });
    Route::resource('invoices', InvoiceController::class);

    Route::resource('invoices-payment-details', InvoicePaymentDetailController::class);

    // Estimates
    Route::get('estimates/delete-image', [EstimateController::class, 'deleteEstimateItemImage'])->name('estimates.delete_image');
    Route::get('estimates/download/{id}', [EstimateController::class, 'download'])->name('estimates.download');
    Route::post('estimates/send-estimate/{id}', [EstimateController::class, 'sendEstimate'])->name('estimates.send_estimate');
    Route::get('estimates/change-status/{id}', [EstimateController::class, 'changeStatus'])->name('estimates.change_status');
    Route::post('estimates/accept/{id}', [EstimateController::class, 'accept'])->name('estimates.accept');
    Route::post('estimates/decline/{id}', [EstimateController::class, 'decline'])->name('estimates.decline');
    Route::get('estimates/add-item', [EstimateController::class, 'addItem'])->name('estimates.add_item');
    Route::resource('estimates', EstimateController::class);


    // Proposals
    Route::get('proposals/delete-image', [ProposalController::class, 'deleteProposalItemImage'])->name('proposals.delete_image');
    Route::get('proposals/download/{id}', [ProposalController::class, 'download'])->name('proposals.download');
    Route::post('proposals/send-proposal/{id}', [ProposalController::class, 'sendProposal'])->name('proposals.send_proposal');
    Route::get('proposals/add-item', [ProposalController::class, 'addItem'])->name('proposals.add_item');
    Route::resource('proposals', ProposalController::class);

    // Proposal Template
    Route::post('proposal-template/apply-quick-action', [ProposalTemplateController::class, 'applyQuickAction'])->name('proposal_template.apply_quick_action');
    Route::get('proposal-template/add-item', [ProposalController::class, 'addItem'])->name('proposal-template.add_item');
    Route::resource('proposal-template', ProposalTemplateController::class);
    Route::get('proposal-template/download/{id}', [ProposalTemplateController::class, 'download'])->name('proposal-template.download');
    Route::get('proposals-template/delete-image', [ProposalTemplateController::class, 'deleteProposalItemImage'])->name('proposal_template.delete_image');

    // Payments
    Route::post('payments/apply-quick-action', [PaymentController::class, 'applyQuickAction'])->name('payments.apply_quick_action');
    Route::get('payments/download/{id}', [PaymentController::class, 'download'])->name('payments.download');
    Route::get('payments/account-list', [PaymentController::class, 'accountList'])->name('payments.account_list');
    Route::get('payments/offline-payments', [PaymentController::class, 'offlineMethods'])->name('offline.methods');
    Route::get('payments/add-bulk-payments', [PaymentController::class, 'addBulkPayments'])->name('payments.add_bulk_payments');
    Route::post('payments/save-bulk-payments', [PaymentController::class, 'saveBulkPayments'])->name('payments.save_bulk_payments');

    Route::resource('payments', PaymentController::class)->except(['edit', 'update']);

    // Credit notes
    Route::post('creditnotes/store_file', [CreditNoteController::class, 'storeFile'])->name('creditnotes.store_file');
    Route::get('creditnotes/file-upload', [CreditNoteController::class, 'fileUpload'])->name('creditnotes.file_upload');
    Route::post('creditnotes/delete-credited-invoice/{id}', [CreditNoteController::class, 'deleteCreditedInvoice'])->name('creditnotes.delete_credited_invoice');
    Route::get('creditnotes/credited-invoices/{id}', [CreditNoteController::class, 'creditedInvoices'])->name('creditnotes.credited_invoices');
    Route::post('creditnotes/apply-invoice-credit/{id}', [CreditNoteController::class, 'applyInvoiceCredit'])->name('creditnotes.apply_invoice_credit');
    Route::get('creditnotes/apply-to-invoice/{id}', [CreditNoteController::class, 'applyToInvoice'])->name('creditnotes.apply_to_invoice');
    Route::get('creditnotes/download/{id}', [CreditNoteController::class, 'download'])->name('creditnotes.download');

    Route::get('creditnotes/convert-invoice/{id}', [CreditNoteController::class, 'convertInvoice'])->name('creditnotes.convert-invoice');

    Route::resource('creditnotes', CreditNoteController::class);

    // Bank account
    Route::post('bankaccount/apply-quick-action', [BankAccountController::class, 'applyQuickAction'])->name('bankaccounts.apply_quick_action');
    Route::post('bankaccount/apply-transaction-quick-action', [BankAccountController::class, 'applyTransactionQuickAction'])->name('bankaccounts.apply_transaction_quick_action');
    Route::get('bankaccounts/create-transaction', [BankAccountController::class, 'createTransaction'])->name('bankaccounts.create_transaction');
    Route::post('bankaccount/store-transaction', [BankAccountController::class, 'storeTransaction'])->name('bankaccounts.store_transaction');
    Route::post('bankaccount/change-status', [BankAccountController::class, 'changeStatus'])->name('bankaccounts.change_status');

    Route::get('bankaccounts/view-transaction/{id}', [BankAccountController::class, 'viewTransaction'])->name('bankaccounts.view_transaction');
    Route::post('bankaccount/destroy-transaction', [BankAccountController::class, 'destroyTransaction'])->name('bankaccounts.destroy_transaction');
    Route::get('bankaccount/generate-statement/{id}', [BankAccountController::class, 'generateStatement'])->name('bankaccounts.generate_statement');
    Route::get('bankaccount/get-bank-statement', [BankAccountController::class, 'getBankStatement'])->name('bankaccounts.get_bank_statement');

    Route::resource('bankaccounts', BankAccountController::class);

    // Expenses
    Route::group(['prefix' => 'expenses'], function () {
        Route::post('recurring-expenses/change-status', [RecurringExpenseController::class, 'changeStatus'])->name('recurring-expenses.change_status');
        Route::resource('recurring-expenses', RecurringExpenseController::class);
        Route::get('change-status', [ExpenseController::class, 'getEmployeeProjects'])->name('expenses.get_employee_projects');
        Route::get('category', [ExpenseController::class, 'getCategoryEmployee'])->name('expenses.get_category_employees');
        Route::post('change-status', [ExpenseController::class, 'changeStatus'])->name('expenses.change_status');
        Route::post('apply-quick-action', [ExpenseController::class, 'applyQuickAction'])->name('expenses.apply_quick_action');
        Route::get('import', [ExpenseController::class, 'import'])->name('expenses.import');
        Route::post('import', [ExpenseController::class, 'importStore'])->name('expenses.import.store');
        Route::post('import/process', [ExpenseController::class, 'importProcess'])->name('expenses.import.process');
    });
    Route::resource('expenses', ExpenseController::class);
    Route::resource('expenseCategory', ExpenseCategoryController::class);

    // Timelogs
    Route::group(['prefix' => 'timelogs'], function () {
        Route::resource('timelog-calendar', TimelogCalendarController::class);
        Route::resource('timelog-break', ProjectTimelogBreakController::class);
        Route::get('by-employee', [TimelogController::class, 'byEmployee'])->name('timelogs.by_employee');
        Route::get('export', [TimelogController::class, 'export'])->name('timelogs.export');
        Route::get('export-time-logs', [TimelogController::class, 'exportTimeLogs'])->name('timelogs.export_time_logs');
        Route::get('show-active-timer', [TimelogController::class, 'showActiveTimer'])->name('timelogs.show_active_timer');
        Route::get('show-timer', [TimelogController::class, 'showTimer'])->name('timelogs.show_timer');
        Route::post('start-timer', [TimelogController::class, 'startTimer'])->name('timelogs.start_timer');
        Route::get('timer-data', [TimelogController::class, 'timerData'])->name('timelogs.timer_data');
        Route::post('stop-timer', [TimelogController::class, 'stopTimer'])->name('timelogs.stop_timer');
        Route::post('pause-timer', [TimelogController::class, 'pauseTimer'])->name('timelogs.pause_timer');
        Route::post('resume-timer', [TimelogController::class, 'resumeTimer'])->name('timelogs.resume_timer');
        Route::post('apply-quick-action', [TimelogController::class, 'applyQuickAction'])->name('timelogs.apply_quick_action');

        Route::post('employee_data', [TimelogController::class, 'employeeData'])->name('timelogs.employee_data');
        Route::post('user_time_logs', [TimelogController::class, 'userTimelogs'])->name('timelogs.user_time_logs');
        Route::post('approve_timelog', [TimelogController::class, 'approveTimelog'])->name('timelogs.approve_timelog');
        Route::post('revert-timelog-to-pending', [TimelogController::class, 'revertTimelogToPending'])->name('timelogs.revert_to_pending');
        Route::get('stopper-alert/{id}', [TimelogController::class, 'stopperAlert'])->name('timelogs.stopper_alert');
        Route::get('check-project-time-limit/{projectId}', [TimelogController::class, 'checkProjectTimeLimit'])->name('timelogs.check_project_time_limit');

        Route::post('change-status', [WeeklyTimesheetController::class, 'changeStatus'])->name('weekly-timesheets.change_status');
        Route::get('pending-approval', [WeeklyTimesheetController::class, 'pendingApproval'])->name('weekly-timesheets.pending_approval');
        Route::resource('weekly-timesheets', WeeklyTimesheetController::class);
    });

    Route::get('show-reject-modal', [WeeklyTimesheetController::class, 'showRejectModal'])->name('weekly-timesheets.show_reject_modal');
    Route::post('timelogs/timelogAction', [TimelogController::class, 'timelogAction'])->name('timelogs.timelog_action');
    Route::get('timelogs/show-reject-modal', [TimelogController::class, 'rejectTimelog'])->name('timelogs.show_reject_modal');
    Route::post('/calculate-time', [TimelogController::class, 'calculateTime'])->name('calculateTime');
    Route::resource('timelogs', TimelogController::class);

    // Contracts
    Route::post('contracts/apply-quick-action', [ContractController::class, 'applyQuickAction'])->name('contracts.apply_quick_action');
    Route::get('contracts/download/{id}', [ContractController::class, 'download'])->name('contracts.download');
    Route::post('contracts/sign/{id}', [ContractController::class, 'sign'])->name('contracts.sign');
    Route::post('companySign/sign/{id}', [ContractController::class, 'companySign'])->name('companySign.sign');
    Route::get('companySignStore/sign/{id}', [ContractController::class, 'companiesSign'])->name('companySignStore.sign');
    Route::post('contracts/project-detail/{id}', [ContractController::class, 'projectDetail'])->name('contracts.project_detail');
    Route::get('contracts/company-sig/{id}', [ContractController::class, 'companySig'])->name('contracts.company_sig');


    Route::group(['prefix' => 'contracts'], function () {
        Route::resource('contractDiscussions', ContractDiscussionController::class);
        Route::get('contractFiles/download/{id}', [ContractFileController::class, 'download'])->name('contractFiles.download');
        Route::resource('contractFiles', ContractFileController::class);
        Route::resource('contractTypes', ContractTypeController::class);
    });

    Route::resource('contracts', ContractController::class);
    Route::resource('contract-renew', ContractRenewController::class);

    // Contract template
    Route::post('contract-template/apply-quick-action', [ContractTemplateController::class, 'applyQuickAction'])->name('contract_template.apply_quick_action');
    Route::resource('contract-template', ContractTemplateController::class);
    Route::get('contract-template/download/{id}', [ContractTemplateController::class, 'download'])->name('contract-template.download');

    // Attendance
    Route::get('attendances/export-attendance/{year}/{month}/{id}', [AttendanceController::class, 'exportAttendanceByMember'])->name('attendances.export_attendance');
    Route::get('attendances/export-all-attendance/{year}/{month}/{id}/{department}/{designation}', [AttendanceController::class, 'exportAllAttendance'])->name('attendances.export_all_attendance');
    Route::get('attendances/export-all-attendance-clock/{year}/{month}/{id}/{department}/{designation}', [AttendanceController::class, 'exportAllAttendanceClock'])->name('attendances.export_all_attendance_clock');
    Route::post('attendances/employee-data', [AttendanceController::class, 'employeeData'])->name('attendances.employee_data');
    Route::get('attendances/mark/{id}/{day}/{month}/{year}', [AttendanceController::class, 'mark'])->name('attendances.mark');
    Route::get('attendances/calendar/{id}/{month}/{year}', [AttendanceController::class, 'calendar'])->name('attendances.calendar');
    Route::post('attendances/day-status', [AttendanceController::class, 'saveDayStatus'])->name('attendances.save_day_status');
    Route::post('attendances/apply-late-half-day', [AttendanceController::class, 'applyLateHalfDay'])->name('attendances.apply_late_half_day');
    Route::get('attendances/by-member', [AttendanceController::class, 'byMember'])->name('attendances.by_member');
    Route::get('attendances/by-hour', [AttendanceController::class, 'byHour'])->name('attendances.by_hour');
    Route::post('attendances/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendances.bulk_mark');
    Route::get('attendances/import', [AttendanceController::class, 'importAttendance'])->name('attendances.import');
    Route::post('attendances/import', [AttendanceController::class, 'importStore'])->name('attendances.import.store');
    Route::post('attendances/import/process', [AttendanceController::class, 'importProcess'])->name('attendances.import.process');
    Route::delete('attendances/import/delete', [AttendanceController::class, 'deleteImportedAttendance'])->name('attendances.import.delete');
    Route::delete('attendances/user/delete', [AttendanceController::class, 'deleteUserMonthAttendance'])->name('attendances.user.delete');
    Route::get('attendances/by-map-location', [AttendanceController::class, 'byMapLocation'])->name('attendances.by_map_location');
    Route::get('attendances/by-request', [AttendanceController::class, 'byRequest'])->name('attendances.by_request');
    Route::post('attendances/regularisationStore', [AttendanceController::class, 'regularisationStore'])->name('attendances.regularisationStore');
    Route::post('attendances/status/accept', [AttendanceController::class, 'acceptStatus'])->name('attendances.accept_status');
    Route::post('attendances/status/reject', [AttendanceController::class, 'rejectStatus'])->name('attendances.reject_status');
    Route::get('edit-attendance-regularisation/{id}', [AttendanceController::class, 'editAttendanceRegularisation'])->name('attendances.edit_attendance_regularisation');
    Route::post('edit-attendance-regularisation/{id}', [AttendanceController::class, 'updateAttendanceRegularisation'])->name('attendances.update_attendance_regularisation');
    Route::get('view-attendance-regularisation/{id}', [AttendanceController::class, 'viewAttendanceRegularisation'])->name('attendances.view_attendance_regularisation');
    Route::get('attendances/show-reject-modal/{id}', [AttendanceController::class, 'rejectAttendance'])->name('attendances.show_reject_modal');
    Route::resource('attendances', AttendanceController::class);
    Route::get('attendance/{id}/{day}/{month}/{year}', [AttendanceController::class, 'addAttendance'])->name('attendances.add-user-attendance');
    Route::get('attendances/{id}/{day}/{month}/{year}', [AttendanceController::class, 'requestRegulisation'])->name('attendances.request-regulisation');
    Route::get('attendances/pending/{id}/{day}/{month}/{year}', [AttendanceController::class, 'pendingRequest'])->name('attendances.request-pending');
    Route::delete('attendances/pending/{id}', [AttendanceController::class, 'deleteRegularisationRequest'])->name('attendances.delete_request');
    Route::post('attendances/check-half-day', [AttendanceController::class, 'checkHalfDay'])->name('attendances.check_half_day');
    Route::get('check-qr-login/{hash}', [AttendanceController::class, 'qrClockInOut'])->name('settings.qr-login');
    Route::post('change-qr-code-status', [AttendanceController::class, 'qrCodeStatus'])->name('settings.change-qr-code-status');

    // Biometric & Access Control (iApp device data)
    Route::get('biometric/overview', [App\Http\Controllers\BiometricController::class, 'overview'])->name('biometric.overview');
    Route::get('biometric/review', [App\Http\Controllers\BiometricController::class, 'review'])->name('biometric.review');
    Route::post('biometric/review/resolve', [App\Http\Controllers\BiometricController::class, 'resolveReview'])->name('biometric.review.resolve');
    Route::post('biometric/review/run', [App\Http\Controllers\BiometricController::class, 'runReview'])->name('biometric.review.run');
    Route::get('biometric/attendance-sync', [App\Http\Controllers\BiometricController::class, 'attendanceSync'])->name('biometric.attendance-sync');
    Route::post('biometric/attendance-sync/preview', [App\Http\Controllers\BiometricController::class, 'previewAttendanceSync'])->name('biometric.attendance-sync.preview');
    Route::post('biometric/attendance-sync/run', [App\Http\Controllers\BiometricController::class, 'runAttendanceSync'])->name('biometric.attendance-sync.run');
    Route::post('biometric/attendance-sync/review', [App\Http\Controllers\BiometricController::class, 'reviewAttendanceDay'])->name('biometric.attendance-sync.review');
    Route::get('biometric/comparison', [App\Http\Controllers\BiometricController::class, 'comparison'])->name('biometric.comparison');
    Route::post('biometric/comparison/create', [App\Http\Controllers\BiometricController::class, 'createFromDevice'])->name('biometric.comparison.create');
    Route::get('biometric/calendar', [App\Http\Controllers\BiometricController::class, 'calendar'])->name('biometric.calendar');
    Route::post('biometric/backfill/start', [App\Http\Controllers\BiometricController::class, 'startBackfill'])->name('biometric.backfill.start');
    Route::post('biometric/backfill/step', [App\Http\Controllers\BiometricController::class, 'stepBackfill'])->name('biometric.backfill.step');
    Route::get('biometric/backfill/status', [App\Http\Controllers\BiometricController::class, 'backfillStatus'])->name('biometric.backfill.status');
    Route::post('biometric/backfill/control', [App\Http\Controllers\BiometricController::class, 'controlBackfill'])->name('biometric.backfill.control');
    Route::get('biometric/attendance', [App\Http\Controllers\BiometricController::class, 'attendance'])->name('biometric.attendance');
    Route::get('biometric/punch-log', [App\Http\Controllers\BiometricController::class, 'punchLog'])->name('biometric.punch-log');
    Route::get('biometric/devices', [App\Http\Controllers\BiometricController::class, 'devices'])->name('biometric.devices');
    Route::get('biometric/sync-logs', [App\Http\Controllers\BiometricController::class, 'syncLogs'])->name('biometric.sync-logs');
    // Enrolments belonging to people who have left. Read-only, like everything
    // else here: it says what to remove on the device, it does not remove it.
    Route::get('biometric/actions', [App\Http\Controllers\BiometricController::class, 'actions'])->name('biometric.actions');
    Route::post('biometric/sync', [App\Http\Controllers\BiometricController::class, 'sync'])->name('biometric.sync');
    Route::get('biometric/employees', [App\Http\Controllers\BiometricController::class, 'employees'])->name('biometric.employees');
    Route::get('biometric/profile', [App\Http\Controllers\BiometricController::class, 'profile'])->name('biometric.profile');
    Route::get('biometric/exceptions', [App\Http\Controllers\BiometricController::class, 'exceptions'])->name('biometric.exceptions');
    Route::post('biometric/exceptions/action', [App\Http\Controllers\BiometricController::class, 'exceptionAction'])->name('biometric.exceptions.action');
    Route::post('biometric/exceptions/resync', [App\Http\Controllers\BiometricController::class, 'resyncDay'])->name('biometric.exceptions.resync');
    Route::get('biometric/shifts', [App\Http\Controllers\BiometricController::class, 'shifts'])->name('biometric.shifts');
    Route::post('biometric/employees/sync', [App\Http\Controllers\BiometricController::class, 'syncEmployees'])->name('biometric.employees.sync');
    Route::post('biometric/employees/machine-list', [App\Http\Controllers\BiometricController::class, 'uploadMachineList'])->name('biometric.employees.machine-list');
    Route::post('biometric/employees/{id}/map', [App\Http\Controllers\BiometricController::class, 'mapEmployee'])->name('biometric.employees.map');
    Route::post('biometric/employees/bulk-map', [App\Http\Controllers\BiometricController::class, 'bulkMapEmployees'])->name('biometric.employees.bulk-map');


    Route::get('shifts/mark/{id}/{day}/{month}/{year}', [EmployeeShiftScheduleController::class, 'mark'])->name('shifts.mark');
    Route::get('shifts/export-all/{year}/{month}/{id}/{department}/{startDate}/{viewType}', [EmployeeShiftScheduleController::class, 'exportAllShift'])->name('shifts.export_all');

    Route::get('shifts/employee-shift-calendar', [EmployeeShiftScheduleController::class, 'employeeShiftCalendar'])->name('shifts.employee_shift_calendar');
    Route::post('shifts/bulk-shift', [EmployeeShiftScheduleController::class, 'bulkShift'])->name('shifts.bulk_shift');

    Route::group(['prefix' => 'shifts'], function () {
        Route::post('shifts-change/approve_request/{id}', [EmployeeShiftChangeRequestController::class, 'approveRequest'])->name('shifts-change.approve_request');
        Route::post('shifts-change/decline_request/{id}', [EmployeeShiftChangeRequestController::class, 'declineRequest'])->name('shifts-change.decline_request');
        Route::post('shifts-change/apply-quick-action', [EmployeeShiftChangeRequestController::class, 'applyQuickAction'])->name('shifts-change.apply_quick_action');
        Route::resource('shifts-change', EmployeeShiftChangeRequestController::class);
    });

    Route::resource('shifts', EmployeeShiftScheduleController::class);

    // Tickets
    Route::post('tickets/apply-quick-action', [TicketController::class, 'applyQuickAction'])->name('tickets.apply_quick_action');
    Route::post('tickets/updateOtherData/{id}', [TicketController::class, 'updateOtherData'])->name('tickets.update_other_data');
    Route::post('tickets/change-status', [TicketController::class, 'changeStatus'])->name('tickets.change-status');
    Route::post('tickets/refreshCount', [TicketController::class, 'refreshCount'])->name('tickets.refresh_count');
    Route::get('tickets/agent-group/{id}/{exceptThis?}', [TicketController::class, 'agentGroup'])->name('tickets.agent_group');
    Route::get('tickets/edit-details/{id}', [TicketController::class, 'editDetail'])->name('tickets.edit_detail');
    Route::put('tickets/update-details/{id}', [TicketController::class, 'updateDetail'])->name('tickets.update_detail');
    Route::resource('tickets', TicketController::class);

    // Ticket Custom Embed From
    Route::post('ticket-form/sort-fields', [TicketCustomFormController::class, 'sortFields'])->name('ticket-form.sort_fields');
    Route::resource('ticket-form', TicketCustomFormController::class);

    Route::get('ticket-files/download/{id}', [TicketFileController::class, 'download'])->name('ticket-files.download');
    Route::resource('ticket-files', TicketFileController::class);

    Route::resource('ticket-replies', TicketReplyController::class);
    Route::post('ticket-replies/edit-note/{id}', [TicketReplyController::class, 'editNote'])->name('ticket-replies.edit_note');

    Route::post('task-report-chart', [TaskReportController::class, 'taskChartData'])->name('task-report.chart');
    Route::get('task-report/employee-wise-task-report', [TaskReportController::class, 'employeeWiseTaskReport'])->name('employee-wise-task-report');
    Route::get('task-report/consolidated-task-report', [TaskReportController::class, 'consolidatedTaskReport'])->name('consolidated-task-report');

    Route::resource('task-report', TaskReportController::class);

    Route::post('time-log-report-chart', [TimelogReportController::class, 'timelogChartData'])->name('time-log-report.chart');
    Route::get('time-log-consolidated-report', [TimelogReportController::class,'consolidateIndex'])->name('time-log-consolidated.report');
    Route::get('time-log-project-wise-report', [TimelogReportController::class,'projectWiseTimelog'])->name('project-wise-timelog.report');
    Route::get('project-wise-timelog/report/export', [TimelogReportController::class,'exportProjectWiseTimeLog'])->name('project-wise-timelog.export');
    Route::resource('time-log-report', TimelogReportController::class);
    Route::post('time-log-report-time', [TimelogReportController::class, 'totalTime'])->name('time-log-report.time');

    Route::resource('time-log-weekly-report', TimelogWeeklyApprovalController::class);
    Route::get('weekly-pending-time-log-report', [TimelogWeeklyApprovalController::class,'pendingTimelogReportIndex'])->name('weekly-pending-time-log-report.report');

    Route::post('finance-report-chart', [FinanceReportController::class, 'financeChartData'])->name('finance-report.chart');
    Route::resource('finance-report', FinanceReportController::class);

    Route::resource('income-expense-report', IncomeVsExpenseReportController::class);

    Route::get('leave-report/leave-quota', [LeaveReportController::class, 'leaveQuota'])->name('leave-report.leave_quota');
    Route::get('leave-report/leave-quota/export-all-leave-quota/{id}/{year}/{month}', [LeavesQuotaController::class, 'exportAllLeaveQuota'])->name('leave_quota.export_all_leave_quota');
    Route::get('leave-report/leave-quota/{id}/{year}/{month}', [LeaveReportController::class, 'employeeLeaveQuota'])->name('leave-report.employee-leave-quota');
    Route::resource('leave-report', LeaveReportController::class);

    Route::resource('attendance-report', AttendanceReportController::class);

    Route::post('expense-report-chart', [ExpenseReportController::class, 'expenseChartData'])->name('expense-report.chart');
    Route::get('expense-report/expense-category-report', [ExpenseReportController::class, 'expenseCategoryReport'])->name('expense-report.expense_category_report');

    Route::resource('expense-report', ExpenseReportController::class);
    Route::get('deal-report/lead', [LeadReportController::class, 'leadContact'])->name('lead-report.lead_contact');
    Route::get('lead-report/total', [LeadReportController::class, 'totalContact'])->name('lead-report.total_contact');

    Route::get('deal-report/chart', [LeadReportController::class, 'averageDealSizeReport'])->name('lead-report.chart');
    Route::get('deal-report/profile', [LeadReportController::class, 'profile'])->name('lead-report.profile');
    Route::get('deal-report/export/{year}/{pipeline}/{category}', [LeadReportController::class, 'exportDealReport'])->name('deal-report.export');



    Route::resource('lead-report', LeadReportController::class);
    Route::resource('sales-report', SalesReportController::class);

    Route::resource('sticky-notes', StickyNoteController::class);

    Route::post('show-notifications', [NotificationController::class, 'showNotifications'])->name('show_notifications');


    Route::get('gdpr/lead/approve-reject/{id}/{type}', [GdprSettingsController::class, 'approveRejectLead'])->name('gdpr.lead.approve_reject');
    Route::get('gdpr/customer/approve-reject/{id}/{type}', [GdprSettingsController::class, 'approveRejectClient'])->name('gdpr.customer.approve_reject');

    Route::post('gdpr-settings/apply-quick-action', [GdprSettingsController::class, 'applyQuickAction'])->name('gdpr_settings.apply_quick_action');
    Route::put('gdpr-settings.update-general', [GdprSettingsController::class, 'updateGeneral'])->name('gdpr_settings.update_general');

    Route::post('gdpr/store-consent', [GdprSettingsController::class, 'storeConsent'])->name('gdpr.store_consent');
    Route::get('gdpr/add-consent', [GdprSettingsController::class, 'AddConsent'])->name('gdpr.add_consent');
    Route::get('gdpr/edit-consent/{id}', [GdprSettingsController::class, 'editConsent'])->name('gdpr.edit_consent');

    Route::put('gdpr/update-consent/{id}', [GdprSettingsController::class, 'updateConsent'])->name('gdpr.update_consent');

    Route::delete('gdpr-settings/purpose-delete/{id}', [GdprSettingsController::class, 'purposeDelete'])->name('gdpr_settings.purpose_delete');

    Route::resource('gdpr-settings', GdprSettingsController::class);

    Route::post('gdpr/update-client-consent', [GdprController::class, 'updateClientConsent'])->name('gdpr.update_client_consent');
    Route::get('gdpr/export-data', [GdprController::class, 'downloadJson'])->name('gdpr.export_data');
    Route::post('gdpr/update-consent-block', [GdprController::class, 'updateConsentBlock'])->name('gdpr.update_consent_block');
    Route::resource('gdpr', GdprController::class);

    Route::get('all-notifications', [NotificationController::class, 'all'])->name('all-notifications');
    Route::post('mark-read', [NotificationController::class, 'markRead'])->name('mark_single_notification_read');
    Route::post('mark_notification_read', [NotificationController::class, 'markAllRead'])->name('mark_notification_read');

    Route::resource('search', SearchController::class);

    // Remove in v 5.2.5
    Route::get('hide-webhook-url', [SettingsController::class, 'hideWebhookAlert'])->name('hideWebhookAlert');

    // Estimate Template
    Route::get('estimate-template/add-item', [EstimateTemplateController::class, 'addItem'])->name('estimate-template.add_item');
    Route::resource('estimate-template', EstimateTemplateController::class);
    Route::get('estimates-template/delete-image', [EstimateTemplateController::class, 'deleteEstimateItemImage'])->name('estimate-template.delete_image');
    Route::get('estimate-template/download/{id}', [EstimateTemplateController::class, 'download'])->name('estimate-template.download');

    Route::get('quickbooks/{hash}/callback', [QuickbookController::class, 'callback'])->name('quickbooks.callback');
    Route::get('quickbooks', [QuickbookController::class, 'index'])->name('quickbooks.index');

    // Estimate Request
    Route::post('estimate-request/apply-quick-action', [EstimateRequestController::class, 'applyQuickAction'])->name('estimate-request.apply_quick_action');
    Route::post('estimate-request/change-status/', [EstimateRequestController::class, 'changeStatus'])->name('estimate-request.change_status');
    Route::get('estimate-request-confirm-rejected/{id}', [EstimateRequestController::class, 'rejectConfirmation'])->name('estimate-request.confirm_rejected');
    Route::get('estimate-request/send-estimate-request', [EstimateRequestController::class, 'sendEstimateRequest'])->name('estimate-request.send_estimate_request');
    Route::post('estimate-request/send_estimate_mail', [EstimateRequestController::class, 'sendEstimateMail'])->name('estimate-request.send_estimate_mail');
    Route::resource('estimate-request', EstimateRequestController::class);

    Route::post('gantt_link.task_update', [GanttLinkController::class, 'taskUpdateController'])->name('gantt_link.task_update');
    Route::resource('gantt_link', GanttLinkController::class);

    // Admin-only embedded pages for existing standalone PHP tools
    Route::get('identity', [App\Http\Controllers\LegacyController::class, 'identity'])->name('identity.index');

    Route::get('offer-letter', [App\Http\Controllers\LegacyController::class, 'offerLetter'])->name('offer-letter.index');

    Route::get('probation-period-requests', [App\Http\Controllers\LegacyController::class, 'probationPeriodRequests'])->name('probation-requests.index');

    Route::get('employee-termination', [App\Http\Controllers\LegacyController::class, 'employeeTermination'])->name('employee-termination.index');

    Route::get('payslips/legacy', function () {
        ob_start();
        include base_path('payslips.php');
        return ob_get_clean();
    })->name('payslips.legacy');

    // The legacy PHP pages, served behind this login instead of straight off
    // /legacy/*.php, which asked for no authentication at all.
    Route::match(['get', 'post'], 'legacy/{page}', [App\Http\Controllers\LegacyPageController::class, 'show'])
        ->name('legacy.page');

    // What a month's payroll will cost, and issuing it in one run.
    Route::get('payroll-run', [App\Http\Controllers\PayrollRunController::class, 'index'])->name('payroll-run.index');
    Route::get('payroll-run/export', [App\Http\Controllers\PayrollRunController::class, 'export'])->name('payroll-run.export');
    Route::post('payroll-run/generate', [App\Http\Controllers\PayrollRunController::class, 'generate'])->name('payroll-run.generate');
    Route::post('payroll-run/component', [App\Http\Controllers\PayrollRunController::class, 'addComponent'])->name('payroll-run.component.add');
    Route::post('payroll-run/component/remove', [App\Http\Controllers\PayrollRunController::class, 'removeComponent'])->name('payroll-run.component.remove');
    Route::post('payroll-run/tds', [App\Http\Controllers\PayrollRunController::class, 'setTds'])->name('payroll-run.tds');
    // Pay that sits outside the standard salary: overtime, bonuses, advances.
    Route::get('pay-elements', [App\Http\Controllers\PayElementsController::class, 'index'])->name('pay-elements.index');
    Route::post('pay-elements/type', [App\Http\Controllers\PayElementsController::class, 'storeType'])->name('pay-elements.type.store');
    Route::post('pay-elements/type/toggle', [App\Http\Controllers\PayElementsController::class, 'toggleType'])->name('pay-elements.type.toggle');
    Route::post('pay-elements/assign', [App\Http\Controllers\PayElementsController::class, 'storeElements'])->name('pay-elements.assign');
    Route::post('pay-elements/remove', [App\Http\Controllers\PayElementsController::class, 'removeElement'])->name('pay-elements.remove');
    Route::post('pay-elements/arrangement', [App\Http\Controllers\PayElementsController::class, 'storeArrangement'])->name('pay-elements.arrangement.store');
    Route::post('pay-elements/arrangement/cancel', [App\Http\Controllers\PayElementsController::class, 'cancelArrangement'])->name('pay-elements.arrangement.cancel');
    Route::post('pay-elements/rule', [App\Http\Controllers\PayElementsController::class, 'storeRule'])->name('pay-elements.rule.store');
    Route::post('pay-elements/rule/stop', [App\Http\Controllers\PayElementsController::class, 'stopRule'])->name('pay-elements.rule.stop');
    Route::post('pay-elements/group', [App\Http\Controllers\PayElementsController::class, 'storeGroup'])->name('pay-elements.group.store');
    Route::post('pay-elements/group/delete', [App\Http\Controllers\PayElementsController::class, 'destroyGroup'])->name('pay-elements.group.delete');
    // An employee's own payslips: the list, the slip, and a PDF of it. Every
    // one of these is scoped to the person signed in by the controller.
    Route::get('my-payslips', [App\Http\Controllers\MyPayslipsController::class, 'index'])->name('my-payslips.index');
    Route::get('my-payslips/{id}', [App\Http\Controllers\MyPayslipsController::class, 'show'])->name('my-payslips.show');
    Route::get('my-payslips/{id}/pdf', [App\Http\Controllers\MyPayslipsController::class, 'pdf'])->name('my-payslips.pdf');
    Route::get('my-form-16', [App\Http\Controllers\MyPayslipsController::class, 'formSixteen'])->name('my-payslips.form-16');
    // The record of what a month actually paid, from the payslips themselves.
    Route::get('payroll-register', [App\Http\Controllers\PayrollRegisterController::class, 'index'])->name('payroll-register.index');
    Route::get('payroll-register/export', [App\Http\Controllers\PayrollRegisterController::class, 'export'])->name('payroll-register.export');
    // Where the money went, over any stretch of dates, with every card openable.
    Route::get('payroll-dashboard', [App\Http\Controllers\PayrollDashboardController::class, 'index'])->name('payroll-dashboard.index');
    Route::get('payroll-dashboard/breakdown', [App\Http\Controllers\PayrollDashboardController::class, 'breakdown'])->name('payroll-dashboard.breakdown');
    // Filling in salary and statutory details for everyone from a spreadsheet.
    Route::get('employee-import', [App\Http\Controllers\EmployeeImportController::class, 'index'])->name('employee-import.index');
    Route::get('employee-import/template', [App\Http\Controllers\EmployeeImportController::class, 'template'])->name('employee-import.template');
    Route::post('employee-import/preview', [App\Http\Controllers\EmployeeImportController::class, 'preview'])->name('employee-import.preview');
    Route::post('employee-import/apply', [App\Http\Controllers\EmployeeImportController::class, 'apply'])->name('employee-import.apply');
    Route::get('employee-import/discard', [App\Http\Controllers\EmployeeImportController::class, 'discard'])->name('employee-import.discard');
    // Income tax: worked out rather than typed in, and Form 16.
    Route::get('income-tax', [App\Http\Controllers\IncomeTaxController::class, 'index'])->name('income-tax.index');
    Route::get('income-tax/workings', [App\Http\Controllers\IncomeTaxController::class, 'workings'])->name('income-tax.workings');
    Route::post('income-tax/declaration', [App\Http\Controllers\IncomeTaxController::class, 'storeDeclaration'])->name('income-tax.declaration');
    Route::post('income-tax/apply', [App\Http\Controllers\IncomeTaxController::class, 'apply'])->name('income-tax.apply');
    Route::post('income-tax/rates', [App\Http\Controllers\IncomeTaxController::class, 'saveRates'])->name('income-tax.rates');
    Route::get('income-tax/form-16', [App\Http\Controllers\IncomeTaxController::class, 'formSixteen'])->name('income-tax.form-16');
    // The monthly filings: provident fund, employee insurance, professional tax.
    Route::get('statutory-returns', [App\Http\Controllers\StatutoryReturnsController::class, 'index'])->name('statutory-returns.index');
    Route::get('statutory-returns/pf', [App\Http\Controllers\StatutoryReturnsController::class, 'providentFund'])->name('statutory-returns.pf');
    Route::get('statutory-returns/sheet', [App\Http\Controllers\StatutoryReturnsController::class, 'spreadsheet'])->name('statutory-returns.spreadsheet');
    // What is owed when somebody leaves.
    // One employee's attendance for one salary period, as payroll counts it.
    Route::get('payroll/attendance/{userId}', [App\Http\Controllers\PayrollAttendanceController::class, 'show'])->name('payroll.attendance');
    Route::get('settlements', [App\Http\Controllers\SettlementController::class, 'index'])->name('settlements.index');
    Route::get('settlements/draft', [App\Http\Controllers\SettlementController::class, 'draft'])->name('settlements.draft');
    Route::post('settlements', [App\Http\Controllers\SettlementController::class, 'store'])->name('settlements.store');
    Route::post('settlements/agree', [App\Http\Controllers\SettlementController::class, 'agree'])->name('settlements.agree');
    Route::post('settlements/paid', [App\Http\Controllers\SettlementController::class, 'markPaid'])->name('settlements.paid');
    Route::post('settlements/cancel', [App\Http\Controllers\SettlementController::class, 'cancel'])->name('settlements.cancel');
    Route::get('settlements/statement', [App\Http\Controllers\SettlementController::class, 'statement'])->name('settlements.statement');

    Route::get('payslips/generated', [App\Http\Controllers\PayslipListController::class, 'index'])->name('payslips.generated');
    Route::get('payslips/generated/{id}/pdf', [App\Http\Controllers\PayslipListController::class, 'pdf'])->name('payslips.generated.pdf');
    Route::post('payslips/generated/email', [App\Http\Controllers\PayslipListController::class, 'email'])->name('payslips.generated.email');
    Route::post('payslips/generated/approve', [App\Http\Controllers\PayslipListController::class, 'approve'])->name('payslips.generated.approve');
    Route::post('payslips/generated/mark-paid', [App\Http\Controllers\PayslipListController::class, 'markPaid'])->name('payslips.generated.paid');
    Route::post('payslips/generated/{id}/revoke', [App\Http\Controllers\PayslipListController::class, 'revoke'])->name('payslips.generated.revoke');
    Route::post('payslips/generated/{id}/delete', [App\Http\Controllers\PayslipListController::class, 'destroy'])->name('payslips.generated.delete');
    Route::get('payslips', [App\Http\Controllers\LegacyController::class, 'payslips'])->name('payslips.index');

    Route::get('payslip-entry', [App\Http\Controllers\LegacyController::class, 'payslipEntry'])->name('payslip-entry.index');

    Route::get('leave-management', [App\Http\Controllers\LegacyController::class, 'leaveManagement'])->name('leave-management.index');
    Route::get('leave-year-end', [App\Http\Controllers\LeaveYearEndController::class, 'index'])->name('leave-year-end.index');
    Route::post('leave-year-end/apply', [App\Http\Controllers\LeaveYearEndController::class, 'apply'])->name('leave-year-end.apply');
});



