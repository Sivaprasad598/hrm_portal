{{-- Table / Calendar / My Leaves. An employee has the calendar and their own
     leave; the table of everybody's is for HR / Admin and reporting managers
     (LeaveController::access()). $active: table, calendar or personal. --}}
@php $leaveAccess = \App\Http\Controllers\LeaveController::access(); @endphp
<div class="btn-group mt-2 mt-lg-0 mt-md-0 {{ $class ?? 'ml-0 ml-lg-3 ml-md-3' }}" role="group" aria-label="Leave views">
    @if ($leaveAccess['table'])
        <a href="{{ route('leaves.index') }}" class="btn btn-secondary f-14 {{ $active === 'table' ? 'btn-active' : '' }}" data-toggle="tooltip"
            data-original-title="@lang('modules.leaves.tableView')"><i class="side-icon bi bi-list-ul"></i></a>
    @endif

    <a href="{{ route('leaves.calendar') }}" class="btn btn-secondary f-14 {{ $active === 'calendar' ? 'btn-active' : '' }}" data-toggle="tooltip"
        data-original-title="@lang('app.menu.calendar')"><i class="side-icon bi bi-calendar"></i></a>

    <a href="{{ route('leaves.personal') }}" class="btn btn-secondary f-14 {{ $active === 'personal' ? 'btn-active' : '' }}" data-toggle="tooltip"
        data-original-title="@lang('modules.leaves.myLeaves')"><i class="side-icon bi bi-person"></i></a>
</div>
