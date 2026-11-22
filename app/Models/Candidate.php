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

    /** The employee who referred them (Source = Referral). Kept when that employee leaves. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by')->withoutGlobalScopes()
            ->leftJoin('employee_details as ref_ed', 'ref_ed.user_id', '=', 'users.id')
            ->leftJoin('teams as ref_team', 'ref_team.id', '=', 'ref_ed.department_id')
            ->select('users.id', 'users.name', 'users.status', 'ref_ed.employee_id', 'ref_team.team_name as division');
    }

    /**
     * "Ravi Kumar (EMP-0042) · Engineering": the referrer with their employee id and
     * Division, "(left)" once they are no longer active; null when nobody is set.
     */
    public function referrerLabel(bool $withId = true, bool $withDivision = true): ?string
    {
        $r = $this->referred_by ? $this->referrer : null;

        if (!$r) {
            return null;
        }

        return $r->name . ($withId && $r->employee_id ? ' (' . $r->employee_id . ')' : '') . ($r->status !== 'active' ? ' (left)' : '')
            . ($withDivision && $r->division ? ' · ' . $r->division : '');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source][0] ?? ucfirst((string) $this->source);
    }

    /** "Referral · Ravi Kumar" for a linked referral, else the source; with the free-text detail when $detail. */
    public function sourceText(bool $detail = false): string
    {
        $text = $this->sourceLabel();

        if ($ref = $this->referrerLabel(false)) {
            $text .= ' · ' . $ref;
        }

        return $detail && $this->source_detail ? $text . ' · ' . $this->source_detail : $text;
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
