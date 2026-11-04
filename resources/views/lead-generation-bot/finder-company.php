<?php
/*
 * Lead Finder → View details: one company with all its projects, 5 per page (LeadGenerationBotController::finderCompany,
 * in the same iframe as the finder). The finder hands the company over in sessionStorage (leadFinderResults); the
 * page shows the projects from the search at once, then asks TargetCompanyFinderController::projects for the full list.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= csrf_token() ?>" />
<title>Lead Finder — Company projects</title>
<style>
:root{--navy:#10284b;--blue:#0878f9;--blue2:#eaf3ff;--ink:#172744;--muted:#65748d;--line:#dfe7f1;--bg:#f3f6fb;--green:#0e9f6e;--amber:#b77908}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}button{font:inherit;cursor:pointer}
a{color:var(--blue)}.wrap{max-width:1240px;margin:0 auto;padding:24px 28px 40px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
.btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600}
.btn:hover{border-color:var(--blue);color:var(--blue)}.btn-primary{background:var(--blue);border-color:var(--blue);color:#fff}.btn-primary:hover{color:#fff;filter:brightness(1.05)}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px 22px;margin-bottom:18px}
.head{display:flex;gap:16px;align-items:flex-start}.logo{width:56px;height:56px;border:1px solid var(--line);border-radius:12px;display:grid;place-items:center;font-weight:800;color:var(--blue);flex-shrink:0}
h1{font-size:22px;margin:0 0 6px}.meta{font-size:13px;color:var(--muted);display:flex;flex-wrap:wrap;gap:6px 14px}
.intent{display:inline-block;margin-top:8px;padding:3px 10px;border-radius:14px;font-size:12px;font-weight:700}
.intent.high{background:#dcfce7;color:#166534}.intent.medium{background:#fef3c7;color:#92400e}.intent.low{background:#f1f5f9;color:#475569}
.desc{font-size:14px;line-height:1.55;margin:14px 0 0;color:#334155}
.bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.bar h2{font-size:17px;margin:0}.count{font-size:13px;color:var(--muted)}
.status{font-size:13px;padding:10px 14px;border-radius:10px;margin-bottom:14px;background:var(--blue2);color:#0b4a99}
.status.err{background:#fff7ed;color:#9a3412}.status.ok{background:#ecfdf5;color:#065f46}
.spin{display:inline-block;width:12px;height:12px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:s 1s linear infinite;vertical-align:-1px;margin-right:6px}@keyframes s{to{transform:rotate(360deg)}}
.src-card{border:1px solid #e3eaf3;border-radius:12px;padding:14px 16px;margin-bottom:12px;background:#fbfdff}
.src-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:6px}.src-head strong{font-size:14px}
.num{color:var(--muted);font-weight:600;margin-right:6px}
.src-meta{display:grid;grid-template-columns:170px minmax(0,1fr);gap:5px 12px;margin:0;font-size:13px}
.src-meta dt{color:#64748b;font-weight:500}.src-meta dd{margin:0;overflow-wrap:anywhere}
.proj-meta{font-size:13px;color:#475569;margin-bottom:6px}.proj-desc{font-size:13px;color:#334155;margin:0 0 8px}
.proj-status{flex-shrink:0;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#eef2ff;color:#3730a3;white-space:nowrap}
.proj-cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;border-top:1px dashed #e3eaf3;padding-top:10px;margin-top:10px}
.proj-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#0f2a4a;margin-bottom:6px}
.proj-cols .src-meta{grid-template-columns:130px minmax(0,1fr)}
.verify{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap}
.verify-official{background:#dcfce7;color:#166534}.verify-verified{background:#e0f2fe;color:#075985}
.verify-unverified{background:#fff7ed;color:#9a3412}.verify-no-link{background:#f1f5f9;color:#64748b}
.muted{color:var(--muted);font-size:13px}.empty{padding:28px;text-align:center;color:var(--muted);font-size:14px}
.pager{display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:16px}
.pager button{min-width:38px;height:38px;border:1px solid var(--line);background:#fff;border-radius:9px;font-size:13px;font-weight:600;color:var(--ink);padding:0 12px}
.pager button.active{background:var(--blue);border-color:var(--blue);color:#fff}.pager button:disabled{opacity:.45;cursor:default}
.logo{position:relative;overflow:hidden;background:#fff}.logo img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:6px;background:#fff}
.hero{display:flex;gap:24px;align-items:center}
.hero-logo{width:150px;height:110px;flex-shrink:0;border:1px solid var(--line);border-radius:14px;display:grid;place-items:center;background:#fff;overflow:hidden;font-size:30px;font-weight:800;color:var(--blue)}
.hero-logo img{width:100%;height:100%;object-fit:contain;padding:10px}
.hero-main{min-width:0;flex:1}.hero-main h1{font-size:26px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.hero-main .intent{margin-top:0;font-size:11px}
.tagline{font-size:16px;color:#64748b;margin:2px 0 6px}
.hero .social{border-top:0;padding-top:4px;margin-top:6px;gap:10px}
.hero .soc{font-size:14px;padding:8px 14px;border-color:transparent;background:#f8fafc}.hero .soc svg{width:22px;height:22px}.hero .soc span{color:var(--blue)}
.hero .desc{margin-top:10px;font-size:13px}
.info-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);gap:18px;align-items:start;margin-bottom:18px}
.info-grid .card{margin-bottom:0}.side-col{display:flex;flex-direction:column;gap:18px}
.ph{display:flex;align-items:center;gap:10px;font-size:17px;margin:0 0 14px}.ph svg{width:22px;height:22px;color:#334155}
.addr-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:14px}
.addr-text{display:flex;gap:10px;font-size:14px;line-height:1.5}.addr-text svg{width:22px;height:22px;color:#e11d48;flex-shrink:0;margin-top:2px}
.maps-btn{display:flex;align-items:center;gap:8px;border:1px solid #c7dafc;border-radius:10px;padding:8px 14px;color:var(--blue);text-decoration:none;font-weight:600;font-size:13px;line-height:1.2;flex-shrink:0}
.maps-btn svg{width:24px;height:24px}.maps-btn:hover{background:var(--blue2)}
.map-box{border:1px solid var(--line);border-radius:12px;overflow:hidden}.map-box iframe{display:block;width:100%;height:300px;border:0}
.map-foot{display:flex;align-items:center;gap:8px;padding:10px 12px;font-size:12px;color:#334155}.map-foot svg{width:16px;height:16px;flex-shrink:0}.map-foot span{flex:1}
.map-foot a{color:var(--blue);font-weight:600;text-decoration:none;white-space:nowrap}
.job{display:flex;gap:14px}.job-logo{width:54px;height:54px;border-radius:10px;display:grid;place-items:center;background:var(--blue2);color:var(--blue);flex-shrink:0}
.job-logo svg{width:30px;height:30px}.job-logo.li{background:#0a66c2;color:#fff}
.job-title{font-size:16px;font-weight:700}.job-co{font-size:13px;color:#475569;margin-bottom:6px}
.job-meta{display:flex;align-items:center;gap:8px;font-size:12px;color:#475569;margin-top:4px}.job-meta svg{width:14px;height:14px}
.job-btn{display:block;text-align:center;margin-top:14px;padding:11px;border-radius:10px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:14px}.job-btn.li{background:#0a66c2}
.details{display:flex;flex-direction:column}.drow{display:grid;grid-template-columns:20px 110px minmax(0,1fr);gap:10px;align-items:center;padding:8px 0;border-top:1px solid #f1f5f9;font-size:13px}
.drow:first-child{border-top:0}.drow svg{width:18px;height:18px;color:#475569}.dl{font-weight:600}.dv{overflow-wrap:anywhere}
.pad{margin:0}
@media(max-width:900px){.info-grid{grid-template-columns:1fr}.hero{flex-direction:column;align-items:flex-start}.addr-top{flex-direction:column}}
.contact{display:flex;flex-direction:column;gap:6px;margin:10px 0 4px;font-size:13px;color:#334155}
.cline{display:flex;align-items:flex-start;gap:8px;line-height:1.45}.cline svg{width:16px;height:16px;flex-shrink:0;margin-top:2px}
.cline a{overflow-wrap:anywhere}.cline .src{font-size:11px;color:var(--muted);margin-left:4px}
.c-address svg{color:#e11d48}.c-email svg{color:#ea580c}.c-web svg{color:var(--blue)}.c-phone svg{color:#16a34a}
.social{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--line);font-size:13px}
.social.muted{color:var(--muted)}
.soc{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border:1px solid var(--line);border-radius:20px;background:#fff;color:var(--ink);text-decoration:none;font-weight:600;font-size:12px}
.soc svg{width:16px;height:16px;flex-shrink:0}.soc:hover{border-color:currentColor;box-shadow:0 2px 8px #10284b14}
.soc-website{color:var(--blue)}.soc-linkedin{color:#0a66c2}.soc-facebook{color:#1877f2}.soc-instagram{color:#d62976}.soc-x{color:#111}.soc-youtube{color:#e00}.soc-other{color:#475569}
.soc span{color:var(--ink)}
.soc-missing{color:#94a3b8 !important;filter:grayscale(1);opacity:.75}.soc-missing span{color:#64748b !important}
.soc em{font-style:normal;font-size:10px;font-weight:600;color:#64748b;background:#e2e8f0;border-radius:8px;padding:1px 6px}
.soc-note{font-size:11px;color:var(--muted);margin-top:6px}
.name-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}.name-row h1{margin:0 0 6px}
.name-actions{display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0}.btn-done{background:#ecfdf5;border-color:#16a34a;color:#166534}
.toast-ok{background:#166534}.toast-bad{background:#9a3412}
.cm-backdrop{position:fixed;inset:0;background:#071a31a8;display:flex;align-items:flex-start;justify-content:center;padding:40px 16px;z-index:40;overflow:auto}
.cm-backdrop[hidden]{display:none}
.cm{background:#fff;border-radius:14px;width:min(640px,100%);padding:22px;box-shadow:0 20px 70px #0003}
.cm-head{display:flex;justify-content:space-between;align-items:center}.cm-head h3{margin:0;font-size:18px}
.cm-x{border:0;background:#eef3fa;border-radius:7px;padding:6px 10px;cursor:pointer}
.cm-sub{margin:4px 0 14px}
.cm-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.cm-grid .full{grid-column:1/-1}
.cm label{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:600;color:#34435d}
.cm input,.cm textarea{font:inherit;font-size:13px;font-weight:400;border:1px solid #d5dfec;border-radius:8px;padding:9px 10px;color:var(--ink)}
.cm input:focus,.cm textarea:focus{outline:none;border-color:#4094ff;box-shadow:0 0 0 3px #0878f91a}
.cm-q{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:13px;font-weight:600}
.cm .cm-yn{flex-direction:row;align-items:center;gap:5px;font-weight:500;font-size:13px}.cm .cm-date{flex-direction:row;align-items:center;gap:8px}
.cm-proj{display:grid;grid-template-columns:1fr 1.4fr auto;gap:8px;align-items:start;margin-top:10px}
#cmAddProject{margin-top:10px}
.cm-error{margin-top:12px;background:#fff7ed;color:#9a3412;border-radius:8px;padding:9px 12px;font-size:13px}
.cm-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
@media(max-width:620px){.cm-grid,.cm-proj{grid-template-columns:1fr}}
.proj-published{text-align:right;font-size:12px;color:#475569;margin-top:10px;padding-top:8px;border-top:1px solid #eef2f7}.proj-published b{color:#0f2a4a}
.proj-actions{display:flex;gap:8px;flex-wrap:wrap;margin:2px 0 4px}
.btn-sm{padding:6px 11px;font-size:12px;color:var(--blue);border-color:#b8d5ff}.btn-sm.on{background:var(--blue2);border-color:var(--blue);font-weight:700}
.toast{position:fixed;right:20px;bottom:20px;background:#10284b;color:#fff;padding:12px 16px;border-radius:10px;font-size:13px;box-shadow:0 10px 30px #0003;z-index:30}
@media(max-width:760px){.wrap{padding:16px}.proj-cols{grid-template-columns:1fr}.src-meta,.proj-cols .src-meta{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <button class="btn" id="backBtn">← Back to results</button>
        <button class="btn" id="refreshBtn" hidden>↻ Search projects again</button>
    </div>
    <div id="page"></div>
    <div class="toast" id="toast" role="status" hidden></div>
    <div class="cm-backdrop" id="cmModal" hidden role="dialog" aria-modal="true" aria-labelledby="cmTitle">
      <form class="cm" id="cmForm" novalidate>
        <div class="cm-head"><h3 id="cmTitle">Add contact details</h3><button type="button" class="cm-x" id="cmClose" aria-label="Close">✕</button></div>
        <p class="muted cm-sub" id="cmCompany"></p>
        <div class="cm-grid">
          <label>Contact person<input id="cmName" maxlength="150" placeholder="Name of the representative you talked with"></label>
          <label>Designation<input id="cmDesignation" maxlength="150" placeholder="e.g. Project Manager"></label>
          <label>Phone<input id="cmPhone" maxlength="60" placeholder="e.g. +91 98765 43210"></label>
          <label>Email<input id="cmEmail" type="email" maxlength="191" placeholder="name@company.com"></label>
          <label class="full">Notes<textarea id="cmNotes" rows="4" maxlength="5000" placeholder="What you discussed with the company representative"></textarea></label>
        </div>
        <div class="cm-q"><span>Did they ask for a follow-up?</span>
          <label class="cm-yn"><input type="radio" name="cmFollow" value="1"> Yes</label><label class="cm-yn"><input type="radio" name="cmFollow" value="0" checked> No</label>
          <label class="cm-date" id="cmDateWrap" hidden>Follow-up date <input type="date" id="cmFollowDate"></label></div>
        <div class="cm-q"><span>Add projects discussed?</span>
          <label class="cm-yn"><input type="radio" name="cmProj" value="1"> Yes</label><label class="cm-yn"><input type="radio" name="cmProj" value="0" checked> No</label></div>
        <div id="cmProjects" hidden>
          <div id="cmProjectRows"></div>
          <button type="button" class="btn btn-sm" id="cmAddProject">＋ Add another project</button>
        </div>
        <div class="cm-error" id="cmError" hidden></div>
        <div class="cm-actions"><button type="button" class="btn" id="cmCancel">Cancel</button><button type="submit" class="btn btn-primary" id="cmSave">Save</button></div>
      </form>
    </div>
</div>
<script>
const FINDER_URL = <?= json_encode(route('lead-generation-bot.finder')) ?>;
const PROJECTS_API = <?= json_encode(route('target-companies.projects')) ?>;
const SAVED_API = <?= json_encode(route('target-companies.saved')) ?>;
const PROFILE_API = <?= json_encode(route('target-companies.profile')) ?>;
const PER_PAGE = 5;

const $ = id => document.getElementById(id);
const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const safeUrl = u => /^https?:\/\//i.test(u || '') ? u : '';
const linkHtml = u => safeUrl(u) ? `<a href="${escapeHtml(u)}" target="_blank" rel="noopener noreferrer">${escapeHtml(u)}</a>` : 'Not given';
function verifyBadge(v) {
    v = v || {key: 'no-link', label: 'No link given'};
    const mark = v.key === 'official' || v.key === 'verified' ? '✓' : (v.key === 'unverified' ? '⚠' : '–');
    return `<span class="verify verify-${escapeHtml(v.key)}">${mark} ${escapeHtml(v.label)}</span>`;
}
function store(key, value) { try { sessionStorage.setItem(key, JSON.stringify(value)); } catch (e) {} }
function load(key) { try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch (e) { return null; } }

const params = new URLSearchParams(location.search);
const companyId = params.get('id') || '';
const results = load('leadFinderResults');
const opened = load('leadFinderCompany');
const company = (results?.companies || []).find(c => c.id === companyId) || (opened && opened.id === companyId ? opened : null);
const cacheKey = 'leadFinderProjects:' + companyId;
// opened from a Search Projects card (Get company details): only that researched project, no "all projects" search
const focusRow = params.get('focus') === '1' ? load('leadFinderFocus') : null;
const focusProject = focusRow && focusRow.id === companyId ? focusRow.project : null;
let projects = focusProject ? [focusProject] : (load(cacheKey) || company?.projects || []);
let page = Math.max(1, parseInt(params.get('page') || '1', 10));
let status = null;

$('backBtn').addEventListener('click', () => { location.href = FINDER_URL + (sessionStorage.getItem('leadFinderMode') === 'projects' ? '?mode=projects' : ''); });

function website(c) { return c.website || ((c.description || '').match(/Website:\s*(\S+)/) || [])[1] || ''; }

/* Website, logo and social media accounts (TargetCompanyFinderController::profile), shown under the logo */
let profile = null, profileState = '';
const SOCIAL_ICONS = {
    website: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg>',
    linkedin: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>',
    facebook: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07"/></svg>',
    instagram: '<svg viewBox="0 0 24 24"><rect x="2.5" y="2.5" width="19" height="19" rx="5.5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4.3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.6" cy="6.4" r="1.3" fill="currentColor"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.67l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64z"/></svg>',
    youtube: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>',
    other: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>'
};
const CONTACT_ICONS = {
    address: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 7 9-7"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.9 2z"/></svg>'
};
/* Address, email, website and phone, one per line under the company name */
function contactLines(site) {
    const line = (icon, cls, html) => `<div class="cline ${cls}">${icon}<span>${html}</span></div>`;
    const rows = [];
    if (profile?.address) rows.push(line(CONTACT_ICONS.address, 'c-address', `<b>Registered office:</b> ${escapeHtml(profile.address)}${safeUrl(profile.address_source) ? ` <a class="src" href="${escapeHtml(profile.address_source)}" target="_blank" rel="noopener noreferrer">source ↗</a>` : ''}`));
    if (profile?.email) rows.push(line(CONTACT_ICONS.email, 'c-email', `<a href="mailto:${escapeHtml(profile.email)}">${escapeHtml(profile.email)}</a>`));
    if (safeUrl(site)) rows.push(line(SOCIAL_ICONS.website, 'c-web', `${profile?.website ? '' : '<b>Seen at:</b> '}<a href="${escapeHtml(site)}" target="_blank" rel="noopener noreferrer">${escapeHtml(site)}</a>`));
    if (profile?.phone) rows.push(line(CONTACT_ICONS.phone, 'c-phone', `<a href="tel:${escapeHtml(profile.phone.replace(/[^\d+]/g, ''))}">${escapeHtml(profile.phone)}</a>`));
    return rows.length ? `<div class="contact">${rows.join('')}</div>` : '';
}
/* Top card (logo, name, tagline, account buttons), Company Address with a map, Latest Job and Company Details */
function profileHtml(site) {
    const c = company, pr = profile || {}, addr = (pr.address || '').replace(/\s+/g, ' ');
    const mapQuery = addr || c.location || c.name;
    const initials = escapeHtml(c.short || c.name.slice(0, 2));
    const tagline = pr.tagline || [c.industry].filter(Boolean).join(' | ');
    const heading = (icon, text) => `<h3 class="ph">${icon}<span>${escapeHtml(text)}</span></h3>`;
    const row = (icon, label, value) => `<div class="drow">${icon}<span class="dl">${escapeHtml(label)}</span><span class="dv">${value}</span></div>`;
    const job = pr.job;
    const loading = profileState === 'loading' ? '<div class="social muted"><span class="spin"></span>Finding the address, email, website and social media accounts…</div>' : '';

    return `
    <div class="card hero">
        <div class="hero-logo">${pr.logo ? `<img src="${escapeHtml(pr.logo)}" alt="${escapeHtml(c.name)} logo" referrerpolicy="no-referrer" onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'${initials.replace(/'/g, '')}'}))">` : `<span>${initials}</span>`}</div>
        <div class="hero-main">
            <div class="name-row"><h1>${escapeHtml(c.name)} <span class="intent ${escapeHtml((c.intent || '').toLowerCase())}">${escapeHtml(c.intent || 'Medium')} intent</span></h1>
                <div class="name-actions"><!-- <button type="button" class="btn ${inContacts ? 'btn-done' : 'btn-primary'}" id="addContactBtn">${inContacts ? '✓ In contacts' : '➕ Add to contacts'}</button><button type="button" class="btn" id="contactDetailsBtn">📝 Add contact details</button> --></div></div>
            ${tagline ? `<div class="tagline">${escapeHtml(tagline)}</div>` : ''}
            ${loading || socialRow()}
            ${c.description ? `<p class="desc">${escapeHtml(c.description)}</p>` : ''}
        </div>
    </div>
    <div class="info-grid">
        <div class="card addr">
            ${heading(CONTACT_ICONS.address, 'Company Address')}
            <div class="addr-top">
                <div class="addr-text">${CONTACT_ICONS.address}<div><b>${addr ? 'Registered Office:' : 'Location:'}</b><br>${escapeHtml(addr || c.location || 'Not known')}${safeUrl(pr.address_source) ? ` <a class="src" href="${escapeHtml(pr.address_source)}" target="_blank" rel="noopener noreferrer">source ↗</a>` : ''}</div></div>
                <a class="maps-btn" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapQuery)}" target="_blank" rel="noopener noreferrer">${MAPS_ICON}<span>View on<br>Google Maps</span></a>
            </div>
            <div class="map-box">
                <iframe title="Map of ${escapeHtml(c.name)}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=${encodeURIComponent(mapQuery)}&z=15&output=embed"></iframe>
                <div class="map-foot">${CONTACT_ICONS.address}<span>${escapeHtml(addr || c.location || '')}</span><a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(mapQuery)}" target="_blank" rel="noopener noreferrer">➦ Get Directions</a></div>
            </div>
        </div>
        <div class="side-col">
            <div class="card">
                ${heading(BRIEFCASE_ICON, job ? `Latest Job on ${job.site}` : 'Latest Job')}
                ${job ? `<div class="job">
                    <div class="job-logo ${/linkedin/i.test(job.site + job.url) ? 'li' : ''}">${/linkedin/i.test(job.site + job.url) ? SOCIAL_ICONS.linkedin : BRIEFCASE_ICON}</div>
                    <div><div class="job-title">${escapeHtml(job.title)}</div><div class="job-co">${escapeHtml(c.name)}</div>
                    ${job.location ? `<div class="job-meta">${CONTACT_ICONS.address}${escapeHtml(job.location)}</div>` : ''}
                    ${job.type ? `<div class="job-meta">${BRIEFCASE_ICON}${escapeHtml(job.type)}</div>` : ''}
                    <div class="job-meta">${CLOCK_ICON}Posted on ${escapeHtml(job.site)}</div></div>
                </div>
                <a class="job-btn ${/linkedin/i.test(job.site + job.url) ? 'li' : ''}" href="${escapeHtml(job.url)}" target="_blank" rel="noopener noreferrer">View Job on ${escapeHtml(job.site)} ↗</a>`
                : `<p class="muted pad">${profileState === 'loading' ? 'Looking for job posts…' : 'No recent job post found.'}</p>`}
            </div>
            <div class="card">
                ${heading(BUILDING_ICON, 'Company Details')}
                <div class="details">
                    ${row(GEAR_ICON, 'Industry', escapeHtml(c.industry || 'Not known'))}
                    ${row(BUILDING_ICON, 'Company Type', escapeHtml(pr.company_type || 'Not known'))}
                    ${pr.cin ? row(BUILDING_ICON, 'CIN', escapeHtml(pr.cin)) : ''}
                    ${pr.incorporated ? row(CLOCK_ICON, 'Incorporated', escapeHtml(pr.incorporated) + (pr.status ? ` · ${escapeHtml(pr.status)}` : '')) : ''}
                    ${(pr.directors || []).length ? row(GEAR_ICON, 'Directors', pr.directors.map(escapeHtml).join(', ')) : ''}
                    ${row(CONTACT_ICONS.address, 'Location', escapeHtml(c.location || 'Not known'))}
                    ${row(SOCIAL_ICONS.website, 'Website', pr.website ? `<a href="${escapeHtml(pr.website)}" target="_blank" rel="noopener noreferrer">${escapeHtml(pr.website.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''))}</a>` : 'Not available')}
                    ${row(CONTACT_ICONS.email, 'Email', pr.email ? `<a href="mailto:${escapeHtml(pr.email)}">${escapeHtml(pr.email)}</a>` : 'Not public')}
                    ${row(CONTACT_ICONS.phone, 'Phone', (pr.phones || (pr.phone ? [pr.phone] : [])).length ? (pr.phones || [pr.phone]).map(n => `<a href="tel:${escapeHtml(n.replace(/[^\d+]/g, ''))}">${escapeHtml(n)}</a>`).join(' / ') : 'Not public')}
                    ${!pr.website && safeUrl(site) ? row(SOCIAL_ICONS.other, 'Seen at', `<a href="${escapeHtml(site)}" target="_blank" rel="noopener noreferrer">${escapeHtml(site.replace(/^https?:\/\//, ''))}</a>`) : ''}
                </div>
            </div>
        </div>
    </div>`;
}
const MAPS_ICON = '<svg viewBox="0 0 24 24"><path fill="#ea4335" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7z"/><circle cx="12" cy="9" r="2.6" fill="#fff"/></svg>';
const BRIEFCASE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="7" width="19" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M2.5 13h19"/></svg>';
const BUILDING_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="11" height="18" rx="1"/><path d="M15 9h5v12h-5M8 7h3M8 11h3M8 15h3"/></svg>';
const GEAR_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>';
const CLOCK_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';

function socialRow() {
    if (profileState === 'loading') return '<div class="social muted"><span class="spin"></span>Finding the address, email, website and social media accounts…</div>';
    if (!profile) return profileState === 'error' ? '<div class="social muted">Website and social media accounts could not be loaded right now.</div>' : '';
    // the five main accounts always: found = the company's page; not found = a search for the company on that site
    const q = encodeURIComponent(company.name), links = profile.links || [];
    const MAIN = [
        ['website', 'Website', `https://www.google.com/search?q=${q}+official+website`],
        ['linkedin', 'LinkedIn', `https://www.linkedin.com/search/results/companies/?keywords=${q}`],
        ['facebook', 'Facebook', `https://www.facebook.com/search/pages/?q=${q}`],
        ['instagram', 'Instagram', `https://www.google.com/search?q=${q}+site%3Ainstagram.com`],
        ['youtube', 'YouTube', `https://www.youtube.com/results?search_query=${q}`],
    ];
    const button = (key, name, url, found, verified) => `<a class="soc soc-${escapeHtml(key)} ${found ? '' : 'soc-missing'}" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" title="${found ? escapeHtml(url) + (verified ? '' : ' (could not be checked)') : `No ${escapeHtml(name)} account found for this company. Click to search ${escapeHtml(name === 'Website' ? 'Google' : name)} for it.`}">${SOCIAL_ICONS[key] || SOCIAL_ICONS.other}<span>${escapeHtml(name)}</span>${found ? '' : '<em>search</em>'}</a>`;
    const main = MAIN.map(([key, name, search]) => { const l = links.find(x => x.key === key); return l ? button(key, l.name, l.url, true, l.verified) : button(key, name, search, false); });
    const extra = links.filter(l => !MAIN.some(([key]) => key === l.key)).map(l => button(l.key, l.name, l.url, true, l.verified));
    const missing = MAIN.filter(([key]) => !links.some(x => x.key === key)).length;
    return '<div class="social">' + main.concat(extra).join('') + '</div>'
        + (missing ? `<div class="soc-note">Grey buttons: no official account was found for this company; they search that site for it.</div>` : '');
}
async function loadProfile(refresh) {
    profileState = 'loading'; render();
    try {
        const q = new URLSearchParams({name: company.name, location: company.location || '', website: website(company), ...(refresh ? {refresh: 1} : {})});
        const r = await fetch(PROFILE_API + '?' + q, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.message || 'error');
        profile = d.profile; profileState = '';
    } catch (e) { profileState = 'error'; }
    render();
}

