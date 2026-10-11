# Leave Quota Changes — Implementer Checklist

Use this as the port/implement checklist in the target workspace.

**Suggested copy-first set (most UI + CF audit):** `LeaveHelper.php`, both quota blades, `EmployeeLeaveQuotaEvent` model + migration — then wire controllers and fix command accrual blocks.

---

## 1. Bug fix: monthly accrual when leave year ≠ calendar year

**Problem:** With `leaves_start_from = year_start` (e.g. April), employees who joined Jan–Mar of the current calendar year were accruing from joining month instead of leave-year start → inflated quotas (e.g. 3.96 instead of 2.64).

- [ ] Fix `calculateNoOfLeavesAlloted()` in `app/Console/Commands/RecalculateLeavesQuotas.php`
- [ ] Fix same logic in `app/Console/Commands/AnnualCarryForwardLeaves.php`
- [ ] Fix same logic in `app/Console/Commands/AnnualReimburseLeaves.php`
- [ ] Accrual start = `max(joining_date month-start, leave_year_start)`
- [ ] Pro-rate half-month when joining after the 15th using **leave-year start** (not Jan 1)
- [ ] Remove any stray `dd()` in joining-date monthly path
- [ ] **Verify:** Recalc for a monthly leave type on a company with April year-start and employees who joined before April

---

## 2. Quota UI redesign (unified ledger + admin override)

### Views / controllers / helpers
- [ ] `resources/views/employees/ajax/leaves_quota.blade.php` — drop hidden “Manage” table; keep date alert + remaining widget + include partial
- [ ] `resources/views/employees/leaves_quota.blade.php` — rewrite as ledger cards
- [ ] `app/Http/Controllers/LeavesQuotaController.php` — accept split fields on save
- [ ] `app/Http/Controllers/EmployeeController.php` — precompute display data
- [ ] `app/Http/Controllers/LeaveReportController.php` — precompute display data
- [ ] `app/Helper/LeaveHelper.php` — breakdown + display helpers
- [ ] `resources/lang/eng/modules.php` — new labels

### Card header badges
- [ ] Leave type name (color)
- [ ] Manual override (if `leave_type_impact = 1`)
- [ ] Accrual hint (`Monthly · X/mo` / `Yearly · X/yr`)
- [ ] Carry forward enabled (if policy supports CF)
- [ ] Paid / Unpaid (from `leave_types.paid`)
- [ ] Monthly limit (if set)

### Ledger (always visible)
- [ ] Accrued this period
- [ ] \+ Carried forward balance (if CF policy; show 0 when none)
- [ ] \= Total allocated
- [ ] − Total leaves taken
- [ ] \= Remaining
- [ ] Expiring on {date} + CF remaining (if unused CF has expiry)
- [ ] Non-expiring balance (current-year remaining)
- [ ] Over utilized (only if > 0)
- [ ] Expired unused leaves (when CF expired; with “Expired on {date}”)
- [ ] Unused leaves (lapsed) — lapse policy
- [ ] Unused leaves — other

### Admin adjust (`update_leaves_quota = all`)
- [ ] Collapsible “Adjust allocation” per card
- [ ] Period allocation (`period_leaves`)
- [ ] Carry forward balance (`carry_forward_leaves`) — only if CF policy
- [ ] Lock from policy updates (`leaveimpact`) — clearer replacement for old “Cannot Edit”
- [ ] Save via existing `employee-leaves.update` AJAX

### Save semantics (`LeavesQuotaController::update`)
- [ ] `no_of_leaves = period_leaves + carry_forward_leaves`
- [ ] Validate: both ≥ 0; total ≥ `leaves_used`; CF only if `unused_leave === 'carry forward'`
- [ ] Recalc `leaves_remaining` / `overutilised_leaves`
- [ ] Do **not** edit `no_of_leaves` as a single “total” that mixes period + CF without splitting

---

## 3. Carry-forward expiry audit trail

- [ ] Migration: `employee_leave_quota_events`
- [ ] Model: `App\Models\EmployeeLeaveQuotaEvent`
- [ ] Event type: `carry_forward_expired`
- [ ] Unique on `(user_id, leave_type_id, event_type, leave_year_start)`

| Column | Purpose |
|--------|---------|
| `amount` | Unused CF that lapsed |
| `carry_forward_total` | CF before expiry |
| `carry_forward_used` | CF used before expiry |
| `leave_year_start` | Leave year of that CF |
| `expired_on` | Configured expiry date |
| `processed_at` | When recorded |

### Write when
- [ ] Quota page loads and CF is expired but no event yet for this leave year
- [ ] `RecalculateLeavesQuotas` zeros expired CF via `LeaveHelper::processExpiredCarryForward()`

### UI
- [ ] “Lapsed carry forward history” under the card when events exist

---

## 4. Performance (quota page)

