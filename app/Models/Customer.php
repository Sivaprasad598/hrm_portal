<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table   = 'customers';
    protected $guarded = ['id'];

    public function meetings()
    {
        return $this->hasMany(CustomerMeeting::class)->orderByDesc('created_at');
    }
}
