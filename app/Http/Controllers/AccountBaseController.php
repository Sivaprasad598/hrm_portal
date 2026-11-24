<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use App\Models\MobileAppDownloadSetting;
use Carbon\Carbon;
use AppModules\Messages\Models\UserChat;
use App\Models\TaskHistory;
use App\Models\UserActivity;
use App\Models\ProjectTimeLog;
use App\Models\ProjectActivity;
use Illuminate\Support\Facades\App;
use App\Traits\UniversalSearchTrait;
use Illuminate\Support\Facades\Route;

class AccountBaseController extends Controller
{

    use  UniversalSearchTrait;

    /**
     * UserBaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if (!(app()->runningInConsole() || config('app.seeding'))) {
            $this->currentRouteName = request()->route()->getName();
        }

        $this->middleware(function ($request, $next) {

            // Keep this function at top
            $this->adminSpecific();

            // Call this function after adminSpecific
            $this->common();

            // a module still being built (Settings -> Assign Menus -> Modules): Coming soon
            if ($module = \App\Services\Access\ComingSoon::blocks($request->route()?->getName())) {
                return $this->comingSoon($request, $module);
            }

            return $next($request);
        });
    }

    /**
     * The answer for a page of a module marked Coming soon: the page itself (also inside the
     * in-place page swap), a side panel, an empty table for a list's data, or a message for a save.
     */
    protected function comingSoon(\Illuminate\Http\Request $request, string $module)
    {
        $this->pageTitle = $module;
        $this->comingSoonModule = $module;

        if ($request->has('draw')) {
            return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        if ($request->ajax() && !$request->headers->has('X-Page-Swap')) {
            if (!$request->isMethod('GET')) {
                // (Reply gives an array: a controller action turns it into JSON, a middleware does not)
                return response()->json(\App\Helper\Reply::error($module . ' is coming soon. It cannot be used yet.'));
            }

            return response()->json(\App\Helper\Reply::dataOnly(['status' => 'success', 'title' => $module,
                'html' => view('coming-soon.panel', $this->data)->render()]));
        }

        return response()->view('coming-soon.index', $this->data);
    }

    public function adminSpecific()
    {

        abort_403(!user()->admin_approval && request()->ajax());

        if (!user()->admin_approval && Route::currentRouteName() != 'account_unverified') {
            // send() is added to force redirect from here rather return to called function
            return redirect(route('account_unverified'))->send();
        }

        $this->adminTheme = admin_theme();
        $this->invoiceSetting = invoice_setting();

        $this->modules = user_modules();

        if (in_array('messages', user_modules()) && class_exists(UserChat::class)) {
            $this->unreadMessagesCount = UserChat::where('to', user()->id)
                ->where('message_seen', 'no')
                ->count();
        }

        $this->viewTimelogPermission = user()->permission('view_timelogs');

        $activeTimerQuery = ProjectTimeLog::whereNull('end_time')
            ->doesntHave('activeBreak')
            ->join('users', 'users.id', '=', 'project_time_logs.user_id');

        if ($this->viewTimelogPermission != 'all' && manage_active_timelogs() != 'all') {
            $activeTimerQuery->where('project_time_logs.user_id', user()->id);
        }

        $this->activeTimerCount = $activeTimerQuery->count();

        $this->selfActiveTimer = ProjectTimeLog::selfActiveTimer();

        $this->customLink = custom_link_setting();
    }

    public function common()
    {
        $this->fields = [];
        $this->languageSettings = language_setting();
        $this->pushSetting = push_setting();
        $this->smtpSetting = smtp_setting();
        $this->pusherSettings = pusher_settings();

        App::setLocale(user()->locale);
        Carbon::setLocale(user()->locale);
        setlocale(LC_TIME, user()->locale . '_' . mb_strtoupper($this->company->locale));

        $this->user = user();
        // counted by the database: loading every unread notification just to count them
        // cost more the longer somebody left them unread
        $this->unreadNotificationCount = $this->user ? \App\Services\Access\SystemAccounts::withoutTestNotifications($this->user->unreadNotifications())->count() : 0;
        $this->stickyNotes = $this->user->sticky;

        $this->arowanaPlugins = arowana_plugins();

        $this->checkListTotal = GlobalSetting::CHECKLIST_TOTAL;

        if (in_array('admin', user_roles())) {
            $this->appTheme = admin_theme();
            $this->checkListCompleted = GlobalSetting::checkListCompleted();
        }
        else if (in_array('client', user_roles())) {
            $this->appTheme = client_theme();
        }
        else {
            $this->appTheme = employee_theme();
        }

        $this->sidebarUserPermissions = sidebar_user_perms();
        $mobileDownloadSetting = MobileAppDownloadSetting::first();
        $this->showMobileAppButton = $mobileDownloadSetting && (filled($mobileDownloadSetting->app_ios) || filled($mobileDownloadSetting->app_android));
    }

    public function logProjectActivity($projectId, $text)
    {
        $activity = new ProjectActivity();
        $activity->project_id = $projectId;
        $activity->activity = $text;
        $activity->save();
    }

    public function logUserActivity($userId, $text)
    {
        $activity = new UserActivity();
        $activity->user_id = $userId;
        $activity->activity = $text;
        $activity->save();
    }

    public function logTaskActivity($taskID, $userID, $text, $boardColumnId = null, $subTaskId = null)
    {
        $activity = new TaskHistory();
        $activity->task_id = $taskID;

        if (!is_null($subTaskId)) {
            $activity->sub_task_id = $subTaskId;
        }

        $activity->user_id = $userID;
        $activity->details = $text;

        if (!is_null($boardColumnId)) {
            $activity->board_column_id = $boardColumnId;
        }

        $activity->save();
    }

}


