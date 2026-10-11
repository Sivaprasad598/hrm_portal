<a @if ($active)
    {{ $attributes->merge(['class' => 'tab-item nav-item nav-link f-15 d-inline-flex align-items-center text-dark-grey active']) }}
@else
    {{ $attributes->merge(['class' => 'tab-item nav-item nav-link f-15 d-inline-flex align-items-center text-dark-grey']) }}
    @endif
    href="{{ $link }}" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}">
    {{ $slot }}
</a>
