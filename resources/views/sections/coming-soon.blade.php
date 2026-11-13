{{-- "Coming soon": a grey overlay over the page content of modules not open yet
     (Assets, Work). The sidebar stays usable so people can leave. To open a
     module again, take its routes out of the list below. --}}
@php
    $comingSoonRoutes = [
        // Assets
        'assets.*', 'asset-types.*',
        // Work
        'contracts.*', 'projects.*', 'tasks.*', 'timelogs.*', 'weekly-timesheets.*', 'taskboards.*',
    ];
@endphp

@if (request()->routeIs(...$comingSoonRoutes))
    <div class="coming-soon" role="dialog" aria-label="Coming soon">
        <div class="coming-soon__card">
            {{-- the illustration: a rocket rising over a gently pulsing ring --}}
            <svg class="coming-soon__art" viewBox="0 0 200 200" aria-hidden="true">
                <circle class="cs-ring cs-ring--1" cx="100" cy="100" r="70" />
                <circle class="cs-ring cs-ring--2" cx="100" cy="100" r="88" />
                <g class="cs-rocket">
                    <path d="M100 38c18 14 26 36 22 62l-8 10H86l-8-10c-4-26 4-48 22-62z" class="cs-body" />
                    <circle cx="100" cy="74" r="9" class="cs-window" />
                    <path d="M78 96l-14 18 18-2zM122 96l14 18-18-2z" class="cs-fin" />
                    <path d="M90 112h20l-4 12h-12z" class="cs-base" />
                    <path d="M94 126c2 10 4 18 6 26 2-8 4-16 6-26z" class="cs-flame" />
                </g>
                <circle class="cs-star cs-star--1" cx="44" cy="54" r="2.5" />
                <circle class="cs-star cs-star--2" cx="160" cy="46" r="2" />
                <circle class="cs-star cs-star--3" cx="154" cy="150" r="2.5" />
                <circle class="cs-star cs-star--4" cx="40" cy="146" r="2" />
            </svg>
            <h2 class="coming-soon__title">Coming soon</h2>
            <p class="coming-soon__text">We're putting the finishing touches on this module. It will be available here shortly.</p>
            <span class="coming-soon__dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>
    </div>

    <style>
        /* over the page content only (the sidebar on the left stays usable) */
        #fullscreen { position: relative; }
        .coming-soon { position: absolute; inset: 0; z-index: 1040; min-height: calc(100vh - 64px);
            background: rgba(100, 108, 120, .55); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
            display: flex; align-items: flex-start; justify-content: center; padding: 12vh 16px 16px; }
        #fullscreen > *:not(.coming-soon) { pointer-events: none; user-select: none; }
        .coming-soon__card { width: 100%; max-width: 420px; text-align: center; background: #fff; border-radius: 18px;
            padding: 30px 28px 26px; box-shadow: 0 24px 60px rgba(16, 24, 40, .25); animation: cs-in .5s ease-out both; }
        .coming-soon__art { width: 150px; height: 150px; display: block; margin: 0 auto 6px; }
        .cs-ring { fill: none; stroke: color-mix(in srgb, var(--epic-blue, #cb6b4f) 30%, #fff); stroke-width: 2; transform-origin: 100px 100px; }
        .cs-ring--1 { animation: cs-pulse 2.4s ease-in-out infinite; }
        .cs-ring--2 { stroke-dasharray: 6 10; animation: cs-spin 18s linear infinite; }
        .cs-rocket { animation: cs-float 2.6s ease-in-out infinite; transform-origin: 100px 100px; }
        .cs-body { fill: var(--epic-blue, #cb6b4f); }
        .cs-window { fill: #fff; stroke: color-mix(in srgb, var(--epic-blue, #cb6b4f) 60%, #000); stroke-width: 2; }
        .cs-fin { fill: color-mix(in srgb, var(--epic-blue, #cb6b4f) 70%, #000); }
        .cs-base { fill: #475467; }
        .cs-flame { fill: #f79009; transform-origin: 100px 126px; animation: cs-flame .35s ease-in-out infinite alternate; }
        .cs-star { fill: #98a2b3; animation: cs-twinkle 1.8s ease-in-out infinite; }
        .cs-star--2 { animation-delay: .4s; } .cs-star--3 { animation-delay: .9s; } .cs-star--4 { animation-delay: 1.3s; }
        .coming-soon__title { font-size: 26px; font-weight: 700; color: #101828; margin: 4px 0 6px; letter-spacing: -.01em; }
        .coming-soon__text { font-size: 14px; color: #667085; margin: 0 0 16px; line-height: 1.55; }
        .coming-soon__dots { display: inline-flex; gap: 6px; }
        .coming-soon__dots i { width: 8px; height: 8px; border-radius: 50%; background: var(--epic-blue, #cb6b4f); animation: cs-dot 1.2s ease-in-out infinite; }
        .coming-soon__dots i:nth-child(2) { animation-delay: .15s; } .coming-soon__dots i:nth-child(3) { animation-delay: .3s; }
        @keyframes cs-in { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
        @keyframes cs-float { 0%, 100% { transform: translateY(4px); } 50% { transform: translateY(-6px); } }
        @keyframes cs-flame { from { transform: scaleY(.75); opacity: .8; } to { transform: scaleY(1.15); opacity: 1; } }
        @keyframes cs-pulse { 0%, 100% { transform: scale(.94); opacity: .6; } 50% { transform: scale(1.04); opacity: 1; } }
        @keyframes cs-spin { to { transform: rotate(360deg); } }
        @keyframes cs-twinkle { 0%, 100% { opacity: .25; } 50% { opacity: 1; } }
        @keyframes cs-dot { 0%, 80%, 100% { transform: scale(.6); opacity: .4; } 40% { transform: scale(1); opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .coming-soon *, .coming-soon__card { animation: none !important; } }
    </style>
@endif
