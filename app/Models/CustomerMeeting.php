<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CustomerMeeting extends Model
{
    protected $table   = 'customer_meetings';
    protected $guarded = ['id'];

    protected $casts = [
        'meeting_date'      => 'date',
        'followup_date'     => 'date',
        'result_at'         => 'datetime',
        'reminder_sent_at'  => 'datetime',
        'ended_notified_at' => 'datetime',
        'auto_notes_enabled' => 'boolean',
        'meet_notes_fetched_at' => 'datetime',
        'meet_notes_checked_at' => 'datetime',
        'notes_last_upload_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function followupMeeting()
    {
        return $this->belongsTo(CustomerMeeting::class, 'followup_meeting_id');
    }

    /**
     * Meeting date and times are entered in the company's timezone.
     */
    public static function timezone(): string
    {
        return optional(Company::first())->timezone ?: config('app.timezone');
    }

    public function startsAt(): ?Carbon
    {
        return $this->meeting_date && $this->start_time
            ? Carbon::parse($this->meeting_date->format('Y-m-d') . ' ' . $this->start_time, self::timezone())
            : null;
    }

    public function endsAt(): ?Carbon
    {
        return $this->meeting_date && $this->end_time
            ? Carbon::parse($this->meeting_date->format('Y-m-d') . ' ' . $this->end_time, self::timezone())
            : null;
    }

    /**
     * lead_owner stores the employee's name (like customers.lead_owner).
     */
    public function leadOwnerUser(): ?User
    {
        return $this->lead_owner ? User::where('name', $this->lead_owner)->first() : null;
    }
}
