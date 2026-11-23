<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of project, one per production Division (Settings -> Project Tracker):
 * what its work is counted in, the steps it goes through, how much each
 * designation does in a day, and the grace days after a deadline.
 */
class ProjectType extends BaseModel
{

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    /** What the work is counted in. */
    public const UNIT_KINDS = [
        'hours' => 'Hours of work',
        'count' => 'A count (features, sheets, models ...)',
        'km' => 'Kilometres (lines, corridors)',
        'sqkm' => 'Square kilometres (areas)',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectTypeStage::class)->orderBy('sort')->orderBy('id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ProjectTypeRate::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id')->withoutGlobalScopes();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** "features", "sq km" ... (hours for an hours-based type) */
    public function unitText(): string
    {
        return $this->unit_kind === 'hours' ? 'hours' : ($this->unit_label ?: ($this->unit_kind === 'km' ? 'km' : ($this->unit_kind === 'sqkm' ? 'sq km' : 'units')));
    }

    public function countsUnits(): bool
    {
        return $this->unit_kind !== 'hours';
    }

    /** @return \Illuminate\Support\Collection<int, self> the company's types, with stages */
    public static function forCompany(?int $companyId = null, bool $activeOnly = false)
    {
        return self::where('company_id', $companyId ?? company()->id)
            ->when($activeOnly, fn ($q) => $q->where('active', true))
            ->with('stages', 'division')->orderBy('sort')->orderBy('name')->get();
    }

}
