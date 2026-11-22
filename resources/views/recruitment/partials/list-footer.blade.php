{{-- The foot of a list, as the app's tables have it (Employees): "Show [10] entries" on the
     left, "Showing 1 to 10 of 15 entries" and Previous / pages / Next on the right - inside
     the list's own card. $paginator; optional $sizes. The page size is a GET parameter
     (per_page), applied in place (PageSwap.submit). --}}
@php $sizes = $sizes ?? [10, 25, 50, 100]; @endphp
@if ($paginator->total() > 0)
    <div class="rc-foot">
        <form method="GET" class="rc-foot-size">
            @foreach (request()->except(['per_page', 'page']) as $key => $value)
                @if (!is_array($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            <label for="rc-per-page">Show</label>
            <select id="rc-per-page" name="per_page" class="form-control" onchange="PageSwap.submit(this.form)">
                @foreach ($sizes as $size)
                    <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <span>entries</span>
        </form>
        <div class="rc-foot-right">
            <span class="rc-foot-info">Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries</span>
            @if ($paginator->hasPages())
                <nav class="rc-foot-pages" aria-label="Pages">
                    @foreach ($paginator->onEachSide(1)->linkCollection() as $link)
                        @php
                            $label = str_contains($link['label'], 'Previous') ? 'Previous' : (str_contains($link['label'], 'Next') ? 'Next' : $link['label']);
                        @endphp
                        @if ($link['url'] && !$link['active'])
                            <a href="{{ $link['url'] }}" class="rc-page">{{ $label }}</a>
                        @else
                            <span class="rc-page {{ $link['active'] ? 'is-active' : 'is-off' }}">{{ $label }}</span>
                        @endif
                    @endforeach
                </nav>
            @endif
        </div>
    </div>
    @once
        <style>
            .rc-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; padding: 14px 20px;
                border-top: 1px solid var(--epic-line, #eaecf0); font-size: 14px; color: var(--epic-text, #1d2939); }
            .rc-list-card > .rc-foot:first-child { border-top: 0; }
            .rc-foot-size { display: flex; align-items: center; gap: 8px; margin: 0; }
            .rc-foot-size label { margin: 0; font-weight: 600; }
            .rc-foot-size select.form-control { width: 76px; height: 36px; padding: 0 8px; border-radius: 6px; }
            .rc-foot-right { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; margin-left: auto; }
            .rc-foot-info { color: #475467; }
            .rc-foot-pages { display: flex; flex-wrap: wrap; gap: 6px; }
            .rc-page { min-width: 36px; height: 36px; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--epic-line, #eaecf0);
                border-radius: 4px; background: #fff; color: var(--epic-blue, #cb6b4f); font-size: 13.5px; }
            a.rc-page:hover { background: #f9fafb; text-decoration: none; }
            .rc-page.is-active { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; }
            .rc-page.is-off { color: #98a2b3; }
            @media (max-width: 767.98px) { .rc-foot-right { margin-left: 0; } }
        </style>
    @endonce
@endif