function projectCard(p, n) {
    const s = p.source || {}, note = p.notification;
    const meta = [p.type ? (p.icon || '✦') + ' ' + p.type : '', p.date, p.location, p.value].filter(Boolean).map(escapeHtml).join(' · ');
    return `<div class="src-card">
        <div class="src-head"><strong><span class="num">${n}.</span>${escapeHtml(p.name)}</strong>${p.status ? `<span class="proj-status">${escapeHtml(p.status)}</span>` : ''}</div>
        ${meta ? `<div class="proj-meta">${meta}</div>` : ''}${p.description ? `<p class="proj-desc">${escapeHtml(p.description)}</p>` : ''}
        <div class="proj-actions">${projectButtons(p, n - 1)}</div>
        <div class="proj-cols">
            <div><div class="proj-label">Source</div><dl class="src-meta">
                <dt>Page</dt><dd>${escapeHtml(s.title || s.publisher || 'Not stated')}${s.publisher && s.title ? ' · ' + escapeHtml(s.publisher) : ''}</dd>
                <dt>Source link</dt><dd>${linkHtml(s.url)}</dd>
                <dt>Verification status</dt><dd>${verifyBadge(s.verification)}</dd></dl></div>
            <div><div class="proj-label">Official notification</div>${note ? `<dl class="src-meta">
                <dt>Notification title</dt><dd>${escapeHtml(note.title)}</dd>
                <dt>Publishing organization</dt><dd>${escapeHtml(note.organization || 'Not stated')}</dd>
                <dt>Publication date</dt><dd>${escapeHtml(note.date || 'Not stated')}</dd>
                <dt>Original source link</dt><dd>${linkHtml(note.url)}</dd>
                <dt>Verification status</dt><dd>${verifyBadge(note.verification)}</dd></dl>` : '<p class="muted" style="margin:0">No official notification found for this project.</p>'}</div>
        </div>${publishedLine(p)}</div>`;
}
/* bottom-right date of a project: when its source was published (else its official notification, else its own date) */
function publishedLine(p) {
    const d = p.source?.published || p.notification?.date || '';
    if (d) return `<div class="proj-published">📅 Published: <b>${escapeHtml(d)}</b></div>`;
    return p.date ? `<div class="proj-published">📅 Dated: <b>${escapeHtml(p.date)}</b></div>` : '<div class="proj-published muted">📅 Published date not stated</div>';
}

