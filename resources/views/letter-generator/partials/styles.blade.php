{{-- The Letter Generator's layout: the form on the left, the live document on the right. --}}
<style>
    .lg-wrap { display: flex; gap: 18px; align-items: flex-start; }
    .lg-form { flex: 0 0 440px; max-width: 440px; }
    .lg-preview { flex: 1 1 auto; min-width: 0; position: sticky; top: 76px; }
    .lg-preview iframe { width: 100%; height: calc(100vh - 150px); border: 1px solid #e8eef3; border-radius: 6px; background: #e9ecef; }
    .lg-card { background: #fff; border: 1px solid #e8eef3; border-radius: 6px; padding: 14px 16px; margin-bottom: 14px; }
    .lg-card h5 { font-size: 13px; font-weight: 600; color: #28313c; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 10px; }
    .lg-card label { font-size: 12.5px; color: #616e80; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; display: block; }
    .lg-card label.lg-check { white-space: normal; display: inline; }
    .lg-sub { font-size: 12px; font-weight: 600; color: #28313c; margin: 4px 0 6px; }
    .lg-card select.form-control { height: calc(1.5em + .75rem + 2px); }
    .min-w-0 { min-width: 0; }
    .lg-card .form-control { font-size: 13.5px; }
    .lg-card .form-group { margin-bottom: 10px; }
    .lg-pay td { padding: 2px 0; font-size: 12.5px; }
    .lg-pay td:last-child { text-align: right; }
    .lg-pay tr.t td { font-weight: 600; border-top: 1px solid #e8eef3; padding-top: 4px; }
    .lg-hint { font-size: 11.5px; color: #99a5b5; }
    .lg-tabs .btn { font-size: 13px; }
    .lg-extra { display: flex; gap: 6px; margin-bottom: 6px; align-items: center; }
    .lg-extra select { flex: 0 0 104px; }
    .lg-extra .lg-amt { flex: 0 0 100px; }
    .lg-extra .lg-del { flex: 0 0 auto; color: #d21010; cursor: pointer; padding: 0 4px; }
    .lg-months td { padding: 3px 4px; font-size: 12.5px; vertical-align: middle; }
    .lg-months input { width: 70px; padding: 2px 6px; height: 28px; font-size: 12.5px; }
    @media (max-width: 1199.98px) { .lg-wrap { flex-direction: column; } .lg-form { flex-basis: auto; max-width: none; width: 100%; } .lg-preview { position: static; width: 100%; } }
</style>
