{{-- What the attendance grid's icons mean: the same icons as the grid, as chips.
     $only: the keys to show (By Hour shows three); all of them by default. --}}
@php
    $legend = [
        'holiday' => ['fa-star', 'att-lg-holiday', __('app.menu.holiday')],
        'dayoff' => ['fa-calendar-week', 'att-lg-dayoff', __('modules.attendance.dayOff')],
        'present' => ['fa-check', 'att-lg-present', __('modules.attendance.present')],
        'wfh' => ['fa-home', 'att-lg-wfh', __('modules.attendance.workFromHomeLegend')],
        'half' => ['fa-star-half-alt', 'att-lg-half', __('modules.attendance.halfDay')],
        'absent' => ['fa-times', 'att-lg-absent', __('modules.attendance.absent')],
        'leave' => ['fa-plane-departure', 'att-lg-leave', __('modules.attendance.leave')],
    ];
    if (!empty($only)) {
        $legend = array_intersect_key($legend, array_flip($only));
    }
@endphp
<div class="att-legend">
    <span class="att-legend__title"><i class="fa fa-info-circle mr-1"></i>Legend</span>
    @foreach ($legend as [$icon, $class, $label])
        <span class="att-legend__item {{ $class }}"><span class="att-legend__icon"><i class="fa {{ $icon }}"></i></span>{{ $label }}</span>
    @endforeach
</div>
