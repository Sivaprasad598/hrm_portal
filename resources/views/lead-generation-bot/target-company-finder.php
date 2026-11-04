<?php
/*
 * Target Company Finder (Lead Finder), shown inside Leads → Lead Generation Bot for administrators
 * (LeadGenerationBotController::finder, in an iframe on lead-generation-bot.index).
 * Searches go to TargetCompanyFinderController (OpenAI with web search, OPENAI_API_KEY in .env).
 * Formerly target_company_finder.html in the project root.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= csrf_token() ?>" />
<title>Lead Finder — Target Companies</title>
<style>
:root{--navy:#10284b;--blue:#0878f9;--blue2:#eaf3ff;--ink:#172744;--muted:#65748d;--line:#dfe7f1;--bg:#f3f6fb;--green:#0e9f6e;--amber:#b77908;--purple:#7445d7}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}button,input,select,textarea{font:inherit}button{cursor:pointer}.app{display:grid;grid-template-columns:228px 1fr;min-height:100vh}.sidebar{background:linear-gradient(180deg,#10294e,#0b1f3d);color:white;padding:22px 14px}.brand{display:flex;align-items:center;gap:10px;font-size:21px;font-weight:750;margin:0 8px 30px}.brand-mark{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#1685ff;font-size:20px}.nav{display:grid;gap:7px}.nav button{border:0;background:transparent;color:#d6e1f1;text-align:left;padding:13px 12px;border-radius:9px;display:flex;align-items:center;gap:12px}.nav button.active,.nav button:hover{background:#0878f9;color:#fff}.nav .ico{width:20px;text-align:center}.sidebar-note{margin:34px 7px 0;padding:14px;border:1px solid #345071;border-radius:10px;color:#c4d4e9;font-size:12px;line-height:1.5}.main{min-width:0}.topbar{height:68px;background:white;border-bottom:1px solid var(--line);display:flex;justify-content:flex-end;align-items:center;gap:16px;padding:0 28px}.global-search{width:min(360px,45vw);padding:11px 14px;border:1px solid var(--line);border-radius:10px;background:#fbfcff}.avatar{width:36px;height:36px;border-radius:50%;background:#8552df;color:white;display:grid;place-items:center;font-weight:700}.content{padding:24px;max-width:1600px;margin:auto}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px}.page-head h1{font-size:27px;margin:0 0 6px;letter-spacing:-.5px}.page-head p{margin:0;color:var(--muted)}.api-status{font-size:12px;background:#ecfdf3;color:#067647;border:1px solid #abefc6;padding:9px 12px;border-radius:20px;white-space:nowrap}.filters,.panel{background:white;border:1px solid var(--line);border-radius:14px;box-shadow:0 4px 18px #203b5c08}.filters{padding:20px;margin-bottom:16px}.section-title{font-weight:750;font-size:17px;margin:0 0 16px}.filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px 18px}.field label{display:block;font-size:12px;font-weight:700;margin:0 0 7px;color:#34435d}.field input,.field select,.field textarea{width:100%;border:1px solid #d5dfec;border-radius:8px;padding:11px 12px;color:var(--ink);background:#fff;outline:none}.field input:focus,.field select:focus,.field textarea:focus{border-color:#4094ff;box-shadow:0 0 0 3px #0878f91a}.filter-actions{display:flex;align-items:end;gap:10px}.btn{border:1px solid #d5dfec;background:white;color:#28415f;border-radius:8px;padding:11px 15px;font-weight:650}.btn:hover{background:#f6f9ff}.btn-primary{background:var(--blue);border-color:var(--blue);color:white}.btn-primary:hover{background:#0069df}.btn-light{color:var(--blue);border-color:#b8d5ff;background:#fff}.btn-small{padding:7px 10px;font-size:12px}.layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(340px,.75fr);gap:16px;align-items:start}.panel{overflow:hidden}.panel-head{padding:17px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:12px}.panel-head h2{font-size:17px;margin:0}.muted{color:var(--muted);font-size:12px}.results{padding:10px}.company-card{border:1px solid #e1e8f2;border-radius:11px;padding:14px;display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:12px;margin-bottom:9px;transition:.15s}.company-card:hover,.company-card.selected{border-color:#2585ff;background:#f7fbff;box-shadow:0 0 0 1px #2585ff20}.logo{height:48px;width:48px;border:1px solid #e8edf5;border-radius:9px;display:grid;place-items:center;background:white;font-size:12px;font-weight:800;color:#0878f9;text-align:center}.company-name{font-size:14px;font-weight:750;margin:1px 0 7px}.company-meta{font-size:12px;color:var(--muted);line-height:1.7}.tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.tag{font-size:11px;border-radius:6px;padding:4px 7px;background:#eaf3ff;color:#1268cb}.tag.green{background:#dcfce9;color:#08794e}.tag.orange{background:#fff0df;color:#b45309}.tag.purple{background:#f0e8ff;color:#6c35c6}.tag.red{background:#ffe4e6;color:#be123c}.intent{display:inline-block;border-radius:20px;padding:5px 9px;font-size:11px;font-weight:700;white-space:nowrap;background:#d9fbe8;color:#08794e}.intent.medium{background:#fff0c2;color:#956000}.intent.low{background:#e8edf4;color:#50617b}.card-actions{display:flex;flex-direction:column;align-items:flex-end;gap:8px}.checkbox-line{font-size:11px;color:var(--muted);white-space:nowrap}.checkbox-line input{vertical-align:middle}.details{padding:19px}.detail-top{display:flex;gap:13px;align-items:flex-start}.detail-top .logo{width:62px;height:62px;font-size:15px}.detail-top h2{font-size:17px;margin:2px 0 7px}.detail-top p{font-size:12px;line-height:1.6;color:var(--muted);margin:0}.description{font-size:13px;line-height:1.65;color:#40516d;margin:15px 0}.tabs{display:flex;border-bottom:1px solid var(--line);gap:3px;margin:0 -19px;padding:0 12px}.tabs button{border:0;background:transparent;padding:11px 10px;font-size:12px;color:#5c6c85;border-bottom:2px solid transparent}.tabs button.active{color:var(--blue);border-color:var(--blue);font-weight:750}.tab-content{padding-top:12px}.signal{display:grid;grid-template-columns:34px 1fr auto;gap:10px;padding:12px 0;border-bottom:1px solid #edf1f6}.signal-icon{width:32px;height:32px;border-radius:8px;background:#dcfce9;display:grid;place-items:center}.signal strong{font-size:12px}.signal p{font-size:12px;color:var(--muted);margin:4px 0 0;line-height:1.4}.signal time{font-size:11px;color:var(--muted);white-space:nowrap}.quick-actions{margin-top:18px}.quick-actions h3{font-size:14px;margin:0 0 12px}.quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.quick-grid .wide{grid-column:1/-1}.empty{padding:30px 15px;text-align:center;color:var(--muted);font-size:13px}.hidden{display:none!important}.contact-row{padding:12px 0;border-bottom:1px solid #edf1f6;display:flex;justify-content:space-between;gap:10px}.contact-row strong{font-size:12px}.contact-row p{font-size:12px;color:var(--muted);margin:4px 0}.contact-role{font-size:11px;color:#1268cb;background:#eaf3ff;padding:4px 7px;border-radius:6px;white-space:nowrap;height:max-content}.notice{font-size:12px;color:#6c5a2b;background:#fff8e6;border:1px solid #f1e2b7;padding:10px;border-radius:8px;line-height:1.5;margin-top:12px}.footer-note{font-size:11px;color:#8b98aa;margin-top:14px;line-height:1.5}.toast{position:fixed;bottom:20px;right:22px;background:#142b4d;color:#fff;padding:12px 17px;border-radius:9px;box-shadow:0 8px 30px #0b1f3d30;display:none;z-index:5;font-size:13px}.modal-backdrop{position:fixed;inset:0;background:#071a31a8;display:none;align-items:center;justify-content:center;padding:20px;z-index:10}.modal{background:white;border-radius:14px;padding:22px;width:min(560px,100%);box-shadow:0 20px 70px #0003}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.modal-head h3{margin:0}.close{border:0;background:#eef3fa;border-radius:7px;padding:7px 10px}.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.modal-grid .full{grid-column:1/-1}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
@media(max-width:1100px){.app{grid-template-columns:190px 1fr}.filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.layout{grid-template-columns:1fr}.detail-panel{order:-1}}
@media(max-width:680px){.app{display:block}.sidebar{padding:12px;position:sticky;top:0;z-index:3}.brand{margin:0 5px 10px}.nav{display:flex;overflow:auto}.nav button{white-space:nowrap;padding:10px}.sidebar-note{display:none}.topbar{height:55px;padding:0 12px}.content{padding:14px}.page-head{display:block}.api-status{display:inline-block;margin-top:12px}.filter-grid{grid-template-columns:1fr}.company-card{grid-template-columns:42px minmax(0,1fr)}.company-card .logo{width:40px;height:40px}.card-actions{grid-column:2;flex-direction:row;align-items:center;justify-content:space-between}.modal-grid{grid-template-columns:1fr}.modal-grid .full{grid-column:auto}}
.src-title{font-size:13px;margin:6px 0 10px;color:#0f2a4a}
.src-card{border:1px solid #e3eaf3;border-radius:10px;padding:12px 14px;margin-bottom:10px;background:#fbfdff}
.src-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:8px}
.src-head strong{font-size:13px}
.src-meta{display:grid;grid-template-columns:150px minmax(0,1fr);gap:4px 12px;margin:0;font-size:12px}
.src-meta dt{color:#64748b;font-weight:500}.src-meta dd{margin:0;overflow-wrap:anywhere}
.src-row{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #eef2f7}
.src-row strong{font-size:13px}.src-row p{margin:3px 0 0;font-size:12px;color:#64748b;overflow-wrap:anywhere}
.rp{position:relative}.rp-row{display:flex;gap:8px}
.rp-toggle{flex:1;min-width:0;display:flex;justify-content:space-between;align-items:center;gap:8px;border:1px solid #d5dfec;border-radius:8px;padding:11px 12px;background:#fff;color:var(--muted);text-align:left}
.rp-toggle span:first-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rp-toggle.chosen{color:var(--ink)}
.rp-toggle:focus,.rp-toggle[aria-expanded="true"]{border-color:#4094ff;box-shadow:0 0 0 3px #0878f91a;outline:none}
.rp-add-btn{flex-shrink:0;border:1px solid var(--blue);background:var(--blue2);color:var(--blue);border-radius:8px;padding:0 12px;font-weight:700;font-size:13px}
.rp-menu{position:absolute;z-index:20;left:0;right:0;top:calc(100% + 6px);background:#fff;border:1px solid #d5dfec;border-radius:10px;box-shadow:0 12px 30px #10284b26;padding:8px}
.field .rp-menu input{padding:8px 10px;font-size:13px}
.rp-list{max-height:220px;overflow:auto;margin:6px 0}
.rp-item{padding:8px 10px;border-radius:7px;font-size:13px;cursor:pointer}.rp-item:hover{background:#f1f6ff}.rp-item.active{background:var(--blue2);color:var(--blue);font-weight:600}
.rp-suggest{color:var(--blue);font-weight:600}
.released{background:#f5f9ff;border:1px solid #dbe8fb;border-radius:10px;padding:10px 12px;margin:8px 0}
.released-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.released-by{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
.released-name{display:block;font-size:17px;font-weight:800;color:#0f2a4a;text-decoration:none;margin:2px 0}.released-name:hover{color:var(--blue);text-decoration:underline}
.released-btn{white-space:nowrap}
.proj-published{text-align:right;font-size:12px;color:#475569;margin-top:10px;padding-top:8px;border-top:1px solid #eef2f7}.proj-published b{color:#0f2a4a}
.ct-details{margin-top:12px;border-top:1px dashed #e3eaf3;padding-top:10px}.ct-sub{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#0f2a4a}
.ct-entry{background:#fff;border:1px solid #eef2f7;border-radius:10px;padding:10px 12px;margin-top:8px}.ct-entry .ct-dl{margin-top:6px}
.ct-entry-head{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#475569}.ct-x{border:0;background:#f1f5f9;border-radius:6px;padding:2px 7px;cursor:pointer;color:#b42318}
.cm-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.cm-grid .full{grid-column:1/-1}.cm textarea{width:100%;border:1px solid #d5dfec;border-radius:8px;padding:9px 10px;font:inherit;font-size:13px}
.cm-q{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:13px;font-weight:600}.cm-yn{display:flex;align-items:center;gap:5px;font-weight:500}
.cm-date{display:flex;align-items:center;gap:8px;font-weight:600}.cm-date input{border:1px solid #d5dfec;border-radius:8px;padding:6px 8px;font:inherit}
.cm-proj{display:grid;grid-template-columns:1fr 1.4fr auto;gap:8px;align-items:start;margin-top:10px}.cm-proj input{border:1px solid #d5dfec;border-radius:8px;padding:9px 10px;font:inherit;font-size:13px}
.cm-error{margin-top:12px;background:#fff7ed;color:#9a3412;border-radius:8px;padding:9px 12px;font-size:13px}
@media(max-width:620px){.cm-grid,.cm-proj{grid-template-columns:1fr}}
.fu-group{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin:10px 4px 8px;color:#0f2a4a}.fu-group.fu-overdue{color:#b42318}.fu-group.fu-today{color:#b45309}.fu-group.fu-done{color:#166534}
.fu-card{display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:14px;align-items:start;border:1px solid #e3eaf3;border-left:4px solid #93c5fd;border-radius:12px;padding:12px 14px;margin:0 2px 10px;background:#fbfdff}
.fu-card.fu-overdue{border-left-color:#ef4444;background:#fff8f7}.fu-card.fu-today{border-left-color:#f59e0b;background:#fffbeb}.fu-card.fu-done{border-left-color:#22c55e;opacity:.75}
.fu-date b{display:block;font-size:14px}.fu-date span{font-size:11px;color:#166534}.fu-who{font-size:13px;margin-top:6px}.fu-notes{font-size:13px;color:#334155;margin-top:6px;white-space:pre-wrap}
.fu-proj{font-size:12px;color:#475569;margin-top:6px}.fu-meta{margin-top:6px}
@media(max-width:680px){.fu-card{grid-template-columns:1fr}}
.api-box{padding:6px 10px 14px;font-size:13px;color:#334155}.api-box h3{font-size:14px;margin:16px 0 8px;color:#0f2a4a}.api-box h3:first-child{margin-top:4px}
.api-code{display:flex;align-items:center;gap:10px;background:#0f172a;border-radius:10px;padding:10px 12px;margin:6px 0}.api-code code{color:#e2e8f0;font-size:12px;overflow-wrap:anywhere;flex:1}
.api-new{background:#ecfdf5;border:1px solid #86efac;border-radius:10px;padding:10px 12px;margin-bottom:10px}
.api-actions{display:flex;gap:8px;margin:8px 0}.api-table{width:100%;border-collapse:collapse;margin-top:8px}.api-table th,.api-table td{text-align:left;border-bottom:1px solid #eef2f7;padding:7px 8px;vertical-align:top}
.api-table th{font-size:12px;color:#64748b}.api-box code{font-size:12px}
.ct-card{border:1px solid #e3eaf3;border-radius:12px;padding:14px 16px;margin:0 2px 12px;background:#fbfdff}
.ct-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.ct-co{font-size:15px;font-weight:700;color:var(--ink);text-decoration:none}.ct-co:hover{color:var(--blue);text-decoration:underline}
.ct-when{text-align:right;font-size:12px;flex-shrink:0}.ct-badge{display:inline-block;font-size:11px;font-weight:700;border-radius:10px;padding:2px 8px;background:var(--blue2);color:var(--blue);margin-bottom:4px}.ct-badge.ct-detail{background:#ecfdf5;color:#166534}
.ct-dl{display:grid;grid-template-columns:130px minmax(0,1fr);gap:6px 12px;margin:12px 0 0;font-size:13px}.ct-dl dt{color:#64748b;font-weight:600}.ct-dl dd{margin:0;overflow-wrap:anywhere}
.ct-notes{white-space:pre-wrap}.ct-yes{color:#166534;font-weight:700}.ct-no{color:#64748b}.ct-proj+.ct-proj{margin-top:4px}
.ct-actions{display:flex;gap:8px;margin-top:12px}.ct-del{color:#b42318;border-color:#f3c5bf}
.card-contacts{display:flex;flex-wrap:wrap;gap:6px 12px;margin-top:8px}.card-contacts:empty{display:none}
.cc{font-size:12px;color:#0f2a4a;text-decoration:none;display:inline-flex;align-items:center;gap:4px}.cc:hover{color:var(--blue);text-decoration:underline}
.cc-li b{background:#0a66c2;color:#fff;border-radius:3px;font-size:10px;padding:0 3px;line-height:14px}.cc-muted{color:var(--muted)}
#contactBotBtn{white-space:nowrap}
.cf-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.cf-ico{font-size:20px}
.cf-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr) minmax(0,1fr) minmax(150px,.7fr);gap:15px 18px;align-items:end}
#cfDateSlot{grid-column:1/2}#cfDateSlot:empty{display:none}
.cf-input{position:relative}.cf-input span{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}.field .cf-input input{padding-left:34px}
@media(max-width:1100px){.cf-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.cf-grid{grid-template-columns:1fr}#cfDateSlot{grid-column:auto}}
.company-link{color:inherit;text-decoration:none}.company-link:hover{color:var(--blue);text-decoration:underline}
.proj-actions{display:flex;gap:8px;flex-wrap:wrap;margin:4px 0 2px}.btn-on{background:var(--blue2);border:1px solid var(--blue);color:var(--blue);font-weight:700}
.proj-company{font-size:12px;color:#475569;margin:2px 0 6px}.proj-company a{color:var(--blue);font-weight:600}
.saved-when{font-size:11px;color:var(--muted);margin:2px 2px 4px}
.layout.single{grid-template-columns:1fr}.layout.single .detail-panel{display:none}
.dt-hidden{position:absolute;left:0;bottom:0;width:100%;height:0;padding:0;border:0;opacity:0;pointer-events:none}
.dt-actions{grid-column:-2/-1}
.loc-field{grid-column:1/-1}.loc-item{display:flex;flex-direction:column;gap:2px}.loc-item strong{font-weight:600;font-size:13px}.loc-item span{font-size:12px;color:var(--muted)}
.loc-credit{font-size:10px;color:var(--muted);text-align:right;padding:2px 4px 0}
.spin-dot{display:inline-block;width:10px;height:10px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 1s linear infinite;vertical-align:-1px;margin-right:4px}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:1100px){.loc-field{grid-column:1/-1}}.rp-own{font-size:10px;font-weight:600;color:var(--muted);background:#f1f5f9;border-radius:8px;padding:1px 6px;margin-left:4px}.rp-empty{padding:10px;font-size:12px;color:var(--muted)}
.rp-new{display:flex;gap:6px;border-top:1px solid #eef2f7;padding-top:8px}.rp-new button{flex:1;padding:9px 12px !important;border:0;background:var(--blue);color:#fff;border-radius:7px;padding:0 12px;font-weight:700;font-size:13px}
.company-pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:6px 10px 14px}
.company-pager:empty{display:none}.pager-info{font-size:12px;color:var(--muted)}.pager-btns{display:flex;gap:6px;flex-wrap:wrap}
.pager-btns button{min-width:34px;height:34px;border:1px solid var(--line);background:#fff;border-radius:8px;font-size:13px;font-weight:600;color:var(--ink);padding:0 10px}
.pager-btns button.active{background:var(--blue);border-color:var(--blue);color:#fff}.pager-btns button:disabled{opacity:.45;cursor:default}
.verify{flex-shrink:0;display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap}
.verify-official{background:#dcfce7;color:#166534}.verify-verified{background:#e0f2fe;color:#075985}
.verify-unverified{background:#fff7ed;color:#9a3412}.verify-no-link{background:#f1f5f9;color:#64748b}
.proj-meta{font-size:12px;color:#475569;margin-bottom:6px}.proj-desc{font-size:12px;color:#334155;margin:0 0 8px}
.proj-status{flex-shrink:0;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#eef2ff;color:#3730a3;white-space:nowrap}
.proj-block{border-top:1px dashed #e3eaf3;padding-top:8px;margin-top:8px}.proj-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#0f2a4a;margin-bottom:6px}
@media(max-width:680px){.src-meta{grid-template-columns:1fr}}
.projects-only .sidebar,.projects-only .topbar{display:none!important}.projects-only .app{display:block}.projects-only .content{padding-top:18px}
</style>
<!-- Flatpickr: the calendars of the Project / signal date filter -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css" />
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js"></script>
</head>
<?php $projectsOnly = request('mode') === 'projects'; // Lead Contacts → Search Projects: the dedicated page ?>
<body class="<?= $projectsOnly ? 'projects-only' : '' ?>">
<div class="app">
  <aside class="sidebar">
    <div class="brand"><span class="brand-mark">▦</span> Lead Finder</div>
    <nav class="nav" aria-label="Main navigation">
      <button class="active" data-nav="search"><span class="ico">⌕</span>Search Projects</button>
      <button data-nav="companySearch"><span class="ico">▦</span>Search Companies</button>
      <button data-nav="targets"><span class="ico">✦</span>My Targets <span id="targetCount" class="muted" style="color:#c7d8f0"></span></button>
      <button data-nav="savedProjects"><span class="ico">☆</span>Saved Projects <span id="savedProjectCount" class="muted" style="color:#c7d8f0"></span></button>
      <button data-nav="targetProjects"><span class="ico">◎</span>Target Projects <span id="targetProjectCount" class="muted" style="color:#c7d8f0"></span></button>
      <!-- hidden for now: <button data-nav="signals"><span class="ico">◈</span>Projects / Signals</button> -->
      <button data-nav="contacts"><span class="ico">♙</span>Contacts <span id="contactCount" class="muted" style="color:#c7d8f0"></span></button>
      <!-- hidden for now: <button data-nav="outreach"><span class="ico">➤</span>Outreach</button> -->
      <button data-nav="followups"><span class="ico">▣</span>Follow-ups <span id="followUpCount" class="muted" style="color:#c7d8f0"></span></button>
      <!-- hidden for now: <button data-nav="apiAccess"><span class="ico">⚿</span>API access</button> -->
    </nav>
    <div class="sidebar-note"><strong>Find the right accounts</strong><br>Search public company data, review buying signals, and identify the people involved in a purchase.</div>
  </aside>
  <main class="main">
    <header class="topbar"><input id="globalSearch" class="global-search" placeholder="⌕  Search company or contact…" aria-label="Search company or contact"><span>♧</span><div class="avatar">PN</div></header>
    <div class="content">
      <div class="page-head"><div><h1 id="pageTitle">Search Projects</h1><p id="pageSubtitle">Search by location and research project: only projects of that category in that location, newest first.</p></div><div class="api-status" id="apiStatus">● Connected to ChatGPT · live web search</div></div>
      <section class="filters hidden" id="companyFilterPanel">
        <h2 class="section-title cf-title"><span class="cf-ico" aria-hidden="true">🏢</span> Project Finder <span class="muted" style="font-weight:400">· Construction &amp; Engineering Company Search</span></h2>
        <form id="companySearchForm">
          <div class="cf-grid">
            <div class="field"><label for="cfQuery">Search Company or Project</label><div class="cf-input"><span aria-hidden="true">⌕</span><input id="cfQuery" type="search" maxlength="120" placeholder="e.g. L&amp;T, Bridge, Revit…" autocomplete="off" /></div></div>
            <div class="field"><label for="cfLocation">Location</label>
              <div class="rp" id="cfLoc"><input type="text" id="cfLocation" value="" placeholder="All locations — type a city, area or country" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="cfLocList" />
                <div class="rp-menu" id="cfLocMenu" hidden><div class="rp-list" id="cfLocList" role="listbox"></div><div class="loc-credit">Suggestions © OpenStreetMap contributors</div></div>
              </div></div>
            <div class="field"><label for="ptToggle">Project Type / Software</label><input type="hidden" id="cfType" value="" />
              <div class="rp" id="pt"><div class="rp-row"><button type="button" class="rp-toggle" id="ptToggle" aria-haspopup="listbox" aria-expanded="false"><span id="ptLabel">All Project Types</span><span aria-hidden="true">▾</span></button><button type="button" class="rp-add-btn" id="ptAddOpen" title="Add a project type or software">+ Add</button></div>
                <div class="rp-menu" id="ptMenu" hidden>
                  <input type="search" id="ptSearch" placeholder="Search project types / software…" autocomplete="off" />
                  <div class="rp-list" id="ptList" role="listbox"></div>
                  <div class="rp-new"><button type="button" id="ptAddBtn">+ Add new project type / software</button></div>
                </div>
              </div></div>
            <div class="filter-actions"><button class="btn btn-primary" style="width:100%" type="submit">⌕ &nbsp; Search</button></div>
            <div id="cfDateSlot"></div>
          </div>
        </form>
      </section>
      <section class="filters" id="filterPanel">
        <h2 class="section-title">Project search <span class="muted" style="font-weight:400">· Choose your target market</span></h2>
        <form id="searchForm">
          <div class="filter-grid">
            <div class="field loc-field"><label for="location">Location</label>
                <div class="rp" id="loc"><input type="text" id="location" value="Gajuwaka, Visakhapatnam, Andhra Pradesh, India" placeholder="Type a city, area or district — e.g. Gajuwaka, Visakhapatnam, Andhra Pradesh, India" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="locList" />
                    <div class="rp-menu" id="locMenu" hidden><div class="rp-list" id="locList" role="listbox"></div><div class="loc-credit">Suggestions © OpenStreetMap contributors</div></div>
                </div></div>
            <input type="hidden" id="industry" value="" /><!-- Industry field, hidden for now (searches run with any industry):
            <div class="field"><label for="inToggle">Industry</label>
                <div class="rp" id="in"><div class="rp-row"><button type="button" class="rp-toggle" id="inToggle" aria-haspopup="listbox" aria-expanded="false"><span id="inLabel">All industries</span><span aria-hidden="true">▾</span></button><button type="button" class="rp-add-btn" id="inAddOpen" title="Add an industry">+ Add</button></div>
                    <div class="rp-menu" id="inMenu" hidden>
                        <input type="search" id="inSearch" placeholder="Search industries…" autocomplete="off" />
                        <div class="rp-list" id="inList" role="listbox"></div>
                        <div class="rp-new"><button type="button" id="inAddBtn">+ Add new industry</button></div>
                    </div>
                </div></div>
            -->
            <div class="field"><label for="rpToggle">Research projects</label><input type="hidden" id="project" value="" />
                <div class="rp" id="rp"><div class="rp-row"><button type="button" class="rp-toggle" id="rpToggle" aria-haspopup="listbox" aria-expanded="false"><span id="rpLabel">Any project</span><span aria-hidden="true">▾</span></button><button type="button" class="rp-add-btn" id="rpAddOpen" title="Add a research project">+ Add</button></div>
                    <div class="rp-menu" id="rpMenu" hidden>
                        <input type="search" id="rpSearch" placeholder="Search projects…" autocomplete="off" />
                        <div class="rp-list" id="rpList" role="listbox"></div>
                        <div class="rp-new"><button type="button" id="rpAddBtn">+ Add new project</button></div>
                    </div>
                </div></div>
            <input type="hidden" id="signalFilter" value="" /><!-- Buying signal field, hidden for now (searches run with any buying signal):
            <div class="field"><label for="bsToggle">Buying signal</label>
                <div class="rp" id="bs"><div class="rp-row"><button type="button" class="rp-toggle" id="bsToggle" aria-haspopup="listbox" aria-expanded="false"><span id="bsLabel">All signals</span><span aria-hidden="true">▾</span></button><button type="button" class="rp-add-btn" id="bsAddOpen" title="Add a buying signal">+ Add</button></div>
                    <div class="rp-menu" id="bsMenu" hidden>
                        <input type="search" id="bsSearch" placeholder="Search signals…" autocomplete="off" />
                        <div class="rp-list" id="bsList" role="listbox"></div>
                        <div class="rp-new"><button type="button" id="bsAddBtn">+ Add new signal</button></div>
                    </div>
                </div></div>
            -->
            <div class="field"><label for="dtToggle">Project / signal date</label>
                <input type="hidden" id="dateLabel" value="Latest first" /><input type="hidden" id="dateFrom" value="" /><input type="hidden" id="dateTo" value="" />
                <div class="rp" id="dt"><div class="rp-row"><button type="button" class="rp-toggle chosen" id="dtToggle" aria-haspopup="listbox" aria-expanded="false"><span id="dtLabel">Latest first</span><span aria-hidden="true">▾</span></button></div>
                    <div class="rp-menu" id="dtMenu" hidden><div class="rp-list" id="dtList" role="listbox" style="max-height:300px;margin:0"></div></div>
                    <input type="text" id="dtMonthInput" class="dt-hidden" tabindex="-1" aria-hidden="true" /><input type="text" id="dtRangeInput" class="dt-hidden" tabindex="-1" aria-hidden="true" />
                </div></div>
            <div class="filter-actions dt-actions"><button class="btn btn-primary" style="width:100%" type="submit">⌕ &nbsp; Search projects</button></div>
          </div>
        </form>
      </section>
      <div class="layout">
        <section class="panel" id="resultsPanel">
          <div class="panel-head"><div><h2 id="resultsTitle">Companies found <span class="muted" id="resultCount">(5)</span></h2><span class="muted">Select a company to review its signals and contacts.</span></div><button type="button" class="btn btn-light btn-small" id="contactBotBtn" title="Find the phone, email, website and LinkedIn of every company in the list">🤖 Contact bot</button><!-- Sort dropdown, hidden for now:
          <select id="sortBy" class="btn" style="padding:8px;font-size:12px"><option value="relevance">Sort: Relevance</option><option value="name">Name A–Z</option><option value="intent">Buying intent</option></select>
          --></div>
          <div class="results" id="companyResults"></div>
          <div class="company-pager" id="companyPager"></div>
          <div class="footer-note" id="demoNote" style="padding:0 10px 12px">Demo records are illustrative, not verified company intelligence. Connect your chosen data provider to retrieve live results.</div>
        </section>
        <section class="panel detail-panel">
          <div class="panel-head"><h2>Company overview</h2><span class="muted" id="detailBadge">Selected account</span></div>
          <div class="details" id="companyDetail"></div>
        </section>
      </div>
    </div>
  </main>
</div>
<div class="toast" id="toast" role="status"></div>
<div class="modal-backdrop" id="cmModal" role="dialog" aria-modal="true" aria-labelledby="cmTitle"><form class="modal cm" id="cmForm" novalidate style="width:min(640px,100%)">
  <div class="modal-head"><h3 id="cmTitle">Add contact details</h3><button type="button" class="close" id="cmClose" aria-label="Close">✕</button></div>
  <p class="muted" id="cmCompany" style="margin:-8px 0 12px"></p>
  <div class="cm-grid">
    <div class="field"><label for="cmName">Contact person</label><input id="cmName" maxlength="150" placeholder="Name of the representative you talked with"></div>
    <div class="field"><label for="cmDesignation">Designation</label><input id="cmDesignation" maxlength="150" placeholder="e.g. Project Manager"></div>
    <div class="field"><label for="cmPhone">Phone</label><input id="cmPhone" maxlength="60" placeholder="e.g. +91 98765 43210"></div>
    <div class="field"><label for="cmEmail">Email</label><input id="cmEmail" type="email" maxlength="191" placeholder="name@company.com"></div>
    <div class="field full"><label for="cmNotes">Notes</label><textarea id="cmNotes" rows="4" maxlength="5000" placeholder="What you discussed with the company representative"></textarea></div>
  </div>
  <div class="cm-q"><span>Did they ask for a follow-up?</span><label class="cm-yn"><input type="radio" name="cmFollow" value="1"> Yes</label><label class="cm-yn"><input type="radio" name="cmFollow" value="0" checked> No</label>
    <span id="cmDateWrap" hidden class="cm-date">Follow-up date <input type="date" id="cmFollowDate"></span></div>
  <div class="cm-q"><span>Add projects discussed?</span><label class="cm-yn"><input type="radio" name="cmProj" value="1"> Yes</label><label class="cm-yn"><input type="radio" name="cmProj" value="0" checked> No</label></div>
  <div id="cmProjects" hidden><div id="cmProjectRows"></div><button type="button" class="btn btn-light btn-small" id="cmAddProject" style="margin-top:10px">＋ Add another project</button></div>
  <div class="cm-error" id="cmError" hidden></div>
  <div class="modal-actions"><button type="button" class="btn" id="cmCancel">Cancel</button><button type="submit" class="btn btn-primary" id="cmSave">Save</button></div>
</form></div>
<div class="modal-backdrop" id="rpModal" role="dialog" aria-modal="true" aria-labelledby="rpModalTitle"><form class="modal" id="rpForm" style="width:min(480px,100%)"><div class="modal-head"><h3 id="rpModalTitle">Add research project</h3><button type="button" class="close" id="rpModalClose" aria-label="Close">✕</button></div>
<div class="field"><label for="rpNew" id="rpNewLabel">Project name</label><input type="text" id="rpNew" maxlength="191" placeholder="e.g. GIS mapping survey" autocomplete="off" /></div>
<p class="muted" id="rpModalMsg" style="margin:8px 0 0;font-size:12px"></p>
<div class="modal-actions"><button type="button" class="btn" id="rpModalCancel">Cancel</button><button type="submit" class="btn btn-primary" id="rpModalSave">Add project</button></div></form></div>
<div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true" aria-labelledby="modalTitle"><div class="modal"><div class="modal-head"><h3 id="modalTitle">Find contacts</h3><button class="close" id="closeModal">✕</button></div><div id="modalBody"></div><div class="modal-actions"><button class="btn" id="cancelModal">Cancel</button><button class="btn btn-primary" id="saveModal">Save</button></div></div></div>
<script>
/*
 API INTEGRATION
 1) Set API_BASE_URL to your own backend URL below.
 2) Implement /api/companies/search, /api/companies/:id, /api/companies/:id/contacts and /api/signals.
 3) Keep provider API keys on the backend; never place secret API keys in browser JavaScript.
 4) Replace demoSearch() with fetch(`${API_BASE_URL}/api/companies/search?...`).
 */
