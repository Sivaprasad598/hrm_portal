<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpJoiningChecklist extends Model
{
    use HasFactory;

    protected $table = 'emp_joining_checklists';

    protected $fillable = [
        'user_id',
        'educational_testimonials',
        'experience_letter',
        'relieving_letter',
        'two_references',
        'salary_certificate',
        'bank_statement',
        'passport_copy',
        'form_16',
        'passport_photographs',
        'offer_letter',
        'resume_copy',
        'nda',
    ];

    protected $casts = [
        'educational_testimonials' => 'boolean',
        'experience_letter' => 'boolean',
        'relieving_letter' => 'boolean',
        'two_references' => 'boolean',
        'salary_certificate' => 'boolean',
        'bank_statement' => 'boolean',
        'passport_copy' => 'boolean',
        'form_16' => 'boolean',
        'passport_photographs' => 'boolean',
        'offer_letter' => 'boolean',
        'resume_copy' => 'boolean',
        'nda' => 'boolean',
    ];

    /**
     * Relationship: Employee joining checklist belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
