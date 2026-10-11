<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProbationReply extends Model
{
    protected $fillable = ['employee_details_id', 'user_id', 'sender_type', 'message'];

    public function employeeDetail()
    {
        return $this->belongsTo(EmployeeDetails::class, 'employee_details_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
