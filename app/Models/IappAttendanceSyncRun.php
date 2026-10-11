<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One deliberate push of device punches into the ERP's attendance.
 *
 * Deliberate by design: this is the only part of the biometric module that
 * writes to a table the rest of the ERP owns, so it is never triggered by a
 * schedule or by a punch sync — somebody chooses a range, reviews what would
 * happen, and confirms it.
 */
class IappAttendanceSyncRun extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_attendance_sync_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** Leave a day that already has attendance from elsewhere alone. */
    public const MODE_SKIP = 'skip';

    /** Replace it, keeping a copy of every displaced row. */
    public const MODE_OVERRIDE = 'override';

    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public function links(): HasMany
    {
        return $this->hasMany(IappAttendanceLink::class, 'run_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

}
