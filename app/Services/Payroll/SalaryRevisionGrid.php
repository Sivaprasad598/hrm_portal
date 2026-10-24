<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Rows of the revision form: an employee as they are now, and what changes. */
class SalaryRevisionGrid
{

    public function __construct(private ?Carbon $appliesFrom = null)
    {
        $this->appliesFrom = $appliesFrom ?: SalaryRevisions::appliesFrom(now()->addDay());
    }

    public function row(int $userId, array $change = []): array
    {
        $e = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.id', $userId)->first(['u.id', 'u.name', 'ed.employee_id', 'd.name as designation', 't.team_name as department']);
        $before = (new SalaryRevisions())->before($userId, $this->appliesFrom);

        return array_merge([
            'user_id' => $userId, 'name' => $e->name ?? '?', 'code' => $e->employee_id ?? '', 'designation' => $e->designation ?? '',
            'department' => $e->department ?? '', 'old_ctc' => round($before['ctc']), 'old_basic_pct' => $before['basic'],
            'old_designation_id' => $before['designation_id'], 'old_department_id' => $before['department_id'],
            'new_ctc' => round($before['ctc']), 'new_basic_pct' => null, 'new_designation_id' => null, 'new_department_id' => null,
            'type' => 'appraisal', 'reason' => '',
        ], $change);
    }

    public function rows(array $userIds): array
    {
        return array_map(fn ($id) => $this->row((int) $id), array_values(array_unique(array_map('intval', $userIds))));
    }

    /** A saved batch, as the form shows it. */
    public function fromBatch(int $batchId): array
    {
        return DB::table('salary_revisions')->where('batch_id', $batchId)->get()->map(fn ($r) => $this->row((int) $r->user_id, [
            'type' => $r->type, 'new_ctc' => (float) $r->new_ctc, 'new_basic_pct' => $r->new_basic_pct !== null ? (float) $r->new_basic_pct : null,
            'new_designation_id' => $r->new_designation_id, 'new_department_id' => $r->new_department_id, 'reason' => (string) $r->reason,
        ]))->sortBy('name')->values()->all();
    }

}
