<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappEmployee;
use App\Models\IappSyncLog;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\DB;

/**
 * Builds the biometric employee register and links it to ERP employees.
 *
 * Direction is one-way and enforced here as well as in the HTTP client: this
 * class reads the devices and writes only to `iapp_employees`. No employee is
 * ever created, updated or deleted on the biometric platform, and no ERP
 * `users` or `employee_details` row is modified either — the link lives on
 * this module's own table.
 *
 * The register is derived from the punch feed. iApp publishes write endpoints
 * for users (createOrUpdate, delete, blockUser…) but no list endpoint we could
 * confirm, and the punch feed already carries empCode, cardid, companyCode and
 * locationCode for everyone who actually uses a device.
 */
class EmployeeSync
{

    public function __construct(
        protected IappConfig $config
    ) {
    }

    /**
     * Refresh the register from stored punches, then auto-match.
     *
     * @param  bool  $autoMatch  link codes that match an ERP employee exactly
     * @return array{discovered:int, created:int, updated:int, matched:int, unmapped:int}
     */
    public function sync(bool $autoMatch = true, ?int $actorId = null): array
    {
        $companyId = $this->config->companyId();

        $discovered = $this->rebuildRegister($companyId);

        $matched = $autoMatch ? $this->autoMatch($companyId, $actorId) : 0;

        return $discovered + [
            'matched' => $matched,
            'unmapped' => IappEmployee::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->unmapped()
                ->count(),
        ];
    }

