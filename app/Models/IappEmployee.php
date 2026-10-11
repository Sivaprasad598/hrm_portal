<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee as the biometric platform knows them, plus the link to the ERP.
 *
 * Direction is one-way by design: the devices are the source of truth for who
 * exists on them, and nothing in this module ever writes an employee back to
 * iApp. Mapping only ever sets the ERP side of the relation.
 */
class IappEmployee extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_employees';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_payload' => 'array',
        'is_active' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'mapped_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public const STATUS_UNMAPPED = 'unmapped';
    public const STATUS_MAPPED = 'mapped';
    public const STATUS_IGNORED = 'ignored';

    /** How the ERP link was established. */
    public const METHOD_AUTO = 'auto';
    public const METHOD_MANUAL = 'manual';

    /** Where the biometric record came from. */
    public const SOURCE_PUNCH = 'punch_feed';
    public const SOURCE_API = 'api';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    /**
     * Punches belonging to this biometric identity.
     *
     * Joined on emp_code rather than an id, because a punch carries the
     * device's code and nothing else.
     */
    public function punches(): HasMany
    {
        return $this->hasMany(IappDeviceTransaction::class, 'emp_code', 'emp_code');
    }

    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->whereNull('user_id')->where('map_status', '!=', self::STATUS_IGNORED);
    }

    public function scopeMapped(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeIgnored(Builder $query): Builder
    {
        return $query->where('map_status', self::STATUS_IGNORED);
    }

    public function isMapped(): bool
    {
        return $this->user_id !== null;
    }

    public function getMapStatusColorAttribute(): string
    {
        return match (true) {
            $this->map_status === self::STATUS_IGNORED => 'secondary',
            $this->isMapped() => 'success',
            default => 'warning',
        };
    }

    public function getMapStatusLabelAttribute(): string
    {
        return match (true) {
            $this->map_status === self::STATUS_IGNORED => 'Ignored',
            $this->isMapped() => 'Mapped',
            default => 'Unmapped',
        };
    }

    /**
     * Link this biometric identity to an ERP user.
     *
     * Writes only to this table. The ERP user record is never altered, and
     * nothing is sent to the device.
     */
    public function mapTo(int $userId, string $method = self::METHOD_MANUAL, ?string $matchedOn = null, ?int $actorId = null): void
    {
        $this->forceFill([
            'user_id' => $userId,
            'map_status' => self::STATUS_MAPPED,
            'map_method' => $method,
            'matched_on' => $matchedOn,
            'mapped_by' => $actorId,
            'mapped_at' => now(),
        ])->save();
    }

    public function unmap(?int $actorId = null): void
    {
        $this->forceFill([
            'user_id' => null,
            'map_status' => self::STATUS_UNMAPPED,
            'map_method' => null,
            'matched_on' => null,
            'mapped_by' => $actorId,
            'mapped_at' => now(),
        ])->save();
    }

    /**
     * Park a code that is not a real employee — a shared door account, a test
     * enrolment — so it stops appearing in the review queue.
     */
    public function ignore(?int $actorId = null, ?string $note = null): void
    {
        $this->forceFill([
            'user_id' => null,
            'map_status' => self::STATUS_IGNORED,
            'map_note' => $note,
            'mapped_by' => $actorId,
            'mapped_at' => now(),
        ])->save();
    }

}
