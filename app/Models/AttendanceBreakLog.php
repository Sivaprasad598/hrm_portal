<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A break an employee marked from the mobile app. Recorded only - see the
 * attendance_break_logs migration.
 */
class AttendanceBreakLog extends Model
{
    const TYPES = ['lunch' => 'Lunch break', 'tea' => 'Tea break', 'other' => 'Other break'];

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /** The break running now for this employee, if any. */
    public static function running(int $userId): ?self
    {
        return static::where('user_id', $userId)->whereNull('ended_at')->latest('started_at')->first();
    }
}
