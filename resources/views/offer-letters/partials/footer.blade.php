{{-- The letterhead's footer (Letter Settings -> Footer), shared by the offer and relieving letters. --}}
<div class="foot">
    <div class="co">{{ $s['footer_company'] }}</div>
    <div class="addr">{{ $s['footer_address'] }}</div>
    <table><tr>
        <td style="text-align:left"><span class="sym">&#9742;</span> {{ $s['footer_phone'] }}</td>
        <td>{{ $s['footer_website'] }}</td>
        <td style="text-align:right"><span class="sym">&#9993;</span> {{ $s['footer_email'] }}</td>
    </tr></table>
    <div class="bar"></div>
</div>
