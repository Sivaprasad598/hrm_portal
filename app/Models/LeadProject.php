<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Project names for lead contacts (Project Name on the lead form, added with its "Add" button).
 */
class LeadProject extends BaseModel
{

    use HasCompany;

    protected $table = 'lead_projects';

    protected $guarded = ['id'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'lead_project_id');
    }

}