// Connected to ChatGPT (OpenAI web search) through the HRMS portal (TargetCompanyFinderController); needs a portal login.
// The OpenAI key stays on the server (.env OPENAI_API_KEY).
const API_BASE_URL = <?= json_encode(url('account/target-companies')) ?>; // the page adds /api/companies/search
const DEMO_COMPANIES = [
 {id:'tcs',name:'Tata Consultancy Services (TCS)',short:'tcs',industry:'IT Services',size:'10,001+ employees',location:'Gajuwaka, Visakhapatnam, Andhra Pradesh, India',intent:'High',description:'Global IT services, consulting and business solutions company. Use verified local records to confirm the relevant office and buying team.',signals:[{type:'Office expansion',icon:'▥',date:'Sep 15, 2026',text:'Illustrative signal: possible regional delivery-centre expansion.'},{type:'Hiring',icon:'♟',date:'Sep 10, 2026',text:'Illustrative signal: cloud, DevOps and full-stack hiring activity.'},{type:'Technology upgrade',icon:'♙',date:'Aug 28, 2026',text:'Illustrative signal: cloud migration or infrastructure modernisation.'},{type:'Tender / contract',icon:'▣',date:'Aug 12, 2026',text:'Check official tender portals for current IT-services opportunities.'},{type:'New project',icon:'✦',date:'Jul 20, 2026',text:'Research public announcements for regional digital projects.'}],contacts:[{name:'Technology Decision Maker',role:'Chief Technology Officer (CTO)',email:'Find via connected contact provider',phone:'Not verified',note:'Technology strategy and vendor evaluation'},{name:'IT Operations Lead',role:'IT Director / Head of Infrastructure',email:'Find via connected contact provider',phone:'Not verified',note:'Infrastructure, cloud and platform needs'},{name:'Procurement Lead',role:'Procurement / Vendor Manager',email:'Find via connected contact provider',phone:'Not verified',note:'Supplier onboarding and purchasing process'}]},
 {id:'infosys',name:'Infosys',short:'Infosys',industry:'IT Services',size:'10,001+ employees',location:'Visakhapatnam, Andhra Pradesh, India',intent:'High',description:'Technology consulting and services company. Validate local presence and relevant business unit before outreach.',signals:[{type:'Hiring',icon:'♟',date:'Sep 10, 2026',text:'Example signal category: cloud and engineering roles.'},{type:'New project',icon:'✦',date:'Aug 22, 2026',text:'Research official company releases and tender notices.'},{type:'Technology upgrade',icon:'♙',date:'Aug 01, 2026',text:'Monitor public cloud and platform announcements.'}],contacts:[{name:'Technology Decision Maker',role:'CTO / Technology Director',email:'Find via connected contact provider',phone:'Not verified',note:'Technology strategy'},{name:'Talent / Delivery Leader',role:'Delivery Manager',email:'Find via connected contact provider',phone:'Not verified',note:'Delivery and hiring priorities'}]},
 {id:'wipro',name:'Wipro',short:'wipro',industry:'IT Services',size:'10,001+ employees',location:'Visakhapatnam, Andhra Pradesh, India',intent:'Medium',description:'IT consulting and services company. Research regional offices, business units and current public signals.',signals:[{type:'Tender / contract',icon:'▣',date:'Sep 02, 2026',text:'Example signal: search relevant public procurement notices.'},{type:'Office expansion',icon:'▥',date:'Aug 18, 2026',text:'Example signal: validate expansion claims using official sources.'}],contacts:[{name:'Infrastructure Leader',role:'IT Director',email:'Find via connected contact provider',phone:'Not verified',note:'Infrastructure priorities'},{name:'Vendor Manager',role:'Procurement Manager',email:'Find via connected contact provider',phone:'Not verified',note:'Vendor qualification'}]},
 {id:'ltim',name:'LTIMindtree',short:'LTIM',industry:'IT Services',size:'5,001–10,000 employees',location:'Andhra Pradesh, India',intent:'Medium',description:'Digital transformation and technology services provider. Confirm target office, team and project fit.',signals:[{type:'Hiring',icon:'♟',date:'Aug 27, 2026',text:'Example signal category: engineering and cloud hiring.'},{type:'New project',icon:'✦',date:'Aug 12, 2026',text:'Research public project announcements.'}],contacts:[{name:'Digital Transformation Leader',role:'VP / Director, Digital',email:'Find via connected contact provider',phone:'Not verified',note:'Digital programmes'},{name:'Technology Procurement',role:'Procurement Lead',email:'Find via connected contact provider',phone:'Not verified',note:'Supplier selection'}]},
 {id:'techm',name:'Tech Mahindra',short:'TechM',industry:'IT Services',size:'10,001+ employees',location:'Andhra Pradesh, India',intent:'Low',description:'Technology and digital services provider. Current buying intent has not been verified in this demo.',signals:[{type:'Hiring',icon:'♟',date:'Aug 14, 2026',text:'Example signal category: technology role openings.'},{type:'Acquisition',icon:'◈',date:'Jul 30, 2026',text:'Check official announcements for corporate developments.'}],contacts:[{name:'Technology Leader',role:'CTO / IT Director',email:'Find via connected contact provider',phone:'Not verified',note:'Technology priorities'}]},
 {id:'sample-mfg',name:'Coastal Manufacturing Group',short:'CMG',industry:'Manufacturing',size:'201–1,000 employees',location:'Gajuwaka, Visakhapatnam, Andhra Pradesh, India',intent:'Medium',description:'Illustrative sample account to demonstrate searching outside IT services. This is not a verified business listing.',signals:[{type:'Technology upgrade',icon:'♙',date:'Sep 04, 2026',text:'Illustrative signal: ERP or factory systems upgrade.'},{type:'New project',icon:'✦',date:'Aug 19, 2026',text:'Illustrative signal: capacity expansion project.'}],contacts:[{name:'Plant Technology Lead',role:'Plant IT / OT Manager',email:'Find via connected contact provider',phone:'Not verified',note:'Factory systems and automation'},{name:'Purchase Lead',role:'Procurement Manager',email:'Find via connected contact provider',phone:'Not verified',note:'Equipment and supplier buying'}]}
];
let companies=[], selectedId='', targets=JSON.parse(localStorage.getItem('leadFinderTargets')||'[]'), outreach=JSON.parse(localStorage.getItem('leadFinderOutreach')||'[]'), activeTab='signals', modalAction=null;
const COMPANY_PAGE_URL = <?= json_encode(route('lead-generation-bot.finder-company')) ?>; // View details: the company's own page with all its projects
const FORM_FIELDS=['dateLabel','dateFrom','dateTo','location','project','globalSearch']; // industry and signalFilter: fields hidden, always 'any'
function rememberResults(){try{sessionStorage.setItem('leadFinderResults',JSON.stringify({companies:window.searchResults||companies,selectedId,form:Object.fromEntries(FORM_FIELDS.map(f=>[f,document.getElementById(f)?.value??'']))}))}catch(e){}}
function openCompanyPage(id,focus){try{focus?sessionStorage.setItem('leadFinderFocus',JSON.stringify(focus)):sessionStorage.removeItem('leadFinderFocus')}catch(e){}try{sessionStorage.setItem('leadFinderView',JSON.stringify({view:currentView,companySearchResults:window.companySearchResults||[]}))}catch(e){}const oc=companies.find(x=>x.id===id)||searchResults.find(x=>x.id===id)||(window.companySearchResults||[]).find(x=>x.id===id);try{sessionStorage.setItem('leadFinderCompany',JSON.stringify(oc||null))}catch(e){}if(searchResults.some(x=>x.id===id))selectedId=id;rememberResults();location.href=COMPANY_PAGE_URL+'?id='+encodeURIComponent(id)+(focus?'&focus=1':'')}
function restoreResults(){try{const r=JSON.parse(sessionStorage.getItem('leadFinderResults')||'null');if(!r||!(r.companies||[]).some(c=>c.live))return;companies=r.companies;searchResults=companies;selectedId=r.selectedId||companies[0].id;Object.entries(r.form||{}).forEach(([f,v])=>{if(!FORM_FIELDS.includes(f))return;const el=document.getElementById(f);if(el)el.value=v})}catch(e){}}
const $=id=>document.getElementById(id); const escapeHtml=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function toast(msg){$('toast').textContent=msg;$('toast').style.display='block';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>$('toast').style.display='none',2800)}
function saveState(){localStorage.setItem('leadFinderTargets',JSON.stringify(targets));localStorage.setItem('leadFinderOutreach',JSON.stringify(outreach));if(window.saved)updateSavedCounts()}
function signalClass(type){if(/Hiring/i.test(type))return 'tag';if(/Office/i.test(type))return 'tag green';if(/Technology/i.test(type))return 'tag purple';if(/Tender/i.test(type))return 'tag orange';if(/Acquisition/i.test(type))return 'tag red';return 'tag orange'}
const COMPANIES_PER_PAGE=5;let companyPage=1;var currentView='search',searchResults=null,saved={target:[],saved_project:[],target_project:[]};
function renderCompanyPager(total,pages,from){if(!total){$('companyPager').innerHTML='';return}
let b=`<button type="button" data-cpage="${companyPage-1}" ${companyPage===1?'disabled':''}>‹ Prev</button>`;for(let i=1;i<=pages;i++)b+=`<button type="button" data-cpage="${i}" class="${i===companyPage?'active':''}">${i}</button>`;b+=`<button type="button" data-cpage="${companyPage+1}" ${companyPage===pages?'disabled':''}>Next ›</button>`;
$('companyPager').innerHTML=`<span class="pager-info">Showing ${from+1}–${Math.min(from+COMPANIES_PER_PAGE,total)} of ${total} · Page ${companyPage} of ${pages}</span><div class="pager-btns">${b}</div>`;
$('companyPager').querySelectorAll('[data-cpage]').forEach(x=>x.addEventListener('click',()=>{companyPage=+x.dataset.cpage;renderCompanies();$('companyResults').scrollIntoView({behavior:'smooth',block:'nearest'})}))}
function panelHeading(title,sub){$('resultsTitle').firstChild.textContent=title+' ';const n=$('resultsTitle').nextElementSibling;if(n)n.textContent=sub}
/* Search Projects results: one card per project (only projects of the research project, in the location; the server
   filters them), newest first, 5 per page */
