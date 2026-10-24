<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A recipient of a Team Chat message: when they read it, and when they were emailed about it (still unread after
 * 15 minutes).
 */
class LeadChatMessageRead extends Model
{

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime', 'emailed_at' => 'datetime'];

    public function message()
    {
        return $this->belongsTo(LeadChatMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
