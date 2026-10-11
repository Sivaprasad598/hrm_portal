<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeExitInterview extends Model
{
    protected $fillable = [
        'exit_id', 'interview_date', 'interviewer_id', 'reason_for_leaving',
        'overall_experience', 'management_feedback', 'job_satisfaction',
        'compensation_feedback', 'work_environment_feedback', 'career_growth_feedback',
        'suggestions', 'rehire_eligibility', 'additional_comments',
    ];

    protected $casts = [
        'interview_date'     => 'date',
        'overall_experience' => 'integer',
        'job_satisfaction'   => 'integer',
    ];

    public function exit()
    {
        return $this->belongsTo(EmployeeExit::class, 'exit_id');
    }

    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
