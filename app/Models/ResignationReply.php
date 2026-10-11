<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResignationReply extends Model
{
    protected $fillable = ['resignation_id', 'user_id', 'sender_type', 'message'];

    public function resignation()
    {
        return $this->belongsTo(Resignation::class, 'resignation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
