<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

}
