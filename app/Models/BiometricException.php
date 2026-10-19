<?php

namespace App\Models;

/**
 * Something the nightly cross-check found that device and ERP do not agree on,
 * or a day whose punches do not make a complete day. One row per problem: the
 * fingerprint identifies it, so finding it again the next night updates the
 * row rather than adding another, and a problem that has gone away is closed
 * by itself (resolution "auto").
 *
 * @property \Illuminate\Support\Carbon $date
 * @property array|null $details
 */
class BiometricException extends BaseModel
{

    public const SYNC = 'sync';
    public const PUNCH = 'punch';

    public const OPEN = 'open';
    public const RESOLVED = 'resolved';
    public const IGNORED = 'ignored';

    /** type => [category, label, what to do about it] */
    public const TYPES = [
        'pull_failed' => [self::SYNC, 'Device pull failed', 'The day was reviewed on what was on record. It is pulled again every night for a week; Re-sync the day once the device is reachable.'],
        'unlinked_code' => [self::SYNC, 'Device code not linked', 'Punches from a device code that is not linked to any employee. Link it on Employee Mapping, or Ignore the code if the person is not an employee here.'],
        'not_written' => [self::SYNC, 'Device day not in ERP', 'The device has the day but ERP attendance does not. Re-sync the day.'],
        'erp_other_source' => [self::SYNC, 'ERP entered elsewhere', 'ERP attendance for the day was entered by hand or by web clock-in, so the device day was not applied. Check which is right.'],
        'time_mismatch' => [self::SYNC, 'ERP differs from device', 'ERP clock-in or clock-out is more than the tolerance away from the device. Re-sync the day, or correct ERP.'],
        'orphan_erp' => [self::SYNC, 'ERP has device attendance without punches', 'ERP attendance written from the device, but the device holds no punches for the day any more.'],
        'payslip_locked' => [self::SYNC, 'Changed after payslip', 'The device now shows something different for a day an approved payslip already counted. ERP was left as paid.'],
        'payslip_mismatch' => [self::SYNC, 'Payslip differs from attendance', 'The approved payslip counted a different number of days than attendance now gives for its cycle. Decide on arrears or a recovery.'],
        'mapping_conflict' => [self::SYNC, 'Employee ID conflict', 'A device code could not be linked by Employee ID: two employees share the ID, or the link was refused. Correct the Employee ID, then Sync all employees again.'],
        'single_punch' => [self::PUNCH, 'Single punch', 'Only one punch for the day, so neither the hours nor the exit are known.'],
        'missing_exit' => [self::PUNCH, 'Missing exit', 'The day has an entry but no exit punch.'],
        'missing_entry' => [self::PUNCH, 'Missing entry', 'The day has an exit but no entry punch.'],
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'details' => 'array',
        'resolved_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(\App\Scopes\ActiveScope::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by')->withoutGlobalScope(\App\Scopes\ActiveScope::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type][1] ?? $this->type;
    }

    public function help(): string
    {
        return self::TYPES[$this->type][2] ?? '';
    }

}
