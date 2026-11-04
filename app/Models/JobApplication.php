<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A candidate applying to one position (or to none yet: "Unassigned", as
 * when an email names no opening). The stage is moved only through
 * App\Services\Recruit\Pipeline, which writes the timeline.
 */
class JobApplication extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'stage_changed_at' => 'datetime',
    ];

    /** stage => [label, badge colour, icon]; in pipeline order. */
    public const STAGES = [
        'new' => ['New', 'secondary', 'fa-inbox'],
        'shortlisted' => ['Shortlisted', 'info', 'fa-star'],
        'interview' => ['Interview', 'primary', 'fa-comments'],
        'selected' => ['Selected', 'success', 'fa-thumbs-up'],
        'offered' => ['Offered', 'warning', 'fa-file-signature'],
        'offer_accepted' => ['Offer Accepted', 'success', 'fa-handshake'],
        'joined' => ['Joined', 'dark', 'fa-user-check'],
        'rejected' => ['Rejected', 'danger', 'fa-times-circle'],
        'on_hold' => ['On Hold', 'warning', 'fa-pause-circle'],
        'withdrawn' => ['Withdrawn', 'secondary', 'fa-sign-out-alt'],
    ];

    /** The stages still moving. */
    public const ACTIVE = ['new', 'shortlisted', 'interview', 'selected', 'offered', 'offer_accepted'];

    /** The stages that end an application; each needs a reason. */
    public const CLOSING = ['rejected', 'on_hold', 'withdrawn'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(RecruitInterview::class)->orderByDesc('starts_at');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class)->orderByDesc('id');
    }

    /** The offer that counts: the latest one not withdrawn. */
    public function offer(): HasOne
    {
        return $this->hasOne(JobOffer::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('status', '<>', 'withdrawn'));
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecruitLog::class)->orderByDesc('id');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage][0] ?? ucfirst((string) $this->stage);
    }

    public function stageBadge(): string
    {
        return self::badge($this->stage);
    }

    public static function badge(?string $stage): string
    {
        [$label, $class] = self::STAGES[$stage] ?? [ucfirst((string) $stage), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

    public function positionLabel(): string
    {
        return $this->position?->title ?? 'Unassigned';
    }

}
