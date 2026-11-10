{{-- What the attendance grid's colours mean: a solid dot per status, the same
     colours the grid's day cells show (public/css/epic/theme.css, .att-dot).
     $only: the keys to show (By Hour shows three); all of them by default. --}}
@php
    $legend = [
        'present' => ['present', __('modules.attendance.present')],
        'wfh' => ['wfh', __('modules.attendance.workFromHomeLegend')],
        'half' => ['half', __('modules.attendance.halfDay')],
        'leave' => ['leave', __('modules.attendance.leave')],
        'absent' => ['absent', __('modules.attendance.absent')],
        'holiday' => ['holiday', __('app.menu.holiday')],
        'dayoff' => ['dayoff', __('modules.attendance.dayOff')],
    ];
    if (!empty($only)) {
        $legend = array_intersect_key($legend, array_flip($only));
    }
@endphp
<div class="att-legend">
    <span class="att-legend__title"><i class="fa fa-info-circle mr-1"></i>Legend</span>
    @foreach ($legend as [$key, $label])
        <span class="att-legend__item"><span class="att-dot att-dot--{{ $key }}"></span>{{ $label }}</span>
    @endforeach
</div>
