<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One hand-over of an asset to an employee; returned_on closes it. */
class AssetAssignment extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'assigned_on' => 'date',
        'expected_return_on' => 'date',
        'returned_on' => 'date',
        'recovery_amount' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withoutGlobalScopes();
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withoutGlobalScopes();
    }

    public function isOverdue(): bool
    {
        return !$this->returned_on && $this->expected_return_on && $this->expected_return_on->lt(now()->startOfDay());
    }

}
