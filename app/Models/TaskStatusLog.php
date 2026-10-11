<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per task status change, with how long the task stayed in the previous status.
 *
 * @property int $id
 * @property int|null $company_id
 * @property int $task_id
 * @property int|null $user_id
 * @property int|null $from_board_column_id
 * @property int|null $to_board_column_id
 * @property \Illuminate\Support\Carbon $changed_at
 * @property int|null $seconds_in_previous
 */
class TaskStatusLog extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function fromColumn(): BelongsTo
    {
        return $this->belongsTo(TaskboardColumn::class, 'from_board_column_id');
    }

    public function toColumn(): BelongsTo
    {
        return $this->belongsTo(TaskboardColumn::class, 'to_board_column_id');
    }

}
