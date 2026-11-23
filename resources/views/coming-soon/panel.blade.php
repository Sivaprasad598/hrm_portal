{{-- A module marked Coming soon (Settings -> Menu Access -> Modules): its pages show this.
     Also the body of a side panel opened from one of them. The rocket is the one the older
     Coming soon overlay used (sections/coming-soon). --}}
<div class="cso">
    <span class="cso-glow cso-glow--1" aria-hidden="true"></span>
    <span class="cso-glow cso-glow--2" aria-hidden="true"></span>
    <div class="cso-card">
        <svg class="cso-art" viewBox="0 0 200 200" aria-hidden="true">
            <circle class="cso-ring cso-ring--1" cx="100" cy="100" r="70" />
            <circle class="cso-ring cso-ring--2" cx="100" cy="100" r="88" />
            <g class="cso-rocket">
                <path d="M100 38c18 14 26 36 22 62l-8 10H86l-8-10c-4-26 4-48 22-62z" class="cso-body" />
                <circle cx="100" cy="74" r="9" class="cso-window" />
                <path d="M78 96l-14 18 18-2zM122 96l14 18-18-2z" class="cso-fin" />
                <path d="M90 112h20l-4 12h-12z" class="cso-base" />
                <path d="M94 126c2 10 4 18 6 26 2-8 4-16 6-26z" class="cso-flame" />
            </g>
            <circle class="cso-star cso-star--1" cx="44" cy="54" r="2.5" />
            <circle class="cso-star cso-star--2" cx="160" cy="46" r="2" />
            <circle class="cso-star cso-star--3" cx="154" cy="150" r="2.5" />
            <circle class="cso-star cso-star--4" cx="40" cy="146" r="2" />
        </svg>
        <span class="cso-tag">Coming soon</span>
        <h2 class="cso-title">{{ $comingSoonModule }} is on its way</h2>
        <p class="cso-text">We're putting the finishing touches on this module. It will be available here shortly.</p>
        <span class="cso-dots" aria-hidden="true"><i></i><i></i><i></i></span>
        <div class="cso-actions">
            <a href="{{ route('dashboard') }}" class="btn btn-primary rounded f-14"><i class="fa fa-home mr-1"></i>Back to Dashboard</a>
        </div>
    </div>
</div>
<style>
    /* the stage behind the card: a soft wash of the theme colour, a fading dot grid, two drifting glows */
    .cso { position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;
        min-height: calc(100vh - 104px); padding: 40px 16px; border-radius: 20px;
        background:
            radial-gradient(ellipse at 50% 0%, color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, transparent), transparent 60%),
            linear-gradient(180deg, color-mix(in srgb, var(--epic-blue, #cb6b4f) 6%, #fff) 0%, #f8f9fb 100%); }
    .cso::before { content: ''; position: absolute; inset: 0; pointer-events: none;
        background-image: radial-gradient(color-mix(in srgb, var(--epic-blue, #cb6b4f) 22%, transparent) 1.2px, transparent 1.4px);
        background-size: 22px 22px;
        -webkit-mask-image: radial-gradient(ellipse at center, #000 20%, transparent 70%); mask-image: radial-gradient(ellipse at center, #000 20%, transparent 70%); }
    .cso-glow { position: absolute; width: 380px; height: 380px; border-radius: 50%; filter: blur(70px); opacity: .55; pointer-events: none; }
    .cso-glow--1 { top: -120px; left: 8%; background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 45%, #fff); animation: cso-drift 14s ease-in-out infinite; }
    .cso-glow--2 { bottom: -140px; right: 6%; background: #c7d7fe; animation: cso-drift 18s ease-in-out infinite reverse; }
    .cso-card { position: relative; z-index: 1; width: 100%; max-width: 460px; text-align: center; border-radius: 20px; padding: 30px 28px 28px;
        background: rgba(255, 255, 255, .86); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, .9); box-shadow: 0 20px 50px rgba(16, 24, 40, .12), 0 0 0 1px rgba(16, 24, 40, .03);
        animation: cso-in .5s ease-out both; }
    .modal .cso, .RightModal .cso, #task-detail-section .cso { min-height: 0; border-radius: 0; }
    .cso-art { width: 160px; height: 160px; display: block; margin: 0 auto 4px; }
    .cso-ring { fill: none; stroke: color-mix(in srgb, var(--epic-blue, #cb6b4f) 30%, #fff); stroke-width: 2; transform-origin: 100px 100px; }
    .cso-ring--1 { animation: cso-pulse 2.4s ease-in-out infinite; }
    .cso-ring--2 { stroke-dasharray: 6 10; animation: cso-spin 18s linear infinite; }
    .cso-rocket { animation: cso-float 2.6s ease-in-out infinite; transform-origin: 100px 100px; }
    .cso-body { fill: var(--epic-blue, #cb6b4f); }
    .cso-window { fill: #fff; stroke: color-mix(in srgb, var(--epic-blue, #cb6b4f) 60%, #000); stroke-width: 2; }
    .cso-fin { fill: color-mix(in srgb, var(--epic-blue, #cb6b4f) 70%, #000); }
    .cso-base { fill: #475467; }
    .cso-flame { fill: #f79009; transform-origin: 100px 126px; animation: cso-flame .35s ease-in-out infinite alternate; }
    .cso-star { fill: #98a2b3; animation: cso-twinkle 1.8s ease-in-out infinite; }
    .cso-star--2 { animation-delay: .4s; } .cso-star--3 { animation-delay: .9s; } .cso-star--4 { animation-delay: 1.3s; }
    .cso-tag { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--epic-blue, #cb6b4f);
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff); border-radius: 20px; padding: 4px 12px; }
    html body .cso-title { font-size: 24px; font-weight: 700; color: #101828 !important; margin: 12px 0 6px; letter-spacing: -.01em; }
    .cso-text { font-size: 14px; color: #667085; margin: 0 auto 14px; max-width: 360px; line-height: 1.55; }
    .cso-dots { display: inline-flex; gap: 6px; margin-bottom: 20px; }
    .cso-dots i { width: 8px; height: 8px; border-radius: 50%; background: var(--epic-blue, #cb6b4f); animation: cso-dot 1.2s ease-in-out infinite; }
    .cso-dots i:nth-child(2) { animation-delay: .15s; } .cso-dots i:nth-child(3) { animation-delay: .3s; }
    .cso-actions .btn { height: 40px; display: inline-flex; align-items: center; padding: 0 18px; }
    @keyframes cso-drift { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(40px, 30px) scale(1.1); } }
    @keyframes cso-in { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
    @keyframes cso-float { 0%, 100% { transform: translateY(4px); } 50% { transform: translateY(-6px); } }
    @keyframes cso-flame { from { transform: scaleY(.75); opacity: .8; } to { transform: scaleY(1.15); opacity: 1; } }
    @keyframes cso-pulse { 0%, 100% { transform: scale(.94); opacity: .6; } 50% { transform: scale(1.04); opacity: 1; } }
    @keyframes cso-spin { to { transform: rotate(360deg); } }
    @keyframes cso-twinkle { 0%, 100% { opacity: .25; } 50% { opacity: 1; } }
    @keyframes cso-dot { 0%, 80%, 100% { transform: scale(.6); opacity: .4; } 40% { transform: scale(1); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .cso *, .cso-card { animation: none !important; } }
    @media (max-width: 575px) { .cso { min-height: calc(100vh - 120px); padding: 24px 12px; } .cso-glow { width: 240px; height: 240px; } }
</style>