var projectResultItems=[];
function projectWhen(p){const ds=[p.source?.published,p.date,p.notification?.date].filter(Boolean);let best=0;
 ds.forEach(d=>{const m=String(d).match(/\d{1,2}\s+[a-z]{3,9},?\s+\d{4}|[a-z]{3,9}\.?\s+\d{1,2},?\s+\d{4}|\d{4}-\d{2}-\d{2}|[a-z]{3,9}\.?\s+\d{4}|\b(19|20)\d{2}\b/i);if(m){const t=Date.parse(/^\d{4}$/.test(m[0])?m[0]+'-01-01':m[0].replace(',',''));if(t>best)best=t}});return best}
function renderProjectResults(){document.querySelector('.layout').classList.add('single');
 projectResultItems=companies.flatMap(c=>(c.projects||[]).map(p=>({c,p}))).sort((a,b)=>projectWhen(b.p)-projectWhen(a.p));
 const total=projectResultItems.length;panelHeading('Projects found','Only projects of your research project in your location, newest first. Click the company name to open its page.');
 $('resultCount').textContent=total||projectSearched?`(${total})`:'';$('contactBotBtn').hidden=!total;const pages=Math.max(1,Math.ceil(total/COMPANIES_PER_PAGE));companyPage=Math.min(Math.max(1,companyPage),pages);const from=(companyPage-1)*COMPANIES_PER_PAGE;renderCompanyPager(total,pages,from);$('demoNote').style.display='none';
 $('companyResults').innerHTML=total?projectResultItems.slice(from,from+COMPANIES_PER_PAGE).map((it,j)=>projectCard(it.c,it.p,'result',from+j)).join(''):projectSearched?'<div class="empty">No projects of this research project were found in this location. Try the district or a wider location.</div>':'<div class="empty">Choose a location and research project, then click Search projects.</div>'}
