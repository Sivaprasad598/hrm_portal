<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One interview round with a candidate. It is also an Event (event_id), so it
 * shows in the interviewers' calendar, takes the event reminders and goes to
 * Google Calendar when that is connected.
 */
class RecruitInterview extends BaseModel
{

    use HasCompany;

    protected $table = 'recruit_interviews';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'completed_at' => 'datetime',
        'interviewer_ids' => 'array',
        'candidate_notified' => 'boolean',
        'max_marks' => 'float',
        'cut_off' => 'float',
        'marks' => 'float',
        'score' => 'float',
        'result_at' => 'datetime',
        'reminded_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public const MODES = [
        'in_person' => ['In person', 'fa-building'],
        'phone' => ['Phone', 'fa-phone'],
        'video' => ['Video call', 'fa-video'],
    ];

    public const STATUSES = [
        // a slot the system suggested, not yet confirmed by HR (no event, nobody told)
        'proposed' => ['Slot suggested', 'info'],
        'scheduled' => ['Scheduled', 'primary'],
        'completed' => ['Completed', 'success'],
        'no_show' => ['No-show', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    public const DURATIONS = [15, 30, 45, 60, 90, 120];

    /** A round's result (null: not decided yet) */
    public const RESULTS = [
        'passed' => ['Cleared', 'success'],
        'failed' => ['Not cleared', 'danger'],
        'waived' => ['Waived', 'secondary'],
    ];

    public function planRound(): BelongsTo
    {
        return $this->belongsTo(RecruitRound::class, 'recruit_round_id');
    }

    public function isTest(): bool
    {
        return $this->round_type === 'test';
    }

    public function resultBadge(): string
    {
        if (!$this->result) {
            return '';
        }

        [$label, $class] = self::RESULTS[$this->result] ?? [ucfirst($this->result), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(RecruitInterviewFeedback::class);
    }

    public function interviewers(): Collection
    {
        return User::withoutGlobalScopes()->whereIn('id', $this->interviewer_ids ?: [])->orderBy('name')->get(['id', 'name', 'email', 'image']);
    }

    public function endsAt()
    {
        return $this->starts_at->copy()->addMinutes($this->duration);
    }

    public function isInterviewer(?int $userId): bool
    {
        return $userId && in_array($userId, array_map('intval', $this->interviewer_ids ?: []), true);
    }

    /** Interviewers who have not handed in their scorecard. */
    public function missingFeedbackIds(): array
    {
        $given = $this->relationLoaded('feedback') ? $this->feedback->pluck('user_id')->all() : $this->feedback()->pluck('user_id')->all();

        return array_values(array_diff(array_map('intval', $this->interviewer_ids ?: []), array_map('intval', $given)));
    }

    public function statusBadge(): string
    {
        [$label, $class] = self::STATUSES[$this->status] ?? [ucfirst((string) $this->status), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode][0] ?? ucfirst((string) $this->mode);
    }

    /** "12-11-2026 10:30 AM – 11:00 AM", in the company's timezone. */
    public function whenText(): string
    {
        // company() is not set in the background run: the interview's own company then
        $company = company() ?: Company::find($this->company_id);
        $tz = $company?->timezone ?? config('app.timezone');
        $date = $company?->date_format ?? 'd-m-Y';
        $time = $company?->time_format ?? 'h:i A';

        return $this->starts_at->copy()->tz($tz)->format($date . ' ' . $time) . ' – ' . $this->endsAt()->tz($tz)->format($time);
    }

}
