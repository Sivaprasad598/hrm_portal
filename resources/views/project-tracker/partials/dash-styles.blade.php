{{-- Project Dashboard, Resource Planner, availability: white cards, 12px corners, quiet titles, load colours. --}}
<style>
    .pd-tile { height: 100%; display: flex; justify-content: space-between; align-items: flex-start; background: #fff; border-radius: 12px; padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .pd-tile p { font-size: 11.5px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #667085; margin: 0 0 6px; }
    .pd-tile h3 { font-size: 26px; font-weight: 700; color: #101828; margin: 0; line-height: 1.15; }
    .pd-tile > div > span { font-size: 12px; color: #98a2b3; }
    .pd-ic { flex: 0 0 38px; width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;
        background: color-mix(in srgb, var(--c) 12%, #fff); color: var(--c); }
    .pd-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 18px 20px; }
    .pd-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .pd-head h5 { font-size: 13px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #475467; margin: 0; }
    .pd-head h5 .fa { color: var(--epic-blue, #cb6b4f); }
    .pd-count { font-size: 12px; font-weight: 600; background: #f2f4f7; color: #475467; border-radius: 10px; padding: 1px 9px; }
    .pd-count.is-hot { background: #fef3f2; color: #b42318; }
    .pd-proj { display: flex; align-items: center; gap: 14px; padding: 12px 10px; margin: 0 -10px; border-radius: 10px; color: inherit; border-left: 3px solid var(--tc); }
    .pd-proj + .pd-proj { margin-top: 4px; }
    .pd-proj:hover { background: #f9fafb; text-decoration: none; }
    .pd-ring { --p: 0; flex: 0 0 48px; width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; background: conic-gradient(var(--tc) calc(var(--p) * 1%), #f2f4f7 0); }
    .pd-ring b { width: 38px; height: 38px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-size: 11.5px; color: #101828; }
    .pd-proj-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .pd-proj-name { font-weight: 600; color: #101828; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pd-proj-sub { font-size: 12.5px; color: #667085; }
    .pd-warn { color: #dc6803; }
    .pd-proj-date { text-align: right; display: flex; flex-direction: column; font-size: 13px; }
    .pd-proj-date small { color: #98a2b3; font-size: 11.5px; }
    .pd-state { flex: 0 0 98px; text-align: center; font-size: 12px; font-weight: 600; border-radius: 999px; padding: 3px 0; }
    .pd-state--blue { background: #eff8ff; color: #175cd3; } .pd-state--green { background: #ecfdf3; color: #067647; }
    .pd-state--amber { background: #fffaeb; color: #b54708; } .pd-state--red { background: #fef3f2; color: #b42318; } .pd-state--grey { background: #f2f4f7; color: #475467; }
    .pd-task { display: grid; grid-template-columns: 1fr auto; gap: 0 10px; padding: 8px 0; border-top: 1px solid #f2f4f7; color: inherit; font-size: 13.5px; }
    .pd-head + .pd-task { border-top: 0; padding-top: 0; }
    .pd-task:hover { text-decoration: none; }
    .pd-task:hover .pd-task-name { color: var(--epic-blue, #cb6b4f); }
    .pd-task-name { color: #101828; font-weight: 500; }
    .pd-task-sub { grid-column: 1; font-size: 12px; color: #667085; }
    .pd-task-due { grid-column: 2; grid-row: 1 / span 2; align-self: center; font-size: 12.5px; color: #475467; white-space: nowrap; }
    .pd-clear { padding: 12px; border-radius: 10px; background: #ecfdf3; color: #067647; font-size: 13.5px; }
    .pd-div { padding: 8px 0; font-size: 13.5px; }
    .pd-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
    .pd-bar { height: 6px; border-radius: 999px; background: #f2f4f7; overflow: hidden; margin: 6px 0 3px; }
    .pd-bar span { display: block; height: 100%; border-radius: 999px; }
    .pd-load-head, .pd-load { display: grid; grid-template-columns: 1fr 76px 76px; gap: 6px; align-items: center; }
    .pd-load-head { font-size: 11.5px; color: #98a2b3; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; text-align: center; }
    .pd-load { padding: 5px 0; color: inherit; font-size: 13px; }
    .pd-load:hover { text-decoration: none; background: #f9fafb; }
    .pd-person { display: flex; align-items: center; gap: 8px; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #101828; }
    .pd-person img { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex: none; }
    .pd-cell { text-align: center; font-size: 12px; font-weight: 600; border-radius: 6px; padding: 4px 0; }
    .pd-cell--low { background: #ecfdf3; color: #067647; } .pd-cell--mid { background: #eff8ff; color: #175cd3; }
    .pd-cell--full { background: #fffaeb; color: #b54708; } .pd-cell--over { background: #fef3f2; color: #b42318; }
    .pd-cell--off { background: #f9fafb; color: #d0d5dd; }
    .pd-money { width: 100%; font-size: 13px; border-collapse: collapse; }
    .pd-money th { font-size: 11.5px; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: #98a2b3; padding: 8px 10px; border-bottom: 1px solid #eaecf0; white-space: nowrap; text-align: right; }
    .pd-money td { padding: 9px 10px; border-bottom: 1px solid #f2f4f7; text-align: right; white-space: nowrap; }
    .pd-money th:first-child, .pd-money td:first-child { text-align: left; white-space: normal; }
    .pd-money td:first-child a { color: #101828; font-weight: 500; }
    .pd-pos { color: #067647; }
    .pt-chart .chart-container .title { display: none; }
    .pt-chart { margin: 0 -10px; }
</style>
