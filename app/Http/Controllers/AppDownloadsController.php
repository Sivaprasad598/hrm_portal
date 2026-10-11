<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\MobileAppDownloadSetting;
use Illuminate\Http\Request;

class AppDownloadsController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.downloads';
        $this->activeSettingMenu = 'app_downloads';
        $this->middleware(function ($request, $next) {
            $canView = user()->permission('manage_company_setting') === 'all'
                || user()->permission('manage_app_setting') === 'all';
            abort_403(!$canView);

            return $next($request);
        });
    }

    public function index()
    {
        $this->downloadSetting = MobileAppDownloadSetting::instance();

        return view('app-downloads.index', $this->data);
    }

    public function update(Request $request)
    {
        abort_403(user()->permission('manage_app_setting') !== 'all');

        $validated = $request->validate([
            'app_ios' => 'nullable|url',
            'app_android' => 'nullable|url',
        ]);

        $setting = MobileAppDownloadSetting::instance();
        $setting->fill($validated);
        $setting->save();

        return Reply::success(__('messages.updateSuccess'));
    }

}
