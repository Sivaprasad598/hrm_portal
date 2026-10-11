<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpPassportDetail extends Model
{
    use HasFactory;

    protected $table = 'emp_passport_details';

    protected $fillable = [
        'user_id',
        'passport_number',
        'passport_date_of_issue',
        'passport_place_of_issue',
        'passport_valid_up_to',
    ];

    protected $casts = [
        'passport_date_of_issue' => 'date',
        'passport_valid_up_to' => 'date',
    ];

    /**
     * Relationship: Employee passport details belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
