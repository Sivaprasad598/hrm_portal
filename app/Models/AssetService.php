<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One service, repair or inspection job on an asset; completed_on closes it. */
class AssetService extends BaseModel
{

    use HasCompany;

    public const FILE_PATH = 'asset-files';

    public const KINDS = ['service' => 'Service', 'repair' => 'Repair', 'inspection' => 'Inspection'];

    protected $guarded = ['id'];

    protected $casts = [
        'sent_on' => 'date',
        'completed_on' => 'date',
        'cost' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function billUrl(): ?string
    {
        return $this->bill ? asset_url_local_s3(self::FILE_PATH . '/' . $this->bill) : null;
    }

}
