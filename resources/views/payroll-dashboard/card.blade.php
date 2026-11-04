{{-- One dashboard card: headline, the lines that make it up, and a click for the full list. --}}
@php
    $fmt = function ($line) {
        return match ($line[2] ?? 'money') {
            'hours' => rtrim(rtrim(number_format((float) $line[1], 2), '0'), '.') . 'h',
            'days' => rtrim(rtrim(number_format((float) $line[1], 2), '0'), '.') . ' d',
            'count' => (string) (int) $line[1],
            default => '₹' . number_format((float) $line[1], 2),
        };
    };
    $lines = array_values(array_filter($card['lines'], fn ($line) => abs((float) $line[1]) >= 0.005));
@endphp
<div class="pd-card pd-{{ $card['tone'] }}" role="button" tabindex="0" data-card="{{ $card['key'] }}" data-title="{{ $card['title'] }}">
    <div class="pd-card__top">
        <div class="pd-card__label">{{ $card['title'] }}</div>
        <span class="pd-card__icon"><i class="fa {{ $card['icon'] }}"></i></span>
    </div>
    <div class="pd-card__value">₹{{ number_format((float) $card['value'], 2) }}</div>
    <div class="pd-card__sub">{{ $card['sub'] }}</div>
    @if ($lines)
        <div class="pd-card__lines">
            @foreach ($lines as $line)
                <div class="pd-card__line"><span title="{{ $line[0] }}">{{ $line[0] }}</span><span>{{ $fmt($line) }}</span></div>
            @endforeach
        </div>
    @endif
    <div class="pd-card__more"><span>View list</span><i class="fa fa-arrow-right"></i></div>
</div>
