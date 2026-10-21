<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The asset's timeline: created, edited, assigned, returned, status changes. */
class AssetHistory extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by')->withoutGlobalScopes();
    }

}
