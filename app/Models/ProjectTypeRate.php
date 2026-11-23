<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much one designation does in a working day, for a project type (or one of its steps). */
class ProjectTypeRate extends BaseModel
{

    protected $guarded = ['id'];

    protected $casts = ['units_per_day' => 'float'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class)->withoutGlobalScopes();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectTypeStage::class, 'stage_id');
    }

}
