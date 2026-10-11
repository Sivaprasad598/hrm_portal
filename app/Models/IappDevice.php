<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Builder;

/**
 * An access controller / biometric reader as reported by iApp.
 */
class IappDevice extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_devices';

    protected $guarded = ['id'];

    protected $casts = [
        'last_activity_on' => 'datetime',
        'raw_payload' => 'array',
        'synced_at' => 'datetime',
    ];

    /** deviceMode values documented by iApp. */
    public const MODE_DEFAULT = 'D';
    public const MODE_TOGGLE = 'tg';
    public const MODE_IN = 'In';
    public const MODE_OUT = 'Out';

    /** Labels match the wording used in the iApp device-management screen. */
    public const MODES = [
        self::MODE_DEFAULT => 'Default',
        self::MODE_TOGGLE => 'Toggle',
        self::MODE_IN => 'In-Mode',
        self::MODE_OUT => 'Out-Mode',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('controller_status', 'Y');
    }

    public function isActive(): bool
    {
        return $this->controller_status === 'Y';
    }

    public function getModeLabelAttribute(): string
    {
        return self::MODES[$this->device_mode] ?? ($this->device_mode ?: '--');
    }

    /**
     * Punch direction implied by this controller.
     *
     * Only "In only" and "Out only" readers imply one. A Default or Toggle
     * reader records both directions on the same device, so its punches
     * can't be classified from the device alone.
     */
    public function impliedPunchDirection(): ?string
    {
        return match ($this->device_mode) {
            self::MODE_IN => 'in',
            self::MODE_OUT => 'out',
            default => null,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return $this->isActive() ? 'success' : 'danger';
    }

}