function renderCompanies(){if(currentView==='search'){renderProjectResults();return}const cb=document.getElementById('contactBotBtn');if(cb)cb.hidden=['contacts','followups','savedProjects','targetProjects','apiAccess'].includes(currentView);if(currentView==='contacts'){renderContactList();return}if(currentView==='followups'){renderFollowUps();return}if(currentView==='apiAccess'){renderApiAccess();return}if(currentView==='savedProjects'||currentView==='targetProjects'){renderProjectList();return}if(currentView==='companySearch'&&!companies.length){panelHeading('Companies found','Results of your company search appear here.');$('resultCount').textContent='(0)';$('companyPager').innerHTML='';$('demoNote').style.display='none';$('companyResults').innerHTML='<div class="empty">Search by company or project name, location, project type or software above, then click Search.</div>';return}panelHeading(currentView==='targets'?'My target companies':'Companies found',currentView==='targets'?'Companies you saved with Save target. Click a name to open its page.':'Select a company to review its signals and contacts.');let q=$('globalSearch').value.trim().toLowerCase();let industry=$('industry').value,sig=$('signalFilter').value;let list=companies.filter(c=>(!q||(c.name+' '+c.location+' '+(c.contacts||[]).map(x=>x.name+' '+x.role).join(' ')).toLowerCase().includes(q))&&(c.live||((!industry||c.industry===industry)&&(!sig||c.signals.some(s=>s.type===sig)))));const sort=$('sortBy')?.value||'relevance';if(sort==='name')list.sort((a,b)=>a.name.localeCompare(b.name));if(sort==='intent')list.sort((a,b)=>({High:3,Medium:2,Low:1}[b.intent]-{High:3,Medium:2,Low:1}[a.intent]));$('resultCount').textContent=`(${list.length})`;const pages=Math.max(1,Math.ceil(list.length/COMPANIES_PER_PAGE));companyPage=Math.min(Math.max(1,companyPage),pages);const from=(companyPage-1)*COMPANIES_PER_PAGE;renderCompanyPager(list.length,pages,from);$('demoNote').style.display=companies.some(c=>c.live)||currentView==='companySearch'?'none':'';$('companyResults').innerHTML=list.length?list.slice(from,from+COMPANIES_PER_PAGE).map(c=>`<article class="company-card ${c.id===selectedId?'selected':''}" data-id="${escapeHtml(c.id)}"><div class="logo">${escapeHtml(c.short)}</div><div><div class="company-name"><a href="#" class="company-link" data-open="${escapeHtml(c.id)}" title="Open the company page">${escapeHtml(c.name)} <span style="color:#1680f8;font-size:12px">↗</span></a></div><div class="company-meta">⌖ ${escapeHtml(c.location)}<br>▦ ${escapeHtml(c.industry)} &nbsp; · &nbsp; ♟ ${escapeHtml(c.size)}</div><div class="tags">${c.signals.slice(0,3).map(s=>`<span class="${signalClass(s.type)}">${escapeHtml(s.type)}</span>`).join('')}</div><div class="card-contacts" data-ck="${escapeHtml(contactKey(c))}">${contactStrip(c)}</div></div><div class="card-actions"><span class="intent ${c.intent.toLowerCase()}">${c.intent} intent</span><button class="btn btn-light btn-small" data-action="details" data-id="${escapeHtml(c.id)}">View details</button><label class="checkbox-line"><input type="checkbox" data-action="target" data-id="${escapeHtml(c.id)}" ${isTarget(c)?'checked':''}> Save target</label></div></article>`).join(''):'<div class="empty">No matching companies. Try changing the location or filters.</div>';document.querySelectorAll('.company-card').forEach(card=>card.addEventListener('click',e=>{if(e.target.closest('button')||e.target.closest('label')||e.target.closest('a'))return;selectCompany(card.dataset.id)}));document.querySelectorAll('[data-action="details"]').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();openCompanyPage(b.dataset.id)}));document.querySelectorAll('[data-action="target"]').forEach(cb=>cb.addEventListener('change',()=>toggleTarget(cb.dataset.id,cb.checked)));if(list.length&&!list.some(c=>c.id===selectedId))selectCompany(list[0].id,false)}
function sizeMatches(actual,filter){if(!filter)return true;if(filter==='51–200 employees')return /51.?200/.test(actual);if(filter==='1–50 employees')return /1.?50/.test(actual);if(filter==='201–1,000 employees')return /201.?1,000/.test(actual);if(filter==='1,001–5,000 employees')return /1,001.?5,000/.test(actual);if(filter==='5,001+ employees')return /5,001|10,001/.test(actual);return true}
function selectCompany(id,rerender=true){selectedId=id;const sc=companies.find(x=>x.id===id);activeTab=sc&&sc.live?'projects':'signals';if(rerender)renderCompanies();renderDetail()}
async function toggleTarget(id,checked){const c=companies.find(x=>x.id===id)||searchResults.find(x=>x.id===id);if(!c)return;
try{await savedCall(checked?'POST':'DELETE',{kind:'target',key:companyKey(c),data:checked?c:undefined});toast(checked?'Company saved to My Targets':'Company removed from My Targets')}catch(e){toast(e.message)}
if(currentView==='targets'){companies=savedTargetCompanies();if(!companies.some(x=>x.id===selectedId))selectedId=companies[0]?.id||''}renderCompanies();renderDetail()}
function renderDetail(){const c=companies.find(x=>x.id===selectedId);if(!c){$('companyDetail').innerHTML='<div class="empty">Select a company to view details.</div>';return}$('companyDetail').innerHTML=`<div class="detail-top"><div class="logo">${escapeHtml(c.short)}</div><div><h2>${escapeHtml(c.name)} <span style="color:#1680f8">↗</span></h2><p>▦ ${escapeHtml(c.industry)} &nbsp;·&nbsp; ♟ ${escapeHtml(c.size)}<br>⌖ ${escapeHtml(c.location)}</p><div class="tags"><span class="intent ${c.intent.toLowerCase()}">${c.intent} intent</span>${isTarget(c)?'<span class="tag green">Saved target</span>':''}</div></div></div><p class="description">${escapeHtml(c.description)}</p><div class="tabs">${c.live?`<button data-tab="projects" class="${activeTab==='projects'?'active':''}">Projects (${(c.projects||[]).length})</button>`:''}<button data-tab="signals" class="${activeTab==='signals'?'active':''}">Buying signals (${c.signals.length})</button><button data-tab="contacts" class="${activeTab==='contacts'?'active':''}">Key contacts (${c.contacts.length})</button><button data-tab="outreach" class="${activeTab==='outreach'?'active':''}">Outreach</button><button data-tab="sources" class="${activeTab==='sources'?'active':''}">Sources &amp; notifications (${(c.sources||[]).length+(c.notifications||[]).length})</button></div><div class="tab-content" id="tabContent">${tabMarkup(c)}</div><div class="quick-actions"><h3>Quick actions</h3><div class="quick-grid"><button class="btn btn-primary wide" id="addTargetBtn">${isTarget(c)?'✓ Saved to My Targets':'＋ Add to My Targets'}</button><button class="btn btn-light" id="findContactsBtn">♟ Find contacts</button><button class="btn btn-light" id="planOutreachBtn">➤ Plan outreach</button></div></div><div class="notice">Contact names, emails, phone numbers and buying signals must be verified through your connected data provider or public sources before outreach.</div>`;document.querySelectorAll('[data-tab]').forEach(b=>b.addEventListener('click',()=>{activeTab=b.dataset.tab;renderDetail()}));$('addTargetBtn').addEventListener('click',()=>toggleTarget(c.id,!isTarget(c)));$('findContactsBtn').addEventListener('click',()=>openModal('contacts'));$('planOutreachBtn').addEventListener('click',()=>openModal('outreach'))}
function verifyBadge(v){v=v||{key:'no-link',label:'No link given'};return `<span class="verify verify-${escapeHtml(v.key)}">${v.key==='official'||v.key==='verified'?'✓':(v.key==='unverified'?'⚠':'–')} ${escapeHtml(v.label)}</span>`}
function safeUrl(u){return /^https?:\/\//i.test(u||'')?u:''}
function sourcesMarkup(c){const notes=c.notifications||[],srcs=c.sources||[];
const noteRows=notes.map(n=>`<div class="src-card"><div class="src-head"><strong>${escapeHtml(n.title)}</strong>${verifyBadge(n.verification)}</div><dl class="src-meta"><dt>Notification title</dt><dd>${escapeHtml(n.title)}</dd><dt>Publishing organization</dt><dd>${escapeHtml(n.organization||'Not stated')}</dd><dt>Publication date</dt><dd>${escapeHtml(n.date||'Not stated')}</dd><dt>Original source link</dt><dd>${safeUrl(n.url)?`<a href="${escapeHtml(n.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(n.url)}</a>`:'Not given'}</dd><dt>Verification status</dt><dd>${verifyBadge(n.verification)}</dd></dl></div>`).join('');
const srcRows=srcs.map(x=>`<div class="src-row"><div style="min-width:0"><strong>${escapeHtml(x.title)}</strong><p>${escapeHtml(x.publisher||'')}${safeUrl(x.url)?` · <a href="${escapeHtml(x.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(x.url)}</a>`:''}</p></div>${verifyBadge(x.verification)}</div>`).join('');
if(!c.live)return '<div class="empty">Sources and official notifications appear for companies found with a live ChatGPT search.</div>';
return `<h4 class="src-title">Official notifications (${notes.length})</h4>${noteRows||'<div class="empty" style="padding:14px">No official notification found for this company.</div>'}<h4 class="src-title">Data sources (${srcs.length})</h4>${srcRows||'<div class="empty" style="padding:14px">No sources were returned.</div>'}<p class="muted">Verification status is checked by the portal: ✓ the link opened (official = government site or the company's own website); ⚠ it did not open. Always read the original before acting on it.</p>`}
function linkHtml(u){return safeUrl(u)?`<a href="${escapeHtml(u)}" target="_blank" rel="noopener noreferrer">${escapeHtml(u)}</a>`:'Not given'}
function projectsMarkup(c){const ps=c.projects||[];if(!ps.length)return '<div class="empty">No projects were found for this company.</div>';
return ps.map((p,pi)=>projectCard(c,p,'detail',pi)).join('')}
function projectCard(c,p,src,pi){{const s=p.source||{},n=p.notification;const meta=[p.type?(p.icon||'✦')+' '+p.type:'',p.date,p.location,p.value].filter(Boolean).map(escapeHtml).join(' · ');
return `<div class="src-card"><div class="src-head"><strong>${escapeHtml(p.name)}</strong>${p.status?`<span class="proj-status">${escapeHtml(p.status)}</span>`:''}</div>${meta?`<div class="proj-meta">${meta}</div>`:''}${p.description?`<p class="proj-desc">${escapeHtml(p.description)}</p>`:''}
${src==='list'?`<div class="proj-company">🏢 <a href="#" data-open-saved="${pi}">${escapeHtml(c.name)}</a>${c.location?' · '+escapeHtml(c.location):''}</div>`:''}${src==='result'?`<div class="released"><div class="released-head"><div style="min-width:0"><span class="released-by">Released by</span><a href="#" class="released-name" data-focus="${pi}" title="Open the company page">🏢 ${escapeHtml(c.name)}</a><div class="muted">${escapeHtml([c.industry,c.location].filter(Boolean).join(' · '))}</div></div><button type="button" class="btn btn-primary btn-small released-btn" data-focus="${pi}">🏢 Get company details</button></div><div class="card-contacts" data-ck="${escapeHtml(contactKey(c))}">${contactStrip(c)}</div></div>`:''}${/* Save project / Target project commented out on the Search Projects results: `<div class="proj-actions">${projectButtons(c,p,src,pi)}</div>` */ src==='result'?'':`<div class="proj-actions">${projectButtons(c,p,src,pi)}</div>`}
<div class="proj-block"><div class="proj-label">Source</div><dl class="src-meta"><dt>Page</dt><dd>${escapeHtml(s.title||s.publisher||'Not stated')}${s.publisher&&s.title?' · '+escapeHtml(s.publisher):''}</dd><dt>Source link</dt><dd>${linkHtml(s.url)}</dd><dt>Verification status</dt><dd>${verifyBadge(s.verification)}</dd></dl></div>
<div class="proj-block"><div class="proj-label">Official notification</div>${n?`<dl class="src-meta"><dt>Notification title</dt><dd>${escapeHtml(n.title)}</dd><dt>Publishing organization</dt><dd>${escapeHtml(n.organization||'Not stated')}</dd><dt>Publication date</dt><dd>${escapeHtml(n.date||'Not stated')}</dd><dt>Original source link</dt><dd>${linkHtml(n.url)}</dd><dt>Verification status</dt><dd>${verifyBadge(n.verification)}</dd></dl>`:'<p class="muted" style="margin:0">No official notification found for this project.</p>'}</div>${(()=>{const rel=x=>/\bago\b|^last |^this |yesterday|today/i.test(x||'');const d=[s.published,n&&n.date].find(x=>x&&!rel(x))||'';return d?`<div class="proj-published">📅 Published: <b>${escapeHtml(d)}</b></div>`:(p.date?`<div class="proj-published">📅 Dated: <b>${escapeHtml(p.date)}</b></div>`:'')})()}</div>`}}
function tabMarkup(c){if(activeTab==='projects')return projectsMarkup(c);if(activeTab==='sources')return sourcesMarkup(c);if(activeTab==='signals')return c.signals.map(s=>`<div class="signal"><div class="signal-icon">${escapeHtml(s.icon)}</div><div><strong>${escapeHtml(s.type)}</strong><p>${escapeHtml(s.text)}</p></div><time>${escapeHtml(s.date)}</time></div>`).join('')||'<div class="empty">No signals available.</div>';if(activeTab==='contacts')return c.contacts.map(x=>`<div class="contact-row"><div><strong>${escapeHtml(x.name)}</strong><p>${escapeHtml(x.role)}<br>${escapeHtml(x.email)} · ${escapeHtml(x.phone)}<br>${escapeHtml(x.note)}</p></div><span class="contact-role">Decision maker</span></div>`).join('')+'<p class="muted">From public sources via ChatGPT web search. Verify each contact before outreach.</p>';return `<p class="muted">Create and track a planned email, call, or LinkedIn touchpoint for this account.</p>${outreach.filter(o=>o.companyId===c.id).map(o=>`<div class="signal"><div class="signal-icon">➤</div><div><strong>${escapeHtml(o.channel)} · ${escapeHtml(o.contact||'Contact to be identified')}</strong><p>${escapeHtml(o.note||'No notes')}</p></div><time>${escapeHtml(o.date||'No date')}</time></div>`).join('')||'<div class="empty">No outreach planned yet.</div>'}<button class="btn btn-light" id="tabPlanBtn">＋ Create outreach task</button>`}
function openModal(action){modalAction=action;const c=companies.find(x=>x.id===selectedId);$('modalTitle').textContent=action==='contacts'?'Identify decision-maker contacts':'Plan outreach';$('modalBody').innerHTML=action==='contacts'?`<p class="muted">Choose the roles you want your contact provider to search for at <strong>${escapeHtml(c.name)}</strong>.</p><div class="modal-grid"><div class="field full"><label>Target job titles</label><input id="contactTitles" value="CTO, IT Director, Project Manager, Procurement Manager"></div><div class="field"><label>Seniority</label><select id="seniority"><option>Director and above</option><option>Manager and above</option><option>Any seniority</option></select></div><div class="field"><label>Department</label><select id="department"><option>IT / Technology</option><option>Procurement</option><option>Engineering / Projects</option><option>Operations</option></select></div></div><p class="notice">The frontend cannot discover private contact data by itself. A backend must call a permitted contact-data API and respect provider terms and privacy rules.</p>`:`<div class="modal-grid"><div class="field"><label>Channel</label><select id="outreachChannel"><option>Email</option><option>Phone call</option><option>LinkedIn</option><option>Other</option></select></div><div class="field"><label>Follow-up date</label><input id="outreachDate" type="date" value="${new Date(Date.now()+86400000).toISOString().slice(0,10)}"></div><div class="field full"><label>Contact / role</label><input id="outreachContact" placeholder="e.g. IT Director (verify contact first)"></div><div class="field full"><label>Notes</label><textarea id="outreachNote" rows="3" placeholder="Personalised reason for reaching out…"></textarea></div></div>`;$('modalBackdrop').style.display='flex';$('saveModal').textContent=action==='contacts'?'Search contacts':'Save outreach task'}
function closeModal(){$('modalBackdrop').style.display='none'}$('closeModal').addEventListener('click',closeModal);$('cancelModal').addEventListener('click',closeModal);$('modalBackdrop').addEventListener('click',e=>{if(e.target===$('modalBackdrop'))closeModal()});$('saveModal').addEventListener('click',()=>{const c=companies.find(x=>x.id===selectedId);if(modalAction==='contacts'){toast('Contact search settings saved. Connect a contact API to return real results.');activeTab='contacts';closeModal();renderDetail();return}outreach.push({id:Date.now(),companyId:c.id,channel:$('outreachChannel').value,date:$('outreachDate').value,contact:$('outreachContact').value,note:$('outreachNote').value});saveState();closeModal();activeTab='outreach';renderDetail();toast('Outreach task saved')});
let searchOk=false,searchEmpty=false,projectSearched=false;
async function demoSearch(filters){searchOk=false;searchEmpty=false;if(API_BASE_URL){try{const params=new URLSearchParams(filters);const r=await fetch(`${API_BASE_URL}/api/companies/search?${params}`,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});if(r.status===401||r.redirected){toast('Log in to the HRMS portal first, then search again.');return companies}const data=await r.json().catch(()=>({message:'The server returned an invalid response.'}));if(!r.ok)throw new Error(data.message||('API returned '+r.status));if(!(data.companies||[]).length){toast('ChatGPT found no matching companies. Try a wider location or another industry.');searchEmpty=true;return []}searchOk=true;return data.companies.map(c=>({...c,live:true}))}catch(e){toast(e.message||'Search failed.');console.error(e);return companies}}return DEMO_COMPANIES.filter(c=>(!filters.industry||c.industry===filters.industry)&&(!filters.q||(c.name+' '+c.location).toLowerCase().includes(filters.q.toLowerCase()))&&(!filters.signal||c.signals.some(s=>s.type===filters.signal)))}
$('searchForm').addEventListener('submit',async e=>{e.preventDefault();const btn=e.submitter;btn.disabled=true;btn.textContent='ChatGPT is searching… (up to a minute)';const filters={date_label:'Latest first',location:$('location').value.trim(),industry:$('industry').value,project:$('project').value,signal:$('signalFilter').value,q:$('globalSearch').value.trim()};companies=await demoSearch(filters);projectSearched=searchEmpty;searchResults=companies;companyPage=1;selectedId=companies[0]?.id||'';rememberResults();btn.disabled=false;btn.innerHTML='⌕ &nbsp; Search projects';renderCompanies();renderDetail();if(searchOk)runContactBot(companies);if(searchOk)toast(`ChatGPT found ${companies.length} compan${companies.length===1?'y':'ies'}`)});$('globalSearch').addEventListener('input',()=>renderCompanies());$('sortBy')?.addEventListener('change',()=>{companyPage=1;renderCompanies()});
document.querySelectorAll('[data-nav]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-nav]').forEach(x=>x.classList.toggle('active',x===b));const nav=b.dataset.nav;const titles={search:['Search Projects','Search by location and research project: only projects of that category in that location, newest first.'],companySearch:['Search Companies','Find construction and engineering companies by company or project name, location, project type or software.'],targets:['My Target Companies','Review the companies you saved for research and outreach.'],savedProjects:['Saved Projects','Projects you saved from company searches and company pages.'],targetProjects:['Target Projects','Projects you are targeting for proposals and outreach.'],signals:['Projects & Buying Signals','Review project announcements, hiring, expansion, tenders and technology changes.'],contacts:['Contacts','Companies you added to contacts and the details of your conversations with them: notes, follow-ups and projects.'],outreach:['Outreach Management','Plan and track email, phone and LinkedIn touchpoints.'],followups:['Follow-ups','Follow-ups asked for in your contact details: overdue, today, upcoming and done.'],apiAccess:['API access','Run the Search Projects search from other programs: GET /api/lead-finder/projects/search with your API key.']};$('pageTitle').textContent=titles[nav][0];$('pageSubtitle').textContent=titles[nav][1];$('filterPanel').classList.toggle('hidden',nav!=='search');$('companyFilterPanel').classList.toggle('hidden',nav!=='companySearch');moveDateField(nav);currentView=nav;companyPage=1;document.querySelector('.layout').classList.toggle('single',nav==='savedProjects'||nav==='targetProjects'||nav==='contacts'||nav==='followups'||nav==='apiAccess');if(nav==='targets')companies=savedTargetCompanies();else if(nav==='savedProjects'||nav==='targetProjects')companies=[];else if(nav==='signals')companies=DEMO_COMPANIES.filter(c=>c.signals.length);else if(nav==='contacts'){companies=[];loadContacts()}else if(nav==='followups'){companies=[];loadContacts()}else if(nav==='apiAccess'){companies=[];loadApiKey()}else if(nav==='outreach')companies=DEMO_COMPANIES.filter(c=>outreach.some(o=>o.companyId===c.id));else if(nav==='companySearch')companies=companySearchResults;else companies=searchResults;selectedId=companies[0]?.id||'';renderCompanies();renderDetail();if(nav==='outreach')toast(`${outreach.length} outreach task(s) saved locally`)}));
searchResults=companies;restoreResults();saveState();renderCompanies();renderDetail();