### `LeaveHelper`
- [ ] `buildQuotaContext()` — leave year start + “now” once per request
- [ ] `enrichQuotasForDisplay()` — one pass: prepare display + attach events
- [ ] Load events in one query; only `firstOrCreate` when missing for leave year
- [ ] Avoid nested write `$model->quotaDisplay['key'] = ...` (copy array → mutate → reassign)

### Controllers / Blade
- [ ] Eager-load `leaveTypes.leaveType`, `employeeDetail`, `roles` where needed
- [ ] Blade uses precomputed `$leavesQuota->quotaDisplay` only (no fallback recalc)

---

## 5. Language keys (`modules.leaves.*`)

- [ ] `lockFromPolicyUpdates`
- [ ] `lockFromPolicyUpdatesHelp`
- [ ] `periodAllocation`
- [ ] `carryForwardBalance`
- [ ] `adjustAllocation`
- [ ] `manualOverride`
- [ ] `totalAllocated`
- [ ] `monthlyAccrualHint` — `Monthly · :count/mo`
- [ ] `yearlyAccrualHint` — `Yearly · :count/yr`
- [ ] `carryForwardPolicy`
- [ ] `carryForwardLapsedHistory`
- [ ] `carryForwardLapsed`
- [ ] `carryForwardLapsedDetail`
- [ ] `carryForwardProcessedOn`
- [ ] `carryForwardExpiresOn` — optional: `:amount days expire on :date`
- [ ] `expiringOn` — `Expiring on :date`
- [ ] `expiredOn` — `Expired on :date`
- [ ] `nonExpiringBalance`
- [ ] `expiredUnusedLeaves`
- [ ] `lapsedUnusedLeaves`
- [ ] Paid/unpaid: reuse `app.paid` / `app.unpaid`

---

## 6. Behaviour notes

| Topic | Behaviour |
|-------|-----------|
| CF expiry | Only CF balance lapses; current-period accrual stays |
| Expiring under Remaining | Uses `carry_forward_remaining` + expiry date |
| Expired unused | Prefer event amount; else live unused CF before recalc zeros it |
| Over utilized on card | From DB `overutilised_leaves` (set by recalculate), not live math in Blade |
| Monthly + lapse | Overutilised often = current-month taken − monthly entitlement |
| Manual lock | `leave_type_impact = 1` → recalculate must keep existing `no_of_leaves` |

---

## 7. Implement order

1. [ ] Fix accrual start (year_start vs joining date) in all three commands
2. [ ] Add/extend `LeaveHelper` (context, breakdown, prepare display, CF expiry process/record)
3. [ ] Migration + `EmployeeLeaveQuotaEvent`
4. [ ] Update `LeavesQuotaController::update` for period + CF fields
5. [ ] Wire `EmployeeController` / `LeaveReportController` with `enrichQuotasForDisplay`
6. [ ] Rewrite quota Blade views + lang strings
7. [ ] Add paid/unpaid badge + remaining split / expired-unused labels
8. [ ] Run migrate; smoke-test: April year-start monthly type, CF with expiry, manual lock, unpaid type

---

## 8. Files checklist

| Path | Change | Done |
|------|--------|------|
| `app/Helper/LeaveHelper.php` | Core APIs | [x] |
| `app/Models/EmployeeLeaveQuotaEvent.php` | New | [x] |
| `database/migrations/2026_07_04_000000_create_employee_leave_quota_events_table.php` | New | [x] |
| `database/migrations/2026_07_03_000000_add_carry_forward_expiry_date_to_leave_types_table.php` | CF expiry column | [x] |
| `app/Console/Commands/RecalculateLeavesQuotas.php` | Accrual fix + CF expire process | [x] |
| `app/Console/Commands/AnnualCarryForwardLeaves.php` | Accrual fix | [x] |
| `app/Console/Commands/AnnualReimburseLeaves.php` | Accrual fix | [x] |
| `app/Http/Controllers/LeavesQuotaController.php` | Split save | [x] |
| `app/Http/Controllers/EmployeeController.php` | Enrich + eager load | [x] |
| `app/Http/Controllers/LeaveReportController.php` | Enrich | [x] |
| `resources/views/employees/leaves_quota.blade.php` | Ledger UI | [x] |
| `resources/views/employees/ajax/leaves_quota.blade.php` | Slim wrapper | [x] |
| `resources/lang/eng/modules.php` | Strings | [x] |

---

## Smoke-test matrix

| Scenario | Expected |
|----------|----------|
| April leave year + monthly type + join before April | Accrual from April, not Jan |
| CF with expiry date passed | CF zeros; event recorded; history shown |
| Manual lock (`leave_type_impact = 1`) | Recalc keeps existing `no_of_leaves` |
| Unpaid leave type | Unpaid badge shows |
| Admin adjust period + CF | `no_of_leaves` = sum; remaining/overutil updated |
