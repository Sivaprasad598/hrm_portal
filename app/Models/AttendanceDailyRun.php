<?php

namespace App\Models;

/**
 * One date of the nightly attendance routine: pulled from the device, built
 * into Device Attendance, written to ERP and cross-checked, notified. A date
 * whose pull failed stays pending and is retried.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property string $status
 * @property bool $device_complete
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $summary
 */
class AttendanceDailyRun extends BaseModel
{

    public const STATUS_PENDING = 'pending';
    public const STATUS_PULLED = 'pulled';
    public const STATUS_REVIEWED = 'reviewed';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'device_complete' => 'boolean',
        'pulled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'notified_at' => 'datetime',
        'built_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'devices' => 'array',
    ];

}