/* Industry, Research projects and Buying signal dropdowns: search box, + Add popup (saved for all administrators in
   lead_finder_projects, type industry / project / signal); the chosen values go with the search */
const LIST_API=<?= json_encode(route('target-companies.research-projects')) ?>;
const PICKERS={
 in:{type:'industry',input:'industry',any:'All industries',noun:'industry',list:'Industry',plural:'industries',standard:['IT Services','Manufacturing','Construction','Healthcare','Logistics & Transportation','Financial Services','Education','Energy'],placeholder:'e.g. Real estate'},
 pt:{type:'project_type',input:'cfType',any:'All Project Types',noun:'project type / software',list:'Project Type / Software',plural:'project types',standard:['Bridges & flyovers','Roads & highways','Metro & railways','Buildings & real estate','Industrial plants & factories','Ports & marine','Water supply & sewerage','Power & energy','Airports','Survey, GIS & mapping','Revit / BIM','AutoCAD','Civil 3D','Tekla Structures','STAAD.Pro','Navisworks','ArcGIS','Primavera P6'],placeholder:'e.g. SketchUp or Tunnels'},
 rp:{type:'project',input:'project',any:'Any project',noun:'project',list:'Research projects',plural:'projects',standard:[],placeholder:'e.g. GIS mapping survey'},
 bs:{type:'signal',input:'signalFilter',any:'All signals',noun:'signal',list:'Buying signal',plural:'signals',standard:['New project','Office expansion','Hiring','Technology upgrade','Tender / contract','Acquisition'],placeholder:'e.g. Land acquisition'}
};
Object.keys(PICKERS).forEach(k=>{if(!document.getElementById(k+'Toggle'))delete PICKERS[k]}); // dropdowns commented out in the form
let popupFor=null;
const pickerItems=k=>{const p=PICKERS[k];return [...p.standard,...(p.saved||[]).filter(n=>!p.standard.some(x=>x.toLowerCase()===n.toLowerCase()))]};
function pickerOpen(k,open){Object.keys(PICKERS).forEach(o=>{if(o===k?!open:open){$(o+'Menu').hidden=true;$(o+'Toggle').setAttribute('aria-expanded',false)}});if(!open)return;$(k+'Menu').hidden=false;$(k+'Toggle').setAttribute('aria-expanded',true);$(k+'Search').value='';pickerRender(k);$(k+'Search').focus()}
function pickerChoose(k,name){const p=PICKERS[k];$(p.input).value=name;$(k+'Label').textContent=name||p.any;$(k+'Toggle').classList.toggle('chosen',!!name);pickerOpen(k,false)}
function pickerRender(k){const p=PICKERS[k],typed=$(k+'Search').value.trim(),q=typed.toLowerCase(),cur=$(p.input).value,all=pickerItems(k),list=all.filter(n=>n.toLowerCase().includes(q));
$(k+'List').innerHTML=(q?'':`<div class="rp-item ${cur?'':'active'}" data-name="">${escapeHtml(p.any)}</div>`)+list.map(n=>`<div class="rp-item ${n===cur?'active':''}" data-name="${escapeHtml(n)}" role="option">${escapeHtml(n)}${p.standard.length&&!p.standard.includes(n)?' <span class="rp-own">added</span>':''}</div>`).join('')
+(q&&!all.some(n=>n.toLowerCase()===q)?`<div class="rp-item rp-suggest" data-add="${escapeHtml(typed)}">+ Add "${escapeHtml(typed)}"</div>`:'')
+(!list.length&&!q?`<div class="rp-empty">No ${p.plural} yet. Click + Add new ${p.noun}.</div>`:'');
$(k+'List').querySelectorAll('[data-name]').forEach(el=>el.addEventListener('click',()=>pickerChoose(k,el.dataset.name)));
$(k+'List').querySelectorAll('[data-add]').forEach(el=>el.addEventListener('click',()=>pickerPopup(k,el.dataset.add)))}
function pickerPopup(k,name){const p=PICKERS[k];popupFor=k;pickerOpen(k,false);$('rpModalTitle').textContent={signal:'Add buying signal',industry:'Add industry',project:'Add research project',project_type:'Add project type / software'}[p.type];$('rpNewLabel').textContent={signal:'Signal name',industry:'Industry name',project:'Project name',project_type:'Project type or software'}[p.type];$('rpNew').placeholder=p.placeholder;$('rpModalSave').textContent='Add '+p.noun;$('rpNew').value=name||'';$('rpModalMsg').style.color='';$('rpModalMsg').textContent=`The ${p.noun} is added to the ${p.list} list for all administrators and selected for your search.`;$('rpModal').style.display='flex';setTimeout(()=>$('rpNew').focus(),30)}
function rpPopupClose(){$('rpModal').style.display='none'}
async function pickerLoad(k){const p=PICKERS[k];try{const r=await fetch(LIST_API+'?type='+p.type,{headers:{Accept:'application/json'},credentials:'same-origin'});if(r.ok)p.saved=(await r.json()).items||[]}catch(e){}if(!$(k+'Menu').hidden)pickerRender(k)}
async function pickerAdd(k,name){const p=PICKERS[k];name=(name||'').trim();if(name.length<2){$('rpModalMsg').style.color='#b42318';$('rpModalMsg').textContent=`Type a ${p.noun} name (at least 2 characters).`;$('rpNew').focus();return}
const same=pickerItems(k).find(n=>n.toLowerCase()===name.toLowerCase());if(same){pickerChoose(k,same);rpPopupClose();toast(`That ${p.noun} is already in the list, selected it.`);return}
$('rpModalSave').disabled=true;try{const r=await fetch(LIST_API,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},credentials:'same-origin',body:JSON.stringify({type:p.type,name})});const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.message||`Could not add the ${p.noun}.`);p.saved=d.items||p.saved;pickerChoose(k,d.added||name);rpPopupClose();toast(`${p.noun[0].toUpperCase()+p.noun.slice(1)} "${d.added||name}" added`)}catch(e){$('rpModalMsg').style.color='#b42318';$('rpModalMsg').textContent=e.message}finally{$('rpModalSave').disabled=false}}
Object.keys(PICKERS).forEach(k=>{
 $(k+'Toggle').addEventListener('click',()=>pickerOpen(k,$(k+'Menu').hidden));
 $(k+'AddOpen').addEventListener('click',()=>pickerPopup(k,''));
 $(k+'Search').addEventListener('input',()=>pickerRender(k));
 $(k+'Search').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();const first=$(k+'List').querySelector('[data-name]:not([data-name=""]),[data-add]');if(first)first.click()}if(e.key==='Escape')pickerOpen(k,false)});
 $(k+'AddBtn').addEventListener('click',()=>pickerPopup(k,$(k+'Search').value.trim()));
 if($(PICKERS[k].input).value)pickerChoose(k,$(PICKERS[k].input).value);
 pickerLoad(k);
});
$('rpForm').addEventListener('submit',e=>{e.preventDefault();if(popupFor)pickerAdd(popupFor,$('rpNew').value)});
$('rpModalClose').addEventListener('click',rpPopupClose);$('rpModalCancel').addEventListener('click',rpPopupClose);
$('rpModal').addEventListener('click',e=>{if(e.target===$('rpModal'))rpPopupClose()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&$('rpModal').style.display==='flex')rpPopupClose()});
document.addEventListener('click',e=>{Object.keys(PICKERS).forEach(k=>{if(!$(k).contains(e.target))pickerOpen(k,false)})});