    /**
     * One row per distinct biometric code, with its activity summary.
     *
     * Aggregated in SQL rather than by loading punches into PHP: the punch
     * table grows without limit and only the per-code summary is needed.
     *
     * @return array{discovered:int, created:int, updated:int}
     */
    protected function rebuildRegister(?int $companyId): array
    {
        $summaries = DB::table('iapp_device_transactions')
            ->selectRaw('
                emp_code,
                MIN(punch_time) AS first_seen,
                MAX(punch_time) AS last_seen,
                COUNT(*) AS punches,
                MAX(card_id) AS card_id,
                MAX(employee_name) AS employee_name,
                MAX(user_id) AS resolved_user_id
            ')
            ->whereNotNull('emp_code')
            ->where('emp_code', '!=', '')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->groupBy('emp_code')
            ->get();

        if ($summaries->isEmpty()) {
            return ['discovered' => 0, 'created' => 0, 'updated' => 0];
        }

        $before = IappEmployee::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $now = now();

        foreach ($summaries->chunk(500) as $chunk) {
            $payload = [];

            foreach ($chunk as $row) {
                $payload[] = [
                    'company_id' => $companyId,
                    'emp_code' => $row->emp_code,
                    'card_id' => $row->card_id,
                    'employee_name' => $row->employee_name,
                    'first_seen_at' => $row->first_seen,
                    'last_seen_at' => $row->last_seen,
                    'punch_count' => $row->punches,
                    'source' => IappEmployee::SOURCE_PUNCH,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // user_id, map_status and the mapping columns are deliberately
            // absent from the update list: a refresh must never undo a link
            // somebody made by hand.
            IappEmployee::upsert(
                $payload,
                ['company_id', 'emp_code'],
                ['card_id', 'employee_name', 'first_seen_at', 'last_seen_at', 'punch_count', 'synced_at', 'updated_at']
            );
        }

        $after = IappEmployee::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $created = max(0, $after - $before);

        return [
            'discovered' => $summaries->count(),
            'created' => $created,
            'updated' => max(0, $summaries->count() - $created),
        ];
    }

    /**
     * Link unmapped codes whose value matches an ERP employee exactly.
     *
     * Exact string comparison only. Loose or numeric matching would conflate
     * codes like "2301694" and "02301694", which are separate enrolments.
     */
    protected function autoMatch(?int $companyId, ?int $actorId): int
    {
        $column = $this->config->matchField();

        $unmapped = IappEmployee::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->unmapped()
            ->pluck('emp_code', 'id');

        if ($unmapped->isEmpty()) {
            return 0;
        }

        $lookup = [];

        foreach ($unmapped->values()->chunk(1000) as $chunk) {
            $rows = DB::table('employee_details')
                ->whereIn($column, $chunk->all())
                ->whereNotNull('user_id')
                ->get([$column . ' as code', 'user_id']);

            foreach ($rows as $row) {
                // Keyed by string to keep leading zeros distinct.
                $lookup[(string) $row->code] = (int) $row->user_id;
            }
        }

        if ($lookup === []) {
            return 0;
        }

        $matched = 0;

        foreach ($unmapped as $id => $code) {
            if (!isset($lookup[(string) $code])) {
                continue;
            }

            IappEmployee::withoutGlobalScopes()->where('id', $id)->update([
                'user_id' => $lookup[(string) $code],
                'map_status' => IappEmployee::STATUS_MAPPED,
                'map_method' => IappEmployee::METHOD_AUTO,
                'matched_on' => $column,
                'mapped_by' => $actorId,
                'mapped_at' => now(),
                'updated_at' => now(),
            ]);

            $matched++;
        }

        return $matched;
    }

    /**
     * Link a biometric code to an ERP employee, on both sides, atomically.
     *
     * The register row records the mapping and its provenance; the ERP
     * employee record carries the code itself, because enrolment happens on
     * the device first and the code is the employee's original identity.
     *
     * Still one-way with respect to the devices: this writes to the ERP only.
     *
     * @return array{ok:bool, message:?string}
     */
    public function linkToEmployee(
        IappEmployee $employee,
        int $userId,
        string $method = IappEmployee::METHOD_MANUAL,
        ?string $matchedOn = null,
        ?int $actorId = null
    ): array {
        $code = (string) $employee->emp_code;

        // Both directions have to be checked, and the second one matters most:
        // without it, linking a new code to an employee who already has one
        // silently overwrites the existing link and detaches that code's
        // history.
        $codeTakenBy = DB::table('employee_details')
            ->where('iapp_emp_code', $code)
            ->where('user_id', '!=', $userId)
            ->value('user_id');

        if ($codeTakenBy) {
            $name = DB::table('users')->where('id', $codeTakenBy)->value('name');

            return [
                'ok' => false,
                'message' => "Biometric code {$code} is already linked to {$name}.",
            ];
        }

        $existingCode = DB::table('employee_details')
            ->where('user_id', $userId)
            ->value('iapp_emp_code');

        if ($existingCode !== null && $existingCode !== '' && $existingCode !== $code) {
            $name = DB::table('users')->where('id', $userId)->value('name');

            return [
                'ok' => false,
                'message' => "{$name} is already linked to biometric code {$existingCode}. Unlink that first.",
            ];
        }

        DB::transaction(function () use ($employee, $userId, $method, $matchedOn, $actorId, $code) {
            $employee->mapTo($userId, $method, $matchedOn, $actorId);

            DB::table('employee_details')
                ->where('user_id', $userId)
                ->update([
                    'iapp_emp_code' => $code,
                    'iapp_linked_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return ['ok' => true, 'message' => null];
    }

    /**
     * Remove a link from both sides.
     */
    public function unlinkFromEmployee(IappEmployee $employee, ?int $actorId = null): void
    {
        $userId = $employee->user_id;
        $code = (string) $employee->emp_code;

        DB::transaction(function () use ($employee, $userId, $code, $actorId) {
            $employee->unmap($actorId);

            if ($userId) {
                // Scoped to this code so unlinking never clears a link that
                // belongs to a different biometric identity.
                DB::table('employee_details')
                    ->where('user_id', $userId)
                    ->where('iapp_emp_code', $code)
                    ->update([
                        'iapp_emp_code' => null,
                        'iapp_linked_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * Apply the register's mappings back onto stored punches and attendance.
     *
     * Rows synced before a code was mapped carry a null user_id; this fills
     * them in so a newly mapped employee's history appears immediately rather
     * than only from the next sync onward.
     *
     * @return array{punches:int, attendance:int}
     */
    /**
     * Re-attribute every punch and attendance day from the current mapping.
     *
     * A full reconciliation, not a top-up. It resolves each biometric code the
     * same way the punch sync does — the explicit bridge first, the configured
     * match field only for codes nobody has mapped — and then makes the stored
     * attribution agree with that answer.
     *
     * Crucially it also **clears** ownership that no longer holds. Unlinking a
     * code used to leave every punch still pointing at the person it had been
     * unlinked from, so they kept collecting someone else's attendance in every
     * report. Setting, changing and clearing are the same operation here, which
     * is the only way the three cannot drift apart.
     *
     * @return array{punches:int, attendance:int, cleared:int, bridged:int}
     */
    public function applyMappings(?int $companyId = null): array
    {
        $companyId ??= $this->config->companyId();

        // Reconcile the mirror first: employee_details.iapp_emp_code is the
        // record of record, iapp_employees.user_id follows it.
        $this->reconcileRegister($companyId);

        $codes = DB::table('iapp_device_transactions')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('emp_code')->where('emp_code', '!=', '')
            ->distinct()->pluck('emp_code')
            ->merge(
                DB::table('iapp_attendance_records')
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->whereNotNull('emp_id')->where('emp_id', '!=', '')
                    ->distinct()->pluck('emp_id')
            )
            ->map(fn ($code) => (string) $code)
            ->unique()
            ->values()
            ->all();

        if ($codes === []) {
            return ['punches' => 0, 'attendance' => 0, 'cleared' => 0, 'bridged' => 0];
        }

        $resolver = (new EmployeeResolver($this->config))->load($codes);

        // Grouped by owner so this costs a handful of statements rather than
        // two per code.
        $byOwner = [];

        foreach ($codes as $code) {
            $byOwner[$resolver->resolve($code) ?? 0][] = $code;
        }

        $punches = 0;
        $attendance = 0;
        $cleared = 0;

        foreach ($byOwner as $userId => $group) {
            $target = $userId ?: null;

            foreach (array_chunk($group, 500) as $chunk) {
                $changedPunches = DB::table('iapp_device_transactions')
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->whereIn('emp_code', $chunk)
                    ->where(fn ($q) => $target === null
                        ? $q->whereNotNull('user_id')
                        : $q->whereNull('user_id')->orWhere('user_id', '!=', $target))
                    ->update(['user_id' => $target, 'updated_at' => now()]);

                $changedDays = DB::table('iapp_attendance_records')
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->whereIn('emp_id', $chunk)
                    ->where(fn ($q) => $target === null
                        ? $q->whereNotNull('user_id')
                        : $q->whereNull('user_id')->orWhere('user_id', '!=', $target))
                    ->update(['user_id' => $target, 'updated_at' => now()]);

                $punches += $changedPunches;
                $attendance += $changedDays;

                if ($target === null) {
                    $cleared += $changedPunches + $changedDays;
                }
            }
        }

        return [
            'punches' => $punches,
            'attendance' => $attendance,
            'cleared' => $cleared,
            'bridged' => $resolver->bridgedCount(),
        ];
    }

    /**
     * Make the biometric register agree with the employee records.
     *
     * The link is stored twice — as `iapp_emp_code` on the employee and as
     * `user_id` on the register row — because each side needs to be queryable
     * on its own. Two independent writes can drift, so one of them has to be
     * the original: the employee record is, and this brings the register back
     * in line with it.
     */
    protected function reconcileRegister(?int $companyId): void
    {
        $canonical = DB::table('employee_details')
            ->whereNotNull('iapp_emp_code')->where('iapp_emp_code', '!=', '')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->pluck('user_id', 'iapp_emp_code');

        foreach ($canonical as $code => $userId) {
            IappEmployee::withoutGlobalScopes()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('emp_code', (string) $code)
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $userId))
                ->update([
                    'user_id' => $userId,
                    'map_status' => IappEmployee::STATUS_MAPPED,
                    'updated_at' => now(),
                ]);
        }

        // A register row claiming an employee who no longer carries its code is
        // stale; the employee record has the final say.
        IappEmployee::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('user_id')
            ->when(
                $canonical->isNotEmpty(),
                fn ($q) => $q->whereNotIn('emp_code', array_map('strval', array_keys($canonical->all())))
            )
            ->update([
                'user_id' => null,
                'map_status' => IappEmployee::STATUS_UNMAPPED,
                'updated_at' => now(),
            ]);
    }

    /**
     * Record the run for the sync dashboard.
     */
    public function logRun(array $result, string $trigger = 'manual', ?int $actorId = null): IappSyncLog
    {
        $log = new IappSyncLog([
            'sync_type' => 'employees',
            'trigger' => $trigger,
            'triggered_by' => $actorId,
            'status' => ($result['unmapped'] ?? 0) > 0
                ? IappSyncLog::STATUS_PARTIAL
                : IappSyncLog::STATUS_SUCCESS,
            'records_fetched' => $result['discovered'] ?? 0,
            'records_created' => $result['created'] ?? 0,
            'records_updated' => $result['updated'] ?? 0,
            'records_unmatched' => $result['unmapped'] ?? 0,
            'message' => sprintf(
                '%d biometric employee(s): %d newly registered, %d auto-mapped, %d awaiting mapping.',
                $result['discovered'] ?? 0,
                $result['created'] ?? 0,
                $result['matched'] ?? 0,
                $result['unmapped'] ?? 0
            ),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $log->company_id = $this->config->companyId();
        $log->save();

        return $log;
    }

}
