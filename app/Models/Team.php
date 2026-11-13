<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Team
 *
 * @property int $id
 * @property string $team_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $added_by
 * @property int|null $last_updated_by
 * @property-read mixed $icon
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\EmployeeDetails[] $teamMembers
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Team[] $childs
 * @property-read int|null $team_members_count
 * @method static \Illuminate\Database\Eloquent\Builder|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereAddedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereLastUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereTeamName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\EmployeeTeam[] $members
 * @property int|null $company_id
 * @property int|null $parent_id
 * @property-read int|null $childs_count
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereParentId($value)
 * @mixin \Eloquent
 */
class Team extends BaseModel
{

    use HasCompany;

    protected $fillable = ['team_name'];

    public function members(): HasMany
    {
        return $this->hasMany(EmployeeTeam::class, 'team_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(EmployeeDetails::class, 'department_id');
    }

    public static function allDepartments()
    {
        if (user()->permission('view_department') == 'all' || user()->permission('view_department') == 'none') {
            return Team::all();
        }

        return Team::where('added_by', user()->id)->get();
    }

    public function childs(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_id');
    }

    /**
     * A division and every level below it (sub divisions, and theirs): picking a
     * division in a filter then covers the people of its sub divisions too.
     *
     * @return array<int>
     */
    public static function withDescendants($id): array
    {
        $byParent = Team::get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [(int) $id];

        for ($i = 0; $i < count($ids); $i++) {
            foreach ($byParent[$ids[$i]] ?? [] as $child) {
                if (!in_array($child->id, $ids)) {
                    $ids[] = (int) $child->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Departments for a dropdown in tree order: each division, then its sub divisions
     * (and theirs) under it. Each item: id, name, depth, division (top-level name),
     * path ("Business Development › IT").
     *
     * @param \Illuminate\Support\Collection|null $only the departments allowed (default all)
     */
    public static function treeOptions($only = null): \Illuminate\Support\Collection
    {
        $all = $only ?? Team::all();
        $byParent = $all->groupBy(fn ($t) => (int) $t->parent_id);
        $ids = $all->pluck('id')->all();
        $out = collect();

        $walk = function ($parentId, $depth, $division, $path) use (&$walk, $byParent, &$out) {
            foreach (($byParent[$parentId] ?? collect())->sortBy('team_name', SORT_NATURAL | SORT_FLAG_CASE) as $t) {
                $top = $division ?? $t->team_name;
                $out->push((object) ['id' => $t->id, 'name' => $t->team_name, 'depth' => $depth,
                    'division' => $top, 'path' => $path ? $path . ' › ' . $t->team_name : $t->team_name]);
                $walk((int) $t->id, $depth + 1, $top, $path ? $path . ' › ' . $t->team_name : $t->team_name);
            }
        };

        // roots: no parent, or a parent not in the list (so nothing is dropped)
        foreach ($byParent->keys()->sort() as $parentId) {
            if ($parentId !== 0 && in_array($parentId, $ids)) {
                continue;
            }
            $walk($parentId, 0, null, '');
        }

        return $out;
    }

}
