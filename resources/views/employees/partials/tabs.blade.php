{{-- The tabs across an employee's page: shared by the profile (employees.show)
     and the attendance tab, which draws its own page.

     Kept to what is used: Emergency Contacts are on the profile, and
     Appreciation, Increment & Promotions, Shift Roster and Activity are gone. --}}
@php
    $tabEmployeeProjects = user()->permission('view_employee_projects');
    $tabEmployeeTasks = user()->permission('view_employee_tasks');
    $tabAttendance = user()->permission('view_attendance');
    $tabLeave = user()->permission('view_leave');
    $tabTimelogs = user()->permission('view_employee_timelogs');
    $tabDocuments = user()->permission('view_documents');
    $tabTickets = user()->permission('view_tickets');
    $tabRolePermissions = user()->permission('manage_role_permission_setting');
    $tabImmigration = user()->permission('view_immigration');
    $tabUrl = fn ($tab) => route('employees.show', $employee->id) . '?tab=' . $tab;
@endphp

<ul class="-primary">
    <li>
        <x-tab :href="route('employees.show', $employee->id)" :text="__('modules.employees.profile')" class="profile" />
    </li>

    @if ($tabEmployeeProjects == 'all' && in_array('projects', user_modules()))
        <li><x-tab :href="$tabUrl('projects')" :text="__('app.menu.projects')" ajax="false" class="projects" /></li>
    @endif

    @if ($tabEmployeeTasks == 'all' && in_array('tasks', user_modules()))
        <li><x-tab :href="$tabUrl('tasks')" :text="__('app.menu.tasks')" ajax="false" class="tasks" /></li>
    @endif

    @if ($tabAttendance != 'none' && $tabAttendance != 5 && in_array('attendance', user_modules()))
        <li><x-tab :href="$tabUrl('attendance')" :text="__('app.menu.attendance')" ajax="false" class="attendance" /></li>
    @endif

    @if (in_array('leaves', user_modules()) && ($tabLeave == 'all' || ($tabLeave == 'owned' || $tabLeave == 'both') && $employee->id == user()->id))
        <li><x-tab :href="$tabUrl('leaves')" text="Leave History" ajax="false" class="leaves" /></li>
        <li><x-tab :href="$tabUrl('leaves-quota')" text="Leaves Balance" class="leaves-quota" /></li>
    @endif

    @if ($tabTimelogs == 'all' && in_array('timelogs', user_modules()))
        <li><x-tab :href="$tabUrl('timelogs')" :text="__('app.menu.timeLogs')" ajax="false" class="timelogs" /></li>
    @endif

    @if ($tabDocuments != 'none')
        <li><x-tab :href="$tabUrl('documents')" :text="__('app.menu.documents')" class="documents" /></li>
    @endif

    @if ($tabTickets == 'all' && in_array('tickets', user_modules()))
        <li><x-tab :href="$tabUrl('tickets')" :text="__('modules.tickets.ticket')" ajax="false" class="tickets" /></li>
    @endif

    @if (\Illuminate\Support\Facades\Schema::hasTable('ot_ledger')
        && (\App\Services\Access\PayrollAccess::any() || $employee->id == user()->id))
        <li><x-tab :href="$tabUrl('ot-bank')" text="OT Bank" class="ot-bank" /></li>
    @endif

    @if (user()->permission('manage_assets') === 'all' || $employee->id == user()->id)
        <li><x-tab :href="$tabUrl('assets')" text="Assets" class="assets" /></li>
    @endif

    @if ($tabRolePermissions == 'all')
        <li><x-tab :href="$tabUrl('permissions')" :text="__('modules.permission.permissions')" class="permissions" /></li>
    @endif

    @if ($tabImmigration == 'all' || (in_array($tabImmigration, ['added', 'owned', 'both']) && user()->id == $employee->id))
        <li><x-tab :href="$tabUrl('immigration')" :text="__('modules.employees.immigration')" class="immigration" /></li>
    @endif
</ul>
