<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which `attendances` rows this module wrote, and what it replaced.
 *
 * `attendances` has no column saying where a row came from, and it could not
 * usefully have one here: the Excel importer already stamps `clock_in_ip` as
 * 127.0.0.1, so a device-written row looks exactly like an imported one. The
 * provenance is kept alongside instead.
 *
 * Two uses. The sync consults it to know which rows are its own — anything not
 * listed here was entered by someone else and is never touched without an
 * explicit override. And an override records the whole displaced row in
 * `payload` before deleting it, so the decision can be undone.
 */
class IappAttendanceLink extends BaseModel
{

    protected $table = 'iapp_attendance_links';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'payload' => 'array',
    ];

    /** Written by this module; may be replaced by a later sync. */
    public const TYPE_CREATED = 'created';

    /** Existed beforehand and was removed by an override; payload holds it. */
    public const TYPE_REPLACED = 'replaced';

    public function run(): BelongsTo
    {
        return $this->belongsTo(IappAttendanceSyncRun::class, 'run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
