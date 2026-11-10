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

    /**
     * The round plan rows from the form (recruitment.partials.round-plan),
     * checked, in the order given.
     */
    protected function planRows(\Illuminate\Http\Request $request): array
    {
        $rows = [];
        $employeeIds = null;

        foreach (array_values((array) $request->input('rounds', [])) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $n = $i + 1;
            $type = ($row['type'] ?? 'interview') === 'test' ? 'test' : 'interview';
            $mode = array_key_exists($row['mode'] ?? '', \App\Models\RecruitInterview::MODES) ? $row['mode'] : 'in_person';
            $duration = (int) ($row['duration'] ?? 30);
            $max = trim((string) ($row['max_marks'] ?? ''));
            $cut = trim((string) ($row['cut_off'] ?? ''));

            if (!in_array($duration, \App\Models\RecruitInterview::DURATIONS, true)) {
                $this->fail('Round ' . $n . ' (' . $name . '): choose a duration.');
            }

            if ($max !== '' && (!is_numeric($max) || $max <= 0)) {
                $this->fail('Round ' . $n . ' (' . $name . '): "out of" must be a number above 0.');
            }

            if ($cut !== '' && (!is_numeric($cut) || $cut < 0)) {
                $this->fail('Round ' . $n . ' (' . $name . '): the cut-off must be a number.');
            }

            if ($type === 'test') {
                if ($max === '') {
                    $this->fail('Round ' . $n . ' (' . $name . '): give the total marks ("out of") for the test.');
                }

                if ($cut !== '' && (float) $cut > (float) $max) {
                    $this->fail('Round ' . $n . ' (' . $name . '): the cut-off cannot be above the total marks.');
                }
            } elseif ($cut !== '' && (float) $cut > 5) {
                $this->fail('Round ' . $n . ' (' . $name . '): an interview pass mark is out of 5.');
            }

            $employeeIds ??= $this->employees()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_intersect(array_unique(array_filter(array_map('intval', (array) ($row['interviewer_ids'] ?? [])))), $employeeIds));

            $rows[] = [
                'id' => (int) ($row['id'] ?? 0) ?: null,
                'name' => mb_substr($name, 0, 100),
                'type' => $type,
                'mode' => $mode,
                'duration' => $duration,
                'interviewer_ids' => $ids,
                'max_marks' => $type === 'test' ? (float) $max : null,
                'cut_off' => $cut === '' ? null : (float) $cut,
                'questions' => $type === 'interview' ? (trim((string) ($row['questions'] ?? '')) ?: null) : null,
            ];
        }

        if (count($rows) > 10) {
            $this->fail('A plan can have at most 10 rounds.');
        }

        return $rows;
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
