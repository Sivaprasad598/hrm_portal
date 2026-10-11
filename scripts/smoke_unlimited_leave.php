<?php

/**
 * Unlimited leave type smoke tests — DB transaction always rolls back.
 * Usage: php scripts/smoke_unlimited_leave.php
 */

use App\Helper\LeaveHelper;
use App\Models\Company;
use App\Models\EmployeeDetails;
use App\Models\EmployeeLeaveQuota;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Observers\LeaveObserver;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$results = [];
$assert = function (string $name, bool $ok, string $detail = '') use (&$results) {
    $results[] = ['ok' => $ok, 'name' => $name, 'detail' => $detail];
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $name . ($detail !== '' ? ' | ' . $detail : '') . PHP_EOL;
};

DB::beginTransaction();

try {
    $company = Company::find(1);
    if (!$company) {
        throw new RuntimeException('Company id=1 not found');
    }

    $user = User::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'name' => 'Unlimited Leave Smoke',
        'email' => 'unlimited-leave-smoke-' . time() . '@example.test',
        'password' => bcrypt('secret'),
        'status' => 'active',
    ]);

    $now = Carbon::now($company->timezone);
    EmployeeDetails::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_id' => 'USMOKE-' . $user->id,
        'joining_date' => Carbon::create($now->year, 1, 15, 9, 0, 0, $company->timezone),
    ]);
    $user->load('employeeDetail');

    // --- 1) Create unpaid unlimited type ---
    $unpaidType = LeaveType::create([
        'company_id' => $company->id,
        'type_name' => 'Smoke LOP Unpaid',
        'color' => '#aa0000',
        'leavetype' => 'unlimited',
        'no_of_leaves' => 0,
        'monthly_limit' => 0,
        'paid' => 0,
        'unused_leave' => 'lapse',
        'over_utilization' => 'not_allowed',
        'allowed_probation' => 1,
        'allowed_notice' => 1,
        'gender' => json_encode(['male', 'female', 'others']),
        'marital_status' => json_encode(['married', 'unmarried']),
        'department' => null,
        'designation' => null,
        'role' => null,
    ]);

    $assert('isUnlimited() true for unpaid type', $unpaidType->isUnlimited());
    $assert('unpaid unlimited keeps paid=0', (int) $unpaidType->paid === 0, 'paid=' . $unpaidType->paid);
    $assert(
        'unpaid unlimited stores safe defaults',
        (float) $unpaidType->no_of_leaves === 0.0
            && (float) $unpaidType->monthly_limit === 0.0
            && $unpaidType->unused_leave === 'lapse'
            && $unpaidType->over_utilization === 'not_allowed',
        "nol={$unpaidType->no_of_leaves} unused={$unpaidType->unused_leave} over={$unpaidType->over_utilization}"
    );

    // --- 2) Create paid unlimited type ---
    $paidType = LeaveType::create([
        'company_id' => $company->id,
        'type_name' => 'Smoke Unlimited Paid',
        'color' => '#00aa00',
        'leavetype' => 'unlimited',
        'no_of_leaves' => 0,
        'monthly_limit' => 0,
        'paid' => 1,
        'unused_leave' => 'lapse',
        'over_utilization' => 'not_allowed',
        'allowed_probation' => 1,
        'allowed_notice' => 1,
        'gender' => json_encode(['male', 'female', 'others']),
        'marital_status' => json_encode(['married', 'unmarried']),
        'department' => null,
        'designation' => null,
        'role' => null,
    ]);

    $assert('paid unlimited keeps paid=1', (int) $paidType->paid === 1, 'paid=' . $paidType->paid);

    // Seed quotas via recalculate
    Artisan::call('app:recalculate-leaves-quotas', [
        'company' => $company->id,
        'user' => $user->id,
        'leaveType' => $unpaidType->id,
    ]);
    Artisan::call('app:recalculate-leaves-quotas', [
        'company' => $company->id,
        'user' => $user->id,
        'leaveType' => $paidType->id,
    ]);

    $unpaidQuota = EmployeeLeaveQuota::where('user_id', $user->id)->where('leave_type_id', $unpaidType->id)->first();
    $paidQuota = EmployeeLeaveQuota::where('user_id', $user->id)->where('leave_type_id', $paidType->id)->first();

    $assert('recalc creates unpaid unlimited quota', $unpaidQuota !== null);
    $assert(
        'recalc unpaid quota zeros allocation/remaining/overutil',
        $unpaidQuota
            && (float) $unpaidQuota->no_of_leaves === 0.0
            && (float) $unpaidQuota->leaves_remaining === 0.0
            && (float) $unpaidQuota->overutilised_leaves === 0.0
            && (float) $unpaidQuota->leaves_used === 0.0,
        $unpaidQuota
            ? "nol={$unpaidQuota->no_of_leaves} rem={$unpaidQuota->leaves_remaining} over={$unpaidQuota->overutilised_leaves} used={$unpaidQuota->leaves_used}"
            : 'missing'
    );

    // --- 3) Apply 5+ leaves via observer path (simulates approved leaves) ---
    $observer = app(LeaveObserver::class);
    $uniqueId = Str::random(16);
    $leaveIds = [];

    for ($i = 0; $i < 6; $i++) {
        $leave = new Leave();
        $leave->company_id = $company->id;
        $leave->user_id = $user->id;
        $leave->leave_type_id = $unpaidType->id;
        $leave->unique_id = $uniqueId;
        $leave->duration = 'single';
        $leave->leave_date = $now->copy()->startOfMonth()->addDays($i)->toDateString();
        $leave->reason = 'smoke unlimited';
        $leave->status = 'approved';
        $leave->setRelation('type', $unpaidType);

        // Mimic LeaveObserver::saving without auth/session side effects
        if ($unpaidType->isUnlimited()) {
            $leave->paid = $unpaidType->paid;
            $leave->over_utilized = 0;
        }

        $leave->saveQuietly();
        $leaveIds[] = $leave->id;
    }

    $leaves = Leave::whereIn('id', $leaveIds)->get();
    $assert('applied 6 unlimited leaves', $leaves->count() === 6, 'count=' . $leaves->count());
    $assert(
        'all leaves over_utilized=0',
        $leaves->every(fn ($l) => (int) $l->over_utilized === 0),
        'over_utilized values=' . $leaves->pluck('over_utilized')->implode(',')
    );
    $assert(
        'all leaves paid matches leave type (unpaid)',
        $leaves->every(fn ($l) => (int) $l->paid === 0),
        'paid values=' . $leaves->pluck('paid')->implode(',')
    );

    // Paid type single leave via observer saving branch
    $paidLeave = new Leave();
    $paidLeave->company_id = $company->id;
    $paidLeave->user_id = $user->id;
    $paidLeave->leave_type_id = $paidType->id;
    $paidLeave->unique_id = Str::random(16);
    $paidLeave->duration = 'single';
    $paidLeave->leave_date = $now->copy()->addDays(10)->toDateString();
    $paidLeave->reason = 'smoke paid unlimited';
    $paidLeave->status = 'approved';
    $paidLeave->setRelation('type', $paidType);
    $paidLeave->paid = $paidType->paid;
    $paidLeave->over_utilized = 0;
    $paidLeave->saveQuietly();

    $assert('paid unlimited leave paid=1 and over_utilized=0', (int) $paidLeave->paid === 1 && (int) $paidLeave->over_utilized === 0);

    // --- 4) Recalculate after usage ---
    Artisan::call('app:recalculate-leaves-quotas', [
        'company' => $company->id,
        'user' => $user->id,
        'leaveType' => $unpaidType->id,
    ]);
    $unpaidQuota->refresh();

    $assert(
        'recalc after usage keeps overutilised=0 remaining=0',
        (float) $unpaidQuota->overutilised_leaves === 0.0
            && (float) $unpaidQuota->leaves_remaining === 0.0
            && (float) $unpaidQuota->no_of_leaves === 0.0
            && (float) $unpaidQuota->leaves_used === 6.0,
        "used={$unpaidQuota->leaves_used} rem={$unpaidQuota->leaves_remaining} over={$unpaidQuota->overutilised_leaves}"
    );

    // --- 5) Display helper ---
    $unpaidQuota->setRelation('leaveType', $unpaidType);
    $display = LeaveHelper::prepareQuotaDisplayData($unpaidQuota, $unpaidType, $company, $user);
    $assert(
        'display marks unlimited and overutilised=0',
        ($display['breakdown']['is_unlimited'] ?? false) === true
            && (float) ($display['breakdown']['overutilised'] ?? -1) === 0.0
            && $display['accrual_hint'] === __('modules.leaves.unlimitedLeaveHint')
            && $display['supports_carry_forward'] === false,
        json_encode([
            'is_unlimited' => $display['breakdown']['is_unlimited'] ?? null,
            'overutilised' => $display['breakdown']['overutilised'] ?? null,
            'hint' => $display['accrual_hint'] ?? null,
            'cf' => $display['supports_carry_forward'] ?? null,
        ])
    );

    LeaveHelper::recalculateRemainingAfterUpdate($unpaidQuota, 99);
    $assert(
        'recalculateRemainingAfterUpdate ignores allocation for unlimited',
        (float) $unpaidQuota->leaves_remaining === 0.0
            && (float) $unpaidQuota->overutilised_leaves === 0.0,
        "rem={$unpaidQuota->leaves_remaining} over={$unpaidQuota->overutilised_leaves}"
    );

    // --- 6) Monthly still enforces / isUnlimited false ---
    $monthlyType = LeaveType::create([
        'company_id' => $company->id,
        'type_name' => 'Smoke Monthly Control',
        'color' => '#0000aa',
        'leavetype' => 'monthly',
        'no_of_leaves' => 1,
        'monthly_limit' => 0,
        'paid' => 1,
        'unused_leave' => 'lapse',
        'over_utilization' => 'not_allowed',
        'allowed_probation' => 1,
        'allowed_notice' => 1,
        'gender' => json_encode(['male', 'female', 'others']),
        'marital_status' => json_encode(['married', 'unmarried']),
        'department' => null,
        'designation' => null,
        'role' => null,
    ]);
    $assert('monthly isUnlimited() false', $monthlyType->isUnlimited() === false);

    Artisan::call('app:recalculate-leaves-quotas', [
        'company' => $company->id,
        'user' => $user->id,
        'leaveType' => $monthlyType->id,
    ]);
    $monthlyQuota = EmployeeLeaveQuota::where('user_id', $user->id)->where('leave_type_id', $monthlyType->id)->first();
    $assert(
        'monthly quota still gets allotment > 0',
        $monthlyQuota && (float) $monthlyQuota->no_of_leaves > 0,
        $monthlyQuota ? 'nol=' . $monthlyQuota->no_of_leaves : 'missing'
    );

    // --- 7) Enum accepts unlimited ---
    $enumType = DB::select("SHOW COLUMNS FROM leave_types WHERE Field = 'leavetype'")[0]->Type ?? '';
    $assert(
        'DB enum includes unlimited',
        str_contains($enumType, 'unlimited'),
        (string) $enumType
    );

} catch (Throwable $e) {
    $assert('smoke harness', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    DB::rollBack();
    echo PHP_EOL . 'Transaction rolled back (no lasting DB changes).' . PHP_EOL;
}

$failed = count(array_filter($results, fn ($r) => !$r['ok']));
$passed = count($results) - $failed;
echo PHP_EOL . "Summary: {$passed} passed, {$failed} failed, " . count($results) . ' total' . PHP_EOL;
exit($failed > 0 ? 1 : 0);
