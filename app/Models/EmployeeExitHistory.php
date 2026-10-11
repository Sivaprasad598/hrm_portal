<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeExitHistory extends Model
{
    protected $fillable = [
        'exit_id', 'user_id', 'company_id', 'previous_status',
        'new_status', 'action', 'changed_by', 'notes',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function exit()
    {
        return $this->belongsTo(EmployeeExit::class, 'exit_id');
    }
}
