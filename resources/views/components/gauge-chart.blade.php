@php
    $progressValue = (float) $value;
    // Ensure value is between 0 and 100 for gauge chart
    $gaugeValue = max(0.1, min(99.9, $progressValue));
@endphp

@if ($progressValue == 0)
    <div class="text-lightest">0% @lang('app.progress')</div>
@elseif ($progressValue == 100)
    <div class="text-lightest">100% @lang('app.progress')</div>
@else
    <div {{ $attributes }}></div>
    <script>
        // Element inside which you want to see the chart
        var elementGauge = document.querySelector("#{{ $attributes['id'] }}")

        // Ensure value is within valid range for arcDelimiters
        var gaugeValue = {{ $value }};
        var delimiterValue = gaugeValue;
        
        // Adjust 100% to 99.99 to stay within valid range
        if (gaugeValue >= 100) {
            delimiterValue = 99.99;
        }
        
        // Ensure minimum value for delimiters
        if (gaugeValue <= 0) {
            delimiterValue = 0.01;
        }

        // Properties of the gauge
        var gaugeOptions = {
            hasNeedle: false,
            needleColor: 'gray',
            needleUpdateSpeed: 1000,
            arcColors: ['rgb(44, 177, 0)', 'rgb(232, 238, 243)'],
            arcDelimiters: [{{ $gaugeValue }}],
            rangeLabel: ['0', '100'],
            centralLabel: '{{ $progressValue }}%'
        }
        
        // Drawing and updating the chart
        GaugeChart.gaugeChart(elementGauge, {{ $width }}, gaugeOptions).updateNeedle({{ $gaugeValue }});

    </script>
@endif

  
