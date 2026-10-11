<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History of sync runs, shown on the sync dashboard.
 */
class IappSyncLog extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_sync_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'unmatched_codes' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    public const TYPE_ATTENDANCE = 'attendance';
    public const TYPE_DEVICES = 'devices';
    public const TYPE_DEVICE_TRANS = 'device_trans';

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function getDurationForHumansAttribute(): string
    {
        if (is_null($this->duration_ms)) {
            return '--';
        }

        return $this->duration_ms < 1000
            ? $this->duration_ms . ' ms'
            : round($this->duration_ms / 1000, 1) . ' s';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'success',
            self::STATUS_PARTIAL => 'warning',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }

}
