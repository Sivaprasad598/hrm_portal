<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveQuotaEvent extends BaseModel
{
    public const TYPE_CARRY_FORWARD_EXPIRED = 'carry_forward_expired';

    protected $guarded = ['id'];

    protected $casts = [
        'leave_year_start' => 'date',
        'expired_on' => 'date',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id')->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
