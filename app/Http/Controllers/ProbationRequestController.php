<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\EmployeeDetails;
use App\Models\EmployeeExitHistory;
use App\Models\ProbationReply;
use App\Models\User;
use App\Notifications\ProbationAdminMessage;
use App\Notifications\ProbationCompletedAdmin;
use App\Notifications\ProbationCompletedEmployee;
use App\Notifications\ProbationReviewRejected;
use App\Scopes\ActiveScope;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProbationRequestController extends AccountBaseController
{

    public function approve(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $employeeDetails = EmployeeDetails::with('user')->where('company_id', company()->id)->findOrFail($id);

        if ($employeeDetails->probation_review_status !== 'pending') {
            return Reply::error('This probation completion request has already been reviewed.');
        }

        if (!$employeeDetails->user) {
            return Reply::error('This employee no longer has a linked user account.');
        }

        $completionDate = Carbon::parse($employeeDetails->joining_date);
        $prevStatus = $employeeDetails->employment_status ?? 'probation';

        $employeeDetails->employment_status       = 'confirmed';
        $employeeDetails->probation_end_date       = now()->toDateString();
        $employeeDetails->probation_review_status = 'approved';
        $employeeDetails->probation_admin_remarks = $request->remark;
        $employeeDetails->probation_reviewed_by   = user()->id;
        $employeeDetails->probation_reviewed_at   = now();
        $employeeDetails->probation_notified_at   = now();
        $employeeDetails->save();

        EmployeeExitHistory::create([
            'user_id'         => $employeeDetails->user_id,
            'company_id'      => company()->id,
            'previous_status' => $prevStatus,
            'new_status'      => 'confirmed',
            'action'          => 'Probation completed and approved — employee confirmed',
            'changed_by'      => user()->id,
            'notes'           => $request->remark,
        ]);

        $employeeDetails->user->notify(new ProbationCompletedEmployee($employeeDetails, $completionDate, $request->remark));

        foreach (User::allAdmins(company()->id) as $hrUser) {
            $hrUser->notify(new ProbationCompletedAdmin($employeeDetails, $completionDate));
        }

        return Reply::success('Probation completion approved. The employee has been marked as confirmed.');
    }

    public function reject(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $request->validate([
            'remark' => 'required|string|max:1000',
        ]);

        $employeeDetails = EmployeeDetails::with('user')->where('company_id', company()->id)->findOrFail($id);

        if ($employeeDetails->probation_review_status !== 'pending') {
            return Reply::error('This probation completion request has already been reviewed.');
        }

        $prevStatus = $employeeDetails->employment_status ?? 'probation';

        // A rejected probation request means the employee did not pass
        // probation, so — mirroring EmployeeExitController::offboard() — they
        // are off-boarded and their login deactivated, not left dangling on
        // "probation" indefinitely.
        $employeeDetails->employment_status       = 'off_boarded';
        $employeeDetails->probation_review_status = 'rejected';
        $employeeDetails->probation_admin_remarks = $request->remark;
        $employeeDetails->probation_reviewed_by   = user()->id;
        $employeeDetails->probation_reviewed_at   = now();
        $employeeDetails->save();

        if ($employeeDetails->user_id) {
            User::where('id', $employeeDetails->user_id)->update(['status' => 'deactive']);
        }

        EmployeeExitHistory::create([
            'user_id'         => $employeeDetails->user_id,
            'company_id'      => company()->id,
            'previous_status' => $prevStatus,
            'new_status'      => 'off_boarded',
            'action'          => 'Probation not approved — employee off-boarded',
            'changed_by'      => user()->id,
            'notes'           => $request->remark,
        ]);

        if ($employeeDetails->user) {
            $employeeDetails->user->notify(new ProbationReviewRejected($employeeDetails, $request->remark));
        }

        return Reply::success('Probation completion rejected. The employee has been off-boarded.');
    }

    /**
     * Reverse an already-reviewed (approved/rejected) probation request:
     * restore the employment status it had before that decision — read from
     * the EmployeeExitHistory row approve()/reject() wrote at the time — and
     * put the request back to "pending" so the admin can decide again. A
     * rejected request also reactivates the employee's login, undoing the
     * deactivation reject() applied.
     */
    public function undo($id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $employeeDetails = EmployeeDetails::with('user')->where('company_id', company()->id)->findOrFail($id);

        if (!in_array($employeeDetails->probation_review_status, ['approved', 'rejected'])) {
            return Reply::error('This request has not been reviewed yet, so there is nothing to undo.');
        }

        $lastDecision = EmployeeExitHistory::where('user_id', $employeeDetails->user_id)
            ->where('company_id', company()->id)
            ->where(function ($query) {
                $query->where('action', 'like', 'Probation completed and approved%')
                    ->orWhere('action', 'like', 'Probation not approved%');
            })
            ->orderByDesc('id')
            ->first();

        $wasRejected = $employeeDetails->probation_review_status === 'rejected';
        $statusBeforeUndo = $employeeDetails->employment_status;
        $restoredStatus = $lastDecision->previous_status ?? 'probation';

        $employeeDetails->employment_status       = $restoredStatus;
        $employeeDetails->probation_review_status = 'pending';
        $employeeDetails->probation_admin_remarks = null;
        $employeeDetails->probation_reviewed_by   = null;
        $employeeDetails->probation_reviewed_at   = null;
        $employeeDetails->probation_notified_at   = null;
        $employeeDetails->save();

        if ($wasRejected && $employeeDetails->user_id) {
            // The rejected user is currently deactivated, and User's ActiveScope
            // global scope excludes non-active users from a plain query — so a
            // scoped query here would silently match zero rows and never
            // reactivate them.
            User::withoutGlobalScope(ActiveScope::class)
                ->where('id', $employeeDetails->user_id)
                ->update(['status' => 'active']);
        }

        EmployeeExitHistory::create([
            'user_id'         => $employeeDetails->user_id,
            'company_id'      => company()->id,
            'previous_status' => $statusBeforeUndo,
            'new_status'      => $restoredStatus,
            'action'          => 'Probation decision undone — request reopened for review',
            'changed_by'      => user()->id,
        ]);

        return Reply::success('Undone. The request is pending review again.');
    }

    /**
     * Post a message into the employee's probation communication thread —
     * notification bar + email — without approving or rejecting the request.
     * Mirrors ResignationManagementController::reply(): available regardless
     * of review status, so the admin and employee can keep talking even
     * after a decision has been made, not just while pending.
     */
    public function message(Request $request, $id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $employeeDetails = EmployeeDetails::with('user')->where('company_id', company()->id)->findOrFail($id);

        if (!$employeeDetails->user) {
            return Reply::error('This employee no longer has a linked user account.');
        }

        $reply = ProbationReply::create([
            'employee_details_id' => $employeeDetails->id,
            'user_id'             => user()->id,
            'sender_type'         => 'admin',
            'message'             => $request->message,
        ]);

        $employeeDetails->user->notify(new ProbationAdminMessage($employeeDetails, $request->message));

        return Reply::successWithData('Message sent to the employee.', [
            'reply' => [
                'sender'    => user()->name,
                'message'   => $reply->message,
                'timestamp' => $reply->created_at->format('d-m-Y h:i A'),
            ],
        ]);
    }

    /**
     * Dismiss a still-pending probation completion request without deciding
     * it — clears the review flags so the employee drops off this list
     * entirely (rather than approved/rejected) and stays on plain
     * "probation" employment status, exactly as before the completion check
     * flagged them. Only valid while pending: an already-decided request's
     * real trail lives in EmployeeExitHistory and Undo, not here.
     */
    public function destroy($id)
    {
        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        $employeeDetails = EmployeeDetails::where('company_id', company()->id)->findOrFail($id);

        if ($employeeDetails->probation_review_status !== 'pending') {
            return Reply::error('Only a pending request can be deleted — this one has already been reviewed.');
        }

        $employeeDetails->probation_review_status = null;
        $employeeDetails->probation_admin_remarks = null;
        $employeeDetails->probation_reviewed_by   = null;
        $employeeDetails->probation_reviewed_at   = null;
        $employeeDetails->probation_notified_at   = null;
        $employeeDetails->save();

        ProbationReply::where('employee_details_id', $employeeDetails->id)->delete();

        return Reply::success('Probation request deleted.');
    }

}
