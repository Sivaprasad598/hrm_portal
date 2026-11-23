<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a project type's work (GIS: Data capture -> Digitisation -> QC ...). */
class ProjectTypeStage extends BaseModel
{

    protected $guarded = ['id'];

    protected $casts = ['is_done' => 'boolean'];

    /** Colours for the steps, in order, when none is set. */
    public const PALETTE = ['#98a2b3', '#0ba5ec', '#7a5af8', '#f79009', '#ee46bc', '#12b76a', '#344054'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function colour(int $position = 0): string
    {
        return $this->color ?: ($this->is_done ? '#12b76a' : self::PALETTE[$position % (count(self::PALETTE) - 1)]);
    }

}
