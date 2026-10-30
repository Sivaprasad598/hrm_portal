<?php

namespace App\Services\Employee;

use App\Helper\LeaveHelper;
use App\Models\User;
use App\Services\Payroll\AttendanceDays;
use App\Services\Payroll\NetPay;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The employee's own dashboard: only their figures - this salary period's
 * attendance counted as payroll counts it, leave balances, the last payslip,
 * the assets they hold, their leave requests, upcoming holidays, their OT bank
 * - and, of colleagues, only names for this week's birthdays and work
 * anniversaries.
 *
 * Who gets it: everyone but the Super Admin, Admin and HR (self()), who keep
 * the company-wide dashboard. Each part is worked out on its own, so one that
 * fails leaves the rest of the page standing.
 */
class PersonalDashboard
{
    /** Roles that keep the company-wide dashboard. */
    const COMPANY_VIEW_ROLES = ['admin', 'manager_admin', 'hr'];

    public static function applies(): bool
    {
        return user() && !in_array('client', user_roles()) && !array_intersect(self::COMPANY_VIEW_ROLES, user_roles());
    }

    public function build(User $user): array
    {
        $tz = company()?->timezone ?: 'Asia/Kolkata';
        $today = now($tz)->startOfDay();
        $out = ['today' => $today];

        foreach (['attendance', 'week', 'leave', 'payslip', 'assets', 'requests', 'holidays', 'otBank', 'celebrations'] as $part) {
            try {
                $out[$part] = $this->$part($user, $today, $tz);
            } catch (\Throwable $e) {
                report($e);
                $out[$part] = null;
            }
        }

        return $out;
    }

    /** This salary period so far, as payroll counts it. */
    protected function attendance(User $user, Carbon $today, string $tz): array
    {
        $cycle = PayPeriod::containing($today);
        $count = (new AttendanceDays($tz))->forRange((int) $user->id, $cycle->start, $cycle->end);
        $b = $count['breakdown'];

        return [
            'cycle' => $cycle,
            'paid' => (float) $count['days_present'],
            'counted' => (int) $count['working_days'],
            'lop' => round(max(0, $count['working_days'] - $count['days_present']), 2),
            'late' => (int) ($b['late_marks'] ?? 0) + (int) ($b['late_half_days'] ?? 0),
            'worked' => (float) ($b['worked'] ?? 0),
        ];
    }

    /** Monday to Sunday of this week, a status for each day. */
    protected function week(User $user, Carbon $today, string $tz): array
    {
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);

