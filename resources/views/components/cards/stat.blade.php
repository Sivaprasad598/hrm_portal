@props([
    'label',
    'value',
    'icon' => 'chart-bar',
    'accent' => 'indigo',
    'info' => null,
    'href' => null,
    'linkId' => null,
    'linkClass' => null,
    'linkData' => [],
    'subValue' => null,
    'subLabel' => null,
    'subInfo' => null,
    'subLinkClass' => null,
    'subLinkData' => [],
])

{{--
    One figure on a dashboard.

    Written rather than re-skinned. The widget this replaces renders its number
    inside a <span> and its caption inside another, so a stylesheet cannot tell
    the two apart — restyling it by selector made the Employees card set its
    caption in the same 26px as its number and wrap over three lines. Here the
    number and the caption are different elements with different names, so they
    can be styled without guessing.

    A card may carry a second, lesser figure — Employees shows its total and how
    many are new — which is what $subValue is for.
--}}

@php
    $body = $linkId || $linkClass ? 'a' : 'div';
@endphp

<div class="arw-stat arw-stat--{{ $accent }}">
    <{{ $body }}
        class="arw-stat__main {{ $linkClass }}"
        @if ($body === 'a') href="{{ $href ?: 'javascript:;' }}" @endif
        @if ($linkId) id="{{ $linkId }}" @endif
        @foreach ($linkData as $key => $dataValue) data-{{ $key }}="{{ $dataValue }}" @endforeach
    >
        <span class="arw-stat__label">
            {{ $label }}
            @if ($info)
                <i class="fa fa-question-circle arw-stat__info" data-toggle="popover" data-placement="top"
                   data-content="{{ $info }}" data-html="true" data-trigger="hover"></i>
            @endif
        </span>
        <span class="arw-stat__value">{{ $value }}</span>
    </{{ $body }}>

    @if (!is_null($subValue))
        <a class="arw-stat__sub {{ $subLinkClass }}" href="javascript:;"
           @foreach ($subLinkData as $key => $dataValue) data-{{ $key }}="{{ $dataValue }}" @endforeach
        >
            <span class="arw-stat__sub-value">{{ $subValue }}</span>
            <span class="arw-stat__sub-label">
                {{ $subLabel }}
                @if ($subInfo)
                    <i class="fa fa-question-circle arw-stat__info" data-toggle="popover" data-placement="top"
                       data-content="{{ $subInfo }}" data-html="true" data-trigger="hover"></i>
                @endif
            </span>
        </a>
    @endif

    <span class="arw-stat__icon"><i class="fa fa-{{ $icon }}"></i></span>
</div>
