<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a candidate's timeline: how they came in, every stage change
 * with its reason, notes, interviews, feedback, offers, joining. Only ever
 * added to - never edited or deleted - so it stands as the record of how a
 * hiring decision was made.
 */
class RecruitLog extends BaseModel
{

    use HasCompany;

    public const UPDATED_AT = null;

    protected $table = 'recruit_logs';

    protected $guarded = ['id'];

    /** action => [label, icon, colour] */
    public const ACTIONS = [
        'received' => ['Received', 'fa-inbox', 'text-primary'],
        'updated' => ['Details updated', 'fa-edit', 'text-dark-grey'],
        'applied' => ['Applied', 'fa-paper-plane', 'text-primary'],
        'stage' => ['Stage changed', 'fa-exchange-alt', 'text-primary'],
        'note' => ['Note', 'fa-sticky-note', 'text-warning'],
        'file' => ['File added', 'fa-paperclip', 'text-dark-grey'],
        'ai' => ['Read with AI', 'fa-magic', 'text-info'],
        'interview' => ['Interview scheduled', 'fa-calendar-plus', 'text-primary'],
        'interview_status' => ['Interview', 'fa-calendar-check', 'text-success'],
        'feedback' => ['Feedback', 'fa-clipboard-check', 'text-success'],
        'offer' => ['Offer', 'fa-file-signature', 'text-warning'],
        'email' => ['Email sent', 'fa-envelope', 'text-dark-grey'],
        'converted' => ['Joined as employee', 'fa-user-check', 'text-success'],
    ];

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

}