/* Save project / Target project: the user's Saved Projects and Target Projects in the finder (lead_finder_saved) */
let saved = {target: [], saved_project: [], target_project: []};
const nameKey = x => String(x || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim().slice(0, 90);
const projectKey = p => nameKey(company.name) + '|' + nameKey(p.name);
const isMarked = (kind, p) => saved[kind].some(t => t.key === projectKey(p));
function projectButtons(p, i) {
    const sp = isMarked('saved_project', p), tp = isMarked('target_project', p);
    return `<button type="button" class="btn btn-sm ${sp ? 'on' : ''}" data-pact="saved_project" data-i="${i}">${sp ? '★ Saved project' : '☆ Save project'}</button>`
        + `<button type="button" class="btn btn-sm ${tp ? 'on' : ''}" data-pact="target_project" data-i="${i}">${tp ? '◉ Target project' : '◎ Target project'}</button>`;
}
async function savedCall(method, body) {
    const r = await fetch(SAVED_API, {method, credentials: 'same-origin', body: method === 'GET' ? undefined : JSON.stringify(body),
        headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.message || 'Could not save. Please try again.');
    saved = d.saved;
}
async function toggleProject(kind, p) {
    const on = isMarked(kind, p), label = kind === 'saved_project' ? 'Saved Projects' : 'Target Projects';
    const c = company, info = {name: c.name, short: c.short || '', location: c.location || '', industry: c.industry || '', size: c.size || '', intent: c.intent || '', website: website(c), description: c.description || ''};
    try {
        await savedCall(on ? 'DELETE' : 'POST', {kind, key: projectKey(p), data: on ? undefined : {...p, company: info}});
        flash(on ? `Removed from ${label}` : `Added to ${label}`);
    } catch (e) { flash(e.message); }
    render();
}
/* Contacts: "Add to contacts" (the company) and "Add contact details" (person, notes, follow-up, projects) */
const CONTACTS_API = <?= json_encode(route('target-companies.contacts')) ?>;
let inContacts = false;
const companyKeyOf = c => String(c.name || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim().slice(0, 180);
function companyInfo() {
    const c = company;
    return {id: c.id, name: c.name, short: c.short || '', industry: c.industry || '', size: c.size || '', location: c.location || '', intent: c.intent || '',
        website: profile?.website || website(c), description: c.description || ''};
}
async function contactCall(body) {
    const r = await fetch(CONTACTS_API, {method: body ? 'POST' : 'GET', credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined,
        headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.errors ? Object.values(d.errors).flat()[0] : (d.message || 'Could not save. Please try again.'));
    inContacts = (d.contacts || []).some(x => x.key === companyKeyOf(company));
    return d;
}
function refreshContactButton() {
    const b = $('addContactBtn'); if (!b) return;
    b.textContent = inContacts ? '✓ In contacts' : '➕ Add to contacts';
    b.className = 'btn ' + (inContacts ? 'btn-done' : 'btn-primary');
}
async function addToContacts() {
    const b = $('addContactBtn'); b.disabled = true;
    try { const d = await contactCall({company: companyInfo()}); flash(d.already ? 'This company is already in Contacts.' : 'Added to contacts successfully.', !d.already); }
    catch (e) { flash(e.message, false, true); }
    b.disabled = false; refreshContactButton();
}
function projectRow(p = {}) {
    const row = document.createElement('div'); row.className = 'cm-proj';
    row.innerHTML = `<input class="cm-pname" maxlength="191" placeholder="Project name" value="${escapeHtml(p.name || '')}"><textarea class="cm-pdetails" rows="2" maxlength="2000" placeholder="Projects they are working on / details">${escapeHtml(p.details || '')}</textarea><button type="button" class="cm-x" title="Remove">✕</button>`;
    row.querySelector('.cm-x').addEventListener('click', () => { row.remove(); if (!$('cmProjectRows').children.length) $('cmProjectRows').appendChild(projectRow()); });
    return row;
}
function openContactForm() {
    $('cmForm').reset(); $('cmError').hidden = true; $('cmDateWrap').hidden = true; $('cmProjects').hidden = true;
    $('cmProjectRows').innerHTML = ''; $('cmProjectRows').appendChild(projectRow());
    $('cmCompany').textContent = company.name + (company.location ? ' · ' + company.location : '');
    $('cmModal').hidden = false; setTimeout(() => $('cmName').focus(), 30);
}
const closeContactForm = () => { $('cmModal').hidden = true; };
document.querySelectorAll('input[name="cmFollow"]').forEach(r => r.addEventListener('change', () => { $('cmDateWrap').hidden = r.value !== '1' || !r.checked; }));
document.querySelectorAll('input[name="cmProj"]').forEach(r => r.addEventListener('change', () => { $('cmProjects').hidden = !(r.value === '1' && r.checked); }));
$('cmAddProject').addEventListener('click', () => $('cmProjectRows').appendChild(projectRow()));
$('cmClose').addEventListener('click', closeContactForm); $('cmCancel').addEventListener('click', closeContactForm);
$('cmModal').addEventListener('click', e => { if (e.target === $('cmModal')) closeContactForm(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !$('cmModal').hidden) closeContactForm(); });
$('cmForm').addEventListener('submit', async e => {
    e.preventDefault();
    const follow = document.querySelector('input[name="cmFollow"]:checked').value === '1';
    const withProjects = document.querySelector('input[name="cmProj"]:checked').value === '1';
    const projects = withProjects ? [...document.querySelectorAll('.cm-proj')].map(r => ({name: r.querySelector('.cm-pname').value.trim(), details: r.querySelector('.cm-pdetails').value.trim()})).filter(p => p.name || p.details) : [];
    const body = {company: companyInfo(), contact_name: $('cmName').value.trim(), designation: $('cmDesignation').value.trim(), phone: $('cmPhone').value.trim(),
        email: $('cmEmail').value.trim(), notes: $('cmNotes').value.trim(), follow_up: follow, follow_up_date: follow ? $('cmFollowDate').value : null, projects};
    const err = msg => { $('cmError').textContent = msg; $('cmError').hidden = false; };
    if (!body.contact_name && !body.phone && !body.email && !body.notes && !follow && !projects.length) return err('Enter at least the contact person, phone, email or notes.');
    if (follow && !body.follow_up_date) return err('Choose the follow-up date.');
    if (withProjects && !projects.length) return err('Enter the project name, or choose No for projects.');
    if (projects.some(p => !p.name)) return err('Enter the name of every project.');
    $('cmSave').disabled = true;
    try { await contactCall(body); closeContactForm(); flash('Contact saved successfully.', true); refreshContactButton(); }
    catch (e2) { err(e2.message); }
    $('cmSave').disabled = false;
});
document.addEventListener('click', e => {
    if (e.target.closest('#addContactBtn')) addToContacts();
    if (e.target.closest('#contactDetailsBtn')) openContactForm();
});

function flash(msg, ok, bad) { const t = $('toast'); t.className = 'toast' + (ok ? ' toast-ok' : '') + (bad ? ' toast-bad' : ''); t.textContent = (ok ? '✓ ' : '') + msg; t.hidden = false; clearTimeout(flash.t); flash.t = setTimeout(() => { t.hidden = true; }, 3200); }
document.addEventListener('click', e => {
    const b = e.target.closest('[data-pact]');
    if (b && projects[+b.dataset.i]) toggleProject(b.dataset.pact, projects[+b.dataset.i]);
});

function pager(pages) {
    if (pages <= 1) return '';
    let html = `<button data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>‹ Prev</button>`;
    for (let i = 1; i <= pages; i++) html += `<button data-page="${i}" class="${i === page ? 'active' : ''}">${i}</button>`;
    return html + `<button data-page="${page + 1}" ${page === pages ? 'disabled' : ''}>Next ›</button>`;
}

function render() {
    if (!company) {
        $('page').innerHTML = '<div class="card empty">This company is no longer in your search results. Go back and search again, then click <b>View details</b>.</div>';
        return;
    }
    const pages = Math.max(1, Math.ceil(projects.length / PER_PAGE));
    page = Math.min(page, pages);
    const from = (page - 1) * PER_PAGE;
    const shown = projects.slice(from, from + PER_PAGE);
    const site = profile?.website || website(company);
    const withNotice = projects.filter(p => p.notification).length;

    if (!$('profileArea')) $('page').innerHTML = '<div id="profileArea"></div><div id="projectsArea"></div>';
    const pv = JSON.stringify([profileState, profile]);
    if ($('profileArea').dataset.v !== pv) { $('profileArea').innerHTML = profileHtml(site); $('profileArea').dataset.v = pv; }

    $('projectsArea').innerHTML = `
        <div class="card">
            <div class="bar"><h2>${focusProject ? 'Researched project' : 'Projects'}</h2><span class="count">${focusProject ? `The project you found in Search Projects · <a href="?id=${encodeURIComponent(companyId)}">see all projects of this company</a>` : projects.length ? `Showing ${from + 1}–${from + shown.length} of ${projects.length} projects · ${withNotice} with an official notification · Page ${page} of ${pages}` : ''}</span></div>
            ${status ? `<div class="status ${status.cls || ''}">${status.html}</div>` : ''}
            ${shown.map((p, i) => projectCard(p, from + i + 1)).join('') || '<div class="empty">No projects found for this company.</div>'}
            <div class="pager">${pager(pages)}</div>
        </div>`;

    document.querySelectorAll('[data-page]').forEach(b => b.addEventListener('click', () => {
        page = +b.dataset.page;
        params.set('page', page);
        history.replaceState(null, '', '?' + params.toString());
        render();
        window.scrollTo({top: 0, behavior: 'smooth'});
    }));
}

/** The full project list from ChatGPT, merged with the projects from the search (same name = same project). */
async function fetchAll() {
    $('refreshBtn').hidden = true;
    status = {html: `<span class="spin"></span>ChatGPT is searching all projects of ${escapeHtml(company.name)}… (up to a minute)`};
    render();
    try {
        const q = new URLSearchParams({name: company.name, location: company.location || '', website: website(company)});
        const r = await fetch(PROJECTS_API + '?' + q, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.message || 'Search failed (' + r.status + ')');
        const seen = new Set(), merged = [];
        [...(data.projects || []), ...(company.projects || [])].forEach(p => {
            const key = (p.name || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
            if (key && !seen.has(key)) { seen.add(key); merged.push(p); }
        });
        projects = merged;
        store(cacheKey, projects);
        status = {cls: 'ok', html: `ChatGPT found ${projects.length} project${projects.length === 1 ? '' : 's'}. Links were checked by the portal.`};
    } catch (e) {
        status = {cls: 'err', html: escapeHtml(e.message) + ' Showing the projects from your search.'};
    }
    $('refreshBtn').hidden = false;
    render();
}

$('refreshBtn').addEventListener('click', () => { sessionStorage.removeItem(cacheKey); page = 1; fetchAll(); if (profile && !profile.links.length) loadProfile(true); });

render();
if (company) savedCall('GET').then(render).catch(() => {});
if (company) contactCall(null).then(() => { refreshContactButton(); const pv = $('profileArea'); if (pv) pv.dataset.v = ''; render(); }).catch(() => {});
if (company && company.live) loadProfile(false);
if (company) {
    if (focusProject) { /* only the researched project */ }
    else if (load(cacheKey)) { $('refreshBtn').hidden = false; }
    else if (company.live) { fetchAll(); }
}
</script>
</body>
</html>
