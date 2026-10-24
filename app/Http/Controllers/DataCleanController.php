<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Maintenance\DataCleaner;
use Illuminate\Http\Request;

/**
 * Settings -> Data Clean: empty the application for a fresh test. Super Admin
 * only; a full backup first; the confirmation phrase typed in.
 */
class DataCleanController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Data Clean';
        $this->activeSettingMenu = 'data_clean';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array('admin', user_roles()));

            return $next($request);
        });
    }

    public function index(DataCleaner $cleaner)
    {
        $this->groups = DataCleaner::GROUPS;
        $this->items = collect(array_keys(DataCleaner::GROUPS))->mapWithKeys(fn ($g) => [$g => $cleaner->items($g)])->all();
        $this->special = DataCleaner::SPECIAL;
        $this->counts = $cleaner->counts();
        $this->phrase = DataCleaner::CONFIRM_PHRASE;
        $this->keptUsers = \App\Models\User::withoutGlobalScopes()->whereIn('id', $cleaner->keptUsers())->get(['id', 'name', 'email']);

        return view('data-clean.index', $this->data);
    }

    /** What the picked items would remove; nothing is removed. */
    public function preview(Request $request, DataCleaner $cleaner)
    {
        $items = $cleaner->resolve((array) $request->items);

        if (!$items) {
            return Reply::error('Tick at least one table.');
        }

        return Reply::dataOnly(['status' => 'success'] + $cleaner->preview($items));
    }

    public function clean(Request $request, DataCleaner $cleaner)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'string',
            'confirm' => 'required|string',
        ]);

        if (trim($request->confirm) !== DataCleaner::CONFIRM_PHRASE) {
            return Reply::error('Type ' . DataCleaner::CONFIRM_PHRASE . ' exactly to confirm.');
        }

        $items = $cleaner->resolve((array) $request->items);

        if (!$items) {
            return Reply::error('Tick at least one table.');
        }

        session()->save();
        @set_time_limit(1800);

        $backup = $cleaner->backup();

        if (!$backup['ok']) {
            return Reply::error($backup['message']);
        }

        $result = $cleaner->clean($items, $backup['path'], (int) user()->id);

        return Reply::successWithData(
            'Cleaned ' . count($result['items']) . ' item(s). ' . number_format(array_sum($result['removed'])) . ' rows removed. Backup: ' . basename($result['backup']),
            ['removed' => $result['removed']]
        );
    }

}
