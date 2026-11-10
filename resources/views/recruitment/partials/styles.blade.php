{{-- Small pieces of layout the recruitment pages share. Colours come from the theme's
     Bootstrap classes, so light / dark and the theme colour carry over. --}}
<style>
    .rc-tile { border-left: 4px solid var(--rc, #6c757d) !important; }
    .rc-tile h3 { color: var(--rc, #6c757d); }
    .rc-avatar { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 13px; background: #eef2f7; color: #475467; flex-shrink: 0; }
    .rc-avatar.lg { width: 56px; height: 56px; font-size: 18px; }
    /* The applicants board fits the screen width: the stages share it (an
       empty one is a narrow strip). Columns grow with their cards and the page
       scrolls as a whole - no scroll bar inside a column. */
    .rc-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; align-items: stretch; }
    .rc-col { flex: 1 1 0; min-width: 185px; background: #f4f6f9; border-radius: 10px; padding: 0 8px 8px; }
    .rc-col.rc-col--empty { flex: 0 0 140px; min-width: 140px; }
    .rc-col-head { gap: 8px; }
    .rc-col-head > span:first-child { min-width: 0; overflow: hidden; white-space: nowrap; }
    .rc-col-head > span:first-child .badge { max-width: 100%; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
    .rc-col-head { display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 13px; padding: 12px 4px 10px; }
    .rc-col-head > span:last-child { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px; background: #fff; display: inline-flex;
        align-items: center; justify-content: center; font-size: 12px; }
    .rc-card .rc-meta { overflow-wrap: anywhere; }
    .rc-card { background: #fff; border-radius: 6px; padding: 10px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); font-size: 13px; }
    .rc-card a.rc-name { font-weight: 600; color: #1d2939; }
    /* A card opens the applicant's details: it looks it */
    .rc-card--quick { cursor: pointer; border: 1px solid transparent; transition: border-color .12s ease, box-shadow .12s ease; }
    .rc-card--quick:hover { border-color: color-mix(in srgb, var(--epic-blue, #cb6b4f) 45%, transparent); box-shadow: 0 3px 10px rgba(16, 24, 40, .10); }
    .rc-quick-actions { display: flex; gap: 6px; margin-top: 6px; }
    .rc-quick-actions .btn { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; height: 24px; padding: 0 9px !important;
        font-size: 11.5px !important; font-weight: 500; line-height: 1 !important; border-radius: 4px; }
    .rc-quick-actions .btn i { font-size: 10px; margin-right: 4px !important; }
    .rc-meta { color: #667085; font-size: 12px; }

    /* Resumes: a row opens the candidate's details */
    tr.rc-row--quick { cursor: pointer; }
    tr.rc-row--quick:hover td { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 5%, #fff); }

    /* List footer: "Showing 1–10 of 13", per page, the pages */
    .rc-pager { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-top: 14px; padding: 10px 16px;
        background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
    .rc-pager .pagination { margin: 0; }
    .rc-pager .page-link { border-radius: 6px !important; margin: 0 2px; min-width: 32px; text-align: center; }
    body.dark-theme .rc-pager { background: #111827; }

    /* Tabs + filters bar (Interviews) */
    .rc-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .rc-tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
    .rc-tab { display: inline-flex; align-items: center; gap: 7px; height: 34px; padding: 0 14px; border-radius: 6px; font-size: 13px; font-weight: 500;
        color: #475467; white-space: nowrap; transition: background .12s ease, color .12s ease; }
    .rc-tab i { font-size: 12px; opacity: .7; }
    .rc-tab:hover { background: #f2f4f7; color: #1d2939; text-decoration: none; }
    .rc-tab.active { background: var(--epic-blue, #cb6b4f); color: #fff; }
    .rc-tab.active i { opacity: 1; }
    .rc-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 0; }
    .rc-filters .form-control { height: 38px; width: auto; min-width: 160px; padding: 0 12px; border-radius: 6px; background-color: #fff; }
    .rc-filters select[name="position_id"] { min-width: 200px; }
    .rc-check { display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 12px; background: #fff; border: 1px solid #e4e7ec;
        border-radius: 6px; font-size: 13px; color: #475467; cursor: pointer; user-select: none; }
    .rc-check input { width: 15px; height: 15px; margin: 0; accent-color: var(--epic-blue, #cb6b4f); cursor: pointer; }
    body.dark-theme .rc-tabs, body.dark-theme .rc-check { background: #111827; border-color: #374151; }
    body.dark-theme .rc-tab { color: #d0d5dd; }
    body.dark-theme .rc-tab:hover { background: #1f2937; }
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

    /* Hiring rounds */
    .rc-round-no { width: 24px; height: 24px; border-radius: 50%; background: var(--epic-line, #e8e9e9); color: var(--epic-text, #212529);
        font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 24px; }
    .rc-round.is-done .rc-round-no { background: #16a34a; color: #fff; }
    .rc-round.is-failed { border-color: #fca5a5 !important; background: #fef2f2; }
    .rc-round.is-failed .rc-round-no { background: #dc2626; color: #fff; }
    .rc-progress { font-size: 11px; color: var(--epic-muted, #878a99); }
    .rc-progress b { color: var(--epic-text, #212529); }
    .rc-pos-tabs a { display: inline-block; padding: 4px 10px; margin: 0 6px 6px 0; border: 1px solid var(--epic-line, #e8e9e9); border-radius: 16px;
        font-size: 13px; color: var(--epic-text, #212529); background: #fff; }
    .rc-pos-tabs a.active { background: var(--epic-blue); border-color: var(--epic-blue); color: #fff; }
    .rc-pos-tabs a span { font-weight: 700; margin-left: 4px; }
</style>
