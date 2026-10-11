<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Branch
 *
 * @property int $id
 * @property string $branch_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $added_by
 * @property int|null $last_updated_by
 * @property int|null $company_id
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\EmployeeDetails[] $branchMembers
 * @method static \Illuminate\Database\Eloquent\Builder|Branch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Branch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Branch query()
 * @mixin \Eloquent
 */
class Branch extends BaseModel
{

    use HasCompany;

    protected $fillable = ['branch_name', 'address', 'emails', 'phones', 'status'];

    protected $casts = [
        'emails' => 'array',
        'phones' => 'array',
        'status' => 'boolean',
    ];

    public function branchMembers(): HasMany
    {
        return $this->hasMany(EmployeeDetails::class, 'branch_id');
    }

    public static function allBranches()
    {
        return Branch::all();
    }

}
