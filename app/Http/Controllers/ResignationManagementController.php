<?php

namespace App\Http\Controllers;

use App\DataTables\ResignationDataTable;
use App\Helper\Reply;
use App\Models\Designation;
use App\Models\EmployeeDetails;
use App\Models\EmployeeExit;
use App\Models\EmployeeExitChecklist;
use App\Models\EmployeeExitHistory;
use App\Models\Resignation;
use App\Models\ResignationReply;
use App\Models\Team;
use App\Notifications\ResignationApproved;
use App\Notifications\ResignationApprovedConfirmation;
use App\Notifications\ResignationDecisionUndone;
use App\Notifications\ResignationRejected;
use App\Notifications\ResignationReplyReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ResignationManagementController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Employee Resignations';
    }

    public function index(ResignationDataTable $dataTable)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $companyId = company()->id;
        $statusSql = Resignation::effectiveStatusSql();

        $this->departments  = Team::where('company_id', $companyId)->orderBy('team_name')->get();
        $this->designations = Designation::where('company_id', $companyId)->orderBy('name')->get();

        $this->totalCount     = Resignation::where('company_id', $companyId)->count();
        $this->pendingCount   = Resignation::where('company_id', $companyId)->whereRaw("{$statusSql} = 'pending'")->count();
        // "Approved" is a rollup of every request HR has signed off on, regardless
        // of whether it's still in notice period or has since completed — those
        // two are shown as their own cards for day-to-day tracking.
        $this->approvedCount  = Resignation::where('company_id', $companyId)->where('status', 'approved')->count();
        $this->noticeCount    = Resignation::where('company_id', $companyId)->whereRaw("{$statusSql} = 'notice_period'")->count();
        $this->rejectedCount  = Resignation::where('company_id', $companyId)->whereRaw("{$statusSql} = 'rejected'")->count();
        $this->completedCount = Resignation::where('company_id', $companyId)->whereRaw("{$statusSql} = 'completed'")->count();

        return $dataTable->render('resignation-management.index', $this->data);
    }

    public function show($id)
    {
        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        $this->resignation = Resignation::with(['employee.employeeDetail.department', 'employee.employeeDetail.designation', 'replies.sender'])
            ->where('company_id', company()->id)
            ->findOrFail($id);
        $this->pageTitle = 'Resignation Details — ' . $this->resignation->employee->name;

        $this->noticePeriodOptions = DB::table('notice_period_settings')
            ->where('company_id', company()->id)
            ->orderBy('name')
            ->get();

        $this->selectedNoticePeriodLabel = Resignation::noticePeriodLabel(
            $this->resignation->notice_period_id,
            $this->resignation->notice_period_days
        );

        $employeeDetail = $this->resignation->employee->employeeDetail;
        $this->employmentStatus = $employeeDetail
            ? EmployeeDetails::where('id', $employeeDetail->id)
                ->selectRaw(EmployeeDetails::employmentStatusSql() . ' as status')
                ->value('status')
            : 'confirmed';

        $view = 'resignation-management.ajax.show';

        if (request()->ajax()) {
            $html = view($view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        return view($view, $this->data);
    }

    public function approve(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $resignation = Resignation::where('company_id', company()->id)->findOrFail($id);

        if ($resignation->status !== 'pending') {
            return Reply::error('This resignation has already been reviewed.');
        }

        // The notice period the resignation is approved with must be a duration
        // the admin explicitly picked from the company's Notice Period Settings —
        // it is no longer guessed from whatever setting was added most recently.
        $request->validate([
            'notice_period_id' => 'required|integer',
        ]);

        $noticePeriodSetting = DB::table('notice_period_settings')
            ->where('company_id', company()->id)
            ->where('id', $request->notice_period_id)
            ->first();

        if (!$noticePeriodSetting) {
            return Reply::error('Please select a valid notice period.');
        }

        // reviewed_at doubles as the Admin Approval Date the notice period is
        // anchored to below — no separate column needed for it.
        $resignation->status      = 'approved';
        $resignation->reviewed_by = user()->id;
        $resignation->reviewed_at = now();

        // The notice period runs from the Admin Approval Date for the
        // admin-selected Notice Period Settings duration — NOT from the
        // employee's original resignation_date/notice_period_days (those
        // reflect what the employee asked for, not the approved policy).
        $resignation->notice_period_id = $noticePeriodSetting->id;

        $approvalDate = now()->startOfDay();
        $noticeEndDate = $approvalDate->copy()->addMonths((int) $noticePeriodSetting->months);

        $resignation->notice_start_date  = $approvalDate->toDateString();
        $resignation->last_working_date  = $noticeEndDate->toDateString();
        $resignation->notice_period_days = $approvalDate->diffInDays($noticeEndDate);

        $resignation->save();

        // Feed the approval into the existing exit-management pipeline so the
        // employee gets the same checklist / interview / history tracking an
        // admin-initiated exit would create.
        $exit = EmployeeExit::create([
            'user_id'             => $resignation->user_id,
            'company_id'          => company()->id,
            'exit_type'           => $resignation->exit_type,
            'exit_reason'         => $resignation->reason,
            'resignation_date'    => $resignation->resignation_date,
            'notice_period_days'  => $resignation->notice_period_days,
            'last_working_date'   => $resignation->last_working_date,
            'remarks'             => $resignation->remarks,
            'exit_initiated_by'   => user()->id,
            'exit_initiated_date' => now()->toDateString(),
            'created_by'          => user()->id,
        ]);

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

        $employee = EmployeeDetails::where('user_id', $resignation->user_id)->first();
        $prevStatus = $employee->employment_status ?? 'confirmed';
        $employee->employment_status        = 'notice_period';
        $employee->notice_period_start_date = $resignation->notice_start_date;
        $employee->notice_period_end_date   = $resignation->last_working_date;
        $employee->last_date                = $resignation->last_working_date;
        $employee->save();

        EmployeeExitHistory::create([
            'exit_id'         => $exit->id,
            'user_id'         => $resignation->user_id,
            'company_id'      => company()->id,
            'previous_status' => $prevStatus,
            'new_status'      => 'notice_period',
            'action'          => 'Resignation approved by ' . user()->name . ' — exit initiated',
            'changed_by'      => user()->id,
        ]);

        Notification::send($resignation->employee, new ResignationApproved($resignation));
        Notification::send(Resignation::adminRecipients(company()->id), new ResignationApprovedConfirmation($resignation));

        return Reply::success('Resignation approved. The employee has been moved to notice period.');
    }

    /**
     * Notice Period Settings for the current company, for the quick-approve
     * picker on the list page (the detail page renders its dropdown server-side
     * instead, since it already has this data via show()).
     */
    public function noticePeriods()
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $options = DB::table('notice_period_settings')
            ->where('company_id', company()->id)
            ->orderBy('name')
            ->get()
            ->map(function ($setting) {
                $days = $setting->months * 30;
                $daysLabel = $days . ' Day' . ($days == 1 ? '' : 's');

                return [
                    'id' => $setting->id,
                    'label' => $setting->name ? "{$setting->name} ({$daysLabel})" : $daysLabel,
                ];
            });

        return Reply::dataOnly(['status' => 'success', 'data' => $options]);
    }

    public function reject(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $resignation = Resignation::where('company_id', company()->id)->findOrFail($id);

        if ($resignation->status !== 'pending') {
            return Reply::error('This resignation has already been reviewed.');
        }

        $resignation->status        = 'rejected';
        $resignation->admin_remarks = $request->reason;
        $resignation->reviewed_by   = user()->id;
        $resignation->reviewed_at   = now();
        $resignation->save();

        // Rejecting a resignation means the employee stays on — put them back
        // to confirmed rather than leaving their status untouched (mirrors
        // how an approved resignation moves them to notice_period below).
        $employee = EmployeeDetails::where('user_id', $resignation->user_id)->first();

        if ($employee) {
            $prevStatus = $employee->employment_status ?? 'confirmed';
            $employee->employment_status = 'confirmed';
            $employee->save();

            EmployeeExitHistory::create([
                'user_id'         => $resignation->user_id,
                'company_id'      => company()->id,
                'previous_status' => $prevStatus,
                'new_status'      => 'confirmed',
                'action'          => 'Resignation rejected by ' . user()->name . ' — employee remains confirmed',
                'changed_by'      => user()->id,
                'notes'           => $request->reason,
            ]);
        }

        Notification::send($resignation->employee, new ResignationRejected($resignation));

        return Reply::success('Resignation rejected.');
    }

    public function undo($id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $resignation = Resignation::where('company_id', company()->id)->findOrFail($id);

        if (!in_array($resignation->status, ['approved', 'rejected'])) {
            return Reply::error('Only an approved or rejected resignation can be undone.');
        }

        $employee = EmployeeDetails::where('user_id', $resignation->user_id)->first();

        if ($employee && $employee->employment_status === 'off_boarded') {
            return Reply::error('This employee has already been off-boarded and can no longer be reverted here.');
        }

        $previousDecision = $resignation->status;

        if ($previousDecision === 'approved') {
            // Reverse the exit-management pipeline approve() created: the
            // EmployeeExit record, its checklist, and the notice_period move.
            $exit = EmployeeExit::where('user_id', $resignation->user_id)
                ->where('resignation_date', $resignation->resignation_date)
                ->latest()
                ->first();

            $prevEmploymentStatus = 'confirmed';

            if ($exit) {
                $approvalHistory = EmployeeExitHistory::where('exit_id', $exit->id)
                    ->where('action', 'like', 'Resignation approved%')
                    ->latest()
                    ->first();

                if ($approvalHistory) {
                    $prevEmploymentStatus = $approvalHistory->previous_status;
                }

                EmployeeExitChecklist::where('exit_id', $exit->id)->delete();
                EmployeeExitHistory::where('exit_id', $exit->id)->delete();
                $exit->delete();
            }

            if ($employee) {
                $employee->employment_status        = $prevEmploymentStatus;
                $employee->notice_period_start_date = null;
                $employee->notice_period_end_date   = null;
                $employee->last_date                = null;
                $employee->save();
            }
        } else {
            // Was rejected — reject() moved the employee to 'confirmed'; find
            // what they were before that for an accurate revert.
            if ($employee) {
                $rejectionHistory = EmployeeExitHistory::where('user_id', $resignation->user_id)
                    ->where('action', 'like', 'Resignation rejected%')
                    ->latest()
                    ->first();

                $employee->employment_status = $rejectionHistory->previous_status ?? 'confirmed';
                $employee->save();
            }
        }

        $resignation->status        = 'pending';
        $resignation->admin_remarks = null;
        $resignation->reviewed_by   = null;
        $resignation->reviewed_at   = null;
        $resignation->save();

        if ($employee) {
            EmployeeExitHistory::create([
                'user_id'         => $resignation->user_id,
                'company_id'      => company()->id,
                'previous_status' => $previousDecision === 'approved' ? 'notice_period' : 'confirmed',
                'new_status'      => $employee->employment_status,
                'action'          => 'Resignation ' . $previousDecision . ' undone by ' . user()->name . ' — back to pending review',
                'changed_by'      => user()->id,
            ]);
        }

        Notification::send($resignation->employee, new ResignationDecisionUndone($resignation, $previousDecision));

        return Reply::success('Resignation decision undone. It is now pending review again.');
    }

    public function reply(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $resignation = Resignation::with('employee')->where('company_id', company()->id)->findOrFail($id);

        ResignationReply::create([
            'resignation_id' => $resignation->id,
            'user_id'        => user()->id,
            'sender_type'    => 'admin',
            'message'        => $request->message,
        ]);

        Notification::send($resignation->employee, new ResignationReplyReceived($resignation, user()));

        return Reply::success('Reply sent successfully.');
    }
}
