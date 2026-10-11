<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A roadblock: the task is On Hold because it waits on a teammate for a resource.
 *
 * @property int $id
 * @property int|null $company_id
 * @property int $task_id
 * @property int|null $raised_by
 * @property int $blocking_user_id
 * @property int|null $linked_task_id
 * @property string $need
 * @property \Illuminate\Support\Carbon|null $needed_by
 * @property string $urgency
 * @property string $status
 * @property string|null $resolution_note
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int|null $previous_board_column_id
 * @property \Illuminate\Support\Carbon|null $last_reminded_at
 * @property \Illuminate\Support\Carbon|null $escalated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TaskBlocker extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'needed_by' => 'date',
        'resolved_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    public function linkedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'linked_task_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by')->withoutGlobalScopes();
    }

    public function blockingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocking_user_id')->withoutGlobalScopes();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withoutGlobalScopes();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('task_blockers.status', 'open');
    }

    public function isOverdue(): bool
    {
        return $this->status == 'open'
            && $this->needed_by
            && $this->needed_by->lt(now(company()?->timezone)->startOfDay());
    }

    /**
     * The blocking person, the person who raised it, admins and the project admin
     * can mark a roadblock resolved.
     */
    public function canResolve(): bool
    {
        return $this->status == 'open'
            && (user()->id == $this->blocking_user_id
                || user()->id == $this->raised_by
                || in_array('admin', user_roles())
                || ($this->task && $this->task->isProjectAdmin()));
    }

    /**
     * Only the person who raised it, admins and the project admin can cancel it.
     */
    public function canCancel(): bool
    {
        return $this->status == 'open'
            && (user()->id == $this->raised_by
                || in_array('admin', user_roles())
                || ($this->task && $this->task->isProjectAdmin()));
    }

}
