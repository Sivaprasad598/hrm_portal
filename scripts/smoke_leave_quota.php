<?php

/**
 * Leave quota smoke tests — runs in a DB transaction that always rolls back.
 * Usage: php artisan smoke:leave-quota  OR  php scripts/smoke_leave_quota.php
 */

use App\Console\Commands\RecalculateLeavesQuotas;
use App\Helper\LeaveHelper;
use App\Models\Company;
use App\Models\EmployeeDetails;
use App\Models\EmployeeLeaveQuota;
use App\Models\EmployeeLeaveQuotaEvent;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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

    $company->year_starts_from = 4;
    $company->leaves_start_from = 'year_start';
    $company->save();
    $company->refresh();

    // --- Create disposable smoke fixtures ---
    $user = User::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'name' => 'Leave Quota Smoke',
        'email' => 'leave-quota-smoke-' . time() . '@example.test',
        'password' => bcrypt('secret'),
        'status' => 'active',
    ]);

    $now = Carbon::now($company->timezone);
    $joiningDate = Carbon::create($now->year, 2, 10, 9, 0, 0, $company->timezone);

    EmployeeDetails::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_id' => 'SMOKE-' . $user->id,
        'joining_date' => $joiningDate,
    ]);

    $user->load('employeeDetail');

    $monthlyType = LeaveType::create([
        'company_id' => $company->id,
        'type_name' => 'Smoke Monthly',
        'color' => '#336699',
        'no_of_leaves' => 0.66,
        'leavetype' => 'monthly',
        'paid' => 1,
        'monthly_limit' => 0,
        'unused_leave' => 'carry forward',
        'carry_forward_expiry_date' => Carbon::create($now->year, 6, 1)->toDateString(), // June 1 → already expired in July
        'allowed_probation' => 1,
        'allowed_notice' => 1,
        'gender' => json_encode(['male', 'female', 'others']),
        'marital_status' => json_encode(['married', 'unmarried']),
        'department' => null,
        'designation' => null,
        'role' => null,
    ]);

    // ========== 1) Accrual math ==========
    $cmd = app(RecalculateLeavesQuotas::class);
    $allotted = (float) $cmd->calculateNoOfLeavesAlloted($company, $joiningDate->copy(), $user, $monthlyType);

    // Apr–Jul inclusive = 4 months * 0.66 = 2.64 → rounding maps 0.64 fraction → 3.0
    $expectedMonths = 4;
    $raw = 0.66 * $expectedMonths; // 2.64
    $expectedRounded = 3.0;

    // Wrong (old) path would use Feb–Jul = 6 * 0.66 = 3.96 → round to 4.0
    $wrongRounded = 4.0;

    $assert(
        'monthly accrual uses leave-year start (not Feb)',
        $allotted === $expectedRounded && $allotted !== $wrongRounded,
        "allotted={$allotted}, expected~{$expectedRounded} (raw {$raw}), must not be {$wrongRounded}"
    );

    // ========== 2) CF expiry process + unique events ==========
    $quota = EmployeeLeaveQuota::create([
        'user_id' => $user->id,
        'leave_type_id' => $monthlyType->id,
        'no_of_leaves' => 5,
        'leaves_used' => 1,
        'leaves_remaining' => 4,
        'overutilised_leaves' => 0,
        'unused_leaves' => 0,
        'carry_forward_leaves' => 2,
        'carry_forward_applied' => 0,
        'leave_type_impact' => 0,
    ]);

    $context = LeaveHelper::buildQuotaContext($company, $user);
    $assert(
        'leave year starts in April',
        $context['leave_year_start']->month === 4,
        $context['leave_year_start']->toDateString()
    );

    $assert(
        'CF is expired (June expiry, now July)',
        LeaveHelper::isCarryForwardExpired($monthlyType, $company, $user) === true,
        'expiry=' . optional(LeaveHelper::getCarryForwardExpiryDate($monthlyType, $context['leave_year_start']))->toDateString()
    );

    $effectiveBeforeZero = LeaveHelper::effectiveLeavesRemaining($quota, $monthlyType, $company, $user);
    $assert(
        'effectiveLeavesRemaining excludes expired CF',
        abs($effectiveBeforeZero - 2.0) < 0.001, // remaining 4 - CF 2 = 2
        "effective={$effectiveBeforeZero}"
    );

    $cf1 = LeaveHelper::processExpiredCarryForward($quota, $monthlyType, $company, $user);
    $quota->refresh();
    $eventCount1 = EmployeeLeaveQuotaEvent::where('user_id', $user->id)
        ->where('leave_type_id', $monthlyType->id)
        ->where('event_type', EmployeeLeaveQuotaEvent::TYPE_CARRY_FORWARD_EXPIRED)
        ->count();

    $assert('processExpiredCarryForward returns 0', $cf1 === 0.0, "got={$cf1}");
    $assert('carry_forward_leaves zeroed', (float) $quota->carry_forward_leaves === 0.0, 'cf=' . $quota->carry_forward_leaves);
    $assert('expiry event created once', $eventCount1 === 1, "count={$eventCount1}");

    // Restore CF to try process again — unique should prevent duplicate for same leave year
    $quota->carry_forward_leaves = 2;
    $quota->save();
    LeaveHelper::processExpiredCarryForward($quota, $monthlyType, $company, $user);
    $eventCount2 = EmployeeLeaveQuotaEvent::where('user_id', $user->id)
        ->where('leave_type_id', $monthlyType->id)
        ->where('event_type', EmployeeLeaveQuotaEvent::TYPE_CARRY_FORWARD_EXPIRED)
        ->count();
    $assert('no duplicate expiry event same leave year', $eventCount2 === 1, "count={$eventCount2}");

    // ========== 3) enrichQuotasForDisplay (no nested mutation crash) ==========
    $quota->setRelation('leaveType', $monthlyType);
    $quotas = collect([$quota]);
    $threw = null;
    try {
        LeaveHelper::enrichQuotasForDisplay($quotas, $user, $company);
    } catch (Throwable $e) {
        $threw = $e->getMessage();
    }
    $assert('enrichQuotasForDisplay does not throw', $threw === null, (string) $threw);
    $display = $quota->quotaDisplay ?? null;
    $assert(
        'quotaDisplay prepared with breakdown',
        is_array($display) && isset($display['breakdown']['total_remaining']),
        json_encode(array_keys((array) $display))
    );

    // Nested mutate anti-pattern check: reassign works
    if (is_array($display)) {
        $copy = $display;
        $copy['smoke_marker'] = true;
        $quota->quotaDisplay = $copy;
        $assert('quotaDisplay reassignment works', isset($quota->quotaDisplay['smoke_marker']));
        unset($quota->quotaDisplay); // not a DB column — do not persist
    }

    // ========== 4) Admin save semantics ==========
    $quota->leaves_used = 1;
    $quota->carry_forward_leaves = 0; // already expired/zeroed path
    $quota->no_of_leaves = 0;
    LeaveHelper::recalculateRemainingAfterUpdate($quota, 4.5); // period 3 + cf 1.5 conceptually
    $quota->no_of_leaves = 4.5;
    $quota->carry_forward_leaves = 1.5;
    $quota->leave_type_impact = 1;
    $quota->save();
    $quota->refresh();

    $assert(
        'admin adjust persists period+CF total and lock',
        (float) $quota->no_of_leaves === 4.5
            && (float) $quota->carry_forward_leaves === 1.5
            && (int) $quota->leave_type_impact === 1
            && (float) $quota->leaves_remaining === 3.5,
        "nol={$quota->no_of_leaves} cf={$quota->carry_forward_leaves} rem={$quota->leaves_remaining} lock={$quota->leave_type_impact}"
    );

    // ========== 5) Recalculate command path for smoke user ==========
    // Unlock so recalc can recalculate period; CF present → expire again
    $quota->leave_type_impact = 0;
    $quota->carry_forward_leaves = 2;
    $quota->save();

    \Artisan::call('app:recalculate-leaves-quotas', [
        'company' => $company->id,
        'user' => $user->id,
        'leaveType' => $monthlyType->id,
    ]);

    $quota->refresh();
    $assert(
        'recalculate zeros expired CF',
        (float) $quota->carry_forward_leaves === 0.0,
        'cf=' . $quota->carry_forward_leaves . ' nol=' . $quota->no_of_leaves
    );
    $assert(
        'recalculate allotment from Apr (~3 after rounding)',
        abs((float) $quota->no_of_leaves - 3.0) < 0.001,
        'nol=' . $quota->no_of_leaves
    );

    $eventCount3 = EmployeeLeaveQuotaEvent::where('user_id', $user->id)
        ->where('leave_type_id', $monthlyType->id)
        ->count();
    $assert('recalc does not add duplicate expiry events', $eventCount3 === 1, "count={$eventCount3}");

} catch (Throwable $e) {
    $assert('smoke harness', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    DB::rollBack();
    echo PHP_EOL . 'Transaction rolled back (no lasting DB changes).' . PHP_EOL;
}

$failed = count(array_filter($results, fn ($r) => !$r['ok']));
$passed = count($results) - $failed;
echo PHP_EOL . "Summary: {$passed} passed, {$failed} failed, " . count($results) . " total" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
