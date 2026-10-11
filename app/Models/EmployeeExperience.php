<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeExperience extends Model
{
    protected $fillable = [
        'user_id', 'serial_no', 'company_name',
        'company_location', 'designation', 'joining_date', 'leaving_date',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'leaving_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
