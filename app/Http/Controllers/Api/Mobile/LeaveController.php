<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Helper\Files;
use App\Helper\LeaveHelper;
use App\Models\Attendance;
use App\Models\EmployeeLeaveQuota;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveFile;
use App\Models\LeaveType;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Services\Leaves\EarnedLeavePolicy;
use App\Services\Leaves\LeaveApprovals;
use App\Services\Leaves\LeaveRules;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The signed-in employee's leave: the types they may take with what is left,
 * applying, and what they applied for with where it stands.
 *
 * Applying follows the website's own-leave rules (LeaveController::store as
 * an employee uses it): the type's conditions, Earned Leave's years of
 * service, holidays left out, the type's Rules (notice, how far back, most
 * days, the document), the balance and the monthly limit. It always goes in
 * as Pending and starts the approval steps (LeaveApprovals), with the same
 * e-mails and bells.
 *
 * One difference: the website quietly rejects an earlier leave on the same
 * day to make room for the new one; the app refuses instead and says which
 * day is taken, so nothing approved is undone from a phone.
 *
 * An application of several days is one unique_id; the list shows it once.
 */
class LeaveController extends MobileController
{
    /** The leave types this employee may apply for, with the balance. */
    public function types()
    {
        $user = $this->employee();
        $quotas = $this->allowedQuotas($user);
        LeaveHelper::enrichQuotasForDisplay($quotas, $user, company());

        $pending = Leave::where('user_id', $user->id)->where('status', 'pending')->get(['leave_type_id', 'duration'])
            ->groupBy('leave_type_id')->map(fn ($rows) => $this->days($rows));

        return $this->ok($quotas->map(function (EmployeeLeaveQuota $quota) use ($pending) {
            $type = $quota->leaveType;
            $breakdown = $quota->quotaDisplay['breakdown'] ?? [];

            return [
                'id' => $type->id,
                'name' => $type->type_name,
                'code' => $type->code ?? null,
                'color' => $type->color,
                'paid' => (bool) $type->paid,
                'unlimited' => $type->isUnlimited(),
                'allowed' => (float) $quota->no_of_leaves,
                'used' => (float) $quota->leaves_used,
                'remaining' => $type->isUnlimited() ? null : (float) ($breakdown['total_remaining'] ?? $quota->leaves_remaining),
                'pending' => (float) ($pending[$type->id] ?? 0),
                'monthly_limit' => (float) $type->monthly_limit ?: null,
                'document' => [
                    'required' => $type->doc_required ?? 'none',         // none | always | over_days
                    'when_more_than_days' => $type->doc_required === 'over_days' ? (float) $type->doc_min_days : null,
                    'label' => ($type->doc_required ?? 'none') !== 'none' ? LeaveRules::docName($type) : null,
                    'may_follow_within_days' => $type->doc_later_days,
                ],
                'rules' => LeaveRules::describe($type),
            ];
        })->values());
    }

    /** The applications, newest first; one entry per application, however many days. */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected',
            'year' => 'nullable|integer|min:2000|max:2100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $leaves = Leave::with('type:id,type_name,color')->withCount('files')
            ->where('user_id', $this->me()->id)
            ->when($request->year, fn ($q, $year) => $q->whereYear('leave_date', $year))
            ->orderByDesc('leave_date')->orderByDesc('id')
            ->get();

        $applications = $leaves->groupBy(fn ($l) => $l->unique_id ?: 'id-' . $l->id)
            ->map(fn ($group) => $this->application($group))
            ->when($request->status, fn ($c, $status) => $c->where('status', $status))
            ->sortByDesc('applied_at')->values();

        $perPage = (int) ($request->per_page ?: 20);
        $page = (int) ($request->page ?: 1);

