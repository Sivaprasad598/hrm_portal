<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Models\User;
use App\Services\Access\SystemAccounts;
use App\Services\Recruit\RecruitAccess;
use Carbon\Carbon;

/**
 * What the Recruitment pages share: the access checks (RecruitAccess), side
 * panels that also open as a page of their own, and dates typed in the
 * company's format.
 */
abstract class RecruitBaseController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->canManage = RecruitAccess::can('manage');
            $this->canSettings = RecruitAccess::can('settings');

            return $next($request);
        });
    }

    protected function needView(): void
    {
        abort_403(!RecruitAccess::can('view'));
    }

    protected function needManage(): void
    {
        abort_403(!RecruitAccess::can('manage'));
    }

    protected function panel(string $view, string $title)
    {
        $this->pageTitle = $title;
        $this->view = $view;

        if (request()->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view($view, $this->data)->render(), 'title' => $title]);
        }

        return view('recruitment.page', $this->data);
    }

    /** A date typed in the company format, as Y-m-d. */
    protected function date(?string $value, string $label, bool $required = true): ?string
    {
        if ($value === null || trim($value) === '') {
            if ($required) {
                $this->fail('Enter the ' . $label . '.');
            }

            return null;
        }

        try {
            return Carbon::createFromFormat(company()->date_format, trim($value))->toDateString();
        } catch (\Throwable) {
            $this->fail('The ' . $label . ' is not a valid date.');
        }
    }

    protected function fail(string $message): never
    {
        abort(response()->json(['status' => 'fail', 'message' => $message, 'errors' => []], 422));
    }

    /** Active employees, for the interview panel. */
    protected function employees()
    {
        return User::join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->whereNotIn('users.id', SystemAccounts::ids())
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'employee_details.employee_id']);
    }

}
