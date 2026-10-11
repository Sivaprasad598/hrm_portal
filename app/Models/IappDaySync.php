<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the last pull for one calendar date found.
 *
 * Exists so the calendar can distinguish a day nobody has pulled from a day
 * that was pulled and turned out to be empty. Absence of a row means "never
 * synced"; it is never written speculatively.
 */
class IappDaySync extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_day_syncs';

    protected $guarded = ['id'];

    protected $casts = [
        'sync_date' => 'date',
        'last_synced_at' => 'datetime',
    ];

    /** Pulled, and the day held punches. */
    public const STATUS_OK = 'ok';
    /** Pulled, and the day genuinely held nothing — a weekend, a holiday. */
    public const STATUS_EMPTY = 'empty';
    public const STATUS_FAILED = 'failed';
    /** Not a stored value: what the calendar shows for a date with no row. */
    public const STATUS_NEVER = 'never';

    public const LABELS = [
        self::STATUS_OK => 'Synced',
        self::STATUS_EMPTY => 'No punches',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_NEVER => 'Not synced',
    ];

    public const COLORS = [
        self::STATUS_OK => 'success',
        self::STATUS_EMPTY => 'warning',
        self::STATUS_FAILED => 'danger',
        self::STATUS_NEVER => 'secondary',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(IappBackfillRun::class, 'last_run_id');
    }

    public function syncedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    /**
     * Record the outcome of pulling one date.
     *
     * Counts come from the stored tables rather than from the pull, so a
     * re-sync that adds nothing still reports what the day actually holds.
     */
    public static function record(
        ?int $companyId,
        string $date,
        int $punches,
        int $employees,
        int $attendanceDays,
        ?int $runId = null,
        ?int $userId = null,
        int $durationMs = 0,
        ?string $error = null
    ): void {
        $existing = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('sync_date', $date)
            ->first();

        $status = match (true) {
            $error !== null => self::STATUS_FAILED,
            $punches > 0 => self::STATUS_OK,
            default => self::STATUS_EMPTY,
        };

        $payload = [
            'company_id' => $companyId,
            'sync_date' => $date,
            'last_synced_at' => now(),
            'status' => $status,
            'punch_count' => $punches,
            'employee_count' => $employees,
            'attendance_days' => $attendanceDays,
            'sync_count' => ($existing->sync_count ?? 0) + 1,
            'duration_ms' => $durationMs,
            'last_run_id' => $runId,
            'synced_by' => $userId,
            'last_error' => $error,
        ];

        if ($existing) {
            $existing->forceFill($payload)->save();

            return;
        }

        static::create($payload);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::COLORS[$this->status] ?? 'secondary';
    }

}