        return $this->ok([
            'applications' => $applications->forPage($page, $perPage)->values(),
            'counts' => [
                'pending' => $applications->where('status', 'pending')->count(),
                'approved' => $applications->where('status', 'approved')->count(),
                'rejected' => $applications->where('status', 'rejected')->count(),
            ],
            'page' => $page,
            'per_page' => $perPage,
            'total' => $applications->count(),
            'last_page' => max(1, (int) ceil($applications->count() / $perPage)),
        ]);
    }

    /** One application: its days, the approval history and the documents. */
    public function show(int $leave)
    {
        $one = Leave::where('user_id', $this->me()->id)->findOrFail($leave);
        $group = Leave::with('type:id,type_name,color', 'files')->withCount('files')
            ->whereIn('id', LeaveApprovals::group($one)->pluck('id'))->orderBy('leave_date')->get();

        return $this->ok($this->application($group, true));
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|integer',
            'duration' => 'required|in:single,multiple,first_half,second_half',
            'leave_date' => 'required_unless:duration,multiple|nullable|date_format:Y-m-d',
            'from_date' => 'required_if:duration,multiple|nullable|date_format:Y-m-d',
            'to_date' => 'required_if:duration,multiple|nullable|date_format:Y-m-d|after_or_equal:from_date',
            'reason' => 'required|string|max:1000',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'mark_leave_anyway' => 'nullable|boolean',
        ]);

        if (!in_array(user()->permission('add_leave'), ['all', 'added'], true)) {
            return $this->fail('You cannot apply for leave. Please contact HR.', 403);
        }

        $company = company();
        $employee = $this->employee();
        $leaveType = LeaveType::find($request->leave_type_id);

        if (!$leaveType || !$this->allowedQuotas($employee)->contains('leave_type_id', $leaveType->id)) {
            return $this->fail(__('messages.leaveTypeNotAllowed'), 422, ['leave_type_id' => [__('messages.leaveTypeNotAllowed')]]);
        }

        $earnedLeave = new EarnedLeavePolicy($employee->company_id);

        if ($earnedLeave->isEarnedLeave((int) $leaveType->id) && !$earnedLeave->canUse((int) $employee->id)) {
            return $this->fail($earnedLeave->refusal((int) $employee->id), 422);
        }

        $halfDay = in_array($request->duration, ['first_half', 'second_half'], true);
        $duration = $halfDay ? 'half day' : $request->duration;

        if ($request->duration === 'multiple') {
            $from = Carbon::createFromFormat('Y-m-d', $request->from_date)->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', $request->to_date)->startOfDay();

            if ($from->diffInDays($to) > 366) {
                return $this->fail('Choose at most a year of days.', 422);
            }

            $dates = collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->copy()->startOfDay());
        } else {
            $dates = collect([Carbon::createFromFormat('Y-m-d', $request->leave_date)->startOfDay()]);
        }

        // Holidays are not leave days.
        $holidays = Holiday::whereIn('date', $dates->map->format('Y-m-d'))->applicableTo($employee->employeeDetail)->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());
        $dates = $dates->reject(fn ($d) => $holidays->contains($d->toDateString()))->values();

        if ($dates->isEmpty()) {
            return $this->fail(__('messages.noLeaveApplyForSelectedDate'), 422);
        }

        $days = $halfDay ? 0.5 * $dates->count() : (float) $dates->count();
        $files = $request->file('files', []);

        if ($refusal = LeaveRules::refusal($leaveType, $dates->all(), $days, count($files) > 0, now($company->timezone)->startOfDay())) {
            return $this->fail($refusal, 422);
        }

        $dateStrings = $dates->map->format('Y-m-d');
        $taken = Leave::whereIn('status', ['approved', 'pending'])->where('user_id', $employee->id)->whereIn('leave_date', $dateStrings)->get()
            // A half day leaves the other half free.
            ->reject(fn ($l) => $halfDay && $l->duration === 'half day' && $l->half_day_type !== $request->duration);

        if ($taken->isNotEmpty()) {
            $list = $taken->map(fn ($l) => Carbon::parse($l->leave_date)->format('d M Y') . ' (' . $l->status . ')')->unique()->implode(', ');

            return $this->fail('You already have leave on ' . $list . '. Cancel it on the website or ask HR before applying again.', 409);
        }

        // A full day off on a day already worked: ask first, as the website does.
        if (!$halfDay && !$request->boolean('mark_leave_anyway') && ($worked = $this->workedOn($employee->id, $dateStrings))) {
            return $this->fail('Your attendance is already marked on ' . $worked . '. Send mark_leave_anyway = 1 to apply anyway.', 409, ['mark_leave_anyway' => ['Attendance is marked on ' . $worked . '.']]);
        }

        if ($refusal = $this->balanceRefusal($employee, $leaveType, $dates, $days)) {
            return $this->fail($refusal, 422);
        }

        // The observer's notifications read the request the way the website's form sends it.
        if ($duration === 'multiple') {
            request()->merge(['multi_date' => $dates->first()->format($company->date_format) . ',' . $dates->last()->format($company->date_format)]);
            session(['leaves_duration' => 'multiple']);
        }

        $uniqueId = Str::random(16);
        $leaveIds = [];

        DB::transaction(function () use ($dates, $employee, $leaveType, $duration, $halfDay, $request, $uniqueId, &$leaveIds) {
            foreach ($dates as $date) {
                $leave = new Leave();
                $leave->user_id = $employee->id;
                $leave->unique_id = $uniqueId;
                $leave->leave_type_id = $leaveType->id;
                $leave->duration = $duration;
                $leave->paid = $leaveType->paid;
                $leave->half_day_type = $halfDay ? $request->duration : null;
                $leave->leave_date = $date->format('Y-m-d');
                $leave->reason = $request->reason;
                $leave->status = 'pending';
                $leave->save();

                $leaveIds[] = $leave->id;
                session()->forget('leaves_duration');
            }
        });

        session()->forget('leaves_duration');

        foreach ($leaveIds as $leaveId) {
            // One copy per day, as the website stores them.
            foreach ($files as $file) {
                $leaveFile = new LeaveFile();
                $leaveFile->leave_id = $leaveId;
                $leaveFile->user_id = $employee->id;
                $leaveFile->filename = $file->getClientOriginalName();
                $leaveFile->hashname = Files::uploadLocalOrS3($file, LeaveFile::FILE_PATH . '/' . $leaveId);
                $leaveFile->size = $file->getSize();
                $leaveFile->save();
            }
        }

        LeaveApprovals::start($leaveIds);

        return $this->ok($this->show($leaveIds[0])->getData(true)['data'], __('messages.leaveApplySuccess'), 201);
    }

    // ------------------------------------------------------------------ helpers

    private function employee(): User
    {
        return User::withoutGlobalScope(ActiveScope::class)->with('roles', 'employeeDetail', 'employee')->findOrFail($this->me()->id);
    }

    /** Quotas whose type the employee may take now, as My Leaves lists them. */
    private function allowedQuotas(User $user): Collection
    {
        return $user->leaveTypes()->with('leaveType')->get()
            ->filter(fn ($q) => $q->leaveType && $q->leaveType->deleted_at === null && $q->leaveType->leaveTypeCondition($q->leaveType, $user))
            ->values();
    }

    /** Over the balance or the monthly limit, for a type that may not go over - the website's checks. */
    private function balanceRefusal(User $employee, LeaveType $type, Collection $dates, float $applying): ?string
    {
        if ($type->over_utilization !== 'not_allowed' || $type->isUnlimited()) {
            return null;
        }

        $quota = EmployeeLeaveQuota::whereUserId($employee->id)->whereLeaveTypeId($type->id)->first();
        $remaining = $quota ? LeaveHelper::effectiveLeavesRemaining($quota, $type, company(), $employee) : 0;
        $month = [$dates->first()->copy()->startOfMonth(), $dates->first()->copy()->endOfMonth()];
        $pendingThisMonth = Leave::where('user_id', $employee->id)->where('status', 'pending')->where('leave_type_id', $type->id)
            ->whereBetween('leave_date', $month)->count();

        if ($remaining - $pendingThisMonth < $applying) {
            return __('messages.leaveLimitError');
        }

        if ($type->monthly_limit > 0) {
            $thisMonth = Leave::where('leave_type_id', $type->id)->where('user_id', $employee->id)->whereBetween('leave_date', $month)
                ->whereIn('status', ['approved', 'pending'])->get(['duration']);

            if ($this->days($thisMonth) + $applying > (float) $type->monthly_limit) {
                return __('messages.monthlyLeaveLimitError');
            }
        }

        return null;
    }

    /** The first of these local dates with a clock-in, as "27 Oct 2026", or null. */
    private function workedOn(int $userId, Collection $dates): ?string
    {
        $tz = $this->tz();
        $first = Carbon::parse($dates->min(), $tz)->startOfDay()->timezone(config('app.timezone'));
        $last = Carbon::parse($dates->max(), $tz)->endOfDay()->timezone(config('app.timezone'));

        $day = Attendance::where('user_id', $userId)->whereBetween('clock_in_time', [$first, $last])->pluck('clock_in_time')
            ->map(fn ($t) => Carbon::parse($t)->timezone($tz)->toDateString())
            ->first(fn ($d) => $dates->contains($d));

        return $day ? Carbon::parse($day)->format('d M Y') : null;
    }

    private function days(Collection $leaves): float
    {
        return (float) $leaves->sum(fn ($l) => $l->duration === 'half day' ? 0.5 : 1);
    }

    /** An application's days as one entry. */
    private function application(Collection $group, bool $full = false): array
    {
        $group = $group->sortBy('leave_date')->values();
        $first = $group->first();
        $statuses = $group->pluck('status')->unique();
        // Days of one application move together; if HR decided them one by one, the open ones lead.
        $status = $statuses->count() === 1 ? $statuses->first() : ($statuses->contains('pending') ? 'pending' : ($statuses->contains('approved') ? 'approved' : 'rejected'));
        $waitingAt = $status === 'pending' && $first->approval_step && $first->approval_step !== LeaveApprovals::DONE ? $first->approval_step : null;

        $out = [
            'id' => $first->id,
            'unique_id' => $first->unique_id,
            'leave_type' => ['id' => $first->leave_type_id, 'name' => $first->type?->type_name, 'color' => $first->type?->color],
            'duration' => $first->duration,                  // single | multiple | half day
            'half_day_type' => $first->half_day_type,        // first_half | second_half | null
            'from_date' => Carbon::parse($first->leave_date)->toDateString(),
            'to_date' => Carbon::parse($group->last()->leave_date)->toDateString(),
            'days' => $this->days($group->whereIn('status', $status === 'rejected' ? ['rejected'] : ['pending', 'approved'])),
            'paid' => (bool) $first->paid,
            'reason' => $first->reason,
            'status' => $status,
            'status_label' => match (true) {
                $waitingAt !== null => 'Pending with ' . LeaveApprovals::who($waitingAt),
                $status === 'pending' => 'Pending',
                default => ucfirst($status),
            },
            'waiting_with' => $waitingAt ? LeaveApprovals::label($waitingAt) : null,
            'reject_reason' => $group->firstWhere('status', 'rejected')?->reject_reason,
            'approve_note' => $group->firstWhere('status', 'approved')?->approve_reason,
            'decided_at' => $status !== 'pending' ? $this->time($group->max('approved_at') ?: $group->max('updated_at')) : null,
            'applied_at' => $this->time($first->created_at),
            'files_count' => (int) $group->max('files_count'),
        ];

        if ($full) {
            $out['dates'] = $group->map(fn ($l) => [
                'id' => $l->id,
                'date' => Carbon::parse($l->leave_date)->toDateString(),
                'status' => $l->status,
                'half_day_type' => $l->half_day_type,
            ])->values();
            $out['history'] = LeaveApprovals::trail($first)->unique(fn ($r) => $r->step . '|' . $r->action . '|' . $r->user_id . '|' . $r->created_at)
                ->map(fn ($r) => [
                    'step' => LeaveApprovals::label($r->step) ?: $r->step,
                    'action' => $r->action,
                    'by' => $r->user_name,
                    'note' => $r->note,
                    'at' => $this->time($r->created_at),
                ])->values();
            $out['files'] = $group->flatMap->files->unique(fn ($f) => $f->filename . '|' . $f->size)->map(fn ($f) => [
                'name' => $f->filename,
                'url' => $f->file_url,
                'size' => (int) $f->size,
            ])->values();
        }

        return $out;
    }
}
