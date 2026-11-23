{{-- A module marked Coming soon (Settings -> Assign Menus -> Modules): its pages show this.
     Also the body of a side panel opened from one of them. --}}
<div class="cs-box">
    <div class="cs-icon"><i class="fa fa-tools"></i></div>
    <span class="cs-tag">Coming soon</span>
    <h3>{{ $comingSoonModule }} is on its way</h3>
    <p>This module is still being built. It will open here as soon as it is ready.</p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary rounded f-14"><i class="fa fa-home mr-1"></i>Back to Dashboard</a>
</div>
<style>
    .cs-box { max-width: 520px; margin: 40px auto; padding: 44px 32px; text-align: center; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .cs-icon { width: 76px; height: 76px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; font-size: 30px;
        color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); }
    .cs-tag { display: inline-block; font-size: 11.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--epic-blue, #cb6b4f);
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff); border-radius: 20px; padding: 4px 12px; }
    .cs-box h3 { margin: 14px 0 8px; font-size: 22px; font-weight: 600; color: #101828; }
    .cs-box p { margin: 0 auto 22px; max-width: 380px; font-size: 14px; color: #667085; }
</style>
