<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IappDeviceTransaction extends Model
{

    protected $guarded = ['id'];

    protected $casts = [
        'punch_time' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public const ARCHIVE = 'iapp_device_transactions_archive';

    /** Punches moved out of the live table after a year (attendance:archive) - still searchable here. */
    public static function archived(): \Illuminate\Database\Eloquent\Builder
    {
        return (new static())->setTable(self::ARCHIVE)->newQuery();
    }

    /** Whether some punches have been archived (the archive table exists and is not empty). */
    public static function hasArchive(): bool
    {
        static $has = null;

        return $has ??= \App\Support\SchemaCache::hasTable(self::ARCHIVE) && \Illuminate\Support\Facades\DB::table(self::ARCHIVE)->exists();
    }

    /**
     * Punches of a date range from where they are: the live table, or the archive for a range that
     * ended before the oldest punch still live.
     */
    public static function forRange($from, $to): \Illuminate\Database\Eloquent\Builder
    {
        if (self::hasArchive()) {
            $oldestLive = \Illuminate\Support\Facades\DB::table((new static())->getTable())->min('punch_time');

            if ($oldestLive && \Carbon\Carbon::parse($to)->lt(\Carbon\Carbon::parse($oldestLive))) {
                return self::archived();
            }
        }

        return static::query();
    }

}
