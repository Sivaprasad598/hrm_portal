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
        $this->counts = $cleaner->counts();
        $this->implied = DataCleaner::IMPLIED_BY_EMPLOYEES;
        $this->phrase = DataCleaner::CONFIRM_PHRASE;
        $this->keptUsers = \App\Models\User::withoutGlobalScopes()->whereIn('id', $cleaner->keptUsers())->get(['id', 'name', 'email']);

        return view('data-clean.index', $this->data);
    }

    public function clean(Request $request, DataCleaner $cleaner)
    {
        $request->validate([
            'groups' => 'required|array|min:1',
            'groups.*' => 'in:' . implode(',', array_keys(DataCleaner::GROUPS)),
            'confirm' => 'required|string',
        ]);

        if (trim($request->confirm) !== DataCleaner::CONFIRM_PHRASE) {
            return Reply::error('Type ' . DataCleaner::CONFIRM_PHRASE . ' exactly to confirm.');
        }

        session()->save();
        @set_time_limit(1800);

        $backup = $cleaner->backup();

        if (!$backup['ok']) {
            return Reply::error($backup['message']);
        }

        $result = $cleaner->clean((array) $request->groups, $backup['path'], (int) user()->id);

        return Reply::successWithData(
            'Cleaned: ' . implode(', ', array_map(fn ($g) => DataCleaner::GROUPS[$g][0], $result['groups'])) . '. '
            . number_format(array_sum($result['removed'])) . ' rows removed. Backup: ' . basename($result['backup']),
            ['removed' => $result['removed']]
        );
    }

}
