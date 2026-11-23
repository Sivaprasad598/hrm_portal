{{-- One chart (Frappe Charts, already used by the app's dashboards): $id, $type (bar | line | axis-mixed |
     percentage | pie | donut), $labels, $datasets [[name, values, chartType?]], optional $colors, $height,
     $stacked, $format ('money' | 'hours' | '%'). Drawn when the page (or the swapped-in content) is ready. --}}
@php
    $config = [
        'type' => $type ?? 'bar',
        'height' => $height ?? 260,
        'data' => ['labels' => array_values($labels), 'datasets' => array_map(fn ($d) => array_filter(['name' => $d[0], 'values' => array_values(array_map(fn ($v) => round((float) $v, 1), $d[1])), 'chartType' => $d[2] ?? null]), $datasets)],
        'colors' => $colors ?? ['#cb6b4f', '#3d78e3', '#12b76a', '#f79009', '#7a5af8', '#0ba5ec', '#ee46bc', '#98a2b3'],
        'barOptions' => ['stacked' => !empty($stacked), 'spaceRatio' => 0.4],
        'lineOptions' => ['regionFill' => ($fill ?? true) ? 1 : 0, 'hideDots' => 0, 'dotSize' => 3],
        'axisOptions' => ['xIsSeries' => 1, 'xAxisMode' => 'tick', 'yAxisMode' => 'span'],
        'tooltipOptions' => [],
    ];
    $fmtJs = $format ?? '';
@endphp
<div id="{{ $id }}" class="pt-chart"></div>
<script>
    (function () {
        var config = @json($config);
        var format = @json($fmtJs);
        if (format) {
            config.tooltipOptions = { formatTooltipY: function (v) {
                if (v === null || v === undefined) { return ''; }
                if (format === 'money') { return '₹' + Number(v).toLocaleString('en-IN'); }
                return Number(v).toLocaleString('en-IN') + (format === 'hours' ? ' h' : format);
            } };
            config.formatTooltipY = config.tooltipOptions.formatTooltipY; // donut / pie legends read it here
        }
        function draw() {
            var el = document.getElementById(@json($id));
            if (!el || !window.frappe || !frappe.Chart) { return false; }
            el.innerHTML = '';
            new frappe.Chart(el, config);
            return true;
        }
        if (!draw()) {
            // the chart library loads once per visit; this page may arrive before it
            var s = document.querySelector('script[data-frappe-charts]');
            if (!s) {
                s = document.createElement('script');
                s.src = @json(asset('vendor/jquery/frappe-charts.min.iife.js'));
                s.setAttribute('data-frappe-charts', '1');
                document.head.appendChild(s);
            }
            s.addEventListener('load', draw);
        }
    })();
</script>
