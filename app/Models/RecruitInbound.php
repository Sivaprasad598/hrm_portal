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
    ];

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