/* Location fields (Search Projects and Search Companies): place suggestions while typing, worldwide (OpenStreetMap
   via LOC_API); picking one fills in "place, district, state, country" (LOC_API/resolve). Anything typed by hand is
   searched as it is. */
const LOC_API=<?= json_encode(route('target-companies.locations')) ?>;
function placeSearch(inputId,menuId,listId){
 const input=$(inputId),menu=$(menuId),list=$(listId);let timer=null,seq=0,active=-1,places=[];
 const show=open=>{menu.hidden=!open;input.setAttribute('aria-expanded',open)};
 function draw(msg){if(msg){list.innerHTML=`<div class="rp-empty">${msg}</div>`;show(true);return}
  if(!places.length){list.innerHTML='<div class="rp-empty">No places found. You can still search with what you typed.</div>';show(true);return}
  list.innerHTML=places.map((p,i)=>`<div class="rp-item loc-item ${i===active?'active':''}" data-i="${i}" role="option"><strong>${escapeHtml(p.name)}</strong>${p.detail?`<span>${escapeHtml(p.detail)}</span>`:''}</div>`).join('');
  list.querySelectorAll('[data-i]').forEach(el=>el.addEventListener('mousedown',e=>{e.preventDefault();pick(+el.dataset.i)}));show(true)}
 async function suggest(){const q=input.value.trim();if(q.length<2){show(false);return}const n=++seq;draw('<span class="spin-dot"></span> Searching places…');
  try{const r=await fetch(LOC_API+'?'+new URLSearchParams({q}),{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json().catch(()=>({}));if(n!==seq)return;if(!r.ok){draw(escapeHtml(d.message||'Place suggestions are not available right now.'));return}places=d.places||[];active=-1;draw()}catch(e){if(n===seq)draw('Place suggestions are not available right now.')}}
 async function pick(i){const p=places[i];if(!p)return;show(false);seq++;input.value=[p.name,p.detail.split(' · ').pop()].filter(Boolean).join(', ');
  try{const r=await fetch(LOC_API+'/resolve?'+new URLSearchParams({id:p.id}),{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json();if(r.ok&&d.place?.label)input.value=d.place.label}catch(e){}}
 input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(suggest,300)});
 input.addEventListener('focus',()=>{if(places.length&&input.value.trim().length>=2)draw()});
 input.addEventListener('keydown',e=>{if(menu.hidden||!places.length)return;
  if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();active=(active+(e.key==='ArrowDown'?1:-1)+places.length)%places.length;draw()}
  else if(e.key==='Enter'&&active>=0){e.preventDefault();pick(active)}else if(e.key==='Escape'){show(false)}});
 input.addEventListener('blur',()=>setTimeout(()=>show(false),150));
}
placeSearch('location','locMenu','locList');
placeSearch('cfLocation','cfLocMenu','cfLocList');


/* Project / signal date (from calender.html): quick periods, a month (Flatpickr month picker) or a start-to-end range;
   sent with the search as date_label, date_from and date_to (YYYY-MM-DD) */
const DT_OPTIONS=['Latest first','This week','Last week','This month','Last month','This year','Choose month and year','Choose date range (Start to End)','All dates'];
const ymd=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
function dtPeriod(name){const t=new Date(),d=new Date(t.getFullYear(),t.getMonth(),t.getDate()),mon=new Date(d);mon.setDate(d.getDate()-((d.getDay()+6)%7));
 if(name==='This week')return[mon,d];if(name==='Last week'){const a=new Date(mon);a.setDate(a.getDate()-7);const b=new Date(mon);b.setDate(b.getDate()-1);return[a,b]}
 if(name==='This month')return[new Date(d.getFullYear(),d.getMonth(),1),d];if(name==='Last month')return[new Date(d.getFullYear(),d.getMonth()-1,1),new Date(d.getFullYear(),d.getMonth(),0)];
 if(name==='This year')return[new Date(d.getFullYear(),0,1),d];return[null,null]}
function dtSet(label,from,to){$('dateLabel').value=label;$('dateFrom').value=from?ymd(from):'';$('dateTo').value=to?ymd(to):'';$('dtLabel').textContent=label;$('dtToggle').classList.toggle('chosen',label!=='All dates');dtShow(false)}
function dtShow(open){$('dtMenu').hidden=!open;$('dtToggle').setAttribute('aria-expanded',open);if(open)dtRender()}
function dtRender(){const cur=$('dateLabel').value,custom=!DT_OPTIONS.includes(cur);
 $('dtList').innerHTML=DT_OPTIONS.map(o=>{const on=o===cur||(custom&&((o==='Choose month and year'&&/^[A-Z][a-z]+ \d{4}$/.test(cur))||(o.startsWith('Choose date range')&&/ to /.test(cur))));return `<div class="rp-item ${on?'active':''}" data-dt="${escapeHtml(o)}" role="option">${escapeHtml(o)}${o.startsWith('Choose')?' <span class="rp-own">📅</span>':''}</div>`}).join('');
 $('dtList').querySelectorAll('[data-dt]').forEach(el=>el.addEventListener('click',()=>{const o=el.dataset.dt;
  if(o==='Choose month and year'){dtShow(false);fpMonth.open()}else if(o.startsWith('Choose date range')){dtShow(false);fpRange.open()}else{const [a,b]=dtPeriod(o);dtSet(o,a,b)}}))}
const fpMonth=flatpickr('#dtMonthInput',{positionElement:$('dtToggle'),plugins:[new monthSelectPlugin({shorthand:false,dateFormat:'F Y',altFormat:'F Y'})],
 onChange:(sel,str)=>{if(sel[0])dtSet(str,new Date(sel[0].getFullYear(),sel[0].getMonth(),1),new Date(sel[0].getFullYear(),sel[0].getMonth()+1,0))}});
const fpRange=flatpickr('#dtRangeInput',{positionElement:$('dtToggle'),mode:'range',dateFormat:'d M Y',
 onChange:(sel)=>{if(sel.length===2)dtSet(`${fpRange.formatDate(sel[0],'d M Y')} to ${fpRange.formatDate(sel[1],'d M Y')}`,sel[0],sel[1])}});
$('dtToggle').addEventListener('click',()=>dtShow($('dtMenu').hidden));
document.addEventListener('click',e=>{if(!$('dt').contains(e.target))dtShow(false)});
document.addEventListener('keydown',e=>{if(e.key==='Escape')dtShow(false)});
$('dtLabel').textContent=$('dateLabel').value||'Latest first';

/* My Targets, Saved Projects and Target Projects: kept on the server for each user (lead_finder_saved) */
const SAVED_API=<?= json_encode(route('target-companies.saved')) ?>;
var projectListItems=[];
const nameKey=x=>String(x||'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim().slice(0,90);
const companyKey=c=>nameKey(c.name);
const projectKey=(c,p)=>companyKey(c)+'|'+nameKey(p.name);
function isTarget(c){return saved.target.some(t=>t.key===companyKey(c))}
function isMarked(kind,c,p){return saved[kind].some(t=>t.key===projectKey(c,p))}
function savedTargetCompanies(){return saved.target.map(t=>({...t.data,id:'t-'+t.key.replace(/ /g,'-'),live:true}))}
function updateSavedCounts(){const n=x=>x?`(${x})`:'';$('targetCount').textContent=n(saved.target.length);$('savedProjectCount').textContent=n(saved.saved_project.length);$('targetProjectCount').textContent=n(saved.target_project.length)}
async function savedCall(method,body){const r=await fetch(SAVED_API,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},credentials:'same-origin',body:method==='GET'?undefined:JSON.stringify(body)});
 const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.message||'Could not save. Please try again.');saved=d.saved;updateSavedCounts();return d}
function projectButtons(c,p,src,pi){const sp=isMarked('saved_project',c,p),tp=isMarked('target_project',c,p);
 return `<button type="button" class="btn btn-small ${sp?'btn-on':'btn-light'}" data-pact="saved_project" data-src="${src}" data-i="${pi}">${sp?'★ Saved project':'☆ Save project'}</button><button type="button" class="btn btn-small ${tp?'btn-on':'btn-light'}" data-pact="target_project" data-src="${src}" data-i="${pi}">${tp?'◉ Target project':'◎ Target project'}</button>`}
async function toggleProject(kind,c,p){const on=isMarked(kind,c,p),label=kind==='saved_project'?'Saved Projects':'Target Projects';
 const company={name:c.name,short:c.short||'',location:c.location||'',industry:c.industry||'',size:c.size||'',intent:c.intent||'',website:c.website||'',description:c.description||''};
 try{await savedCall(on?'DELETE':'POST',{kind,key:projectKey(c,p),data:on?undefined:{...p,company}});toast(on?`Removed from ${label}`:`Added to ${label}`)}catch(e){toast(e.message)}
 renderCompanies();if(currentView!=='savedProjects'&&currentView!=='targetProjects')renderDetail()}
function renderProjectList(){const kind=currentView==='savedProjects'?'saved_project':'target_project';panelHeading(kind==='saved_project'?'Saved projects':'Target projects','Newest first. Click the company name to open its page with all its projects.');projectListItems=saved[kind];const total=projectListItems.length;
 $('resultCount').textContent=`(${total})`;const pages=Math.max(1,Math.ceil(total/COMPANIES_PER_PAGE));companyPage=Math.min(Math.max(1,companyPage),pages);const from=(companyPage-1)*COMPANIES_PER_PAGE;renderCompanyPager(total,pages,from);$('demoNote').style.display='none';
 $('companyResults').innerHTML=total?projectListItems.slice(from,from+COMPANIES_PER_PAGE).map((it,j)=>`<div class="saved-when">Saved ${escapeHtml(it.saved_at)}</div>`+projectCard(it.data.company||{name:''},it.data,'list',from+j)).join(''):`<div class="empty">No ${kind==='saved_project'?'saved':'target'} projects yet. Open a company's Projects and click ${kind==='saved_project'?'☆ Save project':'◎ Target project'}.</div>`}
