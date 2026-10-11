<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpFinalDeclaration extends Model
{
    use HasFactory;

    protected $table = 'emp_final_declarations';

    protected $fillable = [
        'user_id',
        'reported_duty_day',
        'reported_duty_month',
        'reported_duty_year',
        'candidate_signature',
        'hr_signature',
        'hr_comments',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
