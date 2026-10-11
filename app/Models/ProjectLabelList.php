<?php

namespace App\Models;

use App\Traits\HasCompany;

/**
 * App\Models\ProjectLabelList
 *
 * @property int $id
 * @property string $label_name
 * @property string|null $color
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $icon
 * @property-read mixed $label_color
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereLabelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereUpdatedAt($value)
 * @property int|null $project_id
 * @property int|null $company_id
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProjectLabelList whereCompanyId($value)
 * @mixin \Eloquent
 */
class ProjectLabelList extends BaseModel
{

    use HasCompany;

    protected $table = 'project_label_list';

    protected $guarded = ['id'];
    public $appends = ['label_color'];

    public function getLabelColorAttribute()
    {
        return $this->color ?: '#3b0ae1';
    }

}
