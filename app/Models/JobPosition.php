<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An opening the company is hiring for. It closes by itself when as many
 * candidates have joined as there are openings (App\Services\Recruit\Pipeline).
 */
class JobPosition extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'closing_date' => 'date',
        'experience_min' => 'float',
        'experience_max' => 'float',
        'salary_min' => 'float',
        'salary_max' => 'float',
    ];

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'open' => ['Open', 'success'],
        'on_hold' => ['On Hold', 'warning'],
        'closed' => ['Closed', 'dark'],
    ];

    public const TYPES = [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
        'contract' => 'Contract',
        'internship' => 'Internship',
        'remote' => 'Remote',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function nextCode(): string
    {
        $last = static::withoutGlobalScopes()->where('code', 'like', 'JOB-%')->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'JOB-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public function statusBadge(): string
    {
        [$label, $class] = self::STATUSES[$this->status] ?? [ucfirst((string) $this->status), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

    public function experienceText(): string
    {
        if ($this->experience_min === null && $this->experience_max === null) {
            return 'Any experience';
        }

        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');

        if ($this->experience_max === null) {
            return $fmt($this->experience_min) . '+ yrs';
        }

        return $fmt($this->experience_min ?? 0) . '–' . $fmt($this->experience_max) . ' yrs';
    }

    public function salaryText(): ?string
    {
        if (!$this->salary_min && !$this->salary_max) {
            return null;
        }

        $fmt = fn ($v) => $v ? number_format((float) $v / 100000, 1) . ' L' : null;

        return trim(implode(' – ', array_filter([$fmt($this->salary_min), $fmt($this->salary_max)]))) . ' per year';
    }

    public function label(): string
    {
        return $this->title . ' (' . $this->code . ')';
    }

}
