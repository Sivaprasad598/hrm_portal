{{-- Shared sub-navigation for the biometric screens.

     Four tabs for what is looked at every day: Overview (where the nightly run
     stands), Daily Review (each employee's day), Exceptions (what the nightly
     cross-check found) and Employee 360 (one person, every source).

     Everything behind that - the data the run produces, the configuration and
     the history - sits in one Setup menu, grouped.

     $outstanding is the number of departed people still enrolled; the badge is
     only drawn when there are any. --}}
@php
    $outstanding = $outstanding ?? app(\App\Services\Iapp\Reports\DepartedEnrolments::class)->outstanding();
    $openExceptions = \Illuminate\Support\Facades\Schema::hasTable('biometric_exceptions')
        ? \App\Models\BiometricException::where('status', 'open')->count() : 0;

    $flow = [
        'overview' => ['biometric.overview', __('app.menu.biometricOverview'), 'bi-diagram-3'],
        'review' => ['biometric.review', __('app.menu.dailyReview'), 'bi-clipboard-check'],
        'exceptions' => ['biometric.exceptions', __('app.menu.attendanceExceptions'), 'bi-exclamation-diamond'],
        'profile' => ['biometric.profile', __('app.menu.attendanceProfile'), 'bi-person-badge'],
    ];

    $setup = [
        'Data' => [
            'attendance' => ['biometric.attendance', __('app.menu.deviceAttendance')],
            'attendance-sync' => ['biometric.attendance-sync', __('app.menu.attendanceSync')],
            'punch-log' => ['biometric.punch-log', __('app.menu.punchLog')],
        ],
        'Configure' => [
            'settings' => ['biometric-settings.index', __('app.menu.biometricSettings')],
            'devices' => ['biometric.devices', __('app.menu.biometricDevices')],
            'employees' => ['biometric.employees', __('app.menu.biometricEmployees')],
            'shifts' => ['biometric.shifts', __('app.menu.biometricShifts')],
        ],
        'History & tools' => [
            'calendar' => ['biometric.calendar', 'Sync Calendar & re-sync'],
            'sync' => ['biometric.sync-logs', __('app.menu.biometricSync')],
            'comparison' => ['biometric.comparison', __('app.menu.employeeComparison')],
            'actions' => ['biometric.actions', 'Departed enrolments'],
        ],
    ];
    $setupKeys = array_merge(...array_map('array_keys', array_values($setup)));
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
        width: 100%;
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
    /* Text centred in the box whatever size class the select carried
       (form-control-sm pushed it to the top of the taller field) */
    .content-wrapper .bootstrap-select > .dropdown-toggle,
    .content-wrapper .bootstrap-select.form-control-sm > .dropdown-toggle,
    .content-wrapper .bootstrap-select > .btn-sm {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        line-height: normal !important;
    }
    /* bootstrap-select floats this box, and a float is always a plain block,
       so it never centred - the text sat at the top of the field */
    .content-wrapper .bootstrap-select .filter-option {
        float: none !important;
        display: flex !important; align-items: center; height: 100%;
        position: static; padding: 0; top: 0; flex: 1 1 auto; min-width: 0;
    }
    .content-wrapper .bootstrap-select .filter-option-inner { width: 100%; padding: 0; }
    .content-wrapper .bootstrap-select .filter-option-inner-inner { line-height: 1.4; }
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

    /* A picker that sits in a row of buttons (a month switcher, say) keeps its
       own width instead of taking the whole line and pushing the buttons off it */
    .content-wrapper .bootstrap-select.bio-inline { width: auto !important; min-width: 180px; display: inline-block; }

    /* Buttons in a card header line up with the fields beside them */
    .content-wrapper .card-header .btn {
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

            // A select that is not filling a form column (w-auto, or sitting in a
            // flex row of buttons) stays as wide as its contents.
            let inline = $select.hasClass('w-auto')
                || ($select.parent().hasClass('d-flex') || $select.parent().hasClass('card-header'))
                && !$select.closest('[class*="col-"]').length;

            let existing = $select.data('selectpicker');
            let options = $.extend({}, existing ? existing.options : {}, {
                liveSearch: true,
                liveSearchPlaceholder: 'Search…',
                noneResultsText: 'Nothing matches {0}',
                size: 8,
                width: inline ? 'fit' : '100%',
                container: 'body'
            });

            if (existing) {
                if (existing.options.liveSearch && existing.options.container === 'body') {
                    return;   // already searchable and outside the card
                }

                $select.selectpicker('destroy');
            }

            $select.addClass('select-picker').selectpicker(options);

            if (inline) {
                $select.closest('.bootstrap-select').addClass('bio-inline');
            }
        });
    });
</script>

<ul class="nav bio-nav mb-3 d-flex flex-wrap" role="tablist">
    @foreach ($flow as $key => [$route, $label, $icon])
        <li class="nav-item">
            <a class="nav-link {{ $active === $key ? 'active' : '' }}" href="{{ route($route) }}">
                <i class="bi {{ $icon }}"></i>{{ $label }}
                @if ($key === 'exceptions' && $openExceptions > 0)
                    <span class="badge badge-danger f-10 ml-1">{{ $openExceptions }}</span>
                @endif
            </a>
        </li>
    @endforeach

    <li class="nav-item dropdown ml-md-auto">
        <a class="nav-link dropdown-toggle {{ in_array($active, $setupKeys, true) ? 'active' : '' }}" data-toggle="dropdown" href="#" role="button">
            <i class="bi bi-gear"></i>Setup
            @if ($outstanding > 0)
                <span class="badge badge-danger f-10 ml-1">{{ $outstanding }}</span>
            @endif
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            @foreach ($setup as $group => $items)
                @if (!$loop->first)<div class="dropdown-divider"></div>@endif
                <h6 class="dropdown-header">{{ $group }}</h6>
                @foreach ($items as $key => [$route, $label])
                    <a class="dropdown-item d-flex justify-content-between align-items-center {{ $active === $key ? 'active' : '' }}" href="{{ route($route) }}">
                        {{ $label }}
                        @if ($key === 'actions' && $outstanding > 0)
                            <span class="badge badge-danger f-10 ml-2">{{ $outstanding }}</span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </div>
    </li>
</ul>
