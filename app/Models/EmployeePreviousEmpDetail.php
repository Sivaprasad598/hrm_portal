<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePreviousEmpDetail extends Model
{
    use HasFactory;

    protected $table = 'employee_previous_emp_details';

    protected $fillable = [
        'user_id',
        'total_work_experience',
        'previous_company_name',
        'company_location',
        'last_held_designation',
        'date_of_joining',
        'date_of_leaving',
        'employee_id',
        'pf_account_number',
        'esi_ip_number',
        'personal_account_number',
        'ifsc_code',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_leaving' => 'date',
    ];

    /**
     * Relationship: An employee previous experience belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
