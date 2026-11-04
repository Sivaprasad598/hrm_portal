{{-- Card styles shared by the dashboard parts: personal, team (My Team) and me (HR / Admin) --}}
<style>
    .pd-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(16, 24, 40, .08); padding: 18px 20px; height: 100%; display: block; color: inherit; }
    a.pd-card:hover { text-decoration: none; color: inherit; box-shadow: 0 4px 12px rgba(16, 24, 40, .12); }
    .pd-label { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085; margin-bottom: 8px; display: flex; justify-content: space-between; }
    .pd-label i { font-size: 16px; color: #98a2b3; }
    .pd-big { font-size: 26px; font-weight: 700; color: #101828; line-height: 1.2; }
    .pd-big small { font-size: 14px; font-weight: 500; color: #667085; }
    .pd-sub { font-size: 13px; color: #475467; margin-top: 4px; }
    .pd-sub .bad { color: #d92d20; font-weight: 600; }
    .pd-leaves { display: flex; flex-wrap: wrap; gap: 6px 14px; }
    .pd-leaves div { font-size: 13px; color: #475467; }
    .pd-leaves strong { font-size: 20px; color: #101828; margin-right: 3px; }
    .pd-head { font-size: 15px; font-weight: 600; color: #101828; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
    .pd-head a { font-size: 12px; font-weight: 500; }
    .pd-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
    .pd-day { border-radius: 8px; padding: 8px 4px; text-align: center; font-size: 12px; border: 1px solid #eaecf0; }
    .pd-day b { display: block; font-size: 15px; color: #101828; }
    .pd-day span { display: block; color: #475467; font-size: 11px; line-height: 1.3; min-height: 28px; }
    .pd-day.ok { background: #ecfdf3; border-color: #abefc6; } .pd-day.half { background: #fffaeb; border-color: #fedf89; }
    .pd-day.leave { background: #eff8ff; border-color: #b2ddff; } .pd-day.lop { background: #fef3f2; border-color: #fecdca; }
    .pd-day.off { background: #f2f4f7; } .pd-day.holiday { background: #f4f3ff; border-color: #d9d6fe; }
    .pd-day.today { outline: 2px solid #1d82f5; outline-offset: -1px; }
    .pd-list { list-style: none; margin: 0; padding: 0; }
    .pd-list li { display: flex; justify-content: space-between; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f2f4f7; font-size: 13px; }
    .pd-list li:last-child { border-bottom: 0; }
    .pd-muted { color: #667085; font-size: 13px; }
    .pd-pill { font-size: 11px; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
    .pd-pill.approved { background: #ecfdf3; color: #067647; } .pd-pill.pending { background: #fffaeb; color: #b54708; }
    .pd-pill.rejected { background: #fef3f2; color: #b42318; }
    @media (max-width: 575.98px) { .pd-day span { display: none; } .pd-day { padding: 6px 2px; } }

    /* My Team (dashboard/employee/team) and Me (dashboard/employee/me) */
    .pd-team-title { font-size: 16px; font-weight: 700; color: #101828; margin: 4px 0 10px; }
    .pd-team-title span { font-size: 13px; font-weight: 400; color: #667085; margin-left: 6px; }
    .pd-act { font-size: 12px; font-weight: 600; white-space: nowrap; }
    .pd-links { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 10px; font-size: 12px; }
    .pd-today { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; margin-bottom: 8px; }
    .pd-today div { border-radius: 8px; padding: 8px 4px; text-align: center; font-size: 11px; color: #475467; border: 1px solid #eaecf0; }
    .pd-today b { display: block; font-size: 20px; color: #101828; }
    .pd-today .ok { background: #ecfdf3; border-color: #abefc6; } .pd-today .leave { background: #eff8ff; border-color: #b2ddff; }
    .pd-today .lop { background: #fef3f2; border-color: #fecdca; }
    .pd-apply { display: inline-block; margin-top: 10px; font-size: 12px; font-weight: 600; }
</style>
