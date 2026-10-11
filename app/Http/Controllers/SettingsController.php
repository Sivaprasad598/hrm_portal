<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Requests\Settings\UpdateOrganisationSettings;
use App\Traits\CurrencyExchange;

class SettingsController extends AccountBaseController
{

    use CurrencyExchange;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.accountSettings';
        $this->activeSettingMenu = 'company_settings';
        $this->middleware(function ($request, $next) {
            if (user()->permission('manage_company_setting') === 'all') {
                return $next($request);
            }

            // This page is the company's own settings, so somebody without that
            // permission cannot have it. They may still have been granted other
            // settings pages — HR and the Admin hold Attendance, Shift,
            // Biometric and Leaves — and sending them to their profile meant the
            // Settings link in the sidebar could never reach any of them. They
            // land on the first page they do hold; profile settings only when
            // they hold none.
            return redirect()->route($this->firstSettingTheyHold() ?? 'profile-settings.index');
        });
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('company-settings.index', $this->data);
    }

    /**
     * The first settings page this person's roles have been granted, in the
     * order the Settings menu lists them, or null if they hold none.
     *
     * Only a page that still exists as a route is offered, so a stale grant
     * cannot send anybody to a dead one.
     */
    protected function firstSettingTheyHold(): ?string
    {
        $settingsId = \App\Models\SidebarMenu::whereNull('parent_id')->where('slug', 'settings')->value('id');

        if (!$settingsId) {
            return null;
        }

        $routes = \Illuminate\Support\Facades\DB::table('sidebar_menus')
            ->join('role_menus', 'role_menus.menu_id', '=', 'sidebar_menus.id')
            ->join('role_user', 'role_user.role_id', '=', 'role_menus.role_id')
            ->where('role_user.user_id', user()->id)
            ->where('sidebar_menus.parent_id', $settingsId)
            ->where('sidebar_menus.is_active', 1)
            ->whereNotNull('sidebar_menus.route')
            ->orderBy('sidebar_menus.sort_order')
            ->pluck('sidebar_menus.route')
            ->unique();

        foreach ($routes as $route) {
            // company-settings is this page; offering it would loop.
            if ($route === 'company-settings.index' || !\Illuminate\Support\Facades\Route::has($route)) {
                continue;
            }

            return $route;
        }

        return null;
    }

    // phpcs:ignore
    public function update(UpdateOrganisationSettings $request, $id)
    {
        $setting = \company();
        $setting->company_name = $request->company_name;
        $setting->company_email = $request->company_email;
        $setting->company_phone = $request->company_phone;
        $setting->website = $request->website;
        $setting->save();

        return Reply::success(__('messages.updateSuccess'));
    }

    // Remove in v 5.2.5
    public function hideWebhookAlert()
    {
        $this->company->show_new_webhook_alert = false;
        $this->company->saveQuietly();
        session()->forget('company');

        return Reply::success('Webohook alert box has been removed permanently');
    }

}