document.addEventListener('click',e=>{
 const b=e.target.closest('[data-pact]');
 if(b){e.preventDefault();let c,p;if(b.dataset.src==='detail'){c=companies.find(x=>x.id===selectedId);p=c?.projects?.[+b.dataset.i]}else if(b.dataset.src==='result'){const it=projectResultItems[+b.dataset.i];c=it?.c;p=it?.p}else{p=projectListItems[+b.dataset.i]?.data;c=p?.company}if(c&&p)toggleProject(b.dataset.pact,c,p);return}
 const fo=e.target.closest('[data-focus]');if(fo){e.preventDefault();const it=projectResultItems[+fo.dataset.focus];if(it)openCompanyPage(it.c.id,{id:it.c.id,project:it.p});return} // Search Projects: the company page with only this project
 const o=e.target.closest('[data-open]');if(o){e.preventDefault();openCompanyPage(o.dataset.open);return}
 const os=e.target.closest('[data-open-saved]');if(os){e.preventDefault();const p=projectListItems[+os.dataset.openSaved]?.data;if(p?.company){const c={...p.company,id:'p-'+companyKey(p.company).replace(/ /g,'-'),live:true,projects:[p],signals:[],contacts:[],sources:[],notifications:[]};companies=[c];openCompanyPage(c.id)}}
});
savedCall('GET').then(()=>{if(currentView==='targets'){companies=savedTargetCompanies();selectedId=companies[0]?.id||''}renderCompanies();renderDetail()}).catch(()=>{});

/* Search Companies (Project Finder): construction and engineering companies by company / project name, location,
   project type or software and project date (ChatGPT search, mode=companies); its own results list */
var companySearchResults=[];
const dateField=$('dt').closest('.field'),dateHome=dateField.nextElementSibling;dateField.style.display='none';
function moveDateField(nav){if(nav==='companySearch'){$('cfDateSlot').appendChild(dateField)}else if(dateField.parentElement!==dateHome.parentElement){dateHome.parentElement.insertBefore(dateField,dateHome)}
 dateField.style.display=nav==='companySearch'?'':'none'; // Search Projects has no date filter: always newest first
 $('dtLabel').closest('.field').querySelector('label').textContent=nav==='companySearch'?'Project Date':'Project / signal date'}
$('companySearchForm').addEventListener('submit',async e=>{e.preventDefault();const btn=e.submitter||$('companySearchForm').querySelector('[type="submit"]');btn.disabled=true;btn.textContent='ChatGPT is searching… (up to a minute)';
 const filters={mode:'companies',q:$('cfQuery').value.trim(),location:$('cfLocation').value.trim(),project_type:$('cfType').value,date_label:$('dateLabel').value,date_from:$('dateFrom').value,date_to:$('dateTo').value};
 const found=await demoSearch(filters);btn.disabled=false;btn.innerHTML='⌕ &nbsp; Search';
 if(searchOk){companySearchResults=found;runContactBot(found);if(currentView==='companySearch'){companies=found;companyPage=1;selectedId=found[0]?.id||'';renderCompanies();renderDetail()}toast(`ChatGPT found ${found.length} compan${found.length===1?'y':'ies'}`)}});
/* coming back from a company page: open the same section again */
try{const back=JSON.parse(sessionStorage.getItem('leadFinderView')||'null');sessionStorage.removeItem('leadFinderView');if(back){if(Array.isArray(back.companySearchResults))companySearchResults=back.companySearchResults;if(back.view&&back.view!=='search'&&!document.body.classList.contains('projects-only'))document.querySelector(`[data-nav="${back.view}"]`)?.click()}}catch(e){}

/* Contact bot: for every company in the results, finds its phone numbers (official website and its contact page),
   email, website and LinkedIn (TargetCompanyFinderController::profile, kept 7 days on the server) and shows them on
   the company card. Runs after each search; the 🤖 Contact bot button runs it again for the companies shown. */
const PROFILE_API=<?= json_encode(route('target-companies.profile')) ?>;
var contactInfo=(()=>{try{return JSON.parse(sessionStorage.getItem('leadFinderContacts')||'{}')}catch(e){return {}}})(),contactBusy={},botRun=0;
function contactKey(c){return String(c.name||'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim()+'|'+String(c.location||'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim()}
function contactStrip(c){const k=contactKey(c),i=(window.contactInfo||{})[k];
 if(!i)return (window.contactBusy||{})[k]?'<span class="cc cc-muted"><span class="spin-dot"></span> Contact bot is finding the phone, email and LinkedIn…</span>':'';
 if(i.error)return '<span class="cc cc-muted">Contacts could not be found right now.</span>';
 const parts=[];
 (i.phones||[]).forEach(n=>parts.push(`<a class="cc" href="tel:${escapeHtml(n.replace(/[^\d+]/g,''))}" title="Call">☎ ${escapeHtml(n)}</a>`));
 if(i.email)parts.push(`<a class="cc" href="mailto:${escapeHtml(i.email)}" title="Email">✉ ${escapeHtml(i.email)}</a>`);
 if(i.linkedin)parts.push(`<a class="cc cc-li" href="${escapeHtml(i.linkedin)}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(i.linkedin)}"><b>in</b> LinkedIn</a>`);
 if(i.website)parts.push(`<a class="cc" href="${escapeHtml(i.website)}" target="_blank" rel="noopener noreferrer">🌐 ${escapeHtml(i.website.replace(/^https?:\/\/(www\.)?/,'').replace(/\/$/,''))}</a>`);
 return parts.length?parts.join(''):'<span class="cc cc-muted">No public phone, email or LinkedIn found.</span>'}
function refreshStrips(c){const k=contactKey(c);document.querySelectorAll('.card-contacts').forEach(el=>{if(el.dataset.ck===k)el.innerHTML=contactStrip(c)})}
async function findContacts(c){const k=contactKey(c);contactBusy[k]=true;refreshStrips(c);
 try{const site=c.website||((c.description||'').match(/Website:\s*(\S+)/)||[])[1]||'';
  const r=await fetch(PROFILE_API+'?'+new URLSearchParams({name:c.name,location:c.location||'',website:site}),{headers:{Accept:'application/json'},credentials:'same-origin'});
  const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.message||'error');const pr=d.profile||{},links=pr.links||[];
  contactInfo[k]={phones:pr.phones||(pr.phone?[pr.phone]:[]),email:pr.email||'',website:pr.website||'',linkedin:(links.find(l=>l.key==='linkedin')||{}).url||''};
 }catch(e){contactInfo[k]={error:true}}
 delete contactBusy[k];try{sessionStorage.setItem('leadFinderContacts',JSON.stringify(contactInfo))}catch(e){}refreshStrips(c)}
async function runContactBot(list,again){const run=++botRun,todo=(list||[]).filter(c=>c.live&&(again||!contactInfo[contactKey(c)]||contactInfo[contactKey(c)].error));
 if(!todo.length){if(again)toast('Contact bot: every company in the list already has its contacts.');return}
 const btn=$('contactBotBtn');let done=0;const show=()=>{btn.disabled=true;btn.textContent=`🤖 Finding contacts ${done}/${todo.length}…`};show();
 const queue=[...todo];const worker=async()=>{while(queue.length&&run===botRun){await findContacts(queue.shift());done++;show()}};
 await Promise.all([worker(),worker()]);if(run!==botRun)return;
 btn.disabled=false;btn.textContent='🤖 Contact bot';const withPhone=todo.filter(c=>(contactInfo[contactKey(c)]?.phones||[]).length).length;
 toast(`Contact bot: phone numbers found for ${withPhone} of ${todo.length} compan${todo.length===1?'y':'ies'}`)}
$('contactBotBtn').addEventListener('click',()=>{if(!companies.some(c=>c.live)){toast('Contact bot: search first, then the bot finds the contacts of the companies found.');return}runContactBot(companies.filter(c=>c.live),true)});
renderCompanies();

/* Contacts (sidebar): one card per company added from a company page ("Add to contacts"), with the company's own
   phone / email / website / LinkedIn (contact bot) and every conversation saved with "Add contact details" (also from
   here), newest first, each with its date */
const CONTACTS_API=<?= json_encode(route('target-companies.contacts')) ?>;
var contactList=null,contactGroups=[],cmGroup=null;
function groupContacts(list){const by=new Map();list.forEach(x=>{if(!by.has(x.key))by.set(x.key,{key:x.key,company:x.company||{},entries:[]});by.get(x.key).entries.push(x)});
 return [...by.values()].map(g=>{const first=g.entries[g.entries.length-1];return {...g,added_at:first.added_at,added_by:first.added_by,details:g.entries.filter(x=>x.contact_name||x.phone||x.email||x.notes||x.follow_up||(x.projects||[]).length)}})}
function setContacts(list){contactList=list||[];contactGroups=groupContacts(contactList);$('contactCount').textContent=contactGroups.length?`(${contactGroups.length})`:'';
 const pending=contactList.filter(x=>x.follow_up&&!x.follow_up_done).length;$('followUpCount').textContent=pending?`(${pending})`:''}
async function loadContacts(){try{const r=await fetch(CONTACTS_API,{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json();if(r.ok)setContacts(d.contacts)}catch(e){if(contactList===null)setContacts([])}
 if(currentView==='followups')renderCompanies();if(currentView==='contacts'){renderCompanies();contactGroups.forEach(g=>{const c=contactCompany(g);if(!contactInfo[contactKey(c)])findContacts(c)})}}
function contactCompany(g){return {...g.company,id:g.company?.id||('ct-'+g.key.replace(/ /g,'-')),live:true,projects:[],signals:[],contacts:[],sources:[],notifications:[]}}
function detailHtml(x){return `<div class="ct-entry"><div class="ct-entry-head"><span>📅 ${escapeHtml(x.added_at)}${x.added_by?` · ${escapeHtml(x.added_by)}`:''}</span><button type="button" class="ct-x" data-del-contact="${x.id}" title="Remove these details">✕</button></div>
 <dl class="ct-dl">${x.contact_name?`<dt>Contact person</dt><dd>${escapeHtml(x.contact_name)}${x.designation?` · ${escapeHtml(x.designation)}`:''}</dd>`:''}
 ${x.phone?`<dt>Phone</dt><dd><a href="tel:${escapeHtml(x.phone.replace(/[^\d+]/g,''))}">${escapeHtml(x.phone)}</a></dd>`:''}
 ${x.email?`<dt>Email</dt><dd><a href="mailto:${escapeHtml(x.email)}">${escapeHtml(x.email)}</a></dd>`:''}
 ${x.notes?`<dt>Notes</dt><dd class="ct-notes">${escapeHtml(x.notes)}</dd>`:''}
 <dt>Follow-up</dt><dd>${x.follow_up?`<span class="ct-yes">Yes</span> · ${escapeHtml(x.follow_up_date||'')}`:'<span class="ct-no">No</span>'}</dd>
 ${(x.projects||[]).length?`<dt>Projects</dt><dd>${x.projects.map(p=>`<div class="ct-proj"><b>${escapeHtml(p.name)}</b>${p.details?` — ${escapeHtml(p.details)}`:''}</div>`).join('')}</dd>`:''}</dl></div>`}
function renderContactList(){panelHeading('Contacts','Newest first. Add the details of each conversation with 📝 Add contact details; click the company name to open its page.');
 if(contactList===null){$('resultCount').textContent='';$('companyPager').innerHTML='';$('demoNote').style.display='none';$('companyResults').innerHTML='<div class="empty"><span class="spin-dot"></span> Loading contacts…</div>';return}
 const total=contactGroups.length;$('resultCount').textContent=`(${total})`;const pages=Math.max(1,Math.ceil(total/COMPANIES_PER_PAGE));companyPage=Math.min(Math.max(1,companyPage),pages);const from=(companyPage-1)*COMPANIES_PER_PAGE;renderCompanyPager(total,pages,from);$('demoNote').style.display='none';
 $('companyResults').innerHTML=total?contactGroups.slice(from,from+COMPANIES_PER_PAGE).map((g,j)=>{const c=contactCompany(g),i=from+j;
  return `<div class="ct-card"><div class="ct-head"><div style="min-width:0"><a href="#" class="ct-co" data-open-contact="${i}">${escapeHtml(c.name||'')}</a><div class="muted">${escapeHtml([c.industry,c.location].filter(Boolean).join(' · '))}</div>
    <div class="card-contacts" data-ck="${escapeHtml(contactKey(c))}">${contactStrip(c)}</div></div>
   <div class="ct-when"><span class="ct-badge">Added to contacts</span><div>📅 ${escapeHtml(g.added_at)}</div>${g.added_by?`<div class="muted">by ${escapeHtml(g.added_by)}</div>`:''}</div></div>
   <div class="ct-details"><div class="ct-sub">Contact details (${g.details.length})</div>${g.details.map(detailHtml).join('')||'<p class="muted" style="margin:4px 0 0">No contact details yet. Click 📝 Add contact details after you talk with the company.</p>'}</div>
   <div class="ct-actions"><button type="button" class="btn btn-primary btn-small" data-add-details="${i}">📝 Add contact details</button><button type="button" class="btn btn-light btn-small" data-open-contact="${i}">Open company page</button><button type="button" class="btn btn-light btn-small ct-del" data-del-company="${i}">Remove company</button></div></div>`}).join(''):'<div class="empty">No contacts yet. Open a company page and click ➕ Add to contacts.</div>'}
async function deleteContactIds(ids){for(const id of ids){const r=await fetch(CONTACTS_API+'/'+id,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},credentials:'same-origin'});const j=await r.json().catch(()=>({}));if(!r.ok)throw new Error('error');setContacts(j.contacts)}}
/* the form */
function cmProjectRow(){const row=document.createElement('div');row.className='cm-proj';row.innerHTML='<input class="cm-pname" maxlength="191" placeholder="Project name"><textarea class="cm-pdetails" rows="2" maxlength="2000" placeholder="Projects they are working on / details"></textarea><button type="button" class="close" title="Remove">✕</button>';
 row.querySelector('.close').addEventListener('click',()=>{row.remove();if(!$('cmProjectRows').children.length)$('cmProjectRows').appendChild(cmProjectRow())});return row}
function openContactForm(g){cmGroup=g;$('cmForm').reset();$('cmError').hidden=true;$('cmDateWrap').hidden=true;$('cmProjects').hidden=true;$('cmProjectRows').innerHTML='';$('cmProjectRows').appendChild(cmProjectRow());
 $('cmCompany').textContent=(g.company.name||'')+(g.company.location?' · '+g.company.location:'');$('cmModal').style.display='flex';setTimeout(()=>$('cmName').focus(),30)}
