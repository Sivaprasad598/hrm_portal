<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resignation extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'exit_type', 'reason',
        'resignation_date', 'notice_period_days', 'notice_period_id', 'notice_start_date', 'last_working_date',
        'notice_served', 'remarks', 'status', 'admin_remarks', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'resignation_date'   => 'date',
        'notice_start_date'  => 'date',
        'last_working_date'  => 'date',
        'reviewed_at'        => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function replies()
    {
        return $this->hasMany(ResignationReply::class, 'resignation_id')->orderBy('created_at');
    }

    /**
     * Everyone who should be notified about resignation activity for a company:
     * admins, plus anyone individually granted the edit_employees permission
     * (the same permission that gates approve/reject/reply in the admin UI).
     */
    public static function adminRecipients(int $companyId)
    {
        $editEmployeesPermission = Permission::where('name', 'edit_employees')->first();
        $permissionUserIds = $editEmployeesPermission
            ? UserPermission::where('permission_id', $editEmployeesPermission->id)
                ->where('permission_type_id', PermissionType::ALL)
                ->pluck('user_id')
                ->toArray()
            : [];

        $adminUserIds = User::allAdmins($companyId)->pluck('id')->toArray();

        $recipientIds = array_unique(array_merge($adminUserIds, $permissionUserIds));

        return User::whereIn('id', $recipientIds)->get();
    }

    /**
     * Human-readable label for a chosen Notice Period Settings row, e.g.
     * "Standard (60 Days)" when the setting has a name, or just "60 Days"
     * when it doesn't. Falls back to the resignation's own notice_period_days
     * when no setting is linked (legacy resignations approved before the
     * notice-period dropdown existed).
     */
    public static function noticePeriodLabel(?int $noticePeriodId, ?int $days): string
    {
        $setting = $noticePeriodId
            ? \Illuminate\Support\Facades\DB::table('notice_period_settings')->find($noticePeriodId)
            : null;

        $days = $days ?? ($setting ? $setting->months * 30 : null);

        if ($days === null) {
            return '--';
        }

        $daysLabel = $days . ' Day' . ($days == 1 ? '' : 's');

        return ($setting && $setting->name) ? "{$setting->name} ({$daysLabel})" : $daysLabel;
    }

    /**
     * SQL expression resolving a resignation's effective review status.
     *
     * 'pending'/'rejected' are trusted as explicitly set. 'approved' is split
     * into 'notice_period' vs 'completed' based on last_working_date, so an
     * approved resignation automatically moves to "completed" once the date
     * passes instead of needing a separate admin action.
     */
    public static function effectiveStatusSql(string $table = 'resignations'): string
    {
        return "(CASE
            WHEN {$table}.status IN ('pending', 'rejected') THEN {$table}.status
            WHEN {$table}.status = 'approved' AND ({$table}.last_working_date IS NULL OR {$table}.last_working_date >= CURDATE()) THEN 'notice_period'
            WHEN {$table}.status = 'approved' THEN 'completed'
            ELSE {$table}.status
        END)";
    }
}
