<?php

namespace App\Models;

use App\Traits\HasCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One period of something that has to be renewed: AMC, insurance, extended
 * warranty, vehicle RC / PUC, a software licence. Renewing adds the next
 * period with previous_id pointing here; a period nothing points to is the
 * current one.
 */
class AssetRenewal extends BaseModel
{

    use HasCompany;

    public const KINDS = [
        'amc' => 'AMC',
        'insurance' => 'Insurance',
        'warranty' => 'Extended Warranty',
        'rc' => 'Vehicle RC',
        'puc' => 'Vehicle PUC',
        'licence' => 'Software Licence',
        'other' => 'Other',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date',
        'expires_on' => 'date',
        'cost' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function next(): HasOne
    {
        return $this->hasOne(self::class, 'previous_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function documentUrl(): ?string
    {
        return $this->document ? asset_url_local_s3(AssetService::FILE_PATH . '/' . $this->document) : null;
    }

    /** Only periods that have not been renewed into a newer one. */
    public function scopeCurrent($query)
    {
        return $query->whereNotExists(fn ($q) => $q->from('asset_renewals as n')->whereColumn('n.previous_id', 'asset_renewals.id'));
    }

    public function dueBadge(): string
    {
        return Asset::dueBadge($this->expires_on);
    }

}
