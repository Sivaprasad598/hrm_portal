<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One interviewer's scorecard for one interview: a 1-5 rating per question,
 * an overall rating, a recommendation and comments.
 */
class RecruitInterviewFeedback extends Model
{

    protected $table = 'recruit_interview_feedback';

    protected $guarded = ['id'];

    protected $casts = [
        'ratings' => 'array',
    ];

    public const RECOMMENDATIONS = [
        'hire' => ['Hire', 'success'],
        'maybe' => ['Maybe', 'warning'],
        'no_hire' => ['No hire', 'danger'],
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(RecruitInterview::class, 'recruit_interview_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function recommendationBadge(): string
    {
        [$label, $class] = self::RECOMMENDATIONS[$this->recommendation] ?? [ucfirst((string) $this->recommendation), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

}
