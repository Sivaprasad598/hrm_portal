<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * October 2026 payroll (cycle 21 Sep - 20 Oct 2026) as reviewed against the HR calculation sheet OCT-2026, on 26 Nov
 * 2026. Reproduces on this database, by employee code, what was done and checked on the review copy:
 *
 *  1. The employees not in "Data For HRMS.xlsx" (exactly the 145 codes in the data file) are deleted, with every row
 *     that belongs to them. The biometric device register keeps their device codes (link cleared).
 *  2. Payroll settings: late comings not deducted; PF / ESI reduced for unpaid days.
 *  3. The 203 employees' salary on record (CTC, basic %, monthly TDS), PF / ESI / PT, salary paid by, attendance basis.
 *  4. October LOP days as per the sheet (Payroll Run -> Upload LOP days).
 *  5. October payslips: each draft is replaced with the reviewed one (approved / paid payslips are left alone), with the
 *     sheet's "Add On's / OT" as pay element lines; the employee's other unissued lines for October stay on it.
 *  6. The salary revision "October 2026 as per calculation sheet; system salary from November" (5 employees), waiting
 *     for the CFO - the CFO and CEO approve it in Salary Revisions.
 *
 * Data: database/migrations/data/2026_11_26_october_2026_payroll.json. Safety: nothing is done unless at least 190
 * of the sheet's 204 employee codes are on this database. Take a database backup before running it.
 * Running it again does no harm: the deletes find nobody, the values are set again, the drafts are made again.
 */
