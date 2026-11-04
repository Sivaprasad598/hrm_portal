{{-- Small pieces of layout the recruitment pages share. Colours come from the theme's
     Bootstrap classes, so light / dark and the theme colour carry over. --}}
<style>
    .rc-tile { border-left: 4px solid var(--rc, #6c757d) !important; }
    .rc-tile h3 { color: var(--rc, #6c757d); }
    .rc-avatar { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 13px; background: #eef2f7; color: #475467; flex-shrink: 0; }
    .rc-avatar.lg { width: 56px; height: 56px; font-size: 18px; }
    .rc-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; }
    .rc-col { min-width: 250px; width: 250px; flex-shrink: 0; background: #f4f6f9; border-radius: 8px; padding: 10px; }
    .rc-col-head { display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 13px; margin-bottom: 8px; }
    .rc-card { background: #fff; border-radius: 6px; padding: 10px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); font-size: 13px; }
    .rc-card a.rc-name { font-weight: 600; color: #1d2939; }
    .rc-meta { color: #667085; font-size: 12px; }
    .rc-chip { display: inline-block; padding: 1px 8px; border-radius: 10px; background: #eef2f7; color: #475467; font-size: 11px; margin: 0 4px 4px 0; }
    .rc-timeline li { position: relative; padding-left: 26px; margin-bottom: 12px; font-size: 13px; }
    .rc-timeline li i.rc-dot { position: absolute; left: 0; top: 3px; width: 18px; text-align: center; }
    .rc-steps { display: flex; flex-wrap: wrap; gap: 4px; }
    .rc-steps span { padding: 3px 10px; border-radius: 12px; font-size: 12px; background: #eef2f7; color: #667085; }
    .rc-steps span.done { background: #d1fadf; color: #05603a; }
    .rc-steps span.now { background: var(--epic-blue, #1d82f5); color: #fff; }
    .rc-position-card { transition: box-shadow .15s; }
    .rc-position-card:hover { box-shadow: 0 4px 12px rgba(16, 24, 40, .12) !important; }
    .rc-stars label { cursor: pointer; margin-right: 6px; }
    .rc-pre { white-space: pre-wrap; word-break: break-word; font-size: 13px; }
    body.dark-theme .rc-col { background: #1f2937; }
    body.dark-theme .rc-card { background: #111827; }
    body.dark-theme .rc-card a.rc-name { color: #f3f4f6; }
    body.dark-theme .rc-avatar, body.dark-theme .rc-chip, body.dark-theme .rc-steps span { background: #374151; color: #e5e7eb; }
</style>
