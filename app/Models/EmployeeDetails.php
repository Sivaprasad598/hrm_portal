<?php

namespace App\Models;

use App\Enums\MaritalStatus;
use App\Scopes\ActiveScope;
use App\Traits\CustomFieldsTrait;
use App\Traits\HasCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\EmployeeDetails
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $employee_id
 * @property string|null $address
 * @property float|null $hourly_rate
 * @property string|null $slack_username
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon $joining_date
 * @property \Illuminate\Support\Carbon|null $last_date
 * @property int|null $added_by
 * @property int|null $last_updated_by
 * @property-read \App\Models\Team|null $department
 * @property-read \App\Models\Designation|null $designation
 * @property-read mixed $extras
 * @property-read mixed $icon
 * @property-read \App\Models\User $user
 * @method static Builder|EmployeeDetails newModelQuery()
 * @method static Builder|EmployeeDetails newQuery()
 * @method static Builder|EmployeeDetails query()
 * @method static Builder|EmployeeDetails whereAddedBy($value)
 * @method static Builder|EmployeeDetails whereAddress($value)
 * @method static Builder|EmployeeDetails whereCreatedAt($value)
 * @method static Builder|EmployeeDetails whereDepartmentId($value)
 * @method static Builder|EmployeeDetails whereDesignationId($value)
 * @method static Builder|EmployeeDetails whereEmployeeId($value)
 * @method static Builder|EmployeeDetails whereHourlyRate($value)
 * @method static Builder|EmployeeDetails whereId($value)
 * @method static Builder|EmployeeDetails whereJoiningDate($value)
 * @method static Builder|EmployeeDetails whereLastDate($value)
 * @method static Builder|EmployeeDetails whereLastUpdatedBy($value)
 * @method static Builder|EmployeeDetails whereSlackUsername($value)
 * @method static Builder|EmployeeDetails whereUpdatedAt($value)
 * @method static Builder|EmployeeDetails whereUserId($value)
 * @property string|null $attendance_reminder
 * @method static Builder|EmployeeDetails whereAttendanceReminder($value)
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @method static Builder|EmployeeDetails whereDateOfBirth($value)
 * @property int|null $company_id
 * @property string|null $calendar_view
 * @property string|null $about_me
 * @property int|null $reporting_to
 * @property-read \App\Models\Company|null $company
 * @property-read mixed $upcoming_birthday
 * @property-read \App\Models\User|null $reportingTo
 * @method static Builder|EmployeeDetails whereAboutMe($value)
 * @method static Builder|EmployeeDetails whereCalendarView($value)
 * @method static Builder|EmployeeDetails whereCompanyId($value)
 * @method static Builder|EmployeeDetails whereReportingTo($value)
 * @property string|null $contract_end_date
 * @property string|null $internship_end_date
 * @property string|null $employment_type
 * @property string|null $marriage_anniversary_date
 * @property string|null $marital_status
 * @property string|null $notice_period_end_date
 * @property string|null $notice_period_start_date
 * @property string|null $probation_end_date
 * @property string|null $probation_notified_at
 * @property string|null $probation_review_status
 * @property string|null $probation_admin_remarks
 * @property int|null $probation_reviewed_by
 * @property string|null $probation_reviewed_at
 * @property string|null $name
 * @property string|null $occassion
 * @method static Builder|EmployeeDetails whereContractEndDate($value)
 * @method static Builder|EmployeeDetails whereEmploymentType($value)
 * @method static Builder|EmployeeDetails whereInternshipEndDate($value)
 * @method static Builder|EmployeeDetails whereMaritalStatus($value)
 * @method static Builder|EmployeeDetails whereMarriageAnniversaryDate($value)
 * @method static Builder|EmployeeDetails whereNoticePeriodEndDate($value)
 * @method static Builder|EmployeeDetails whereNoticePeriodStartDate($value)
 * @method static Builder|EmployeeDetails whereProbationEndDate($value)
 * @mixin \Eloquent
 */
class EmployeeDetails extends BaseModel
{

    use CustomFieldsTrait, HasCompany;

    protected $table = 'employee_details';

    /**
     * SQL expression that resolves an employee's effective exit-management status.
     *
     * employment_status is only reliably set by the explicit actions (confirm,
     * initiate exit, off-board), so 'confirmed'/'notice_period'/'off_boarded' are
     * trusted as-is. But probation is set once and never auto-clears, so it is
     * re-derived from probation_end_date (the date data HR already maintains)
     * every time — an employee whose probation window has passed is treated as
     * confirmed even if nobody clicked "Mark Confirmed".
     */
    public static function employmentStatusSql(string $table = 'employee_details'): string
    {
        return "(CASE
            WHEN {$table}.employment_status IN ('off_boarded', 'notice_period', 'confirmed') THEN {$table}.employment_status
            WHEN {$table}.probation_end_date IS NOT NULL AND {$table}.probation_end_date >= CURDATE() THEN 'probation'
            ELSE 'confirmed'
        END)";
    }

    protected $fillable = [
        'user_id',
        'employee_id',
        'address',
        'permanent_address',
        'hourly_rate',
        'slack_username',
        'department_id',
        'branch_id',
        'designation_id',
        'joining_date',
        'last_date',
        'added_by',
        'last_updated_by',
        'attendance_reminder',
        'date_of_birth',
        'company_id',
        'calendar_view',
        'about_me',
        'reporting_to',
        'probation_end_date',
        'notice_period_start_date',
        'notice_period_end_date',
        'marital_status',
        'marriage_anniversary_date',
        'employment_type',
        'internship_end_date',
        'contract_end_date',
        'total_experience',
        'previous_company_name',
        'company_location',
        'last_held_designation',
        'experience_joining_date',
        'experience_leaving_date',
        'pf_account_number',
        'esi_number',
        'personal_account_number',
        'ifsc_code',
        'aadhar_card_number',
        'father_or_husband_name',
        'qualification',
        'blood_group',
    ];

    protected $casts = [
        'joining_date' => 'datetime',
        'last_date' => 'datetime',
        'date_of_birth' => 'datetime',
        'calendar_view	' => 'array',
        'marital_status' => MaritalStatus::class,
    ];

    protected $with = ['company:id'];

    protected $appends = ['upcoming_birthday'];

    const CUSTOM_FIELD_MODEL = 'App\Models\EmployeeDetails';

    public function getUpcomingBirthdayAttribute()
    {
        if (is_null($this->date_of_birth)) {
            return null;
        }

        $dob = Carbon::parse(now($this->company->timezone)->year . '-' . $this->date_of_birth->month . '-' . $this->date_of_birth->day);

        if ($dob->isPast()) {
            $dob->addYear();
        }

        return $dob->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScope(ActiveScope::class);
    }

    public function reportingTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_to')->withoutGlobalScope(ActiveScope::class);
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

    public function probationReplies()
    {
        return $this->hasMany(ProbationReply::class, 'employee_details_id')->orderBy('created_at');
    }

}
