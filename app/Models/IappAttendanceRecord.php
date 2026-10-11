<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single day's attendance for one employee, exactly as iApp returned it.
 *
 * This is the authoritative device record. `attendances` rows are derived
 * from it, never the other way round.
 */
class IappAttendanceRecord extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_attendance_records';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'first_in' => 'datetime',
        'last_out' => 'datetime',
        'leave_qty' => 'float',
        'absent' => 'float',
        'present' => 'float',
        'tour' => 'float',
        'raw_payload' => 'array',
        'controller_codes' => 'array',
        'sessions' => 'array',
        'synced_at' => 'datetime',
        'promoted_at' => 'datetime',
    ];

    /** How the row was produced. */
    public const SOURCE_SUMMARY = 'device_summary';
    public const SOURCE_PUNCH = 'punch_derived';

    /** How the day is classified, derived once at sync time. */
    public const DAY_PRESENT = 'present';
    public const DAY_ABSENT = 'absent';
    public const DAY_LEAVE = 'leave';
    public const DAY_WEEK_OFF = 'week_off';
    public const DAY_HOLIDAY = 'holiday';
    public const DAY_UNKNOWN = 'unknown';

    /** Whether the row has been carried into `attendances`. */
    public const PROMOTE_PENDING = 'pending';
    public const PROMOTE_DONE = 'promoted';
    public const PROMOTE_SKIPPED = 'skipped';
    public const PROMOTE_CONFLICT = 'conflict';
    public const PROMOTE_UNMATCHED = 'unmatched';
    public const PROMOTE_NOT_APPLICABLE = 'not_applicable';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /** Rows that represent real worked time and can become an attendance row. */
    public function scopePromotable(Builder $query): Builder
    {
        return $query->whereNotNull('user_id')->whereNotNull('first_in');
    }

    public function scopeAwaitingPromotion(Builder $query): Builder
    {
        return $query->promotable()->where('promote_status', self::PROMOTE_PENDING);
    }

    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    public function getDayTypeColorAttribute(): string
    {
        return match ($this->day_type) {
            self::DAY_PRESENT => 'success',
            self::DAY_ABSENT => 'danger',
            self::DAY_LEAVE => 'warning',
            self::DAY_WEEK_OFF, self::DAY_HOLIDAY => 'info',
            default => 'secondary',
        };
    }

    public function getPromoteStatusColorAttribute(): string
    {
        return match ($this->promote_status) {
            self::PROMOTE_DONE => 'success',
            self::PROMOTE_CONFLICT => 'warning',
            self::PROMOTE_UNMATCHED => 'danger',
            default => 'secondary',
        };
    }

}
