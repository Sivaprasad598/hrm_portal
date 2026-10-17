{{-- Shared sub-navigation for the biometric screens.

     The everyday flow comes first, in the order things happen: Overview (where
     the device → attendance → payslip chain stands), Exceptions (days to look
     at), Device Attendance (what the device saw), Attendance Sync (what went
     into the ERP) and Employee 360 (one person, every source).

     Everything else is either set up once (Setup) or looked at when something
     needs explaining (History & tools), so it sits in two menus instead of
     eight more tabs.

     $outstanding is the number of departed people still enrolled; the badge is
     only drawn when there are any. --}}
@php
    $outstanding = $outstanding ?? app(\App\Services\Iapp\Reports\DepartedEnrolments::class)->outstanding();

    $flow = [
        'overview' => ['biometric.overview', __('app.menu.biometricOverview'), 'bi-diagram-3'],
        'review' => ['biometric.review', __('app.menu.dailyReview'), 'bi-clipboard-check'],
        'exceptions' => ['biometric.exceptions', __('app.menu.attendanceExceptions'), 'bi-exclamation-diamond'],
        'attendance' => ['biometric.attendance', __('app.menu.deviceAttendance'), 'bi-calendar-check'],
        'attendance-sync' => ['biometric.attendance-sync', __('app.menu.attendanceSync'), 'bi-box-arrow-in-right'],
        'profile' => ['biometric.profile', __('app.menu.attendanceProfile'), 'bi-person-badge'],
    ];

    $setup = [
        'employees' => ['biometric.employees', __('app.menu.biometricEmployees')],
        'devices' => ['biometric.devices', __('app.menu.biometricDevices')],
        'shifts' => ['biometric.shifts', __('app.menu.biometricShifts')],
        'settings' => ['biometric-settings.index', __('app.menu.biometricSettings')],
    ];

    $tools = [
        'punch-log' => ['biometric.punch-log', __('app.menu.punchLog')],
        'calendar' => ['biometric.calendar', __('app.menu.syncCalendar')],
        'sync' => ['biometric.sync-logs', __('app.menu.biometricSync')],
        'comparison' => ['biometric.comparison', __('app.menu.employeeComparison')],
        'actions' => ['biometric.actions', __('app.menu.biometricActions')],
    ];
@endphp

