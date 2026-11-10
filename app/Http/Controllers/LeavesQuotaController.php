<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Exports\LeaveQuotaReportExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use App\Helper\Reply;
use App\Helper\LeaveHelper;
use App\Models\LeaveType;
use App\Scopes\ActiveScope;
use Illuminate\Http\Request;
use App\Models\EmployeeLeaveQuota;
use Illuminate\Support\Facades\Artisan;

class LeavesQuotaController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.leaves';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('leaves', $this->user->modules));
            return $next($request);
        });
    }

    public function update(Request $request, $id)
    {
        $type = EmployeeLeaveQuota::with('leaveType')->findOrFail($id);
        $leaveType = $type->leaveType;
        $supportsCarryForward = $leaveType && $leaveType->unused_leave === 'carry forward';

        $periodLeaves = (float) ($request->period_leaves ?? $request->leaves ?? 0);
        $carryForwardLeaves = $supportsCarryForward
            ? (float) ($request->carry_forward_leaves ?? 0)
            : 0;

        if ($periodLeaves < 0 || $carryForwardLeaves < 0) {
            return Reply::error('messages.employeeLeaveQuota');
        }

        $totalLeaves = $periodLeaves + $carryForwardLeaves;

        if ($totalLeaves < $type->leaves_used) {
            return Reply::error('messages.employeeLeaveQuota');
        }

        $type->carry_forward_leaves = $carryForwardLeaves;
        $type->no_of_leaves = $totalLeaves;
        $type->leave_type_impact = $request->leaveimpact;

        LeaveHelper::recalculateRemainingAfterUpdate($type, $totalLeaves);

        $type->save();

        session()->forget('user');

        return Reply::success(__('messages.leaveTypeAdded'));
    }

    public function employeeLeaveTypes($userId)
    {
        if ($userId != 0) {
            $employee = User::withoutGlobalScope(ActiveScope::class)->with(['roles', 'leaveTypes'])->findOrFail($userId);
            $options = '';
            
            foreach($employee->leaveTypes as $leavesQuota) {
                $hasLeave = ($leavesQuota->leaveType && $leavesQuota->leaveType->deleted_at == null) ? $leavesQuota->leaveType->leaveTypeCondition($leavesQuota->leaveType, $employee) : false;

                if ($hasLeave) {
                    $labelSuffix = $leavesQuota->leaveType->isUnlimited()
                        ? __('app.unlimitedLeaveType')
                        : $leavesQuota->leaves_remaining;
                    $options .= '<option value="' . $leavesQuota->leave_type_id . '"> ' .  $leavesQuota->leaveType->type_name .' (' . $labelSuffix . ') </option>'; /** @phpstan-ignore-line */
                }
            }

            $usage = $this->monthUsage($employee);
        }
        else {
            $leaveQuotas = LeaveType::all();

            $options = '';

            foreach ($leaveQuotas as $leaveQuota) {
                $labelSuffix = $leaveQuota->isUnlimited()
                    ? __('app.unlimitedLeaveType')
                    : $leaveQuota->no_of_leaves;
                $options .= '<option value="' . $leaveQuota->id . '"> ' .  $leaveQuota->type_name . ' (' . $labelSuffix . ') </option>'; /** @phpstan-ignore-line */
            }
        }

        return Reply::dataOnly(['status' => 'success', 'data' => $options, 'usage' => $usage ?? '']);
    }

    /**
     * This month's use of each leave type that has a monthly limit, for the
     * New Leave form: "Casual 1/1 used - limit reached · Sick 0/1 used"
     * (QA TC-0006). Approved and pending leave count; a half day is 0.5.
     */
    private function monthUsage(User $employee): string
    {
        $from = now(company()->timezone)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $used = \Illuminate\Support\Facades\DB::table('leaves')
            ->where('user_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereBetween('leave_date', [$from->toDateString(), $to->toDateString()])
            ->get(['leave_type_id', 'duration'])
            ->groupBy('leave_type_id')
            ->map(fn ($rows) => $rows->sum(fn ($l) => $l->duration === 'half day' ? 0.5 : 1));

        $parts = [];

        foreach ($employee->leaveTypes as $quota) {
            $type = $quota->leaveType;

            if (!$type || $type->deleted_at || $type->isUnlimited()) {
                continue;
            }

            $fmt = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.');
            $limit = $type->leavetype === 'monthly' ? (float) $type->no_of_leaves : (float) $type->monthly_limit;

            if ($limit > 0) {
                // A monthly limit: this month's use against it
                $n = (float) ($used[$type->id] ?? 0);
                $parts[] = e($type->type_name) . ' <strong>' . $fmt($n) . '/' . $fmt($limit) . '</strong> used this month'
                    . ($n >= $limit ? ' <span class="text-danger">- monthly limit reached</span>' : '');
            } elseif ((float) $quota->no_of_leaves > 0) {
                // Yearly with no monthly limit: the year's use against the quota
                $n = (float) $quota->leaves_used;
                $parts[] = e($type->type_name) . ' <strong>' . $fmt($n) . '/' . $fmt((float) $quota->no_of_leaves) . '</strong> used this year'
                    . ($n >= (float) $quota->no_of_leaves ? ' <span class="text-danger">- none left</span>' : '');
            }
        }

        return $parts ? implode(' &middot; ', $parts) : '';
    }

    public function exportAllLeaveQuota($id, $year, $month)
    {
        abort_403(!canDataTableExport());
        $name = __('app.leaveQuotaReport') . '-' . Carbon::createFromDate($year, $month, 1)->startOfDay()->translatedFormat('F-Y');
        return Excel::download(new LeaveQuotaReportExport($id, $year, $month), $name . '.xlsx');
    }

}
