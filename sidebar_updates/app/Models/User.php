<?php

namespace App\Models;

use App\Enums\Salutation;
use App\Notifications\ResetPassword;
use App\Scopes\ActiveScope;
use App\Traits\HasMaskImage;
use App\Helper\UserService;
use App\Traits\HasCompany;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use IvanoMatteo\LaravelDeviceTracking\Traits\UseDevices;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Shanmuga\LaravelEntrust\Traits\LaravelEntrustUserTrait;

/**
 * App\Models\User
 *
 * (Do not modify the doc block — it is auto-generated)
 */
class User extends BaseModel implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{

    use Notifiable, LaravelEntrustUserTrait, Authenticatable, Authorizable, CanResetPassword, HasFactory, TwoFactorAuthenticatable;
    use HasCompany;
    use HasMaskImage;
    use UseDevices;


    const ALL_ADDED_BOTH = ['all', 'added', 'both'];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope(new ActiveScope());
    }

    protected $with = [
       'session:id',
       'clientContact'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token', 'created_at', 'updated_at', 'headers','location_details'];

    public $dates = ['created_at', 'updated_at', 'last_login', 'two_factor_expires_at'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_login' => 'datetime',
        'two_factor_expires_at	' => 'array',
        'salutation' => Salutation::class,
        'employee_ctc' => 'decimal:2',
        'basic_for_payroll' => 'decimal:2',
    ];

    protected $appends = ['image_url', 'modules', 'mobile_with_phonecode', 'name_salutation'];

    public function getNameSalutationAttribute()
    {
        return ($this->salutation ? $this->salutation->label() . ' ' : '') . $this->name;
    }

    public function getImageUrlAttribute()
    {
        $gravatarHash = !is_null($this->email) ? md5(strtolower(trim($this->email))) : md5($this->id);

        return ($this->image) ? asset_url_local_s3('avatar/' . $this->image) : asset('img/gravatar.png');
    }

    public function maskedImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                return ($this->image) ? $this->generateMaskedImageAppUrl('avatar/' . $this->image) : asset('img/gravatar.png');
            },
        );

    }

    public function hasGravatar($email)
    {
        $hash = md5(strtolower(trim($email)));
        $uri = 'http://www.gravatar.com/avatar/' . $hash . '?d=404';
        $headers = @get_headers($uri);

        $hasValidAvatar = true;

        try {
            if (!preg_match('|200|', $headers[0])) {
                $hasValidAvatar = false;
            }
        } catch (\Exception $e) {
            $hasValidAvatar = true;
        }

        return $hasValidAvatar;
    }

    public function getMobileWithPhoneCodeAttribute()
    {
        if (!is_null($this->mobile) && !is_null($this->country_phonecode)) {
            return '+' . $this->country_phonecode . $this->mobile;
        }

        return '--';
    }

    public function routeNotificationForSlack()
    {
        $slack = $this->company->slackSetting;

        return $slack->slack_webhook;
    }

    public function routeNotificationForOneSignal()
    {
        return $this->onesignal_player_id;
    }

    public function routeNotificationForTwilio()
    {
        if (!is_null($this->mobile) && !is_null($this->country_phonecode)) {
            return '+' . $this->country_phonecode . $this->mobile;
        }

        return null;
    }

    public function routeNotificationForEmail($notification = null)
    {
        $containsExample = Str::contains($this->email, 'example');

        if (\config('app.env') === 'demo' && $containsExample) {
            return null;
        }

        return $this->email;
    }

    public function routeNotificationForNexmo($notification)
    {
        if (!is_null($this->mobile) && !is_null($this->country_phonecode)) {
            return $this->country_phonecode . $this->mobile;
        }

        return null;
    }

    public function routeNotificationForVonage($notification)
    {
        if (!is_null($this->mobile) && !is_null($this->country_phonecode)) {
            return $this->country_phonecode . $this->mobile;
        }

        return null;
    }

    public function routeNotificationForMsg91($notification)
    {
        if (!is_null($this->mobile) && !is_null($this->country_phonecode)) {
            return $this->country_phonecode . $this->mobile;
        }

        return null;
    }

    public function clientDetails(): HasOne
    {
        return $this->hasOne(ClientDetails::class, 'user_id');
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Deal::class, 'user_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function employee(): HasMany
    {
        return $this->hasMany(EmployeeDetails::class, 'user_id');
    }

    public function employeeDetail(): HasOne
    {
        return $this->hasOne(EmployeeDetails::class, 'user_id');
    }

    public function employeePreviousDetails(): HasOne
    {
        return $this->hasOne(EmployeePreviousEmpDetail::class, 'user_id');
    }

    public function empPassportDetails(): HasOne
    {
        return $this->hasOne(EmpPassportDetail::class, 'user_id');
    }

    public function empJoiningChecklist(): HasOne
    {
        return $this->hasOne(EmpJoiningChecklist::class, 'user_id');
    }

    public function empFinalDeclaration(): HasOne
    {
        return $this->hasOne(EmpFinalDeclaration::class, 'user_id');
    }

    public function employeeExperiences(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class, 'user_id')->orderBy('serial_no');
    }

    public function clientContact(): HasOne
    {
        return $this->hasOne(ClientContact::class, 'id','is_client_contact');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function member(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'user_id');
    }

    public function appreciations(): HasMany
    {
        return $this->hasMany(Appreciation::class, 'award_to');
    }

    public function appreciationsGrouped(): HasMany
    {
        return $this->hasMany(Appreciation::class, 'award_to')->select('appreciations.*', DB::raw('count("award_id") as no_of_awards'))->groupBy('award_id');
    }

    public function templateMember(): HasMany
    {
        return $this->hasMany(ProjectTemplateMember::class, 'user_id');
    }

    public function role(): HasMany
    {
        return $this->hasMany(RoleUser::class, 'user_id');
    }

    public function attendee(): HasMany
    {
        return $this->hasMany(EventAttendee::class, 'user_id');
    }

    public function agent(): HasMany
    {
        return $this->hasMany(TicketAgentGroups::class, 'agent_id');
    }

    public function agentGroup(): BelongsToMany
    {
        return $this->belongsToMany(TicketGroup::class, 'ticket_agent_groups', 'agent_id', 'group_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Ticket::class, 'agent_id');
    }

    public function leadAgent(): HasMany
    {
        return $this->hasMany(LeadAgent::class, 'user_id');
    }

    public function leadAgentCategory(): BelongsToMany
    {
        return $this->belongsToMany(LeadCategory::class, 'lead_agent_categories', 'lead_category_id', 'user_id');
    }

    public function group(): HasMany
    {
        return $this->hasMany(EmployeeTeam::class, 'user_id');
    }

    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }

    public function skills(): array
    {
        return EmployeeSkill::select('skills.name')->join('skills', 'skills.id', 'employee_skills.skill_id')->where('user_id', $this->id)->pluck('name')->toArray();
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(EmployeeLeaveQuota::class);
    }

    public function employeeLeaveTypes(): BelongsToMany
    {
        return $this->belongsToMany(LeaveType::class, 'employee_leave_quotas');
    }

    public function leaveQuotaHistory(): HasMany
    {
        return $this->hasMany(EmployeeLeaveQuotaHistory::class);
    }

    public function reportingTeam(): HasMany
    {
        return $this->hasMany(EmployeeDetails::class, 'reporting_to')->whereHas('user', function ($query) {
            $query->where('status', 'active');
        });
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_users');
    }

    public function openTasks(): BelongsToMany
    {
        $taskBoardColumn = TaskboardColumn::completeColumn();

        return $this->belongsToMany(Task::class, 'task_users')->where('tasks.board_column_id', '<>', $taskBoardColumn->id);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id')->orderByDesc('id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'user_id');
    }

    public function documentExpiries(): HasMany
    {
        return $this->hasMany(EmployeeDocumentExpiry::class, 'user_id');
    }

    public function clientDocuments(): HasMany
    {
        return $this->hasMany(ClientDocument::class, 'user_id');
    }

    public function visa(): HasMany
    {
        return $this->hasMany(VisaDetail::class, 'user_id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(ProjectTimeLog::class, 'user_id');
    }

    public static function allClients($exceptId = null, $active = true, $overRidePermission = null, $companyId = null)
    {
        if (!isRunningInConsoleOrSeeding() && !is_null($overRidePermission)) {
            $viewClientPermission = $overRidePermission;

        }
        elseif (!isRunningInConsoleOrSeeding() && user()) {
            $viewClientPermission = user()->permission('view_clients');
        }

        if (isset($viewClientPermission) && $viewClientPermission == 'none') {
            return collect([]);
        }

        $id = UserService::getUserId();

        $clients = User::without('session')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('client_details', 'users.id', '=', 'client_details.user_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'client_details.company_name', 'users.image', 'users.email_notifications', 'users.mobile', 'users.country_id', 'users.salutation', 'users.status','users.is_client_contact')
            ->whereNull('users.is_client_contact')
            ->where('roles.name', 'client');

        if (!is_null($exceptId)) {
            if (is_array($exceptId)) {
                $clients->whereNotIn('users.id', $exceptId);
            }
            else {
                $clients->where('users.id', '<>', $exceptId);
            }
        }

        if ($active) {
            $clients->where('users.status', 'active');
        }
        else {
            $clients->withoutGlobalScope(ActiveScope::class);
        }

        if (!is_null($companyId)) {
            $clients->where('users.company_id', '<>', $companyId);
        }

        if (!isRunningInConsoleOrSeeding() && isset($viewClientPermission) && $viewClientPermission == 'added') {
            $clients->where('client_details.added_by', user()->id);
        }

        if (!isRunningInConsoleOrSeeding() && in_array('client', user_roles())) {
            $clients->where('client_details.user_id', $id);
        }

        return $clients->orderBy('users.name', 'asc')->get();
    }

    public static function client()
    {
        $id = UserService::getUserId();

        return User::withoutGlobalScope(ActiveScope::class)
            ->with('clientDetails')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('client_details', 'users.id', '=', 'client_details.user_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'client_details.company_name', 'users.image', 'users.email_notifications', 'users.mobile', 'users.country_id')
            ->where('roles.name', 'client')
            ->where('users.id', $id)
            ->orderBy('users.name', 'asc')
            ->get();
    }

    public static function allEmployees($exceptId = null, $active = false, $overRidePermission = null, $companyId = null)
    {
        if (!isRunningInConsoleOrSeeding() && !is_null($overRidePermission)) {
            $viewEmployeePermission = $overRidePermission;

        }
        elseif (!isRunningInConsoleOrSeeding() && user()) {
            $viewEmployeePermission = user()->permission('view_employees');
        }

        $users = User::withRole('employee')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('designations', 'employee_details.designation_id', '=', 'designations.id')
            ->select('users.id', 'users.company_id', 'users.name', 'users.email', 'users.created_at', 'users.image', 'designations.name as designation_name', 'users.email_notifications', 'users.mobile', 'users.country_id', 'users.status', 'users.last_activity');

        if (!is_null($exceptId)) {
            if (is_array($exceptId)) {
                $users->whereNotIn('users.id', $exceptId);

            }
            else {
                $users->where('users.id', '<>', $exceptId);
            }
        }

        if (!is_null($companyId)) {
            $users->where('users.company_id', $companyId);
        }

        if (!$active) {
            $users->withoutGlobalScope(ActiveScope::class);
        }

        $id = UserService::getUserId();

        if (!isRunningInConsoleOrSeeding() && user()) {
            if (isset($viewEmployeePermission)) {
                if (($viewEmployeePermission == 'added' && !in_array('client', user_roles()))) {
                    $users->where(function ($q) {
                        $q->where('employee_details.user_id', user()->id);
                        $q->orWhere('employee_details.added_by', user()->id);
                    });

                }
                elseif ($viewEmployeePermission == 'owned' && !in_array('client', user_roles())) {
                    $users->where('users.id', user()->id);

                }
                elseif ($viewEmployeePermission == 'both' && !in_array('client', user_roles())) {
                    $users->where(function ($q) {
                        $q->where('employee_details.user_id', user()->id);
                        $q->orWhere('employee_details.added_by', user()->id);
                    });

                }
                elseif (($viewEmployeePermission == 'none' || $viewEmployeePermission == '') && !in_array('client', user_roles())) {
                    $users->where('users.id', user()->id);
                }
            }

            if (in_array('client', user_roles())) {
                $clientEmployees = Project::where('client_id', $id)
                    ->join('project_members', 'project_members.project_id', '=', 'projects.id')
                    ->select('project_members.user_id')
                    ->get()
                    ->pluck('user_id');

                $users->whereIn('users.id', $clientEmployees);
            }

        }

        if (!isRunningInConsoleOrSeeding() && user() && in_array('client', user_roles())) {
            $clientEmployess = Project::where('client_id', $id)->join('project_members', 'project_members.project_id', '=', 'projects.id')
                ->select('project_members.user_id')->get()->pluck('user_id');

            $users->whereIn('users.id', $clientEmployess);
        }

        $users->orderBy('users.name');
        $users->groupBy('users.id');

        return $users->get();
    }

    public static function allAdmins($companyId = null)
    {
        $users = User::withOut('clientDetails')->withRole('admin');

        if (!is_null($companyId)) {
            return $users->where('users.company_id', $companyId)->get();
        }

        return $users->get();
    }

    public static function departmentUsers($teamId)
    {
        $users = User::join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at')
            ->where('employee_details.department_id', $teamId);

        return $users->get();
    }

    public static function userListLatest($userID, $term)
    {
        $termCnd = '';

        if ($term) {
            $termCnd = 'and users.name like %' . $term . '%';
        }

        $messageSetting = message_setting();

        if (in_array('admin', user_roles())) {
            if ($messageSetting->allow_client_admin == 'no') {
                $termCnd .= "and roles.name != 'client'";
            }
        }
        elseif (in_array('employee', user_roles())) {
            if ($messageSetting->allow_client_employee == 'no') {
                $termCnd .= "and roles.name != 'client'";
            }
        }
        elseif (in_array('client', user_roles())) {
            if ($messageSetting->allow_client_admin == 'no') {
                $termCnd .= "and roles.name != 'admin'";
            }

            if ($messageSetting->allow_client_employee == 'no') {
                $termCnd .= "and roles.name != 'employee'";
            }
        }

        $query = DB::select('SELECT * FROM ( SELECT * FROM (
                    SELECT users.id,"0" AS groupId, users.name, users.image, users.email, users_chat.created_at as last_message, users_chat.message, users_chat.message_seen, users_chat.user_one
                    FROM users
                    INNER JOIN users_chat ON users_chat.from = users.id
                    LEFT JOIN role_user ON role_user.user_id = users.id
                    LEFT JOIN roles ON roles.id = role_user.role_id
                    WHERE users_chat.to = ' . $userID . ' ' . $termCnd . '
                    UNION
                    SELECT users.id,"0" AS groupId, users.name,users.image, users.email, users_chat.created_at  as last_message, users_chat.message, users_chat.message_seen, users_chat.user_one
                    FROM users
                    INNER JOIN users_chat ON users_chat.to = users.id
                    LEFT JOIN role_user ON role_user.user_id = users.id
                    LEFT JOIN roles ON roles.id = role_user.role_id
                    WHERE users_chat.from = ' . $userID . ' ' . $termCnd . '
                    ) AS allUsers
                    ORDER BY  last_message DESC
                    ) AS allUsersSorted
                    GROUP BY id
                    ORDER BY  last_message DESC');

        return $query;
    }

    public static function isAdmin($userId)
    {
        $user = User::find($userId);

        if ($user) {
            return $user->hasRole('admin');
        }

        return false;
    }

    public static function isClient($userId): bool
    {
        $user = User::find($userId);

        if ($user) {
            return $user->hasRole('client');
        }

        return false;
    }

    public static function isEmployee($userId): bool
    {
        $user = User::find($userId);

        if ($user) {
            return $user->hasRole('employee');
        }

        return false;
    }

    public function getModulesAttribute()
    {
        return user_modules();
    }

    public function sticky(): HasMany
    {
        return $this->hasMany(StickyNote::class, 'user_id')->orderByDesc('updated_at');
    }

    public function userChat(): HasMany
    {
        return $this->hasMany(UserChat::class, 'to')->where('message_seen', 'no');
    }

    public function employeeDetails(): HasOne
    {
        return $this->hasOne(EmployeeDetails::class);
    }

    public function getUnreadNotificationsAttribute()
    {
        return $this->unreadNotifications()->get();
    }

    public function can($permission, $requireAll = false)
    {
        config(['cache.default' => 'array']);

        if (is_array($permission)) {
            foreach ($permission as $permName) {
                $hasPerm = $this->can($permName);

                if ($hasPerm && !$requireAll) {
                    return true;
                }

                if (!$hasPerm && $requireAll) {
                    return false;
                }
            }

            return $requireAll;
        }
        else {
            foreach ($this->cachedRoles() as $role) {
                foreach ($role->cachedPermissions() as $perm) {
                    if (Str::is($permission, $perm->name)) {
                        return true;
                    }
                }
            }
        }

        config(['cache.default' => 'file']);

        return false;
    }

    public function getUserOtherRoleAttribute()
    {
        $userRole = null;

        $nonClientRoles = cache()->remember(
            'non-client-roles',
            now()->addDay(),
            fn() => Role::where('name', '<>', 'client')->orderBy('id')->get()
        );

        foreach ($nonClientRoles as $role) {
            foreach ($this->role as $urole) {
                if ($role->id == $urole->role_id) {
                    $userRole = $role->name;
                }

                if ($userRole == 'admin') {
                    break;
                }
            }
        }

        return $userRole;
    }

    public function permission($permission)
    {
        $cacheKey = 'permission-' . $permission . '-' . $this->id;

        cache()->forget($cacheKey);

        if (cache()->has($cacheKey)) {
            return cache($cacheKey);
        }

        $permissionType = UserPermission::join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->join('permission_types', 'user_permissions.permission_type_id', '=', 'permission_types.id')
            ->select('permission_types.name')
            ->where('permissions.name', $permission)
            ->where('user_permissions.user_id', $this->id)
            ->first();

        $permissionType = $permissionType ? $permissionType->name : false;

        cache([$cacheKey => $permissionType]);

        return $permissionType;
    }

    public function permissionTypeId($permission)
    {
        $cacheKey = 'permission-id-' . $permission . '-' . $this->id;

        if (cache()->has($cacheKey)) {
            return cache($cacheKey);
        }

        $permissionType = UserPermission::join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->join('permission_types', 'user_permissions.permission_type_id', '=', 'permission_types.id')
            ->select('permission_types.name', 'permission_types.id')
            ->where('permissions.name', $permission)
            ->where('user_permissions.user_id', $this->id)
            ->first();

        $permissionName = $permissionType ? $permissionType->name : false;

        cache([$cacheKey => $permissionName]);

        return $permissionName;
    }

    public function permissionTypes(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    public function session(): HasOne
    {
        return $this->hasOne(Session::class, 'user_id')->select('user_id', 'ip_address', 'last_activity');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'client_id', 'id');
    }

    public function assignUserRolePermission($roleId)
    {
        $rolePermissions = PermissionRole::where('role_id', $roleId)->get();
        $data = [];

        UserPermission::where('user_id', $this->id)->delete();

        foreach ($rolePermissions as $permission) {
            $data[] = [
                'permission_id' => $permission->permission_id,
                'user_id' => $this->id,
                'permission_type_id' => $permission->permission_type_id,
            ];
        }

        foreach (array_chunk($data, 100) as $item) {
            UserPermission::insert($item);
        }
    }

    public function assignModuleRolePermission($module)
    {
        $module = Module::where('module_name', $module)->first();

        if (!$module) {
            return true;
        }

        $rolePermissions = PermissionRole::join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.module_id', $module->id)
            ->select('permission_role.*')
            ->get();

        foreach ($rolePermissions as $key => $value) {
            $userPermission = UserPermission::where('permission_id', $value->permission_id)
                ->where('user_id', $this->id)
                ->firstOrNew();
            $userPermission->permission_id = $value->permission_id;
            $userPermission->user_id = $this->id;
            $userPermission->permission_type_id = $value->permission_type_id;
            $userPermission->save();
        }
    }

    public function generateTwoFactorCode()
    {
        $this->timestamps = false;
        $this->two_factor_code = rand(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();
    }

    public function resetTwoFactorCode()
    {
        $this->timestamps = false;
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }

    public function confirmTwoFactorAuth($code)
    {
        $codeIsValid = app(TwoFactorAuthenticationProvider::class)
            ->verify(decrypt($this->two_factor_secret), $code);

        if ($codeIsValid) {
            $this->two_factor_confirmed = true;
            $this->save();

            return true;
        }

        return false;
    }

    public function unreadMessages(): HasMany
    {
        return $this->hasMany(UserChat::class, 'from')->where('to', user()->id)->where('message_seen', 'no');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(EmployeeShiftSchedule::class, 'user_id');
    }

    public function employeeShift(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeShift::class, 'employee_shift_schedules');
    }

    public function userBadge()
    {
        $itsYou = ' <span class="ml-1 badge badge-secondary pr-1">' . __('app.itsYou') . '</span>';
        $name = $this->name_salutation;

        if (user() && user()->id == $this->id) {
            return $name . $itsYou;
        }

        return $name;
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'client_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    public function scopeOnlyEmployee($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'employee');
        })->whereHas('employeeDetail');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    public static function allLeaveReportEmployees($exceptId = null, $active = false, $overRidePermission = null, $companyId = null)
    {
        if (!isRunningInConsoleOrSeeding() && !is_null($overRidePermission)) {
            $viewEmployeePermission = $overRidePermission;

        }
        elseif (!isRunningInConsoleOrSeeding() && user()) {
            $viewEmployeePermission = user()->permission('view_leave_report');
        }

        $users = User::withRole('employee')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('designations', 'employee_details.designation_id', '=', 'designations.id')
            ->select('users.id', 'users.company_id', 'users.name', 'users.email', 'users.created_at', 'users.image', 'designations.name as designation_name', 'users.email_notifications', 'users.mobile', 'users.country_id');

        if (!is_null($exceptId)) {
            if (is_array($exceptId)) {
                $users->whereNotIn('users.id', $exceptId);

            }
            else {
                $users->where('users.id', '<>', $exceptId);
            }
        }

        if (!is_null($companyId)) {
            $users->where('users.company_id', $companyId);
        }

        if (!$active) {
            $users->withoutGlobalScope(ActiveScope::class);
        }

        if (!isRunningInConsoleOrSeeding() && user()) {
            if (isset($viewEmployeePermission)) {
                if ($viewEmployeePermission == 'added' && !in_array('client', user_roles())) {
                    $users->where(function ($q) {
                        $q->where('employee_details.added_by', user()->id);
                    });
                }

                elseif ($viewEmployeePermission == 'owned' && !in_array('client', user_roles())) {
                    $users->where('users.id', user()->id);

                }
                elseif ($viewEmployeePermission == 'both' && !in_array('client', user_roles())) {
                    $users->where(function ($q) {
                        $q->where('employee_details.user_id', user()->id);
                        $q->orWhere('employee_details.added_by', user()->id);
                    });

                }
                elseif (($viewEmployeePermission == 'none' || $viewEmployeePermission == '') && !in_array('client', user_roles())) {
                    $users->where('users.id', user()->id);
                }
            }

            if (in_array('client', user_roles())) {
                $clientEmployees = Project::where('client_id', user()->id)
                    ->join('project_members', 'project_members.project_id', '=', 'projects.id')
                    ->select('project_members.user_id')
                    ->get()
                    ->pluck('user_id');

                $users->whereIn('users.id', $clientEmployees);
            }

        }

        if (!isRunningInConsoleOrSeeding() && user() && in_array('client', user_roles())) {
            $clientEmployees = Project::where('client_id', user()->id)
                ->join('project_members', 'project_members.project_id', '=', 'projects.id')
                ->select('project_members.user_id')
                ->get()
                ->pluck('user_id');

            $users->whereIn('users.id', $clientEmployees);
        }

        $users->orderBy('users.name');
        $users->groupBy('users.id');

        return $users->get();
    }

    public function ticketReply(): BelongsToMany
    {
        return $this->belongsToMany(TicketReply::class, 'ticket_reply_users', 'user_id', 'ticket_reply_id');
    }

    /* =========================================================
     * MENU ACCESS — DYNAMIC SIDEBAR HELPERS
     * ========================================================= */

    /**
     * Get all sidebar menus accessible by this user based on their roles.
     * Admin bypass: gets all menus.
     *
     * ⭐ CACHE BYPASSED — was caching empty result before DB was populated.
     * Once confirmed working, uncomment the cache wrapper below.
     */
    public function getAccessibleMenus()
    {
        // ⭐ ADMIN → all active menus with children
        if ($this->roles()->where('name', 'admin')->exists()) {
            return \App\Models\SidebarMenu::with(['children' => function ($q) {
                    $q->where('is_active', 1)->orderBy('sort_order')->orderBy('id');
                }, 'children.children' => function ($q) {
                    $q->where('is_active', 1)->orderBy('sort_order')->orderBy('id');
                }])
                ->whereNull('parent_id')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        // ⭐ NON-ADMIN → only assigned menus
        return $this->getRoleAssignedMenus();

        /*
        // Uncomment this block once everything works to re-enable caching:
        return cache()->remember('sidebar_menus_' . $this->id, now()->addMinutes(30), function () {
            if ($this->roles()->where('name', 'admin')->exists()) {
                return \App\Models\SidebarMenu::with('children.children')
                    ->whereNull('parent_id')
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            }
            return $this->getRoleAssignedMenus();
        });
        */
    }

    /**
     * Get menus assigned to this user via role_menus pivot.
     * If $roleName is provided, restrict to that role only.
     * If null, merge across all of the user's roles.
     * Returns a hierarchical tree with only assigned nodes.
     */
    public function getRoleAssignedMenus($roleName = null)
    {
        $query = \DB::table('role_menus')
            ->join('role_user', 'role_menus.role_id', '=', 'role_user.role_id')
            ->join('menus', 'menus.id', '=', 'role_menus.menu_id')
            ->where('role_user.user_id', $this->id)
            ->where('menus.is_active', 1);

        if ($roleName) {
            $query->join('roles', 'roles.id', '=', 'role_menus.role_id')
                  ->where('roles.name', $roleName);
        }

        $menuIds = $query->pluck('menus.id')->unique()->map(fn ($id) => (int) $id)->flip();

        if ($menuIds->isEmpty()) {
            return collect();
        }

        $menus = \App\Models\SidebarMenu::with('children.children')
            ->whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Recursively keep only assigned nodes (and their ancestors)
        $filterTree = function ($menu) use (&$filterTree, $menuIds) {
            $children = $menu->children
                ->map(fn ($child) => $filterTree($child))
                ->filter()
                ->values();

            $menu->setRelation('children', $children);

            return $menuIds->has((int) $menu->id) || $children->isNotEmpty()
                ? $menu
                : null;
        };

        return $menus->map(fn ($menu) => $filterTree($menu))
            ->filter()
            ->values();
    }

    /**
     * Check if the user has access to a specific menu (by slug or name).
     */
    public function hasMenuAccess(string $menuName): bool
    {
        $menuName = Str::slug($menuName);

        $matchesMenu = function ($menu) use (&$matchesMenu, $menuName): bool {
            $name  = Str::slug((string) ($menu->slug ?? $menu->name ?? $menu->menu_name ?? ''));
            $route = (string) ($menu->route ?? '');

            if ($name === $menuName || Str::slug(Str::before($route, '.')) === $menuName) {
                return true;
            }

            foreach ($menu->children ?? [] as $child) {
                if ($matchesMenu($child)) {
                    return true;
                }
            }

            return false;
        };

        return $this->getAccessibleMenus()->contains($matchesMenu);
    }

}
