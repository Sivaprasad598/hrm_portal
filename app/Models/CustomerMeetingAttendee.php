<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person invited to a lead meeting: the lead contact (is_lead), the lead owner or a lead team member (user_id).
 */
class CustomerMeetingAttendee extends Model
{

    protected $guarded = ['id'];

    protected $casts = ['is_lead' => 'boolean'];

    /** Same key the Invite checkboxes use: "lead" or "user-<id>". */
    public function getKeyNameAttribute(): string
    {
        return $this->is_lead ? 'lead' : 'user-' . $this->user_id;
    }

}
