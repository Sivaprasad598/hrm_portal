@php
    $childMenus = $children();
    $childOpen = $childMenus->contains(fn ($child) => \App\View\Components\SettingMenuItem::isTreeActive($child));
@endphp

<li {{ $isActive($menu) ? 'class=active' : '' }} {{ $attributes }} >
    @if ($childMenus->isEmpty())
        <a class="d-block f-15 text-dark-grey  border-bottom-grey" href="{{ $href }}">{{ $text }}</a>
    @else
        <div class="d-flex align-items-center border-bottom-grey setting-submenu-row">
            <a class="d-block flex-grow-1 f-15 text-dark-grey" href="{{ $href }}">{{ $text }}</a>
            <button type="button" class="setting-submenu-toggle {{ $childOpen ? 'is-open' : '' }}"
                    aria-expanded="{{ $childOpen ? 'true' : 'false' }}" title="Show sub menus">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        @include('components.setting-menu-children', ['childMenus' => $childMenus, 'depth' => 1, 'open' => $childOpen])
    @endif
</li>

@once
    <style>
        .setting-submenu-toggle {
            background: transparent;
            border: 0;
            padding: 15px 20px;
            color: #99a5b5;
            cursor: pointer;
        }

        .setting-submenu-toggle i {
            display: inline-block;
            font-size: 12px;
            transition: transform .2s ease;
        }

        .setting-submenu-toggle.is-open i {
            transform: rotate(90deg);
        }

        .settings-menu .setting-submenu-children {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .settings-menu .setting-submenu-children li a {
            font-size: 14px;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .settings-menu .setting-submenu-children li.active > a,
        .settings-menu .setting-submenu-children li.active > .setting-submenu-row > a {
            font-weight: 500;
        }
    </style>

    <script>
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('.setting-submenu-toggle');
            if (!toggle) return;

            e.preventDefault();
            var children = toggle.parentElement.nextElementSibling;
            var open = children.style.display === 'none';

            children.style.display = open ? '' : 'none';
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    </script>
@endonce