return new class extends Migration {

    private const DATA = __DIR__ . '/data/2026_11_26_october_2026_payroll.json';

    private const ADDON_LABEL = "Add On's / OT (OCT-2026 sheet)";

    public function up(): void
    {
        if (!is_file(self::DATA) || !Schema::hasTable('payslips')) {
            return;
        }

        $data = json_decode(file_get_contents(self::DATA), true);
        $users = DB::table('employee_details')->pluck('user_id', 'employee_id')->mapWithKeys(fn ($id, $code) => [strtoupper((string) $code) => (int) $id]);

        $present = count(array_filter($data['keep_codes'], fn ($c) => $users->has($c)));
        if ($present < 190) {
            throw new RuntimeException("October 2026 payroll: only {$present} of the sheet's employees are on this database - stopped, nothing changed.");
        }

        $this->deleteEmployees($data['deleted_codes'], $users->all());
        $users = DB::table('employee_details')->pluck('user_id', 'employee_id')->mapWithKeys(fn ($id, $code) => [strtoupper((string) $code) => (int) $id])->all();
        $company = (int) DB::table('companies')->orderBy('id')->value('id');

        DB::transaction(function () use ($data, $users, $company) {
            $this->settings($company);
            $this->employees($data['employees'], $users);
            $this->lopDays($data['lop_days'], $data['cycle'], $users, $company);
            $this->payslips($data, $users, $company);
            $this->revision($data['revision'] ?? null, $users, $company);
        });

        \App\Services\Payroll\Statutory::forget();
    }

    public function down(): void
    {
        // A data migration: put back from the backup taken before it ran.
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function deleteEmployees(array $codes, array $users): void
    {
        $ids = array_values(array_filter(array_map(fn ($c) => $users[strtoupper($c)] ?? null, $codes)));
        $ids = array_values(array_diff($ids, \App\Services\Access\SystemAccounts::notCounted()));

        if (!$ids) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ([['iapp_employees', 'user_id'], ['candidates', 'user_id']] as [$table, $column]) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)->whereIn($column, $ids)->update([$column => null]);
                }
            }
            if (Schema::hasTable('universal_search')) {
                DB::table('universal_search')->where('module_type', 'employee')->whereIn('searchable_id', $ids)->delete();
            }

            $tables = collect(DB::select("SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'user_id'"))
                ->pluck('t')->reject(fn ($t) => in_array($t, ['users', 'ticket_settings_for_agents', 'lead_setting', 'candidates', 'iapp_employees'], true));

            foreach ($tables as $table) {
                DB::table($table)->whereIn('user_id', $ids)->delete();
            }

            DB::table('users')->whereIn('id', $ids)->delete();

            // What the database links would have done (cascade / set null) for rows left pointing at them.
            if (class_exists(\App\Services\Maintenance\DataCleaner::class)) {
                (new \App\Services\Maintenance\DataCleaner())->sweepOrphans();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function settings(int $company): void
    {
        if (Schema::hasColumn('payroll_options', 'deduct_late_comings')) {
            DB::table('payroll_options')->where('company_id', $company)->exists()
                ? DB::table('payroll_options')->where('company_id', $company)->update(['deduct_late_comings' => 0, 'updated_at' => now()])
                : DB::table('payroll_options')->insert(['company_id' => $company, 'deduct_late_comings' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        $latest = DB::table('payroll_calculations')->orderByDesc('id')->value('id');
        if ($latest && Schema::hasColumn('payroll_calculations', 'prorate_statutory')) {
            DB::table('payroll_calculations')->where('id', $latest)->update(['prorate_statutory' => 1]);
        }
    }

    private function employees(array $rows, array $users): void
    {
        $flags = Schema::hasColumn('employee_details', 'pf_applicable');

        foreach ($rows as $e) {
            $id = $users[strtoupper($e['code'])] ?? null;
            if (!$id) {
                continue;
            }

            DB::table('users')->where('id', $id)->update([
                'employee_ctc' => $e['employee_ctc'], 'basic_for_payroll' => $e['basic_for_payroll'], 'monthly_tds' => $e['monthly_tds'],
            ]);

            $detail = ['attendance_exempt' => (int) $e['attendance_exempt']];
            if ($flags) {
                $detail += ['pf_applicable' => (int) $e['pf_applicable'], 'esi_applicable' => (int) $e['esi_applicable'],
                    'pt_applicable' => (int) $e['pt_applicable'], 'salary_paid_by' => $e['salary_paid_by'] ?: 'bank'];
            }
            DB::table('employee_details')->where('user_id', $id)->update($detail);
        }
    }

    private function lopDays(array $rows, array $cycle, array $users, int $company): void
    {
        if (!Schema::hasTable('payroll_lop_days')) {
            return;
        }

        foreach ($rows as $l) {
            $id = $users[strtoupper($l['code'])] ?? null;
            if ($id) {
                DB::table('payroll_lop_days')->updateOrInsert(['user_id' => $id, 'period_start' => $cycle['start']], [
                    'company_id' => $company, 'period_end' => $cycle['end'], 'lop_days' => $l['lop_days'], 'note' => $l['note'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function payslips(array $data, array $users, int $company): void
    {
        $cycle = $data['cycle'];
        $addons = collect($data['addons'])->groupBy(fn ($a) => strtoupper($a['code']));
        $overtimeType = DB::table('pay_element_types')->where('name', 'Overtime')->value('id');
        $columns = array_flip(Schema::getColumnListing('payslips'));

        foreach ($data['payslips'] as $slip) {
            $id = $users[strtoupper($slip['code'])] ?? null;
            if (!$id) {
                continue;
            }

            $current = DB::table('payslips')->where('user_id', $id)->where('period', $cycle['label'])->where('status', 'active')->whereNull('deleted_at')->get(['id', 'approval_status']);

            // An approved or paid October payslip is left as it is.
            if ($current->contains(fn ($p) => $p->approval_status !== 'draft')) {
                continue;
            }

            // The drafts go; their lines are released for the new payslip.
            if ($current->isNotEmpty()) {
                DB::table('payslip_components')->whereIn('payslip_id', $current->pluck('id'))->update(['payslip_id' => null]);
                DB::table('payslips')->whereIn('id', $current->pluck('id'))->update(['deleted_at' => now()]);
            }

            $row = array_intersect_key($slip, $columns);
            unset($row['code']);
            $row['user_id'] = $id;
            $row['company_id'] = $company;
            $row['payslip_number'] = $this->nextNumber($cycle['label']);
            $row['created_at'] = now();
            $newId = DB::table('payslips')->insertGetId($row);

            // The sheet's add-ons / OT, made again (never twice); the employee's other unissued October lines go on it too.
            DB::table('payslip_components')->where('user_id', $id)->where('period_start', $cycle['start'])->where('label', self::ADDON_LABEL)->delete();
            foreach ($addons->get(strtoupper($slip['code']), []) as $a) {
                DB::table('payslip_components')->insert([
                    'company_id' => $company, 'user_id' => $id, 'period_start' => $cycle['start'], 'kind' => $a['kind'], 'label' => $a['label'],
                    'amount' => $a['amount'], 'element_type_id' => $overtimeType, 'note' => $a['note'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('payslip_components')->where('user_id', $id)->where('period_start', $cycle['start'])->whereNull('payslip_id')->update(['payslip_id' => $newId]);
        }
    }

    private function revision(?array $revision, array $users, int $company): void
    {
        if (!$revision || !Schema::hasTable('salary_revision_batches')
            || DB::table('salary_revision_batches')->where('name', $revision['name'])->exists()) {
            return;
        }

        $rows = array_values(array_filter($revision['rows'], fn ($r) => isset($users[strtoupper($r['code'])])));
        if (!$rows) {
            return;
        }

        $preparer = DB::table('users as u')->join('role_user as ru', 'ru.user_id', '=', 'u.id')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('r.name', ['admin', 'manager_admin', 'hr'])->where('u.status', 'active')->orderBy('u.id')->value('u.id');

        $batch = DB::table('salary_revision_batches')->insertGetId([
            'company_id' => $company, 'name' => $revision['name'], 'kind' => $revision['kind'], 'effective_date' => $revision['effective_date'],
            'applies_from' => $revision['applies_from'], 'note' => $revision['note'], 'status' => 'pending_cfo',
            'created_by' => $preparer, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($rows as $r) {
            $id = $users[strtoupper($r['code'])];
            $detail = DB::table('employee_details')->where('user_id', $id)->first(['designation_id', 'department_id']);
            DB::table('salary_revisions')->insert([
                'company_id' => $company, 'batch_id' => $batch, 'user_id' => $id, 'type' => $r['type'],
                'old_ctc' => $r['old_ctc'], 'new_ctc' => $r['new_ctc'], 'old_basic_pct' => $r['old_basic_pct'], 'new_basic_pct' => $r['new_basic_pct'],
                'old_designation_id' => $detail->designation_id ?? null, 'old_department_id' => $detail->department_id ?? null,
                'reason' => $r['reason'], 'created_by' => $preparer, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['created', 'submitted'] as $action) {
            DB::table('salary_revision_logs')->insert(['batch_id' => $batch, 'action' => $action, 'user_id' => $preparer, 'note' => null, 'created_at' => now()]);
        }
    }

    /** As PayslipWriter numbers payslips: AROW/<financial year>/<running number>. */
    private function nextNumber(string $period): string
    {
        $date = strtotime('first day of ' . str_replace(' - ', ' ', $period)) ?: time();
        $start = (int) date('n', $date) >= 4 ? (int) date('Y', $date) : (int) date('Y', $date) - 1;
        $label = $start . '-' . substr((string) ($start + 1), -2);
        $highest = (int) DB::table('payslips')->where('payslip_number', 'like', 'AROW/' . $label . '/%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(payslip_number, '/', -1) AS UNSIGNED)), 0) AS n")->value('n');

        return sprintf('AROW/%s/%06d', $label, $highest + 1);
    }

};
