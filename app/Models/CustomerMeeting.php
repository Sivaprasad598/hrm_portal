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

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /** Client (users table, client role) the meeting is with, when made from the Clients page. */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id')->withoutGlobalScopes()->with('clientDetails');
    }

    /**
     * Who the meeting is with: the client, the lead, or the customer for meetings made before leads were used.
     * Same shape for all: company_name, name, email, company_id.
     */
    public function getContactAttribute(): object
    {
        if ($this->client_id && $this->client) {
            return (object) [
                'company_name' => ($this->client->clientDetails->company_name ?? null) ?: $this->client->name,
                'name'         => $this->client->name,
                'email'        => $this->client->email,
                'company_id'   => $this->client->company_id,
            ];
        }

        if ($this->lead_id && $this->lead) {
            return (object) [
                'company_name' => $this->lead->company_name ?: $this->lead->client_name,
                'name'         => $this->lead->client_name,
                'email'        => $this->lead->client_email,
                'company_id'   => $this->lead->company_id,
            ];
        }

        return (object) [
            'company_name' => $this->customer->company_name ?? null,
            'name'         => $this->customer->customer_name ?? null,
            'email'        => $this->customer->email ?? null,
            'company_id'   => $this->customer->company_id ?? null,
        ];
    }

    /** People invited to the meeting (lead meetings: chosen from the lead's team). */
    public function attendees()
    {
        return $this->hasMany(CustomerMeetingAttendee::class, 'customer_meeting_id')->orderByDesc('is_lead')->orderBy('id');
    }

    /** The Lead Team Meeting record (its own ID, LTM-0001) when the meeting was made from a lead's Lead Team tab. */
    public function leadTeamMeeting()
    {
        return $this->hasOne(LeadTeamMeeting::class, 'customer_meeting_id');
    }

    /** meeting_role values: a lead meeting, a client meeting, or a Lead Team meeting (made from the Lead Team tab). */
    public const ROLE_LEAD = 'lead';
    public const ROLE_CLIENT = 'client';
    public const ROLE_LEAD_TEAM = 'lead_team_meeting';

    public const ROLE_LABELS = [self::ROLE_LEAD => 'Lead', self::ROLE_CLIENT => 'Client', self::ROLE_LEAD_TEAM => 'Lead Team'];

    /** A meeting made from a lead's Lead Team tab (role lead_team_meeting); it has its own Lead Team Meeting record. */
    public function isLeadTeamMeeting(): bool
    {
        return $this->meeting_role === self::ROLE_LEAD_TEAM && $this->lead_id && !$this->client_id;
    }

    /** Chip on the meeting cards: Lead, Client or Lead Team. */
    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->meeting_role] ?? ($this->client_id ? 'Client' : 'Lead');
    }

    /** Lead meetings, client meetings or Lead Team meetings. */
    public function scopeRole($query, string $role)
    {
        return $query->where('meeting_role', $role);
    }

    /** Project (lead_projects) the meeting is about. */
    public function leadProject()
    {
        return $this->belongsTo(LeadProject::class, 'lead_project_id');
    }

    /**
     * Meetings grouped by project for the collapsible "Project: …" sections: keyed by lead_project_id
     * (0 = no project), sorted by project name, meetings without a project last.
     */
    public static function groupByProject(\Illuminate\Support\Collection $meetings): \Illuminate\Support\Collection
    {
        return $meetings->groupBy(fn ($meeting) => $meeting->lead_project_id ?: 0)
            ->sortBy(fn ($group, $projectId) => $projectId ? '0' . strtolower($group->first()->leadProject->name ?? '') : '1');
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
