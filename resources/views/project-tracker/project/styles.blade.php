{{-- The tracker tab's look: white cards, the type's colour, steps in their colours. --}}
<style>
    .trk { --line: #eaecf0; --muted: #667085; --ink: #101828; }
    .trk-empty { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 40px 24px; text-align: center; }
    .trk-empty-ic { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 22px;
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); color: var(--epic-blue, #cb6b4f); margin-bottom: 12px; }
    .trk-empty h4 { font-size: 18px; font-weight: 600; color: var(--ink); }
    .trk-empty p { max-width: 560px; margin: 6px auto 18px; color: var(--muted); font-size: 14px; }

    .trk-head { display: flex; flex-wrap: wrap; align-items: center; gap: 20px; background: #fff; border-radius: 12px; border-top: 3px solid var(--tc);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 18px 20px; margin-bottom: 14px; }
    .trk-ring { --p: 0; flex: 0 0 84px; width: 84px; height: 84px; border-radius: 50%; display: grid; place-items: center;
        background: conic-gradient(var(--tc) calc(var(--p) * 1%), #f2f4f7 0); }
    .trk-ring span { width: 66px; height: 66px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-size: 20px; font-weight: 700; color: var(--ink); }
    .trk-ring small { font-size: 11px; font-weight: 600; color: var(--muted); }
    .trk-head-main { flex: 1; min-width: 280px; }
    .trk-head-top { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
    .trk-type { display: inline-flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 600; color: var(--ink); }
    .trk-type-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--tc); }
    .trk-state { font-size: 12px; font-weight: 600; border-radius: 999px; padding: 2px 10px; }
    .trk-state--blue { background: #eff8ff; color: #175cd3; } .trk-state--green { background: #ecfdf3; color: #067647; }
    .trk-state--amber { background: #fffaeb; color: #b54708; } .trk-state--red { background: #fef3f2; color: #b42318; } .trk-state--grey { background: #f2f4f7; color: #475467; }
    .trk-facts { display: flex; flex-wrap: wrap; gap: 8px 26px; }
    .trk-facts b { display: block; font-size: 16px; color: var(--ink); }
    .trk-facts span { font-size: 12px; color: var(--muted); }
    .trk-facts .is-bad b { color: #d92d20; } .trk-facts .is-warn b { color: #dc6803; }
    .trk-head-actions { display: flex; gap: 8px; }
    .trk-head-actions .btn, .trk-bar .btn { height: 38px; display: inline-flex; align-items: center; border-radius: 8px !important; padding: 0 14px !important; }

    .trk-flow { display: flex; gap: 4px; margin: 0 0 14px; }
    .trk-flow-step { flex: 1 1 0; min-width: 70px; }
    .trk-flow-bar { height: 8px; border-radius: 999px; background: var(--sc); }
    .trk-flow-label { font-size: 12px; color: var(--muted); margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .trk-flow-label b { color: var(--ink); }

    .trk-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .trk-switch { display: inline-flex; padding: 3px; background: #fff; border-radius: 9px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
    .trk-switch button { border: 0; background: transparent; padding: 7px 14px; border-radius: 7px; font-size: 13.5px; font-weight: 500; color: #475467; }
    .trk-switch button.active { background: var(--epic-blue, #cb6b4f); color: #fff; }
    .trk-search { max-width: 280px; height: 38px; border-radius: 8px; }

    .trk-tree { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); overflow: hidden; }
    .trk-none { padding: 30px; text-align: center; color: var(--muted); }
    .trk-group { padding: 10px 20px; background: #f9fafb; border-bottom: 1px solid var(--line); font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #475467; }
    .trk-row { --depth: 0; display: grid; grid-template-columns: minmax(260px, 2.6fr) minmax(110px, 1fr) 100px minmax(120px, 1fr) 90px 70px; gap: 12px; align-items: center;
        padding: 10px 20px 10px calc(20px + var(--depth) * 26px); border-bottom: 1px solid #f2f4f7; font-size: 13.5px; }
    .trk-row:hover { background: #fcfcfd; }
    .trk-row--epic { background: #fafaff; }
    .trk-row--epic .trk-title { font-weight: 700; }
    .trk-row.is-done .trk-title { color: #667085; text-decoration: line-through; text-decoration-color: #d0d5dd; }
    .trk-name { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .trk-fold, .trk-fold-space { flex: 0 0 20px; width: 20px; height: 20px; }
    .trk-fold { border: 0; background: transparent; color: #98a2b3; padding: 0; transition: transform .15s; }
    .trk-row.is-folded .trk-fold { transform: rotate(-90deg); }
    .trk-level { flex: 0 0 26px; width: 26px; height: 26px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;
        background: color-mix(in srgb, var(--lc) 12%, #fff); color: var(--lc); }
    .trk-title { color: var(--ink); font-weight: 500; }
    .trk-title:hover { color: var(--epic-blue, #cb6b4f); text-decoration: none; }
    .trk-sub { font-size: 12px; color: var(--muted); margin-top: 1px; }
    .trk-late { color: #d92d20 !important; }
    .trk-pill { display: inline-block; font-size: 12px; font-weight: 500; padding: 2px 10px; border-radius: 999px; color: var(--sc);
        background: color-mix(in srgb, var(--sc) 12%, #fff); border: 1px solid color-mix(in srgb, var(--sc) 28%, #fff); white-space: nowrap; }
    .trk-people { display: flex; align-items: center; }
    .trk-people img { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff; margin-left: -6px; object-fit: cover; box-shadow: 0 0 0 1px #eaecf0; }
    .trk-people img:first-child { margin-left: 0; }
    .trk-more { font-size: 11px; color: var(--muted); margin-left: 4px; }
    .trk-units-top { display: flex; align-items: baseline; gap: 4px; font-size: 12.5px; color: var(--muted); }
    .trk-units-top b { color: var(--ink); font-size: 13.5px; }
    .trk-units-input { width: 64px; height: 26px; padding: 0 6px; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 13px; font-weight: 600; color: var(--ink); }
    .trk-meter { height: 5px; border-radius: 999px; background: #f2f4f7; overflow: hidden; margin-top: 5px; }
    .trk-meter span { display: block; height: 100%; background: #12b76a; border-radius: 999px; }
    .trk-hours { font-size: 12.5px; color: var(--muted); white-space: nowrap; }
    .trk-hours b { color: var(--ink); }
    .trk-act { display: flex; justify-content: flex-end; gap: 4px; }
    .trk-icon { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--line); display: inline-flex; align-items: center; justify-content: center; color: #475467; font-size: 12px; }
    .trk-icon:hover { background: #f9fafb; text-decoration: none; color: var(--epic-blue, #cb6b4f); }

    .trk-board { display: flex; gap: 12px; overflow-x: auto; align-items: stretch; height: calc(100vh - 330px); min-height: 380px; padding-bottom: 10px; }
    .trk-lane { flex: 0 0 270px; display: flex; flex-direction: column; background: #f4f5f7; border: 1px solid #e4e7ec; border-top: 3px solid var(--sc); border-radius: 12px; overflow: hidden; }
    .trk-lane.is-drop { background: color-mix(in srgb, var(--sc) 10%, #fff); border-color: var(--sc); }
    .trk-lane-head { display: flex; justify-content: space-between; align-items: center; padding: 11px 14px; background: #fff; border-bottom: 1px solid #e4e7ec; font-size: 13.5px; font-weight: 600; color: var(--ink); }
    .trk-lane-head b { font-size: 12px; background: #f2f4f7; border-radius: 10px; padding: 1px 8px; color: #475467; }
    .trk-lane-body { flex: 1; overflow-y: auto; padding: 10px; }
    .trk-lane-empty { padding: 16px 8px; border: 1px dashed #d0d5dd; border-radius: 10px; text-align: center; color: #98a2b3; font-size: 13px; }
    .trk-card { background: #fff; border: 1px solid #eaecf0; border-radius: 10px; padding: 11px 12px; margin-bottom: 9px; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); font-size: 13px; }
    .trk-card[draggable] { cursor: grab; }
    .trk-card.is-dragging { opacity: .5; }
    .trk-card.is-late { box-shadow: inset 3px 0 0 #f04438; }
    .trk-card-top { display: flex; gap: 8px; align-items: flex-start; }
    .trk-card .trk-level { flex-basis: 22px; width: 22px; height: 22px; font-size: 10px; }
    .trk-card-units { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-top: 8px; }
    .trk-card-units b { color: var(--ink); }
    .trk-card-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 9px; font-size: 12px; color: var(--muted); }
    .trk-card .trk-people img { width: 22px; height: 22px; }

    @media (max-width: 991.98px) {
        .trk-row { grid-template-columns: 1fr auto; padding-left: calc(14px + var(--depth) * 16px); }
        .trk-people, .trk-hours { display: none; }
        .trk-units { grid-column: 1 / -1; }
        .trk-head-actions { width: 100%; }
    }
</style>
