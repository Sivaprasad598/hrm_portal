<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeExitChecklist extends Model
{
    protected $fillable = [
        'exit_id', 'category', 'item_key', 'item_label',
        'status', 'completed_by', 'completed_date', 'remarks',
    ];

    protected $casts = [
        'completed_date' => 'date',
    ];

    public function exit()
    {
        return $this->belongsTo(EmployeeExit::class, 'exit_id');
    }

    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'hr_clearance'         => 'HR Clearance',
            'it_clearance'         => 'IT Clearance',
            'finance_clearance'    => 'Finance Clearance',
            'department_clearance' => 'Department Clearance',
            'management_clearance' => 'Management Clearance',
            default                => ucfirst(str_replace('_', ' ', $this->category)),
        };
    }
}
