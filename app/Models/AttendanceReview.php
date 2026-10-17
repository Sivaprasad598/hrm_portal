<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one employee's day was, judged the morning after: present, late, short
 * hours, missing exit, absent without leave, a leave clash... and whether HR
 * has dealt with it.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $user_id
 * @property string $status
 * @property array|null $flags
 * @property bool $is_exception
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $first_in
 * @property \Illuminate\Support\Carbon|null $last_out
 * @property int $presence_minutes
 * @property int $punches
 * @property float $credit
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class AttendanceReview extends BaseModel
{

    public const PRESENT = 'present';
    public const HALF_DAY = 'half_day';
    public const SHORT_HOURS = 'short_hours';
    public const ABSENT = 'absent';
    public const ON_LEAVE = 'on_leave';
    public const LEAVE_WORKED = 'leave_worked';
    public const LEAVE_PENDING = 'leave_pending';
    public const WEEK_OFF = 'week_off';
    public const HOLIDAY = 'holiday';
    public const WORKED_OFF_DAY = 'worked_off_day';

    /** Status => [label, badge class, needs attention] */
    public const STATUSES = [
        self::ABSENT => ['Absent, no leave', 'badge-danger', true],
        self::SHORT_HOURS => ['Short hours (no credit)', 'badge-danger', true],
        self::HALF_DAY => ['Half day', 'badge-warning', true],
        self::LEAVE_WORKED => ['Worked on a leave day', 'badge-warning', true],
        self::LEAVE_PENDING => ['Leave not approved', 'badge-warning', true],
        self::WORKED_OFF_DAY => ['Worked on week-off / holiday', 'badge-info', true],
        self::PRESENT => ['Present', 'badge-success', false],
        self::ON_LEAVE => ['On leave', 'badge-secondary', false],
        self::WEEK_OFF => ['Week off', 'badge-light border', false],
        self::HOLIDAY => ['Holiday', 'badge-light border', false],
    ];

    /** Flag => label */
    public const FLAGS = [
        'missing_exit' => 'No exit punch',
        'single_punch' => 'Single punch',
        'late' => 'Late',
        'left_early' => 'Left early',
        'not_on_device' => 'Not linked to device',
        'device_incomplete' => 'Device data incomplete',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'flags' => 'array',
        'is_exception' => 'boolean',
        'first_in' => 'datetime',
        'last_out' => 'datetime',
        'credit' => 'float',
        'employee_notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withoutGlobalScopes();
    }

    public function label(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function badge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'badge-light border';
    }

}
