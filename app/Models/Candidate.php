<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One person in the talent pool, however they came in (careers mailbox,
 * website, typed in). They apply to positions through JobApplication; the
 * same person is never entered twice (App\Services\Recruit\CandidateIntake
 * matches on email, then mobile).
 */
class Candidate extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'total_experience' => 'float',
        'current_ctc' => 'float',
        'expected_ctc' => 'float',
        'skills' => 'array',
        'work_experience' => 'array',
        'ai_filled_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public const SOURCES = [
        'email' => ['Careers mailbox', 'fa-envelope'],
        'website' => ['Website', 'fa-globe'],
        'manual' => ['Added by HR', 'fa-user-edit'],
        'referral' => ['Referral', 'fa-user-friends'],
        'other' => ['Other', 'fa-circle'],
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class)->orderByDesc('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CandidateFile::class)->orderByDesc('id');
    }

    public function resume(): HasOne
    {
        // The filter goes inside ofMany: a where() before latestOfMany() would not reach its MAX(id) subquery.
        return $this->hasOne(CandidateFile::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('category', 'resume'));
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecruitLog::class)->orderByDesc('id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source][0] ?? ucfirst((string) $this->source);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    }

    public function experienceText(): string
    {
        if ($this->total_experience === null) {
            return '--';
        }

        return $this->total_experience == 0 ? 'Fresher' : rtrim(rtrim(number_format($this->total_experience, 1), '0'), '.') . ' yrs';
    }

}