const closeContactForm=()=>{$('cmModal').style.display='none'};
document.querySelectorAll('input[name="cmFollow"]').forEach(r=>r.addEventListener('change',()=>{$('cmDateWrap').hidden=!(r.value==='1'&&r.checked)}));
document.querySelectorAll('input[name="cmProj"]').forEach(r=>r.addEventListener('change',()=>{$('cmProjects').hidden=!(r.value==='1'&&r.checked)}));
$('cmAddProject').addEventListener('click',()=>$('cmProjectRows').appendChild(cmProjectRow()));
$('cmClose').addEventListener('click',closeContactForm);$('cmCancel').addEventListener('click',closeContactForm);
$('cmModal').addEventListener('click',e=>{if(e.target===$('cmModal'))closeContactForm()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&$('cmModal').style.display==='flex')closeContactForm()});
$('cmForm').addEventListener('submit',async e=>{e.preventDefault();if(!cmGroup)return;
 const follow=document.querySelector('input[name="cmFollow"]:checked').value==='1',withProjects=document.querySelector('input[name="cmProj"]:checked').value==='1';
 const projects=withProjects?[...document.querySelectorAll('.cm-proj')].map(r=>({name:r.querySelector('.cm-pname').value.trim(),details:r.querySelector('.cm-pdetails').value.trim()})).filter(p=>p.name||p.details):[];
 const body={company:cmGroup.company,contact_name:$('cmName').value.trim(),designation:$('cmDesignation').value.trim(),phone:$('cmPhone').value.trim(),email:$('cmEmail').value.trim(),notes:$('cmNotes').value.trim(),follow_up:follow,follow_up_date:follow?$('cmFollowDate').value:null,projects};
 const err=m=>{$('cmError').textContent=m;$('cmError').hidden=false};
 if(!body.contact_name&&!body.phone&&!body.email&&!body.notes&&!follow&&!projects.length)return err('Enter at least the contact person, phone, email or notes.');
 if(follow&&!body.follow_up_date)return err('Choose the follow-up date.');
 if(withProjects&&!projects.length)return err('Enter the project name, or choose No for projects.');
 if(projects.some(p=>!p.name))return err('Enter the name of every project.');
 $('cmSave').disabled=true;
 try{const r=await fetch(CONTACTS_API,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},credentials:'same-origin',body:JSON.stringify(body)});
  const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.errors?Object.values(d.errors).flat()[0]:(d.message||'Could not save. Please try again.'));
  setContacts(d.contacts);closeContactForm();renderCompanies();toast('✓ Contact saved successfully.')}catch(e2){err(e2.message)}
 $('cmSave').disabled=false});
document.addEventListener('click',async e=>{
 const o=e.target.closest('[data-open-contact]');if(o){e.preventDefault();const g=contactGroups[+o.dataset.openContact];if(!g)return;const c=contactCompany(g);companies=[c];openCompanyPage(c.id);return}
 const a=e.target.closest('[data-add-details]');if(a){const g=contactGroups[+a.dataset.addDetails];if(g)openContactForm(g);return}
 const d=e.target.closest('[data-del-contact]');if(d){if(!confirm('Remove these contact details?'))return;try{await deleteContactIds([d.dataset.delContact]);renderCompanies();toast('Contact details removed')}catch(e2){toast('Could not remove. Please try again.')}return}
 const dc=e.target.closest('[data-del-company]');if(dc){const g=contactGroups[+dc.dataset.delCompany];if(!g||!confirm(`Remove ${g.company.name} and all its contact details from Contacts?`))return;try{await deleteContactIds(g.entries.map(x=>x.id));renderCompanies();toast('Removed from Contacts')}catch(e2){toast('Could not remove. Please try again.')}}
});
loadContacts();

/* Follow-ups (sidebar): every contact detail saved with "Did they ask for a follow-up? Yes", by date: overdue, today,
   upcoming, then done; "Mark done" / "Undo" */
var followUpItems=[];
function renderFollowUps(){panelHeading('Follow-ups','From contact details saved with Follow-up: Yes. Mark each one done after you follow up.');
 if(contactList===null){$('resultCount').textContent='';$('companyPager').innerHTML='';$('demoNote').style.display='none';$('companyResults').innerHTML='<div class="empty"><span class="spin-dot"></span> Loading follow-ups…</div>';return}
 const today=ymd(new Date());const all=contactList.filter(x=>x.follow_up);
 const when=x=>x.follow_up_done?'done':(!x.follow_up_on?'upcoming':x.follow_up_on<today?'overdue':x.follow_up_on===today?'today':'upcoming');
 const order={overdue:0,today:1,upcoming:2,done:3};
 followUpItems=all.slice().sort((a,b)=>order[when(a)]-order[when(b)]||(when(a)==='done'?String(b.follow_up_on).localeCompare(String(a.follow_up_on)):String(a.follow_up_on).localeCompare(String(b.follow_up_on))));
 $('resultCount').textContent=`(${all.filter(x=>!x.follow_up_done).length} pending)`;$('companyPager').innerHTML='';$('demoNote').style.display='none';
 if(!followUpItems.length){$('companyResults').innerHTML='<div class="empty">No follow-ups yet. In Contacts, add contact details and choose Yes for “Did they ask for a follow-up?”.</div>';return}
 const labels={overdue:'⚠ Overdue',today:'📌 Today',upcoming:'🗓 Upcoming',done:'✓ Done'};let last='';
 $('companyResults').innerHTML=followUpItems.map((x,i)=>{const w=when(x),c=x.company||{};const head=w!==last?`<div class="fu-group fu-${w}">${labels[w]} (${followUpItems.filter(y=>when(y)===w).length})</div>`:'';last=w;
  return head+`<div class="fu-card fu-${w}"><div class="fu-date"><b>${escapeHtml(x.follow_up_date||'No date')}</b>${w==='done'&&x.follow_up_done_at?`<span>done ${escapeHtml(x.follow_up_done_at)}</span>`:''}</div>
   <div class="fu-main"><a href="#" class="ct-co" data-open-follow="${i}">${escapeHtml(c.name||'')}</a><div class="muted">${escapeHtml([c.industry,c.location].filter(Boolean).join(' · '))}</div>
    <div class="fu-who">${x.contact_name?`👤 ${escapeHtml(x.contact_name)}${x.designation?` · ${escapeHtml(x.designation)}`:''}`:''}${x.phone?` &nbsp; ☎ <a href="tel:${escapeHtml(x.phone.replace(/[^\d+]/g,''))}">${escapeHtml(x.phone)}</a>`:''}${x.email?` &nbsp; ✉ <a href="mailto:${escapeHtml(x.email)}">${escapeHtml(x.email)}</a>`:''}</div>
    ${x.notes?`<div class="fu-notes">${escapeHtml(x.notes)}</div>`:''}
    ${(x.projects||[]).length?`<div class="fu-proj">Projects: ${x.projects.map(p=>`<b>${escapeHtml(p.name)}</b>${p.details?` (${escapeHtml(p.details)})`:''}`).join(', ')}</div>`:''}
    <div class="muted fu-meta">Saved ${escapeHtml(x.added_at)}${x.added_by?` by ${escapeHtml(x.added_by)}`:''}</div></div>
   <div class="fu-actions">${x.follow_up_done?`<button type="button" class="btn btn-light btn-small" data-follow-done="${x.id}" data-done="0">↺ Undo</button>`:`<button type="button" class="btn btn-primary btn-small" data-follow-done="${x.id}" data-done="1">✓ Mark done</button>`}</div></div>`}).join('')}
document.addEventListener('click',async e=>{
 const o=e.target.closest('[data-open-follow]');if(o){e.preventDefault();const x=followUpItems[+o.dataset.openFollow];if(!x)return;const c={...x.company,id:x.company?.id||('ct-'+x.key.replace(/ /g,'-')),live:true,projects:[],signals:[],contacts:[],sources:[],notifications:[]};companies=[c];openCompanyPage(c.id);return}
 const b=e.target.closest('[data-follow-done]');if(!b)return;b.disabled=true;
 try{const r=await fetch(CONTACTS_API+'/'+b.dataset.followDone+'/follow-up',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},credentials:'same-origin',body:JSON.stringify({done:b.dataset.done==='1'})});
  const j=await r.json();if(!r.ok)throw new Error('error');setContacts(j.contacts);renderCompanies();toast(b.dataset.done==='1'?'✓ Follow-up marked done':'Follow-up moved back to pending')}catch(e2){b.disabled=false;toast('Could not update. Please try again.')}
});

/* API access (sidebar): the user's key for GET /api/lead-finder/projects/search (LeadFinderApiController); the key
   itself is shown once, when it is made */
const APIKEY_URL=<?= json_encode(route('target-companies.api-key')) ?>;
var apiInfo=null,apiNewKey='';
async function apiKeyCall(method){const r=await fetch(APIKEY_URL,{method,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},credentials:'same-origin'});const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.message||'Please try again.');return d}
async function loadApiKey(){try{apiInfo=await apiKeyCall('GET')}catch(e){apiInfo={error:e.message}}if(currentView==='apiAccess')renderCompanies()}
function renderApiAccess(){panelHeading('API access','Search Projects as an API: the same search as this page (Location, Research project, Project / signal date), as JSON.');
 $('resultCount').textContent='';$('companyPager').innerHTML='';$('demoNote').style.display='none';
 if(!apiInfo){$('companyResults').innerHTML='<div class="empty"><span class="spin-dot"></span> Loading…</div>';return}
 if(apiInfo.error){$('companyResults').innerHTML=`<div class="empty">${escapeHtml(apiInfo.error)}</div>`;return}
 const ep=apiInfo.endpoint,key=apiNewKey||'YOUR_API_KEY',ex=`${ep}?location=${encodeURIComponent('Gajuwaka, Visakhapatnam, Andhra Pradesh, India')}&project=${encodeURIComponent('GIS Mapping survey')}&date=this_month`;
 $('companyResults').innerHTML=`<div class="api-box">
  <h3>1. Your API key</h3>
  ${apiNewKey?`<div class="api-new"><b>Your new key — copy it now, it is shown only once:</b><div class="api-code"><code id="apiKeyText">${escapeHtml(apiNewKey)}</code><button type="button" class="btn btn-light btn-small" data-copy="apiKeyText">Copy</button></div></div>`:''}
  <p>${apiInfo.has_key?`You have a key ending in <b>…${escapeHtml(apiInfo.last_four)}</b>, made ${escapeHtml(apiInfo.created_at)}. Last used: <b>${escapeHtml(apiInfo.last_used_at||'never')}</b>.`:'You have no API key yet.'}</p>
  <div class="api-actions"><button type="button" class="btn btn-primary btn-small" id="apiCreate">${apiInfo.has_key?'Make a new key (replaces the old one)':'Create API key'}</button>${apiInfo.has_key?'<button type="button" class="btn btn-light btn-small ct-del" id="apiRevoke">Revoke key</button>':''}</div>
  <p class="muted">Keep the key secret: anyone with it can run searches as you. Send it in the header <code>Authorization: Bearer &lt;key&gt;</code>. From this computer (localhost, e.g. Postman here) the API also works without a key.</p>
  <h3>2. Request</h3>
  <div class="api-code"><code id="apiEndpoint">GET ${escapeHtml(ep)}</code><button type="button" class="btn btn-light btn-small" data-copy="apiEndpoint">Copy</button></div>
  <table class="api-table"><thead><tr><th>Parameter</th><th>Required</th><th>Meaning</th></tr></thead><tbody>
   <tr><td><code>location</code></td><td>no (India)</td><td>Location field, e.g. <code>Gajuwaka, Visakhapatnam, Andhra Pradesh, India</code></td></tr>
   <tr><td><code>project</code></td><td>no (any)</td><td>Research project, e.g. <code>GIS Mapping survey</code></td></tr>
   <tr><td><code>date</code></td><td>no (latest)</td><td>Project / signal date: <code>latest</code>, <code>this_week</code>, <code>last_week</code>, <code>this_month</code>, <code>last_month</code>, <code>this_year</code>, <code>all</code>, or a month <code>2026-10</code></td></tr>
   <tr><td><code>date_from</code>, <code>date_to</code></td><td>no</td><td>A date range instead of <code>date</code>: <code>2026-10-01</code> … <code>2026-10-31</code></td></tr>
  </tbody></table>
  <h3>3. Example</h3>
  <div class="api-code"><code id="apiCurl">curl -H "Authorization: Bearer ${escapeHtml(key)}" -H "Accept: application/json" "${escapeHtml(ex)}"</code><button type="button" class="btn btn-light btn-small" data-copy="apiCurl">Copy</button></div>
  <p class="muted">Answer (JSON): <code>status</code>, <code>query</code> (location, project, date label / from / to), <code>count</code>, <code>projects_count</code> and <code>companies</code> — each company with its projects, signals, sources, official notifications and verification status, as on this page. A search takes up to a minute; at most 10 searches a minute. Errors: 401 (missing or wrong key), 403 (not an active administrator), 422 (wrong parameter), 429 (too many searches), 502 (ChatGPT error).</p>
 </div>`;
 $('apiCreate').addEventListener('click',async()=>{if(apiInfo.has_key&&!confirm('Make a new key? The old key stops working.'))return;try{const d=await apiKeyCall('POST');apiNewKey=d.key;apiInfo=d;renderCompanies();toast('✓ API key created — copy it now')}catch(e){toast(e.message)}});
 $('apiRevoke')?.addEventListener('click',async()=>{if(!confirm('Revoke the key? Programs using it stop working.'))return;try{apiInfo=await apiKeyCall('DELETE');apiNewKey='';renderCompanies();toast('API key revoked')}catch(e){toast(e.message)}});
 document.querySelectorAll('[data-copy]').forEach(b=>b.addEventListener('click',()=>{const t=document.getElementById(b.dataset.copy).textContent;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(()=>toast('Copied')).catch(()=>{const r=document.createRange();r.selectNodeContents(document.getElementById(b.dataset.copy));const sel=getSelection();sel.removeAllRanges();sel.addRange(r);toast('Selected — press Ctrl+C')})}))}

/* dedicated Search Projects page: the company page's "Back to results" comes back here */
try{document.body.classList.contains('projects-only')?sessionStorage.setItem('leadFinderMode','projects'):sessionStorage.removeItem('leadFinderMode')}catch(e){}
</script>
</body>
</html>
