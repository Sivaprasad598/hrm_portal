<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every email read from the careers mailbox and every submission from the
 * website, kept as it arrived: the proof of what the candidate sent, and the
 * key (Message-ID / submission hash) that stops the same one being imported
 * twice.
 */
class RecruitInbound extends BaseModel
{

    use HasCompany;

    protected $table = 'recruit_inbound';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
    ];

    public const STATUSES = [
        'imported' => ['New candidate', 'success'],
        'updated' => ['Added to existing candidate', 'info'],
        'skipped' => ['Not a job application', 'secondary'],
        'failed' => ['Failed', 'danger'],
        // Taken in, still being imported (stays so only when the run was cut off half-way)
        'processing' => ['Processing', 'warning'],
    ];

    /**
     * Take a submission in, once: the row is written before the import, so
     * when the same email or website submission is handled twice at the same
     * moment (Fetch now during the 15-minute run, a double click on the
     * website) the unique key lets only one through. A row that failed
     * before is taken back for a new try.
     *
     * @return self|null the row to import into, or null when another run has it
     */
    public static function claim(array $attributes): ?self
    {
        $key = fn () => static::withoutGlobalScopes()->where('company_id', $attributes['company_id'])
            ->where('channel', $attributes['channel'])->where('external_id', $attributes['external_id']);

        $earlier = $key()->first();

        if ($earlier) {
            // Only a failed try is taken again, and only by one of two runs at the same moment
            if ($earlier->status !== 'failed' || !$key()->where('status', 'failed')->update(['status' => 'processing', 'reason' => null])) {
                return null;
            }

            $earlier->fill(['status' => 'processing', 'reason' => null] + $attributes)->save();

            return $earlier;
        }

        $record = new static($attributes + ['status' => 'processing']);

        try {
            $record->save();
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {   // duplicate key: another run took it first
                return null;
            }

            throw $e;
        }

        return $record;
    }

    /** The same submission already in (and not failed), for the website's "already received" answer. */
    public static function earlier(?int $companyId, string $channel, string $externalId): ?self
    {
        return static::withoutGlobalScopes()->where('company_id', $companyId)->where('channel', $channel)
            ->where('external_id', $externalId)->first();
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function statusBadge(): string
    {
        [$label, $class] = self::STATUSES[$this->status] ?? [ucfirst((string) $this->status), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

}