        return (new AttendanceDays($tz))->forRange((int) $user->id, $monday, $monday->copy()->addDays(6))['days'];
    }

    /** Remaining leave by type, as the employee's Leaves Balance tab shows it. */
    protected function leave(User $user): array
    {
        $quotas = [];

        foreach ($user->leaveTypes as $quota) {
            // Unpaid leave (LOP) has no balance to show.
            $unpaid = strtoupper(trim((string) $quota->leaveType?->code)) === 'LOP' || (string) $quota->leaveType?->paid === '0';

            if ($quota->leaveType && $quota->leaveType->deleted_at === null && !$unpaid
                && $quota->leaveType->leaveTypeCondition($quota->leaveType, $user)) {
                $quotas[] = $quota;
            }
        }

        LeaveHelper::enrichQuotasForDisplay($quotas, $user, company());

        return collect($quotas)->map(fn ($q) => [
            'name' => $q->leaveType->type_name,
            'code' => $q->leaveType->code ?: strtoupper(substr($q->leaveType->type_name, 0, 2)),
            'remaining' => (float) ($q->quotaDisplay['breakdown']['total_remaining'] ?? 0),
        ])->values()->all();
    }

    /** The latest issued payslip (approved or paid), as My Payslips shows it. */
    protected function payslip(User $user): ?array
    {
        $p = DB::table('payslips')->where('user_id', $user->id)->where('status', 'active')
            ->whereIn('approval_status', ['approved', 'paid'])->whereNull('deleted_at')
            ->orderByDesc('period_start')->orderByDesc('id')->first();

        if (!$p) {
            return null;
        }

        $extras = DB::table('payslip_components')->where('payslip_id', $p->id)->get(['kind', 'amount']);

        return [
            'id' => $p->id,
            'month' => $p->period,
            'net' => NetPay::takeHome($p, (float) $extras->where('kind', 'earning')->sum('amount'), (float) $extras->where('kind', 'deduction')->sum('amount'))
                + (float) $p->leave_encashment,
        ];
    }

    protected function assets(User $user): array
    {
        return \App\Services\Assets\AssetFlow::heldBy((int) $user->id)
            ->map(fn ($a) => $a->asset?->name)->filter()->values()->all();
    }

    /** The latest leave requests, with where each one stands. */
    protected function requests(User $user): array
    {
        return DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->where('l.user_id', $user->id)
            ->orderByDesc('l.created_at')->orderByDesc('l.leave_date')->limit(3)
            ->get(['l.id', 'l.leave_date', 'l.duration', 'l.status', 't.type_name',
                DB::raw(\Illuminate\Support\Facades\Schema::hasColumn('leaves', 'approval_step') ? 'l.approval_step' : 'NULL as approval_step')])
            ->map(fn ($l) => [
                'id' => $l->id, 'date' => Carbon::parse($l->leave_date), 'type' => $l->type_name,
                'half' => $l->duration === 'half day', 'status' => $l->status,
                'waiting_on' => $l->status === 'pending' && $l->approval_step
                    ? (\App\Services\Leaves\LeaveApprovals::STEPS[$l->approval_step] ?? null) : null,
            ])->all();
    }

    protected function holidays(User $user, Carbon $today): array
    {
        return DB::table('holidays')->whereDate('date', '>=', $today->toDateString())
            ->orderBy('date')->limit(3)->get(['date', 'occassion'])
            ->map(fn ($h) => ['date' => Carbon::parse($h->date), 'name' => $h->occassion])->all();
    }

    /** OT bank hours, when the OT bank is in use. */
    protected function otBank(User $user): ?float
    {
        $bank = new \App\Services\Payroll\OtBank();

        return $bank->enabled((int) $user->company_id) ? round($bank->balance((int) $user->id), 2) : null;
    }

    /** Colleagues' birthdays and work anniversaries in the coming 7 days: names only. */
    protected function celebrations(User $user, Carbon $today): array
    {
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $days[$today->copy()->addDays($i)->format('m-d')] = $i;
        }

        $out = [];
        $rows = DB::table('employee_details as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('u.status', 'active')
            ->where(fn ($q) => $q->whereIn(DB::raw("DATE_FORMAT(e.date_of_birth, '%m-%d')"), array_keys($days))
                ->orWhereIn(DB::raw("DATE_FORMAT(e.joining_date, '%m-%d')"), array_keys($days)))
            ->get(['u.id', 'u.name', 'e.date_of_birth', 'e.joining_date']);

        foreach ($rows as $row) {
            if (\App\Services\Access\SystemAccounts::is((int) $row->id)) {
                continue;
            }

            if ($row->date_of_birth && isset($days[Carbon::parse($row->date_of_birth)->format('m-d')])) {
                $in = $days[Carbon::parse($row->date_of_birth)->format('m-d')];
                $out[] = ['name' => $row->name, 'what' => 'Birthday', 'icon' => '🎂', 'in' => $in, 'me' => (int) $row->id === (int) $user->id];
            }

            if ($row->joining_date) {
                $joined = Carbon::parse($row->joining_date);
                $mmdd = $joined->format('m-d');
                $years = $today->year - $joined->year + ($mmdd < $today->format('m-d') ? 1 : 0);

                if (isset($days[$mmdd]) && $years >= 1) {
                    $out[] = ['name' => $row->name, 'what' => $years . ' ' . ($years === 1 ? 'year' : 'years') . ' at work', 'icon' => '🎉',
                        'in' => $days[$mmdd], 'me' => (int) $row->id === (int) $user->id];
                }
            }
        }

        usort($out, fn ($a, $b) => [$a['in'], $a['name']] <=> [$b['in'], $b['name']]);

        return array_slice($out, 0, 8);
    }
}
