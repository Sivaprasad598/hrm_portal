<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Module;
use App\Models\Company;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\UserPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('module_name', 'employees')->first();

        if ($module) {

            $permissions = [
                [
                    'module_id' => $module->id,
                    'name' => 'view_employee_menu',
                    'display_name' => 'View Employee Menu',
                    'allowed_permissions' => Permission::ALL_NONE,
                    'is_custom' => 1
                ]
            ];

            $companies = Company::select('id')->get();

            foreach ($permissions as $permissionData) {
                $permission = Permission::updateOrCreate(
                    [
                        'name' => $permissionData['name'],
                        'module_id' => $permissionData['module_id'],
                        'display_name' => $permissionData['display_name'],
                        'is_custom' => $permissionData['is_custom'],
                        'allowed_permissions' => $permissionData['allowed_permissions'],
                    ]
                );

                foreach ($companies as $company) {
                    // Add permission to admin role with "All" access
                    $adminRole = Role::where('name', 'admin')
                        ->where('company_id', $company->id)
                        ->first();

                    if ($adminRole) {
                        $permissionRole = PermissionRole::where('permission_id', $permission->id)
                            ->where('role_id', $adminRole->id)
                            ->first();

                        $permissionRole = $permissionRole ?: new PermissionRole();
                        $permissionRole->permission_id = $permission->id;
                        $permissionRole->role_id = $adminRole->id;
                        $permissionRole->permission_type_id = 4; // All
                        $permissionRole->save();
                    }

                    // Add permission to employee role 
                    $employeeRole = Role::with('roleuser.user.role')->where('name', 'employee')
                        ->where('company_id', $company->id)
                        ->first();

                    if ($employeeRole) {
                        $permissionRole = PermissionRole::where('permission_id', $permission->id)
                            ->where('role_id', $employeeRole->id)
                            ->first();

                        $permissionRole = $permissionRole ?: new PermissionRole();
                        $permissionRole->permission_id = $permission->id;
                        $permissionRole->role_id = $employeeRole->id;
                        $permissionRole->permission_type_id = 4;
                        $permissionRole->save();
                        
                        // Add user permissions for employees who don't have customized permissions
                        foreach ($employeeRole->roleuser as $roleuser) {
                            if ($roleuser->user && count($roleuser->user->role) == 1 && $roleuser->user->customised_permissions == 0) {
                                $userPermission = UserPermission::where('user_id', $roleuser->user->id)
                                    ->where('permission_id', $permission->id)
                                    ->first() ?: new UserPermission();
                                $userPermission->user_id = $roleuser->user->id;
                                $userPermission->permission_id = $permission->id;
                                $userPermission->permission_type_id = 4; // All
                                $userPermission->save();
                                
                                // Clear cache for this user
                                cache()->forget('sidebar_user_perms_' . $roleuser->user->id);
                            }
                        }
                    }

                    // Add permission to custom roles (roles created from employee role)
                    $customRoles = Role::with('roleuser.user')->where('company_id', $company->id)
                        ->whereNotIn('name', ['admin', 'client', 'employee'])
                        ->get();

                    foreach ($customRoles as $customRole) {
                        $permissionRole = PermissionRole::where('permission_id', $permission->id)
                            ->where('role_id', $customRole->id)
                            ->first();

                        $permissionRole = $permissionRole ?: new PermissionRole();
                        $permissionRole->permission_id = $permission->id;
                        $permissionRole->role_id = $customRole->id;
                        $permissionRole->permission_type_id = 4;
                        $permissionRole->save();
                        
                        // Add user permissions for custom role users who don't have customized permissions
                        foreach ($customRole->roleuser as $roleuser) {
                            if ($roleuser->user && $roleuser->user->customised_permissions == 0) {
                                $userPermission = UserPermission::where('user_id', $roleuser->user->id)
                                    ->where('permission_id', $permission->id)
                                    ->first() ?: new UserPermission();
                                $userPermission->user_id = $roleuser->user->id;
                                $userPermission->permission_id = $permission->id;
                                $userPermission->permission_type_id = 4; // All
                                $userPermission->save();
                                
                                // Clear cache for this user
                                cache()->forget('sidebar_user_perms_' . $roleuser->user->id);
                            }
                        }
                    }
                }

                // Add permission to all admin users
                $adminUsers = User::allAdmins();

                foreach ($adminUsers as $adminUser) {
                    $userPermission = UserPermission::where('user_id', $adminUser->id)
                        ->where('permission_id', $permission->id)
                        ->first() ?: new UserPermission();
                    $userPermission->user_id = $adminUser->id;
                    $userPermission->permission_id = $permission->id;
                    $userPermission->permission_type_id = 4; // All
                    $userPermission->save();
                    
                    // Clear cache for this user
                    cache()->forget('sidebar_user_perms_' . $adminUser->id);
                }
            }
        }
        
        // Clear application cache
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('module_name', 'employees')->first();

        if (!is_null($module)) {
            $permissions = ['view_employee_menu'];

            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('module_id', $module->id)->first();

                if ($permission) {
                    PermissionRole::where('permission_id', $permission->id)->delete();

                    UserPermission::where('permission_id', $permission->id)->delete();

                    $permission->delete();
                }
            }
        }
    }
};
