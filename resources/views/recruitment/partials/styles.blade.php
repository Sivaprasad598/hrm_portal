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

    /* A list in one white card with its foot (recruitment.partials.list-footer), as the app's tables (Employees) */
    .rc-list-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); overflow: hidden; margin-bottom: 16px; }
    .rc-list thead th { background: #fff; border-top: 0 !important; border-bottom: 1px solid var(--epic-line, #eaecf0) !important; padding: 16px 20px !important;
        font-size: 12px; font-weight: 500; letter-spacing: .04em; text-transform: uppercase; color: #98a2b3; white-space: nowrap; }
    .rc-list tbody td { padding: 14px 20px !important; vertical-align: middle !important; border-top: 0 !important; border-bottom: 1px solid #f2f4f7; }
    .rc-list tbody tr:nth-child(even) td { background: #fcfcfd; }
    .rc-list tbody tr:last-child td { border-bottom: 0; }
    body.dark-theme .rc-list-card, body.dark-theme .rc-list thead th { background: #111827; }

    /* Positions: one card per opening */
    .rc-pos { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 18px 20px; height: 100%; display: flex; flex-direction: column;
        border: 1px solid transparent; transition: box-shadow .15s, border-color .15s; }
    .rc-pos:hover { box-shadow: 0 6px 16px rgba(16, 24, 40, .10); border-color: var(--epic-line, #eaecf0); }
    .rc-pos-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .rc-pos-title { font-size: 16.5px; font-weight: 600; color: var(--epic-text, #1d2939); line-height: 1.3; }
    .rc-pos-title:hover { color: var(--epic-blue, #cb6b4f); text-decoration: none; }
    .rc-pos-sub { font-size: 12.5px; color: #667085; margin-top: 2px; }
    .rc-pos-meta { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0 14px; }
    .rc-pos-meta span { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: #475467; background: #f2f4f7; border-radius: 999px; padding: 3px 10px; }
    .rc-pos-meta i { color: #98a2b3; font-size: 11px; }
    .rc-pos-stats { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--epic-line, #eaecf0); border-radius: 10px; overflow: hidden; }
    .rc-pos-stats a, .rc-pos-stats div { padding: 10px 6px; text-align: center; color: #667085; font-size: 12px; }
    .rc-pos-stats > * + * { border-left: 1px solid var(--epic-line, #eaecf0); }
    .rc-pos-stats a:hover { background: #f9fafb; text-decoration: none; }
    .rc-pos-stats b { display: block; font-size: 18px; font-weight: 600; color: var(--epic-text, #1d2939); line-height: 1.2; }
    .rc-pos-stats .is-hot b { color: var(--epic-blue, #cb6b4f); }
    .rc-pos-fill { margin-top: 12px; }
    .rc-pos-fill-bar { height: 6px; border-radius: 999px; background: #f2f4f7; overflow: hidden; }
    .rc-pos-fill-bar span { display: block; height: 100%; background: #12b76a; border-radius: 999px; }
    .rc-pos-fill-text { display: flex; justify-content: space-between; font-size: 12px; color: #667085; margin-top: 5px; }
    .rc-pos-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-top: auto; padding-top: 14px; }
    .rc-pos-foot .btn { height: 32px; display: inline-flex; align-items: center; padding: 0 12px !important; font-size: 13px; border-radius: 8px !important; }
    .rc-pos-close { font-size: 12.5px; color: #667085; }
    .rc-pos-close.is-late { color: #d92d20; }

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

    /* Candidate timeline: by day, a line joining the events, the icon in a soft circle */
    .rc-tl-filter { display: flex; flex-wrap: wrap; gap: 6px; }
    .rc-tl-chip { border: 1px solid var(--epic-line, #e4e7ec); background: #fff; color: #475467; border-radius: 999px; padding: 4px 12px; font-size: 12.5px; line-height: 1.4; cursor: pointer; }
    .rc-tl-chip span { margin-left: 4px; color: #98a2b3; }
    .rc-tl-chip:hover { border-color: #d0d5dd; background: #f9fafb; }
    .rc-tl-chip.active { background: var(--epic-blue, #cb6b4f); border-color: var(--epic-blue, #cb6b4f); color: #fff; }
    .rc-tl-chip.active span { color: rgba(255, 255, 255, .8); }
    .rc-tl-day + .rc-tl-day { margin-top: 6px; }
    .rc-tl-date { display: inline-block; font-size: 11.5px; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: #667085;
        background: #f2f4f7; border-radius: 4px; padding: 2px 8px; margin: 0 0 10px; }
    .rc-tl-item { position: relative; display: flex; gap: 12px; padding-bottom: 16px; }
    .rc-tl-item:not(:last-child)::before { content: ''; position: absolute; left: 15px; top: 34px; bottom: 2px; width: 2px; background: #eaecf0; }
    .rc-tl-icon { flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
    .rc-tl-icon--blue  { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); }
    .rc-tl-icon--green { background: #dcfae6; color: #067647; }
    .rc-tl-icon--amber { background: #fef0c7; color: #b54708; }
    .rc-tl-icon--teal  { background: #ccfbef; color: #107569; }
    .rc-tl-icon--grey  { background: #f2f4f7; color: #475467; }
    .rc-tl-body { flex: 1; min-width: 0; padding-top: 5px; }
    .rc-tl-head { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 6px; font-size: 13.5px; }
    .rc-tl-title { font-weight: 600; color: var(--epic-text, #1d2939); }
    .rc-tl-tag { font-size: 11.5px; color: #475467; background: #f2f4f7; border-radius: 4px; padding: 1px 6px; }
    .rc-tl-time { margin-left: auto; font-size: 12px; color: #98a2b3; white-space: nowrap; }
    .rc-tl-text { font-size: 13px; color: #475467; margin-top: 3px; word-break: break-word; }
    .rc-tl-more { margin-top: 6px; padding: 10px 12px; background: #f9fafb; border: 1px solid #f2f4f7; border-radius: 6px; color: #475467; }
    .rc-tl-toggle { display: inline-block; font-size: 12px; margin-top: 4px; }
    .rc-tl-by { font-size: 11.5px; color: #98a2b3; margin-top: 3px; }
    body.dark-theme .rc-tl-chip, body.dark-theme .rc-tl-date, body.dark-theme .rc-tl-tag, body.dark-theme .rc-tl-more { background: #1f2937; border-color: #374151; color: #d0d5dd; }
    body.dark-theme .rc-tl-item::before { background: #374151; }
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
