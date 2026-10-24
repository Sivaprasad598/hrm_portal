{{-- Shared look of the Working Hours / Lates & Half Day / Overtime tabs: one
     setting per row, its name and a line of explanation on the left, the
     control on the right. --}}
<style>
    .rules-tab .rules-head { margin-bottom: 18px; }
    .rules-tab .rules-head h3 { font-size: 18px; font-weight: 600; color: #1d2939; margin: 0 0 4px; }
    .rules-tab .rules-head p { font-size: 13px; color: #667085; margin: 0; }
    .rules-tab .rules-group { border: 1px solid #eaecf0; border-radius: 10px; background: #fff; margin-bottom: 20px; }
    .rules-tab .rules-group > h4 { font-size: 14px; font-weight: 600; color: #344054; margin: 0; padding: 12px 18px; border-bottom: 1px solid #eaecf0; background: #f9fafb; border-radius: 10px 10px 0 0; }
    .rules-tab .rule-row { display: flex; flex-wrap: wrap; align-items: center; padding: 14px 18px; border-bottom: 1px solid #f2f4f7; }
    .rules-tab .rule-row:last-child { border-bottom: 0; }
    .rules-tab .rule-label { flex: 1 1 320px; padding-right: 24px; }
    .rules-tab .rule-label strong { display: block; font-size: 14px; font-weight: 500; color: #344054; }
    .rules-tab .rule-label span { display: block; font-size: 12px; color: #667085; margin-top: 2px; }
    .rules-tab .rule-control { flex: 0 1 300px; }
    .rules-tab .rule-control .input-group-text { font-size: 13px; color: #667085; background: #f9fafb; }
    .rules-tab .rule-control .form-control { font-size: 14px; }
    .rules-tab .rule-summary { font-size: 13px; color: #344054; background: #f4f6fb; border-radius: 8px; padding: 10px 14px; }
    .rules-tab .rule-summary b { color: #1d2939; }
    .rules-tab .rules-table th { font-size: 12px; font-weight: 600; color: #667085; text-transform: uppercase; letter-spacing: .3px; }
    .rules-tab .rules-table td { vertical-align: middle; font-size: 13px; }
    @media (max-width: 767.98px) { .rules-tab .rule-control { flex-basis: 100%; margin-top: 8px; } }
</style>
