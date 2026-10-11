<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resumable pull of punch history.
 *
 * Progress lives in the row rather than in a process, so a run survives a
 * closed tab, a refresh or a PHP timeout and picks up from the same second.
 */
class IappBackfillRun extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_backfill_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'sync_dates' => 'array',
        'cursor_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const LABELS = [
        self::STATUS_RUNNING => 'Running',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_DONE => 'Completed',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** Still has work left, whether or not a step is being served right now. */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RUNNING, self::STATUS_PAUSED], true);
    }

    /**
     * The dates this run covers, oldest first.
     *
     * Named sync_dates, not dates: Eloquent reserves `$dates` as a property on
     * the model base, and it would shadow the attribute when read from inside
     * the class.
     *
     * @return array<int, string>
     */
    public function dateList(): array
    {
        $dates = $this->getAttribute('sync_dates');

        return is_array($dates) ? array_values($dates) : [];
    }

    /** The date currently being pulled, or null once every one is done. */
    public function currentDate(): ?string
    {
        return $this->dateList()[(int) $this->date_index] ?? null;
    }

    /**
     * How far through the run, 0-100.
     *
     * Whole days completed, plus how far into the day in progress, so the bar
     * still creeps forward during a single long day instead of sitting at zero.
     */
    public function progress(): int
    {
        if ($this->status === self::STATUS_DONE) {
            return 100;
        }

        $dates = $this->dateList();
        $total = count($dates);

        if ($total === 0) {
            return 0;
        }

        $done = min((int) $this->date_index, $total);
        $partial = 0.0;

        $current = $this->currentDate();

        if ($current && $this->cursor_at) {
            $start = \Carbon\Carbon::parse($current)->startOfDay();
            $end = \Carbon\Carbon::parse($current)->endOfDay();
            $span = max(1, $end->getTimestamp() - $start->getTimestamp());
            $into = $this->cursor_at->getTimestamp() - $start->getTimestamp();
            $partial = max(0.0, min(1.0, $into / $span));
        }

        return max(0, min(100, (int) round(($done + $partial) / $total * 100)));
    }

    /** Days finished so far, for the "3 of 31" readout. */
    public function daysDone(): int
    {
        return min((int) $this->date_index, count($this->dateList()));
    }

    public function dayCount(): int
    {
        return count($this->dateList());
    }

    public function getStatusLabelAttribute(): string
    {
        return self::LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DONE => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_PAUSED => 'warning',
            default => 'info',
        };
    }

}
