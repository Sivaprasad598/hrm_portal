<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use AppModules\Leads\Http\Controllers\DealController;
use AppModules\Leads\Http\Controllers\DealNoteController;
use AppModules\Leads\Http\Controllers\LeadAgentSettingController;
use AppModules\Leads\Http\Controllers\LeadBoardController;
use AppModules\Leads\Http\Controllers\LeadCategoryController;
use AppModules\Leads\Http\Controllers\LeadContactController;
use AppModules\Leads\Http\Controllers\LeadCustomFormController;
use AppModules\Leads\Http\Controllers\LeadFileController;
use AppModules\Leads\Http\Controllers\LeadNoteController;
use AppModules\Leads\Http\Controllers\LeadPipelineSettingController;
use AppModules\Leads\Http\Controllers\LeadSettingController;
use AppModules\Leads\Http\Controllers\LeadSourceSettingController;
use AppModules\Leads\Http\Controllers\LeadStageSettingController;
use AppModules\Leads\Http\Controllers\ProposalController;
use AppModules\Leads\Http\Controllers\ProposalTemplateController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::get('/lead-form/{id}', [HomeController::class, 'leadForm'])->name('front.lead_form');
Route::post('/lead-form/leadStore', [HomeController::class, 'leadStore'])->name('front.lead_store');
Route::post('/proposal-action/{id}', [HomeController::class, 'proposalActionStore'])->name('front.proposal_action');
Route::get('/proposal/download/{id}', [HomeController::class, 'downloadProposal'])->name('front.download_proposal');
Route::get('/proposal/{hash}', [HomeController::class, 'proposal'])->name('front.proposal')->middleware('signed');

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deal-files/download/{id}', [LeadFileController::class, 'download'])->name('deal-files.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deal-files/layout', [LeadFileController::class, 'layout'])->name('deal-files.layout');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('deal-files', LeadFileController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deals/follow-up/{leadID}', [DealController::class, 'followUpCreate'])->name('deals.follow_up');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/follow-up-store', [DealController::class, 'followUpStore'])->name('deals.follow_up_store');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deals/follow-up-edit/{id?}', [DealController::class, 'editFollow'])->name('deals.follow_up_edit');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/follow-up-update', [DealController::class, 'updateFollow'])->name('deals.follow_up_update');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/follow-up-delete/{id}', [DealController::class, 'deleteFollow'])->name('deals.follow_up_delete');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('stage-change/{id}', [DealController::class, 'stageChange'])->name('deals.stage_change');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('save-stage-change', [DealController::class, 'saveStageChange'])->name('deals.save_stage_change');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/change-stage', [DealController::class, 'changeStage'])->name('deals.change_stage');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/apply-quick-action', [DealController::class, 'applyQuickAction'])->name('deals.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deals/gdpr-consent', [DealController::class, 'consent'])->name('deals.gdpr_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/save-deal-consent/{deal}', [DealController::class, 'saveLeadConsent'])->name('deals.save_lead_consent');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deals/change-follow-up-status', [DealController::class, 'changeFollowUpStatus'])->name('deals.change_follow_up_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('/update-lead-category', [LeadCategoryController::class, 'updateLeadCategory'])->name('category.updateDefault');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('leadCategory', LeadCategoryController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('lead-notes/ask-for-password/{id}', [LeadNoteController::class, 'askForPassword'])->name('lead-notes.ask_for_password');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('lead-notes/check-password', [LeadNoteController::class, 'checkPassword'])->name('lead-notes.check_password');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('lead-notes/apply-quick-action', [LeadNoteController::class, 'applyQuickAction'])->name('lead-notes.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('lead-notes', LeadNoteController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('deal-notes/apply-quick-action', [DealNoteController::class, 'applyQuickAction'])->name('deal-notes.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('deal-notes', DealNoteController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('leadboards/get-stage-slug', [LeadBoardController::class, 'getStageSlug'])->name('leadboards.get_stage_slug');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('leadboards/collapseColumn', [LeadBoardController::class, 'collapseColumn'])->name('leadboards.collapse_column');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('leadboards/updateIndex', [LeadBoardController::class, 'updateIndex'])->name('leadboards.update_index');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('leadboards/loadMore', [LeadBoardController::class, 'loadMore'])->name('leadboards.load_more');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('leadboards', LeadBoardController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('lead-form/sortFields', [LeadCustomFormController::class, 'sortFields'])->name('lead-form.sortFields');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('lead-form', LeadCustomFormController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'deals'], function () {
        Route::get('import', [DealController::class, 'importLead'])->name('deals.import');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'deals'], function () {
        Route::post('import', [DealController::class, 'importStore'])->name('deals.import.store');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'deals'], function () {
        Route::post('import/process', [DealController::class, 'importProcess'])->name('deals.import.process');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'lead-contact'], function () {
        Route::get('import', [LeadContactController::class, 'importLead'])->name('lead-contact.import');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'lead-contact'], function () {
        Route::post('import', [LeadContactController::class, 'importStore'])->name('lead-contact.import.store');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'lead-contact'], function () {
        Route::post('import/process', [LeadContactController::class, 'importProcess'])->name('lead-contact.import.process');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('lead-contact', LeadContactController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('lead-contact/apply-quick-action', [LeadContactController::class, 'applyQuickAction'])->name('lead-contact.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deals/get-stage/{id}', [DealController::class, 'getStages'])->name('deals.get-stage');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deals/get-deals/{id}', [DealController::class, 'getDeals'])->name('deals.get-deals');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('deals/get-agent/{id}', [DealController::class, 'getAgents'])->name('deals.get_agents');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('deals', DealController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('proposals/delete-image', [ProposalController::class, 'deleteProposalItemImage'])->name('proposals.delete_image');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('proposals/download/{id}', [ProposalController::class, 'download'])->name('proposals.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('proposals/send-proposal/{id}', [ProposalController::class, 'sendProposal'])->name('proposals.send_proposal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('proposals/add-item', [ProposalController::class, 'addItem'])->name('proposals.add_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('proposals', ProposalController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('proposal-template/apply-quick-action', [ProposalTemplateController::class, 'applyQuickAction'])->name('proposal_template.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('proposal-template/add-item', [ProposalController::class, 'addItem'])->name('proposal-template.add_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('proposal-template', ProposalTemplateController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('proposal-template/download/{id}', [ProposalTemplateController::class, 'download'])->name('proposal-template.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('proposals-template/delete-image', [ProposalTemplateController::class, 'deleteProposalItemImage'])->name('proposal_template.delete_image');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('lead-settings', LeadSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::post('lead-settings-status/update-status/{companyId}', [LeadSettingController::class, 'updateLeadSettingStatus'])->name('lead-setting.update_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('lead-source-settings', LeadSourceSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::get('lead-stage-update/{statusId}', [LeadStageSettingController::class, 'statusUpdate'])->name('lead-stage-setting.stageUpdate');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('lead-stage-setting', LeadStageSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::get('lead-pipeline-update/{statusId}', [LeadPipelineSettingController::class, 'statusUpdate'])->name('lead-pipeline-update.stageUpdate');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('lead-pipeline-setting', LeadPipelineSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::resource('lead-agent-settings', LeadAgentSettingController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::post('lead-agent-settings/update-category/{id}', [LeadAgentSettingController::class, 'updateCategory'])->name('lead_agents.update_category');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::post('lead-agent-settings/update-status/{id}', [LeadAgentSettingController::class, 'updateStatus'])->name('lead_agents.update_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account/settings'], function () {
    Route::get('agent-category', [LeadAgentSettingController::class, 'agentCategories'])->name('lead_agent.categories');
});
});
