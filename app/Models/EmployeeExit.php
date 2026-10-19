<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeExit extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'resignation_id', 'exit_type', 'exit_reason',
        'resignation_date', 'notice_period_days', 'last_working_date',
        'exit_interview_status', 'asset_clearance_status', 'finance_clearance_status',
        'hr_clearance_status', 'final_settlement_status', 'offboarding_date',
        'remarks', 'exit_initiated_by', 'exit_initiated_date', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'resignation_date'    => 'date',
        'last_working_date'   => 'date',
        'offboarding_date'    => 'date',
        'exit_initiated_date' => 'date',
    ];

    /**
     * The exit still in progress for this employee, if any: not off-boarded
     * yet, and not from an employment that has since ended in a rejoin.
     */
    public static function openFor(int $userId, ?int $exceptResignationId = null): ?self
    {
        return static::where('user_id', $userId)
            ->whereNull('offboarding_date')
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('employee_exits', 'closed_by_rejoin_id'),
                fn ($q) => $q->whereNull('closed_by_rejoin_id'))
            ->when($exceptResignationId, fn ($q) => $q->where(fn ($w) => $w->whereNull('resignation_id')
                ->orWhere('resignation_id', '<>', $exceptResignationId)))
            ->orderByDesc('id')
            ->first();
    }

    public function employee()
    {
        // Includes people who have left: an exit is mostly about them.
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScope(\App\Scopes\ActiveScope::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'exit_initiated_by');
    }

    public function checklists()
    {
        return $this->hasMany(EmployeeExitChecklist::class, 'exit_id');
    }

    public function interview()
    {
        return $this->hasOne(EmployeeExitInterview::class, 'exit_id');
    }

    public function histories()
    {
        return $this->hasMany(EmployeeExitHistory::class, 'exit_id');
    }

    public function getChecklistProgressAttribute(): int
    {
        $total = $this->checklists()->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->checklists()->whereIn('status', ['completed', 'not_applicable'])->count();
        return (int) round(($done / $total) * 100);
    }

    public static function checklistTemplate(): array
    {
        return [
            'hr_clearance' => [
                ['key' => 'exit_docs_signed',       'label' => 'Exit documents signed'],
                ['key' => 'experience_letter',      'label' => 'Experience letter issued'],
                ['key' => 'noc_issued',             'label' => 'NOC issued'],
                ['key' => 'full_final_initiated',   'label' => 'Full & Final settlement initiated'],
            ],
            'it_clearance' => [
                ['key' => 'laptop_returned',        'label' => 'Laptop / Hardware returned'],
                ['key' => 'system_access_revoked',  'label' => 'System access revoked'],
                ['key' => 'email_deactivated',      'label' => 'Email account deactivated'],
                ['key' => 'vpn_access_removed',     'label' => 'VPN / remote access removed'],
            ],
            'finance_clearance' => [
                ['key' => 'expense_claims_cleared', 'label' => 'Pending expense claims cleared'],
                ['key' => 'salary_advance_recovered','label' => 'Salary advance recovered'],
                ['key' => 'pending_salary_paid',    'label' => 'Pending salary credited'],
            ],
            'department_clearance' => [
                ['key' => 'knowledge_transfer',     'label' => 'Knowledge transfer completed'],
                ['key' => 'projects_handed_over',   'label' => 'Projects / tasks handed over'],
                ['key' => 'docs_returned',          'label' => 'Company documents returned'],
            ],
            'management_clearance' => [
                ['key' => 'relieving_letter',       'label' => 'Relieving letter issued'],
                ['key' => 'feedback_recorded',      'label' => 'Manager feedback recorded'],
            ],
        ];
    }
}
