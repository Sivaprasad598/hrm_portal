<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One item in the asset register. Its status is set by what happens to it:
 * assigning makes it "assigned", the return puts it back to "available" or
 * "repair"; repair, lost and disposed are also set by hand.
 */
class Asset extends BaseModel
{

    use HasCompany;

    public const FILE_PATH = 'asset-images';

    public const STATUSES = [
        'available' => ['Available', 'success'],
        'assigned' => ['Assigned', 'primary'],
        'repair' => ['Under Repair', 'warning'],
        'lost' => ['Lost', 'danger'],
        'disposed' => ['Disposed', 'secondary'],
    ];

    public const CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged'];

    protected $guarded = ['id'];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'purchase_cost' => 'decimal:2',
        'next_service_on' => 'date',
        'next_due_on' => 'date',
    ];

    /** Days ahead at which something counts as "due soon". */
    public const DUE_SOON_DAYS = 30;

    protected $appends = ['image_url'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->orderByDesc('assigned_on')->orderByDesc('id');
    }

    /** The hand-over not yet returned, if the asset is out. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_on')->latestOfMany();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->orderByDesc('id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(AssetService::class)->orderByDesc('sent_on')->orderByDesc('id');
    }

    /** A repair or service sent out and not back yet. */
    public function openService(): HasOne
    {
        return $this->hasOne(AssetService::class)->whereNull('completed_on')->latestOfMany();
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(AssetRenewal::class)->orderBy('expires_on');
    }

    /**
     * Green / amber / red for a due date: overdue, due within DUE_SOON_DAYS,
     * or fine. Shared by services, renewals and warranty everywhere.
     */
    public static function dueBadge($date): string
    {
        if (!$date) {
            return '';
        }

        $date = \Carbon\Carbon::parse($date)->startOfDay();
        $days = (int) now()->startOfDay()->diffInDays($date, false);
        $when = $date->format(company()?->date_format ?? 'd-m-Y');

        if ($days < 0) {
            return '<span class="badge badge-danger px-2 py-1">' . e($when) . ' · ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue</span>';
        }

        if ($days <= self::DUE_SOON_DAYS) {
            return '<span class="badge badge-warning px-2 py-1">' . e($when) . ' · ' . ($days === 0 ? 'today' : 'in ' . $days . ' day' . ($days === 1 ? '' : 's')) . '</span>';
        }

        return '<span class="badge badge-success px-2 py-1">' . e($when) . '</span>';
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset_url_local_s3(self::FILE_PATH . '/' . $this->image) : null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        [$label, $colour] = self::STATUSES[$this->status] ?? [ucfirst((string) $this->status), 'secondary'];

        return '<span class="badge badge-' . $colour . ' px-2 py-1">' . e($label) . '</span>';
    }

    /** The next free code, AST-0001 style. */
    public static function nextCode(): string
    {
        $last = static::where('asset_code', 'like', 'AST-%')->pluck('asset_code')
            ->map(fn ($code) => (int) substr($code, 4))->max();

        return 'AST-' . str_pad((string) (($last ?: 0) + 1), 4, '0', STR_PAD_LEFT);
    }

}
