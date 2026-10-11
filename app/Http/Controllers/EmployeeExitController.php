<?php

namespace App\Http\Controllers;

use App\DataTables\EmployeeExitDataTable;
use App\Helper\Reply;
use App\Models\Designation;
use App\Models\EmployeeDetails;
use App\Models\EmployeeExit;
use App\Models\EmployeeExitChecklist;
use App\Models\EmployeeExitHistory;
use App\Models\EmployeeExitInterview;
use App\Models\Team;
use App\Models\User;
use App\Notifications\EmployeeOffBoarded;
use App\Notifications\EmployeeOffBoardedAdmin;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeExitController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Employee Exit';
    }

    public function index(EmployeeExitDataTable $dataTable)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $this->departments  = Team::where('company_id', company()->id)->orderBy('team_name')->get();
        $this->designations = Designation::where('company_id', company()->id)->orderBy('name')->get();

        $companyId = company()->id;
        $statusSql = EmployeeDetails::employmentStatusSql();

        $this->totalCount       = EmployeeDetails::where('company_id', $companyId)->count();
        $this->confirmedCount   = EmployeeDetails::where('company_id', $companyId)->whereRaw("{$statusSql} = 'confirmed'")->count();
        $this->probationCount   = EmployeeDetails::where('company_id', $companyId)->whereRaw("{$statusSql} = 'probation'")->count();
        $this->noticeCount      = EmployeeDetails::where('company_id', $companyId)->whereRaw("{$statusSql} = 'notice_period'")->count();
        $this->offBoardedCount  = EmployeeDetails::where('company_id', $companyId)->whereRaw("{$statusSql} = 'off_boarded'")->count();

        return $dataTable->render('employee-exit.index', $this->data);
    }

    public function initiate($userId)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $this->employee = User::with('employeeDetail')->findOrFail($userId);
        $this->pageTitle = 'Initiate Exit — ' . $this->employee->name;

        $view = 'employee-exit.ajax.initiate';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function store(Request $request)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $request->validate([
            'user_id'          => 'required|exists:users,id',
            'exit_type'        => 'required|in:resignation,termination,contract_end,retirement,absconded,other',
            'exit_reason'      => 'nullable|max:1000',
            'resignation_date' => 'required|date',
            'notice_period_days' => 'required|integer|min:0|max:365',
            'last_working_date'  => 'required|date|after_or_equal:resignation_date',
        ]);

        $exit = EmployeeExit::create([
            'user_id'            => $request->user_id,
            'company_id'         => company()->id,
            'exit_type'          => $request->exit_type,
            'exit_reason'        => $request->exit_reason,
            'resignation_date'   => Carbon::parse($request->resignation_date)->toDateString(),
            'notice_period_days' => $request->notice_period_days,
            'last_working_date'  => Carbon::parse($request->last_working_date)->toDateString(),
            'remarks'            => $request->remarks,
            'exit_initiated_by'  => user()->id,
            'exit_initiated_date' => now()->toDateString(),
            'created_by'         => user()->id,
        ]);

        // Seed the checklist
        foreach (EmployeeExit::checklistTemplate() as $category => $items) {
            foreach ($items as $item) {
                EmployeeExitChecklist::create([
                    'exit_id'    => $exit->id,
                    'category'   => $category,
                    'item_key'   => $item['key'],
                    'item_label' => $item['label'],
                    'status'     => 'pending',
                ]);
            }
        }

        // Update employment status
        $employee = EmployeeDetails::where('user_id', $request->user_id)->first();
        $prevStatus = $employee->employment_status ?? 'confirmed';
        $employee->employment_status = 'notice_period';
        $employee->notice_period_start_date = Carbon::parse($request->resignation_date)->toDateString();
        $employee->notice_period_end_date   = Carbon::parse($request->last_working_date)->toDateString();
        $employee->last_date = Carbon::parse($request->last_working_date)->toDateString();
        $employee->save();

        // Log history
        EmployeeExitHistory::create([
            'exit_id'         => $exit->id,
            'user_id'         => $request->user_id,
            'company_id'      => company()->id,
            'previous_status' => $prevStatus,
            'new_status'      => 'notice_period',
            'action'          => 'Exit initiated — ' . ucfirst(str_replace('_', ' ', $request->exit_type)),
            'changed_by'      => user()->id,
            'notes'           => $request->exit_reason,
        ]);

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => route('employee-exit.index')]);
    }

    public function show($id)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $this->exit = EmployeeExit::with(['employee', 'employee.employeeDetail', 'checklists', 'interview', 'initiatedBy'])->findOrFail($id);
        $this->pageTitle = 'Exit Details — ' . $this->exit->employee->name;

        $view = 'employee-exit.ajax.show';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function edit($id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $this->exit = EmployeeExit::with(['employee', 'employee.employeeDetail'])->findOrFail($id);
        $this->pageTitle = 'Edit Exit — ' . $this->exit->employee->name;

        $view = 'employee-exit.ajax.edit';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function update(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $exit = EmployeeExit::findOrFail($id);

        $request->validate([
            'exit_type'          => 'required|in:resignation,termination,contract_end,retirement,absconded,other',
            'exit_reason'        => 'nullable|max:1000',
            'resignation_date'   => 'required|date',
            'notice_period_days' => 'required|integer|min:0|max:365',
            'last_working_date'  => 'required|date|after_or_equal:resignation_date',
        ]);

        $exit->update([
            'exit_type'          => $request->exit_type,
            'exit_reason'        => $request->exit_reason,
            'resignation_date'   => Carbon::parse($request->resignation_date)->toDateString(),
            'notice_period_days' => $request->notice_period_days,
            'last_working_date'  => Carbon::parse($request->last_working_date)->toDateString(),
            'exit_interview_status'    => $request->exit_interview_status ?? $exit->exit_interview_status,
            'asset_clearance_status'   => $request->asset_clearance_status ?? $exit->asset_clearance_status,
            'finance_clearance_status' => $request->finance_clearance_status ?? $exit->finance_clearance_status,
            'hr_clearance_status'      => $request->hr_clearance_status ?? $exit->hr_clearance_status,
            'final_settlement_status'  => $request->final_settlement_status ?? $exit->final_settlement_status,
            'remarks'    => $request->remarks,
            'updated_by' => user()->id,
        ]);

        // Sync last_date in employee_details
        EmployeeDetails::where('user_id', $exit->user_id)->update([
            'last_date'                => Carbon::parse($request->last_working_date)->toDateString(),
            'notice_period_end_date'   => Carbon::parse($request->last_working_date)->toDateString(),
        ]);

        EmployeeExitHistory::create([
            'exit_id'    => $exit->id,
            'user_id'    => $exit->user_id,
            'company_id' => company()->id,
            'action'     => 'Exit record updated',
            'changed_by' => user()->id,
            'notes'      => 'Last working date updated to ' . Carbon::parse($request->last_working_date)->format(company()->date_format),
        ]);

        return Reply::successWithData(__('messages.updateSuccess'), ['redirectUrl' => route('employee-exit.index')]);
    }

    public function checklist($id)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $this->exit = EmployeeExit::with(['employee', 'checklists'])->findOrFail($id);
        $this->checklistsByCategory = $this->exit->checklists->groupBy('category');
        $this->pageTitle = 'Offboarding Checklist — ' . $this->exit->employee->name;

        $view = 'employee-exit.ajax.checklist';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function updateChecklist(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $exit = EmployeeExit::findOrFail($id);
        $items = $request->get('items', []);

        foreach ($items as $itemId => $data) {
            $checklist = EmployeeExitChecklist::where('exit_id', $id)->find($itemId);
            if (!$checklist) {
                continue;
            }

            $status = $data['status'] ?? 'pending';
            $checklist->status   = $status;
            $checklist->remarks  = $data['remarks'] ?? null;
            if ($status === 'completed') {
                $checklist->completed_by   = user()->id;
                $checklist->completed_date = now()->toDateString();
            } else {
                $checklist->completed_by   = null;
                $checklist->completed_date = null;
            }
            $checklist->save();
        }

        // Auto-update clearance summary fields on exit record
        $this->syncClearanceStatus($exit);

        return Reply::success('Checklist updated successfully.');
    }

    private function syncClearanceStatus(EmployeeExit $exit): void
    {
        $checklists = $exit->checklists;

        $categories = [
            'hr_clearance'         => 'hr_clearance_status',
            'finance_clearance'    => 'finance_clearance_status',
            'it_clearance'         => 'asset_clearance_status',
            'department_clearance' => 'asset_clearance_status',
        ];

        foreach ($categories as $cat => $field) {
            $items = $checklists->where('category', $cat);
            if ($items->isEmpty()) {
                continue;
            }
            $allDone = $items->every(fn($i) => in_array($i->status, ['completed', 'not_applicable']));
            if ($allDone) {
                $exit->$field = 'completed';
            }
        }

        $exit->save();
    }

    public function interview($id)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $this->exit      = EmployeeExit::with(['employee', 'interview'])->findOrFail($id);
        $this->interview = $this->exit->interview;
        $this->pageTitle = 'Exit Interview — ' . $this->exit->employee->name;

        $view = 'employee-exit.ajax.interview';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function storeInterview(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $exit = EmployeeExit::findOrFail($id);

        $request->validate([
            'interview_date'       => 'required|date',
            'reason_for_leaving'   => 'required|max:2000',
            'overall_experience'   => 'required|integer|between:1,5',
            'job_satisfaction'     => 'required|integer|between:1,5',
            'rehire_eligibility'   => 'required|in:eligible,not_eligible,review_required',
        ]);

        EmployeeExitInterview::updateOrCreate(
            ['exit_id' => $id],
            [
                'interview_date'           => Carbon::parse($request->interview_date)->toDateString(),
                'interviewer_id'           => user()->id,
                'reason_for_leaving'       => $request->reason_for_leaving,
                'overall_experience'       => $request->overall_experience,
                'management_feedback'      => $request->management_feedback,
                'job_satisfaction'         => $request->job_satisfaction,
                'compensation_feedback'    => $request->compensation_feedback,
                'work_environment_feedback'=> $request->work_environment_feedback,
                'career_growth_feedback'   => $request->career_growth_feedback,
                'suggestions'              => $request->suggestions,
                'rehire_eligibility'       => $request->rehire_eligibility,
                'additional_comments'      => $request->additional_comments,
            ]
        );

        $exit->update(['exit_interview_status' => 'completed']);

        return Reply::success('Exit interview saved successfully.');
    }

    public function history($userId)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $this->employee = User::findOrFail($userId);
        $this->histories = EmployeeExitHistory::with('changedByUser')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
        $this->pageTitle = 'Exit History — ' . $this->employee->name;

        $view = 'employee-exit.ajax.history';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function confirmEmployee(Request $request)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $userId = $request->user_id;
        $employee = EmployeeDetails::where('user_id', $userId)->firstOrFail();
        $prevStatus = $employee->employment_status ?? 'probation';

        $employee->employment_status  = 'confirmed';
        $employee->probation_end_date = now()->toDateString();
        $employee->save();

        EmployeeExitHistory::create([
            'user_id'         => $userId,
            'company_id'      => company()->id,
            'previous_status' => $prevStatus,
            'new_status'      => 'confirmed',
            'action'          => 'Employee confirmed after probation',
            'changed_by'      => user()->id,
            'notes'           => $request->notes ?? null,
        ]);

        return Reply::success('Employee marked as confirmed successfully.');
    }

    public function offboard(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $exit = EmployeeExit::findOrFail($id);
        $exit->update([
            'offboarding_date' => now()->toDateString(),
            'updated_by'       => user()->id,
        ]);

        $employee = EmployeeDetails::where('user_id', $exit->user_id)->first();
        $prevStatus = $employee->employment_status ?? 'notice_period';
        $employee->employment_status = 'off_boarded';
        $employee->save();

        // Mark the user inactive
        User::where('id', $exit->user_id)->update(['status' => 'deactive']);

        EmployeeExitHistory::create([
            'exit_id'         => $exit->id,
            'user_id'         => $exit->user_id,
            'company_id'      => company()->id,
            'previous_status' => $prevStatus,
            'new_status'      => 'off_boarded',
            'action'          => 'Employee off-boarded',
            'changed_by'      => user()->id,
            'notes'           => $request->notes ?? null,
        ]);

        // Just deactivated above, so re-fetch bypassing the active-user scope
        // — notify() still works fine on a deactivated account (email/bell
        // row both persist regardless of whether they can still log in).
        $offBoardedUser = User::withoutGlobalScopes()->find($exit->user_id);

        if ($offBoardedUser) {
            $offBoardedUser->notify(new EmployeeOffBoarded($exit));
        }

        foreach (User::allAdmins(company()->id) as $hrUser) {
            $hrUser->notify(new EmployeeOffBoardedAdmin($exit));
        }

        return Reply::success('Employee has been off-boarded successfully.');
    }
}