<style>
    .bio-nav { border-bottom: 1px solid #e8eef3; gap: 2px; }
    .bio-nav .nav-link { color: #667085; font-size: 14px; padding: 8px 12px; border: 0; border-bottom: 2px solid transparent; }
    .bio-nav .nav-link:hover { color: #1d2939; }
    .bio-nav .nav-link.active, .bio-nav .show > .nav-link { color: #1d82f5; border-bottom-color: #1d82f5; background: transparent; }
    .bio-nav .nav-link i { margin-right: 4px; }
    .bio-nav .dropdown-menu { font-size: 13px; }
    .bio-nav .dropdown-header { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    @media (max-width: 767.98px) {
        .bio-nav { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; }
        .bio-nav .nav-link { white-space: nowrap; }
    }

    /* One size for every field in the biometric module: dropdowns, date and
       text inputs and the buttons beside them all share --bio-field, so a
       filter row lines up whatever it holds. */
    .content-wrapper { --bio-field: 38px; }

    .content-wrapper input.form-control:not([type="checkbox"]):not([type="radio"]),
    .content-wrapper select.form-control {
        height: var(--bio-field) !important;
        font-size: 14px;
        padding-top: 0;
        padding-bottom: 0;
        border: 1px solid #d0d5dd;
        border-radius: 4px;
        box-shadow: none;
    }

    .content-wrapper input.form-control:focus { border-color: #1d82f5; box-shadow: none; }

    /* bootstrap-select copies .form-control onto its wrapper, which drew a second
       bordered, padded box round the real button - the oversized dropdown */
    .content-wrapper .bootstrap-select.form-control,
    .content-wrapper .bootstrap-select {
        width: 100% !important;
        height: auto !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .content-wrapper .bootstrap-select > .dropdown-toggle {
        height: var(--bio-field); min-height: var(--bio-field);
        padding: 0 12px; font-size: 14px; background: #fff !important; color: #344054;
        border: 1px solid #d0d5dd !important; border-radius: 4px; box-shadow: none !important;
        display: flex; align-items: center;
    }

    .content-wrapper .bootstrap-select > .dropdown-toggle:focus { border-color: #1d82f5 !important; outline: none !important; }
    .content-wrapper .bootstrap-select .filter-option { display: flex; align-items: center; }
    .content-wrapper .bootstrap-select .filter-option-inner-inner { overflow: hidden; text-overflow: ellipsis; }
    .bootstrap-select .bs-searchbox .form-control { height: 32px !important; font-size: 13px; }
    .bootstrap-select .dropdown-menu { font-size: 14px; z-index: 1060; }

    /* Buttons in a filter row are as tall as the fields beside them */
    .content-wrapper form .btn:not(.btn-xs):not(.btn-sm):not(.dropdown-toggle) {
        height: var(--bio-field);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding-top: 0;
        padding-bottom: 0;
    }

    /* Labels the same size and distance above every field */
    .content-wrapper form label { font-size: 13px; margin-bottom: 6px; }
</style>


{{-- Every dropdown on a biometric page is searchable and looks the same.
     Already-built pickers keep their own options and gain the search box;
     plain selects become pickers. The DataTables page-size selector is left
     as it is. --}}
<script>
    window.addEventListener('load', function () {
        if (!window.jQuery || !$.fn.selectpicker) {
            return;
        }

        $('.content-wrapper select').each(function () {
            let $select = $(this);

            if ($select.is('[data-no-search]') || $select.closest('.dataTables_length').length) {
                return;
            }

            let existing = $select.data('selectpicker');
            let options = $.extend({}, existing ? existing.options : {}, {
                liveSearch: true,
                liveSearchPlaceholder: 'Search…',
                noneResultsText: 'Nothing matches {0}',
                size: 8,
                width: '100%',
                container: 'body'
            });

            if (existing) {
                if (existing.options.liveSearch && existing.options.container === 'body') {
                    return;   // already searchable and outside the card
                }

                $select.selectpicker('destroy');
            }

            $select.addClass('select-picker').selectpicker(options);
        });
    });
</script>

<ul class="nav bio-nav mb-3 d-flex flex-wrap" role="tablist">
    @foreach ($flow as $key => [$route, $label, $icon])
        <li class="nav-item">
            <a class="nav-link {{ $active === $key ? 'active' : '' }}" href="{{ route($route) }}">
                <i class="bi {{ $icon }}"></i>{{ $label }}
            </a>
        </li>
    @endforeach

    <li class="nav-item dropdown ml-md-auto">
        <a class="nav-link dropdown-toggle {{ array_key_exists($active, $setup) ? 'active' : '' }}" data-toggle="dropdown" href="#" role="button">
            <i class="bi bi-gear"></i>Setup
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <h6 class="dropdown-header">Set up once</h6>
            @foreach ($setup as $key => [$route, $label])
                <a class="dropdown-item {{ $active === $key ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a>
            @endforeach
        </div>
    </li>

    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ array_key_exists($active, $tools) ? 'active' : '' }}" data-toggle="dropdown" href="#" role="button">
            <i class="bi bi-clock-history"></i>History &amp; tools
            @if ($outstanding > 0)
                <span class="badge badge-danger f-10 ml-1">{{ $outstanding }}</span>
            @endif
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <h6 class="dropdown-header">When something needs explaining</h6>
            @foreach ($tools as $key => [$route, $label])
                <a class="dropdown-item d-flex justify-content-between align-items-center {{ $active === $key ? 'active' : '' }}" href="{{ route($route) }}">
                    {{ $label }}
                    @if ($key === 'actions' && $outstanding > 0)
                        <span class="badge badge-danger f-10 ml-2">{{ $outstanding }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </li>
</ul>
