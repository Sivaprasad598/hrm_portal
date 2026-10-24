<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Lead Team Meeting: its own record and ID (LTM-0001), made when a meeting is created from a lead's Lead Team tab.
 * lead_id links it to the lead, customer_meeting_id to the scheduled meeting (date, link, invites, recording).
 * The project qualification notes (Validate Fit … Decision Makers) are filled in after the call
 * (LeadMeetingQualifier, or edited by hand).
 */
class LeadTeamMeeting extends Model
{

    public const FITS = ['high' => 'High Fit', 'medium' => 'Medium Fit', 'low' => 'Low Fit'];

    protected $guarded = ['id'];

    protected $casts = [
        'service_requirements' => 'array',
        'decision_makers' => 'array',
        'team_capabilities' => 'array',
        'analysed_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(CustomerMeeting::class, 'customer_meeting_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /** Shown on the card: LTM-0001. */
    public function getReferenceAttribute(): string
    {
        return 'LTM-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Whether the qualification notes have been filled in (by the transcript or by hand). */
    public function hasNotes(): bool
    {
        return $this->source !== null;
    }

    public function fitLabel(): ?string
    {
        return self::FITS[$this->validate_fit] ?? null;
    }

}
