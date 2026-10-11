<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\ProjectLabel
 *
 * @property int $id
 * @property int $label_id
 * @property int $project_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $icon
 * @property-read \App\Models\ProjectLabelList $label
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel whereLabelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProjectLabel extends BaseModel
{

    protected $guarded = ['id'];

    public function label(): BelongsTo
    {
        return $this->belongsTo(ProjectLabelList::class, 'label_id');
    }

}
