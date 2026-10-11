<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * Class EmployeeDocumentExpiry
 *
 * @package App\Models
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $document_name
 * @property string $document_number
 * @property \Carbon\Carbon $issue_date
 * @property \Carbon\Carbon $expiry_date
 * @property int $alert_before_days
 * @property boolean $alert_enabled
 * @property string|null $filename
 * @property string|null $hashname
 * @property string|null $size
 * @property int|null $added_by
 * @property int|null $last_updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $doc_url
 * @property-read mixed $icon
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Company|null $company
 * @property-read boolean $is_expired
 * @property-read boolean $is_expiring_soon
 * @property-read int $days_until_expiry
 */
class EmployeeDocumentExpiry extends BaseModel
{
    use HasCompany;

    const FILE_PATH = 'employee-document-expiry';

    protected $fillable = [
        'company_id',
        'user_id',
        'document_name',
        'document_number',
        'issue_date',
        'expiry_date',
        'alert_before_days',
        'alert_enabled',
        'filename',
        'hashname',
        'size',
        'added_by',
        'last_updated_by'
    ];

    protected $guarded = ['id'];
    protected $table = 'employee_document_expiries';
    protected $appends = ['doc_url', 'icon', 'is_expired', 'is_expiring_soon', 'days_until_expiry'];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'alert_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDocUrlAttribute()
    {
        if ($this->hashname) {
            return asset_url_local_s3(EmployeeDocumentExpiry::FILE_PATH . '/' . $this->user_id . '/' . $this->hashname);
        }
        return null;
    }

    public function getIconAttribute()
    {
        if (!$this->hashname) {
            return 'fa-file';
        }

        $extension = pathinfo($this->filename, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case 'pdf':
                return 'fa-file-pdf';
            case 'doc':
            case 'docx':
                return 'fa-file-word';
            case 'xls':
            case 'xlsx':
                return 'fa-file-excel';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return 'fa-file-image';
            default:
                return 'fa-file';
        }
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute()
    {
        if ($this->is_expired || !$this->alert_enabled) {
            return false;
        }

        // Show warning from 2 months (60 days) before expiry until expiry date
        $twoMonthsBefore = $this->expiry_date->copy()->subDays(60);
        return Carbon::now()->between($twoMonthsBefore, $this->expiry_date);
    }

    public function getDaysUntilExpiryAttribute()
    {
        if ($this->is_expired) {
            return 0;
        }

        return $this->expiry_date->diffInDays(Carbon::now());
    }

    public function scopeExpiringSoon($query)
    {
        return $query->where('alert_enabled', true)
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(60));
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', Carbon::now());
    }
}
