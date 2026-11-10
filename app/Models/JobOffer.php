<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The offer made to a candidate. The letter is the company's offer letter
 * (offer-letter.php?offer_id=), filled from here. Accepting it needs proof:
 * the signed copy, or a note of how the acceptance came in.
 */
class JobOffer extends BaseModel
{

    use HasCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'offer_date' => 'date',
        'joining_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'annual_ctc' => 'float',
    ];

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'sent' => ['Sent', 'primary'],
        'accepted' => ['Accepted', 'success'],
        'declined' => ['Declined', 'danger'],
        'withdrawn' => ['Withdrawn', 'dark'],
        'expired' => ['Expired', 'dark'],
    ];

    /** An offer still standing: only one of these per application */
    public const OPEN = ['draft', 'sent', 'accepted'];

    /** What a new offer starts from: the position's role, the candidate's expected CTC. */
    public static function defaultsFor(JobApplication $application): array
    {
        $position = $application->position;
        $candidate = $application->candidate;

        return [
            'reference_no' => self::nextReference(),
            'designation_id' => $position?->designation_id,
            'department_id' => $position?->department_id,
            'branch_id' => $position?->branch_id,
            'annual_ctc' => $candidate?->expected_ctc ?: ($position?->salary_max ?: 0),
            'offer_date' => now(),
            'joining_date' => now()->addDays(max(15, (int) $candidate?->notice_period_days)),
            'valid_until' => now()->addDays(7),
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function signedFile(): BelongsTo
    {
        return $this->belongsTo(CandidateFile::class, 'signed_file_id');
    }

    public function statusBadge(): string
    {
        [$label, $class] = self::STATUSES[$this->status] ?? [ucfirst((string) $this->status), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

    public static function nextReference(): string
    {
        $year = now()->year;
        $count = static::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return 'AR/OL/' . $year . '/' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

}
