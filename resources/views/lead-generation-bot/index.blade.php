@extends('layouts.app')

@section('content')

<div class="content-wrapper" id="leadBot">
    <header class="lb-top">
        <div>
            <div class="lb-eyebrow">CRM Automation · AI Prospecting</div>
            <h1>Target Account &amp; Lead Generation</h1>
            <p class="lb-muted mb-0">Target companies, research projects and buying signals, identify contacts, execute outreach.</p>
        </div>
        <span class="lb-pill">Owner: Lead Generation / SDR</span>
    </header>

    <div class="lb-layout">
        <aside class="lb-card">
            <div class="lb-pad">
                <h2>Search settings</h2>
                <p class="lb-muted">Choose the type of project you want to find.</p>

                <div class="lb-field">
                    <label for="lbCategory">Project category</label>
                    <select id="lbCategory">
                        @foreach ($categories as $category)
                            <option>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lb-field">
                    <label for="lbLocation">Target location</label>
                    <div class="lb-suggest-wrap">
                        <input id="lbLocation" value="India" maxlength="80" autocomplete="off" placeholder="City, state or country"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="lbLocationList">
                        <ul class="lb-suggest" id="lbLocationList" role="listbox"></ul>
                    </div>
                </div>
                <div class="lb-field">
                    <label for="lbCompany">Company name <span class="lb-optional">(optional, worldwide)</span></label>
                    <div class="lb-suggest-wrap">
                        <input id="lbCompany" maxlength="120" autocomplete="off" placeholder="e.g. Larsen &amp; Toubro, Siemens…"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="lbCompanyList">
                        <ul class="lb-suggest" id="lbCompanyList" role="listbox"></ul>
                    </div>
                    <p class="lb-muted lb-hint">AI suggests companies as you type. With a company, the bot researches that company's projects.</p>
                </div>
                <div class="lb-field">
                    <label for="lbCompanyType">Company type</label>
                    <select id="lbCompanyType">
                        <option>Any company</option>
                        @foreach ($companyTypeGroups as $group => $companyTypes)
                            <optgroup label="{{ $group }}">
                                @foreach ($companyTypes as $companyType)
                                    <option>{{ $companyType }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <button type="button" class="lb-primary lb-full" id="lbSearchBtn">Search companies</button>
                <div class="lb-notice"><b>Live AI search:</b> Claude searches the web for real tenders, RFPs and buying signals. A search can take 1–3 minutes. Always verify a lead at its source before outreach.</div>
            </div>
        </aside>

        <main class="lb-card">
            <div class="lb-toolbar">
                <div>
                    <h2>Companies</h2>
                    <p class="lb-muted mb-0" id="lbSummary">No companies found yet. Start a search.</p>
                </div>
                <input class="lb-filter" id="lbFilter" placeholder="Search companies…" aria-label="Search companies">
            </div>
            <div class="lb-runstate" id="lbRunstate"><span id="lbMessage">Searching…</span><div class="lb-progress"><span id="lbProgress"></span></div></div>
            <div id="lbGrid" class="lb-grid"></div>
            <div class="lb-empty" id="lbEmpty"><strong>No companies yet</strong>Choose a project category and click Search companies.</div>
        </main>
    </div>
    <div class="lb-toast" id="lbToast" role="status"></div>
</div>

@endsection

@push('scripts')
<style>
    #leadBot { --lb-blue: #2563eb; --lb-navy: #172b4d; --lb-border: #dbe3ef; --lb-muted: #64748b; color: #172033; }
    #leadBot .lb-top { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
    #leadBot .lb-eyebrow { color: var(--lb-blue); font-size: 11px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
    #leadBot h1 { font-size: 26px; margin: 7px 0; }
    #leadBot h2 { font-size: 17px; margin: 0 0 7px; }
    #leadBot .lb-muted { color: var(--lb-muted); font-size: 12px; line-height: 1.5; }
    #leadBot .lb-pill { background: #e0e7ff; color: #3730a3; border-radius: 20px; padding: 8px 12px; font-size: 12px; font-weight: bold; }
    #leadBot .lb-layout { display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 18px; align-items: start; }
    #leadBot .lb-card { background: #fff; border: 1px solid var(--lb-border); border-radius: 14px; box-shadow: 0 4px 18px #172b4d0a; overflow: hidden; }
    #leadBot .lb-pad { padding: 20px; }
    #leadBot .lb-field { margin: 17px 0; }
    #leadBot label { display: block; font-weight: bold; font-size: 12px; margin-bottom: 7px; }
    #leadBot select, #leadBot input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font: inherit; color: #172033; }
    #leadBot button { cursor: pointer; border: 0; border-radius: 8px; padding: 10px 13px; font-weight: bold; font-size: 12px; }
    #leadBot button:disabled { opacity: .7; cursor: wait; }
    #leadBot .lb-primary { background: var(--lb-blue); color: #fff; }
    #leadBot .lb-secondary { background: #fff; border: 1px solid var(--lb-border); color: var(--lb-navy); }
    #leadBot .lb-danger { background: #fff1f2; color: #be123c; }
    #leadBot .lb-full { width: 100%; }
    #leadBot .lb-notice { background: #f8fafc; border: 1px solid var(--lb-border); padding: 12px; border-radius: 9px; font-size: 11px; color: var(--lb-muted); line-height: 1.5; margin-top: 14px; }
    #leadBot .lb-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 18px; border-bottom: 1px solid var(--lb-border); }
    #leadBot .lb-toolbar h2 { margin: 0; }
    #leadBot .lb-filter { max-width: 230px; }
    #leadBot .lb-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 18px; }
    #leadBot .lb-grid:empty { display: none; }
    #leadBot .lb-company-card { border: 1px solid var(--lb-border); border-radius: 11px; padding: 15px; background: #fff; min-width: 0; transition: .15s; }
    #leadBot .lb-company-card:hover { border-color: #93c5fd; box-shadow: 0 4px 14px #2563eb0c; }
    #leadBot .lb-company { font-weight: bold; color: var(--lb-navy); font-size: 13px; line-height: 1.4; }
    #leadBot .lb-domain { font-size: 11px; color: var(--lb-muted); margin-top: 4px; overflow-wrap: anywhere; }
    #leadBot .lb-title { font-size: 12px; font-weight: bold; margin-top: 12px; line-height: 1.4; }
    #leadBot .lb-snippet { font-size: 11px; color: var(--lb-muted); margin-top: 5px; line-height: 1.5; }
    #leadBot .lb-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 12px; }
    #leadBot .lb-score { font-weight: bold; font-size: 11px; color: #1d4ed8; }
    #leadBot .lb-status { display: inline-block; padding: 5px 8px; border-radius: 20px; font-size: 10px; font-weight: bold; }
    #leadBot .lb-status.Pending { background: #fff7ed; color: #9a3412; }
    #leadBot .lb-status.Approved { background: #dcfce7; color: #166534; }
    #leadBot .lb-status.Rejected { background: #ffe4e6; color: #9f1239; }
    #leadBot .lb-link { display: inline-block; color: var(--lb-blue); font-size: 11px; font-weight: bold; margin-top: 10px; }
    #leadBot .lb-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
    #leadBot .lb-actions button { padding: 7px 9px; }
    #leadBot .lb-empty { text-align: center; padding: 48px 18px; color: var(--lb-muted); }
    #leadBot .lb-empty strong { display: block; color: var(--lb-navy); margin-bottom: 7px; }
    #leadBot .lb-runstate { display: none; background: #eff6ff; padding: 12px 18px; color: #1d4ed8; font-size: 12px; }
    #leadBot .lb-runstate.show { display: block; }
    #leadBot .lb-progress { height: 5px; background: #dbeafe; margin-top: 8px; border-radius: 6px; overflow: hidden; }
    #leadBot .lb-progress span { height: 100%; display: block; background: var(--lb-blue); width: 0; transition: width .2s; }
    #leadBot .lb-toast { display: none; position: fixed; right: 20px; bottom: 20px; background: var(--lb-navy); color: #fff; padding: 14px 17px; border-radius: 9px; max-width: 330px; z-index: 1050; }
    #leadBot .lb-toast.show { display: block; }
    #leadBot .lb-suggest-wrap { position: relative; }
    #leadBot .lb-suggest { display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20; max-height: 260px; overflow-y: auto; margin: 0; padding: 4px; list-style: none; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 8px 24px #172b4d1f; }
    #leadBot .lb-suggest.show { display: block; }
    #leadBot .lb-suggest li { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; font-size: 12px; cursor: pointer; }
    #leadBot .lb-suggest li.active, #leadBot .lb-suggest li:hover { background: #eff6ff; color: #1d4ed8; }
    #leadBot .lb-suggest li small { color: var(--lb-muted); font-size: 10px; text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
    #leadBot .lb-suggest li.lb-none { color: var(--lb-muted); cursor: default; background: none; }
    #leadBot .lb-suggest li span em { display: block; font-style: normal; color: var(--lb-muted); font-size: 11px; margin-top: 2px; }
    #leadBot .lb-optional { font-weight: normal; color: var(--lb-muted); }
    #leadBot .lb-hint { margin: 6px 0 0; font-size: 11px; }
    @media (max-width: 900px) {
        #leadBot .lb-layout, #leadBot .lb-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
        #leadBot h1 { font-size: 22px; }
        #leadBot .lb-filter { max-width: none; }
    }
</style>
<script>
(function () {
    const SEARCH_URL = "{{ route('lead-generation-bot.search') }}";
    const TERMS = {
        "GIS Projects": ["gis", "geospatial", "mapping", "spatial data", "remote sensing", "survey"],
        "AutoCAD Projects": ["autocad", "cad", "drafting", "engineering drawing"],
        "Web Development Projects": ["website", "web development", "web application", "frontend", "backend"],
        "Mobile App Development": ["mobile app", "android", "ios", "api integration"],
        "Cloud & AWS Projects": ["aws", "cloud migration", "devops"],
        "Data Science & AI Projects": ["ai", "machine learning", "data science", "analytics"]
    };

    let prospects = [], toastTimer;
    const $id = id => document.getElementById(id);
    const esc = v => String(v ?? "").replace(/[&<>"']/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"}[c]));

    function toast(s) {
        $id("lbToast").textContent = s;
        $id("lbToast").classList.add("show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => $id("lbToast").classList.remove("show"), 3500);
    }

    // Rough fit score from the category keywords, buying-signal words and the location.
    function score(p, cat, loc) {
        let t = (p.title + " " + p.snippet + " " + p.url).toLowerCase(), n = 0;
        (TERMS[cat] || []).forEach(x => { if (t.includes(x)) n += 10; });
        if (/tender|rfp|project|contract|opportunity/i.test(t + " " + p.signal)) n += 15;
        if (loc && t.includes(loc.toLowerCase())) n += 10;
        if (/company|consult|infrastructure|services|solutions|studio|group|authority|department|corporation/i.test(p.company + " " + t)) n += 10;
        if (/development|implementation|digitization|modernization|services|mapping/i.test(t)) n += 10;
        return Math.min(100, n);
    }

    function render() {
        let f = $id("lbFilter").value.trim().toLowerCase();
        let list = prospects.filter(p => (p.company + " " + p.domain + " " + p.title + " " + p.snippet).toLowerCase().includes(f));

        $id("lbGrid").innerHTML = list.map(p => `
            <article class="lb-company-card">
                <div class="lb-company">${esc(p.company)}</div>
                <div class="lb-domain">${esc(p.domain)}</div>
                <div class="lb-title">${esc(p.title)}</div>
                <div class="lb-snippet">${esc(p.snippet)}</div>
                ${p.signal ? `<div class="lb-domain"><b>Signal:</b> ${esc(p.signal)}</div>` : ""}
                ${p.contact ? `<div class="lb-domain"><b>Contact:</b> ${esc(p.contact)}</div>` : ""}
                <div class="lb-foot"><span class="lb-score">Match ${p.score}/100</span><span class="lb-status ${p.status}">${esc(p.status)}</span></div>
                <a class="lb-link" href="${esc(p.url)}" target="_blank" rel="noopener noreferrer">View source ↗</a>
                <div class="lb-actions">${p.status === "Pending"
                    ? `<button type="button" class="lb-primary" data-act="approve" data-id="${p.id}">Approve</button><button type="button" class="lb-danger" data-act="reject" data-id="${p.id}">Reject</button>`
                    : `<button type="button" class="lb-secondary" data-act="reset" data-id="${p.id}">Reset</button>`}</div>
            </article>`).join("");

        $id("lbEmpty").style.display = list.length ? "none" : "block";
        $id("lbEmpty").innerHTML = prospects.length
            ? "<strong>No matching companies</strong>Try another search term."
            : "<strong>No companies yet</strong>Choose a project category and click Search companies.";
        $id("lbSummary").textContent = `Showing ${list.length} of ${prospects.length} companies`;
    }

    async function doSearch() {
        let cat = $id("lbCategory").value, loc = $id("lbLocation").value.trim(), company = $id("lbCompany").value.trim();
        if (!loc && !company) return toast("Enter a target location or a company name.");

        let b = $id("lbSearchBtn");
        b.disabled = true;
        b.textContent = "Searching…";
        $id("lbRunstate").classList.add("show");
        $id("lbMessage").textContent = company
            ? `Claude is researching ${company}'s ${cat} worldwide…`
            : `Claude is searching the web for ${cat} in ${loc}…`;
        $id("lbProgress").style.width = "10%";
        let n = 10, tm = setInterval(() => { $id("lbProgress").style.width = (n = Math.min(90, n + 3)) + "%"; }, 3000);

        try {
            let r = await fetch(SEARCH_URL, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({category: cat, location: loc, company: company, companyType: $id("lbCompanyType").value, exclude: prospects.map(p => p.company)})
            });
            let d = await r.json().catch(() => ({error: "The server returned an invalid response."}));
            if (!r.ok || d.error) {
                throw new Error(d.error || (d.errors && Object.values(d.errors)[0][0]) || d.message || "Search failed.");
            }

            let added = 0, seen = new Set(prospects.map(p => p.url));
            d.companies.forEach((s, i) => {
                if (seen.has(s.url)) return;
                seen.add(s.url);
                prospects.push({...s, id: Date.now() + "-" + i, score: score(s, cat, loc), status: "Pending", category: cat, location: loc});
                added++;
            });
            $id("lbProgress").style.width = "100%";
            $id("lbMessage").textContent = `Found ${added} new compan${added === 1 ? "y" : "ies"}.`;
            toast(added ? `${added} new lead(s) added.` : "No new companies found. Try another location or company type.");
        } catch (err) {
            $id("lbMessage").textContent = err.message;
            toast(err.message);
        } finally {
            clearInterval(tm);
            b.disabled = false;
            b.textContent = "Search companies";
            render();
            setTimeout(() => $id("lbRunstate").classList.remove("show"), 4000);
        }
    }

    // ----------------------------------------------------
    // TARGET LOCATION SUGGESTIONS
    // ----------------------------------------------------
    const LOCATIONS = @json($locations);
    const locInput = $id("lbLocation"), locList = $id("lbLocationList");
    let locMatches = [], locActive = -1;

    function showLocations() {
        let q = locInput.value.trim().toLowerCase();
        // Names that start with the text first, then names that contain it
        locMatches = !q ? LOCATIONS.slice(0, 12) : [
            ...LOCATIONS.filter(l => l.name.toLowerCase().startsWith(q)),
            ...LOCATIONS.filter(l => !l.name.toLowerCase().startsWith(q) && l.name.toLowerCase().includes(q))
        ].slice(0, 12);
        locActive = -1;
        locList.innerHTML = locMatches.length
            ? locMatches.map((l, i) => `<li role="option" data-i="${i}"><span>${esc(l.name)}</span><small>${esc(l.type)}</small></li>`).join("")
            : `<li class="lb-none">No suggestions. You can still search for "${esc(locInput.value.trim())}".</li>`;
        locList.classList.add("show");
        locInput.setAttribute("aria-expanded", "true");
    }

    function hideLocations() {
        locList.classList.remove("show");
        locInput.setAttribute("aria-expanded", "false");
    }

    function pickLocation(i) {
        if (!locMatches[i]) return;
        locInput.value = locMatches[i].name;
        hideLocations();
    }

    function highlightLocation(i) {
        let items = locList.querySelectorAll("li[data-i]");
        if (!items.length) return;
        locActive = (i + items.length) % items.length;
        items.forEach(li => li.classList.toggle("active", +li.dataset.i === locActive));
        items[locActive].scrollIntoView({block: "nearest"});
    }

    locInput.addEventListener("input", showLocations);
    locInput.addEventListener("focus", () => { locInput.select(); showLocations(); });
    locInput.addEventListener("blur", () => setTimeout(hideLocations, 150));
    locInput.addEventListener("keydown", e => {
        if (e.key === "ArrowDown" || e.key === "ArrowUp") {
            e.preventDefault();
            if (!locList.classList.contains("show")) showLocations();
            highlightLocation(locActive + (e.key === "ArrowDown" ? 1 : -1));
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (locList.classList.contains("show") && locActive > -1) pickLocation(locActive);
            else { hideLocations(); doSearch(); }
        } else if (e.key === "Escape") {
            hideLocations();
        }
    });
    // mousedown, not click: it fires before the input's blur hides the list
    locList.addEventListener("mousedown", e => {
        let li = e.target.closest("li[data-i]");
        if (li) { e.preventDefault(); pickLocation(+li.dataset.i); }
    });

    // ----------------------------------------------------
    // COMPANY NAME: AI SUGGESTIONS (worldwide)
    // ----------------------------------------------------
    const COMPANIES_URL = "{{ route('lead-generation-bot.companies') }}";
    const coInput = $id("lbCompany"), coList = $id("lbCompanyList");
    let coMatches = [], coActive = -1, coTimer, coSeq = 0;

    function showCompanyList(html) {
        coList.innerHTML = html;
        coList.classList.add("show");
        coInput.setAttribute("aria-expanded", "true");
    }

    function hideCompanies() {
        coList.classList.remove("show");
        coInput.setAttribute("aria-expanded", "false");
    }

    // Waits until typing pauses, so the AI is asked once per word rather than per key
    function queueCompanies() {
        clearTimeout(coTimer);
        let q = coInput.value.trim();
        coMatches = [];
        coActive = -1;
        if (q.length < 2) return hideCompanies();
        showCompanyList(`<li class="lb-none">Finding companies…</li>`);
        coTimer = setTimeout(() => fetchCompanies(q), 450);
    }

    async function fetchCompanies(q) {
        let seq = ++coSeq;
        try {
            let r = await fetch(COMPANIES_URL + "?q=" + encodeURIComponent(q), {headers: {"Accept": "application/json"}});
            let d = await r.json();
            // A slower answer to an older query must not replace a newer one
            if (seq !== coSeq || coInput.value.trim() !== q) return;
            if (!r.ok || d.error) throw new Error(d.error || "Company suggestions are unavailable.");
            coMatches = d.companies || [];
            showCompanyList(coMatches.length
                ? coMatches.map((c, i) => `<li role="option" data-i="${i}"><span>${esc(c.name)}<em>${esc(c.industry)}</em></span><small>${esc(c.country)}</small></li>`).join("")
                : `<li class="lb-none">No suggestions. You can still search for "${esc(q)}".</li>`);
        } catch (err) {
            if (seq === coSeq) showCompanyList(`<li class="lb-none">${esc(err.message)}</li>`);
        }
    }

    function pickCompany(i) {
        if (!coMatches[i]) return;
        coInput.value = coMatches[i].name;
        hideCompanies();
    }

    function highlightCompany(i) {
        let items = coList.querySelectorAll("li[data-i]");
        if (!items.length) return;
        coActive = (i + items.length) % items.length;
        items.forEach(li => li.classList.toggle("active", +li.dataset.i === coActive));
        items[coActive].scrollIntoView({block: "nearest"});
    }

    coInput.addEventListener("input", queueCompanies);
    coInput.addEventListener("blur", () => setTimeout(hideCompanies, 150));
    coInput.addEventListener("keydown", e => {
        if (e.key === "ArrowDown" || e.key === "ArrowUp") {
            e.preventDefault();
            highlightCompany(coActive + (e.key === "ArrowDown" ? 1 : -1));
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (coList.classList.contains("show") && coActive > -1) pickCompany(coActive);
            else { clearTimeout(coTimer); coSeq++; hideCompanies(); doSearch(); }
        } else if (e.key === "Escape") {
            hideCompanies();
        }
    });
    coList.addEventListener("mousedown", e => {
        let li = e.target.closest("li[data-i]");
        if (li) { e.preventDefault(); pickCompany(+li.dataset.i); }
    });

    $id("lbSearchBtn").addEventListener("click", doSearch);
    $id("lbFilter").addEventListener("input", render);
    $id("lbGrid").addEventListener("click", e => {
        let b = e.target.closest("button[data-act]");
        if (!b) return;
        let p = prospects.find(x => x.id === b.dataset.id);
        if (!p) return;
        p.status = {approve: "Approved", reject: "Rejected", reset: "Pending"}[b.dataset.act];
        render();
    });
    render();
})();
</script>
@endpush
