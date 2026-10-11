{{-- Shared sub-navigation for the biometric screens.

     In the same order as the sidebar: set the devices up and say who is who,
     pull what they recorded, read it before trusting it, then put it into the
     ERP and check what went. Actions is last because it is what is left over.

     $outstanding is the number of departed people still enrolled; the badge is
     only drawn when there are any. --}}
@php
    $outstanding = $outstanding ?? app(\App\Services\Iapp\Reports\DepartedEnrolments::class)->outstanding();
@endphp

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $active === 'devices' ? 'active' : '' }}" href="{{ route('biometric.devices') }}">
            @lang('app.menu.biometricDevices')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'employees' ? 'active' : '' }}" href="{{ route('biometric.employees') }}">
            @lang('app.menu.biometricEmployees')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'comparison' ? 'active' : '' }}" href="{{ route('biometric.comparison') }}">
            @lang('app.menu.employeeComparison')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'calendar' ? 'active' : '' }}" href="{{ route('biometric.calendar') }}">
            @lang('app.menu.syncCalendar')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'punch-log' ? 'active' : '' }}" href="{{ route('biometric.punch-log') }}">
            @lang('app.menu.punchLog')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'attendance' ? 'active' : '' }}" href="{{ route('biometric.attendance') }}">
            @lang('app.menu.deviceAttendance')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'shifts' ? 'active' : '' }}" href="{{ route('biometric.shifts') }}">
            @lang('app.menu.biometricShifts')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'exceptions' ? 'active' : '' }}" href="{{ route('biometric.exceptions') }}">
            @lang('app.menu.attendanceExceptions')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'profile' ? 'active' : '' }}" href="{{ route('biometric.profile') }}">
            @lang('app.menu.attendanceProfile')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'attendance-sync' ? 'active' : '' }}" href="{{ route('biometric.attendance-sync') }}">
            @lang('app.menu.attendanceSync')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'sync' ? 'active' : '' }}" href="{{ route('biometric.sync-logs') }}">
            @lang('app.menu.biometricSync')
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'actions' ? 'active' : '' }}" href="{{ route('biometric.actions') }}">
            @lang('app.menu.biometricActions')
            @if ($outstanding > 0)
                <span class="badge badge-danger f-10 ml-1">{{ $outstanding }}</span>
            @endif
        </a>
    </li>
</ul>
