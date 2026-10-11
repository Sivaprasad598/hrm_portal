{{-- Recursive child menus (from the menus table) under a settings sidebar item --}}
<ul class="setting-submenu-children" @unless ($open) style="display: none;" @endunless>
    @foreach ($childMenus as $childMenu)
        @php
            $childUrl = \App\View\Components\SettingMenuItem::menuUrl($childMenu);
            $grandChildren = \App\View\Components\SettingMenuItem::activeChildren($childMenu);
            $childActive = $childUrl && rtrim($childUrl, '/') === rtrim(url()->current(), '/');
            $grandOpen = $grandChildren->contains(fn ($item) => \App\View\Components\SettingMenuItem::isTreeActive($item));
            $indent = 24 + $depth * 20;
        @endphp

        <li class="{{ $childActive ? 'active' : '' }}">
            @if ($grandChildren->isEmpty())
                <a class="d-block text-dark-grey border-bottom-grey" style="padding-left: {{ $indent }}px;"
                   href="{{ $childUrl ?: 'javascript:;' }}">{{ $childMenu->menu_name ?? $childMenu->name }}</a>
            @else
                <div class="d-flex align-items-center border-bottom-grey setting-submenu-row">
                    <a class="d-block flex-grow-1 text-dark-grey" style="padding-left: {{ $indent }}px;"
                       href="{{ $childUrl ?: 'javascript:;' }}">{{ $childMenu->menu_name ?? $childMenu->name }}</a>
                    <button type="button" class="setting-submenu-toggle {{ $grandOpen ? 'is-open' : '' }}"
                            aria-expanded="{{ $grandOpen ? 'true' : 'false' }}" title="Show sub menus">
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>

                @include('components.setting-menu-children', ['childMenus' => $grandChildren, 'depth' => $depth + 1, 'open' => $grandOpen])
            @endif
        </li>
    @endforeach
</ul>
