<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Backend of target_company_finder.html (Lead Finder — Target Companies).
 *
 * The page's filters (country, state, district, city, industry, size, buying signal, keyword) go to ChatGPT (OpenAI,
 * with web search) and come back as companies in the page's own format: name, industry, size, location, buying
 * intent, description, signals and publicly listed contacts. Uses OPENAI_API_KEY from .env (never sent to the browser).
 * Behind the portal login.
 */
class TargetCompanyFinderController extends AccountBaseController
{

    private const ICONS = [
        'Office expansion' => '▥',
        'Hiring' => '♟',
        'Technology upgrade' => '♙',
        'Tender / contract' => '▣',
        'New project' => '✦',
        'Funding' => '◈',
        'Acquisition' => '◈',
    ];

    public function search(Request $request)
    {
        // Administrators only, like the page it serves (Leads → Lead Generation Bot)
        abort_403(!in_array('admin', user_roles() ?? []));

        $request->validate([
            'country' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'district' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:80',
            'location' => 'nullable|string|max:200',
            'industry' => 'nullable|string|max:191',
            'project' => 'nullable|string|max:191',
            'signal' => 'nullable|string|max:191',
            'q' => 'nullable|string|max:120',
            'date_label' => 'nullable|string|max:60',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'mode' => 'nullable|in:projects,companies',
            'project_type' => 'nullable|string|max:100',
        ]);

        if (!config('services.openai.key')) {
            return response()->json(['message' => 'OpenAI is not configured (OPENAI_API_KEY in .env).'], 503);
        }

        // one Location field ("Gajuwaka, Visakhapatnam, Andhra Pradesh") plus the country; or the older state / district / city
        $location = $request->location
            ? trim($request->location . ($request->country && !Str::contains(Str::lower($request->location), Str::lower($request->country)) ? ', ' . $request->country : ''))
            : (collect([$request->city, $request->district, $request->state, $request->country])->filter()->implode(', ') ?: 'India');
        $criteria = collect([
            'Location' => "{$location}: every project must be IN this place (the town / city itself or its immediate area); leave out projects elsewhere, even in the same district or state",
            'Industry' => $request->industry
                ?: 'ALL industries: mix them (construction, infrastructure, real estate, utilities and power, ports and logistics, mining, manufacturing, oil and gas, government and public bodies …); not only IT companies',
            'Research project' => $request->project
                ? "\"{$request->project}\": list ONLY projects of exactly this category (its MAIN work is \"{$request->project}\", or its tender / contract is for this work); a project of any other kind does not count, even from the same company, and neither does a bigger project (construction, civil works, sewerage, roads …) that only includes this as one step, so leave them out"
                : null,
            'Buying signal' => $request->signal
                ? "{$request->signal}: every company must have at least one recent \"{$request->signal}\" signal; give that signal and its project the type \"{$request->signal}\" (a tender, contract or announcement about a new project counts as \"{$request->signal}\" when it fits)"
                : 'any',
            'Keyword' => $request->q,
            // Project / signal date filter (calendar on the page)
            'Project / signal date' => $request->date_from
                ? sprintf('%s (%s to %s): every signal and project must be dated in this period', $request->date_label ?: 'Chosen dates',
                    \Carbon\Carbon::parse($request->date_from)->format('d M Y'), \Carbon\Carbon::parse($request->date_to ?: $request->date_from)->format('d M Y'))
                : ($request->date_label === 'Latest first' ? 'latest first: newest signals and projects first' : null),
        ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $signalTypes = implode(', ', array_keys(self::ICONS));

        $intro = <<<TXT
We sell IT, GIS, CAD, survey and engineering services and are looking for CLIENTS: organizations that will BUY such
work. Use web search to find 5 to 8 different REAL client organizations that match ALL these criteria:
{$criteria}

Do not list IT, GIS, CAD, survey or engineering service providers (they are our competitors), unless they themselves
are buying this work. Do not stop after the first company: run several searches (news, tender and procurement
portals, government notifications, project announcements, company directories, industrial parks) until you have at
least 5, unless fewer truly exist for this location.
TXT;

        // Search Companies (Project Finder): construction and engineering companies by name, project, type or software
        if ($request->mode === 'companies') {
            $criteria = collect([
                'Company or project' => $request->q
                    ? "\"{$request->q}\": a company name, a project name or a keyword (e.g. a structure type or a software such as Revit); companies that match it"
                    : null,
                'Location' => $request->location ?: 'any location (worldwide)',
                'Project type' => $request->project_type
                    ? "\"{$request->project_type}\": companies doing this kind of project or using this software"
                    : 'any',
                'Project date' => $request->date_from
                    ? sprintf('%s (%s to %s): their projects must be dated in this period', $request->date_label ?: 'Chosen dates',
                        \Carbon\Carbon::parse($request->date_from)->format('d M Y'), \Carbon\Carbon::parse($request->date_to ?: $request->date_from)->format('d M Y'))
                    : ($request->date_label === 'Latest first' ? 'latest first: newest projects first' : null),
            ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

            $intro = <<<TXT
Use web search to find 5 to 8 different REAL construction and engineering companies (contractors, EPC companies,
infrastructure developers, engineering / design / BIM consultants) that match ALL these criteria:
{$criteria}

Every entry must be a COMPANY (the firm's own name, e.g. "PJR Project Construction Pvt. Ltd."), never a tender, project
or authority title; when you find a project, list the company doing it and put the project under its "projects".
List each company's matching projects (with the project type or software in the description). Do not stop after the
first company: run several searches (company websites, news, project announcements, tender results, directories)
until you have at least 5, unless fewer truly exist.
TXT;
        }

        $strict = $request->mode !== 'companies';
        $where = $strict
            ? "List only projects located in the Location above" . ($request->project ? " and of exactly the Research project category above" : '') . "; a company with no such project must not be listed. Order each company's projects newest first."
            : 'Prefer organizations with an office or project in or near the location. Order each company\'s projects newest first.';
        $check = $strict
            ? ',"in_location":"yes if the project is in the Location above, else no"' . ($request->project ? ',"category_match":"yes only if the main work of the project is the Research project category above, else no"' : '')
            : '';
        $howMany = $strict
            ? 'Give EVERY company 1 to 4 projects that meet the Location' . ($request->project ? ' and Research project' : '') . ' criteria above, never other projects.'
            : 'Give EVERY company 1 to 4 projects: each
buying signal above normally is a project (the expansion, the tender, the new plant), so list it here with its details.';

        $prompt = <<<TXT
{$intro}

{$where} For each, find recent public signals (last 12 months, or the Project / signal date period above when given):
{$signalTypes}. Never invent companies, people, emails or phone numbers. Only list a contact name if it is
published on the company's own site or in a news source; otherwise give the role only. Email and phone only if
officially published (e.g. a company's general contact), else "Not public".

Reply with ONLY a JSON object (no markdown, no code fences):
{"companies":[{"name":"","industry":"","size":"e.g. 51–200 employees","location":"city, district, state, country",
"intent":"High|Medium|Low","description":"1-2 sentences","website":"",
"signals":[{"type":"one of: {$signalTypes}","date":"Mon DD, YYYY or Mon YYYY","text":"what happened","source":"url"}],
"contacts":[{"name":"person or role","role":"","email":"","phone":"","note":"why relevant"}],
"projects":[{"name":"project name","type":"one of: {$signalTypes}","status":"Announced|Tender open|Awarded|In progress|Completed",
"date":"project date: DD Mon YYYY, or Mon YYYY if no day is known","location":"place, city, district, state (same spelling as the Location above)",
"value":"budget / contract value if published, else empty","description":"1-2 sentences",
"source":{"title":"","publisher":"","url":"page where you found the project","published":"date that page was published (e.g. 14 May 2026)"},
"notification":{"title":"","organization":"","date":"","url":""} or null{$check}}],
"sources":[{"title":"page title","publisher":"site or organization","url":"the exact page you used"}],
"notifications":[{"title":"official notice / tender / press release title","organization":"who published it",
"date":"publication date as published","url":"link to the original notice"}]}]}
"projects" = the company's current or recent projects (last 24 months: new offices, plants, tenders won or issued,
expansions, technology programs), each with the page it came from and the OFFICIAL notification for that specific
project (tender notice, order, press release) if one exists, else null. {$howMany}
"sources" = every page you used for this company. "notifications" = only OFFICIAL notices published by a government
body, a public sector organization or the company itself (tenders, RFPs, orders, circulars, press releases);
empty array if none. Every "url" (and "source") must be the FULL web address starting with https:// copied from
your search results, never a citation reference such as "turn0search1"; never make up a URL.
TXT;

        $answer = $this->ask($prompt, 16000);

        if ($answer instanceof \Illuminate\Http\JsonResponse) {
            return $answer;
        }

        [$data, $cited] = $answer;

        if (!is_array($data['companies'] ?? null)) {
            \Illuminate\Support\Facades\Log::warning('Lead finder: answer without companies', ['keys' => array_keys($data), 'answer' => mb_substr(json_encode($data), 0, 1500)]);

            return response()->json(['message' => 'ChatGPT returned an answer that could not be read. Please search again.'], 502);
        }

        $period = $request->date_from
            ? [\Carbon\Carbon::parse($request->date_from)->startOfDay(), \Carbon\Carbon::parse($request->date_to ?: $request->date_from)->endOfDay()]
            : null;
        $places = $strict ? $this->places($location) : [];

        $companies = collect($data['companies'])
            ->map(function ($c) use ($strict, $request, $places, $period) {
                if (!is_array($c)) {
                    return null;
                }
                $projects = collect($c['projects'] ?? [])->filter(fn ($x) => is_array($x));
                if ($strict) {
                    // Search Projects: only the chosen category, inside the location, in the chosen period
                    $projects = $projects->filter(fn ($x) => !($request->project && strtolower(trim((string) ($x['category_match'] ?? ''))) === 'no')
                        && $this->inPlace($x, $c, $places)
                        && (!$period || !($r = $this->projectRange($x)) || ($r[1] >= $period[0] && $r[0] <= $period[1])));
                    if ($projects->isEmpty()) {
                        return null;
                    }
                }
                // newest first; undated last
                $c['projects'] = $projects->sortByDesc(fn ($x) => ($r = $this->projectRange($x)) ? $r[0]->timestamp : 0)->values()->all();

                return $c;
            })->filter()->values()
            ->map(fn ($c, $i) => $this->company($c, $i, $cited))->filter()
            // the chosen buying signal first (in its signals or projects), then the companies with the newest projects
            ->sort(fn ($a, $b) => [$this->hasSignal($a, $request->signal), $this->newest($b)] <=> [$this->hasSignal($b, $request->signal), $this->newest($a)])
            ->values()->all();

        return response()->json(['companies' => $this->verifyLinks($companies)]);
    }

    /**
     * The search form's own lists, A–Z: ?type=project (the "Research projects" dropdown), ?type=signal ("Buying
     * signal") or ?type=industry ("Industry"); the page adds the standard signals and industries itself.
     */
    public function researchProjects(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        return response()->json(['items' => $this->listNames($this->listType($request))]);
    }

    /** A dropdown's Add button: saves a new research project or buying signal (once per name) and returns its list. */
    public function addResearchProject(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        $type = $this->listType($request);
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->input('name')));
        $request->merge(['name' => $name])->validate(['name' => 'required|string|min:2|max:191']);

        $companyId = company()?->id;

        if (!DB::table('lead_finder_projects')->where('company_id', $companyId)->where('type', $type)->where('name', $name)->exists()) {
            DB::table('lead_finder_projects')->insert([
                'company_id' => $companyId, 'type' => $type, 'name' => $name, 'added_by' => user()?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json(['items' => $this->listNames($type), 'added' => $name]);
    }

    /**
     * Location field suggestions while typing: places from OpenStreetMap (Photon, made for search-as-you-type), in the
     * chosen country. Cached a day per query.
     */
    public function locations(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate(['q' => 'required|string|min:2|max:100', 'country' => 'nullable|string|max:80']);

        $q = trim($request->q);
        $country = trim((string) $request->country);

        $places = Cache::remember('lead-finder-places-v2:' . md5(Str::lower($q . '|' . $country)), now()->addDay(), function () use ($q, $country) {
            try {
                $response = Http::timeout(6)->withHeaders(['User-Agent' => 'HRMS-Portal-LeadFinder/1.0 (' . config('app.url') . ')'])
                    ->get('https://photon.komoot.io/api/', ['q' => $country ? "$q, $country" : $q, 'limit' => 15, 'lang' => 'en']);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return null;
            }

            if ($response->failed()) {
                return null;
            }

            return collect($response->json('features') ?? [])->map(fn ($f) => $f['properties'] ?? [])
                ->filter(fn ($p) => !empty($p['name']) && in_array($p['osm_key'] ?? '', ['place', 'boundary'], true))
                ->filter(fn ($p) => $country === '' || strcasecmp($p['country'] ?? '', $country) === 0)
                ->map(fn ($p) => [
                    'id' => strtoupper(substr($p['osm_type'] ?? 'N', 0, 1)) . ($p['osm_id'] ?? ''),
                    'name' => $p['name'],
                    // a second line to tell places of the same name apart
                    'detail' => collect([$p['city'] ?? null, $p['county'] ?? null, $p['state'] ?? null, $p['country'] ?? null])
                        ->filter(fn ($v) => $v && $v !== $p['name'] && strcasecmp($v, $country) !== 0)->unique()->implode(' · '),
                ])->unique(fn ($p) => $p['name'] . '|' . $p['detail'])->take(8)->values()->all();
        });

        if ($places === null) {
            Cache::forget('lead-finder-places-v2:' . md5(Str::lower($q . '|' . $country)));

            return response()->json(['message' => 'Place suggestions are not available right now. Type the location in full.'], 502);
        }

        return response()->json(['places' => $places]);
    }

    /**
     * The chosen suggestion as "place, district, state" (e.g. Gajuwaka, Visakhapatnam, Andhra Pradesh): the address
     * of that one place from OpenStreetMap (Nominatim lookup; districts are not in the suggestions). Cached 30 days.
     */
    public function resolveLocation(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate(['id' => ['required', 'regex:/^[NWR]\d{1,15}$/']]);

        $place = Cache::remember('lead-finder-place-v2:' . $request->id, now()->addDays(30), function () use ($request) {
            try {
                $response = Http::timeout(8)->withHeaders(['User-Agent' => 'HRMS-Portal-LeadFinder/1.0 (' . config('app.url') . ')'])
                    ->get('https://nominatim.openstreetmap.org/lookup', ['osm_ids' => $request->id, 'format' => 'jsonv2', 'addressdetails' => 1, 'accept-language' => 'en']);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return null;
            }

            $a = $response->successful() ? ($response->json('0.address') ?? null) : null;

            if (!$a) {
                return null;
            }

            $name = $response->json('0.name') ?: ($a['suburb'] ?? $a['village'] ?? $a['town'] ?? $a['city'] ?? '');
            $district = preg_replace('/\s+(District|Division)$/i', '', (string) ($a['state_district'] ?? $a['county'] ?? ''));
            $city = $a['city'] ?? $a['town'] ?? null;

            return [
                'name' => $name,
                'city' => $city,
                'district' => $district,
                'state' => $a['state'] ?? '',
                'country' => $a['country'] ?? '',
                'label' => collect([$name, $district ?: $city, $a['state'] ?? null, $a['country'] ?? null])->filter()->unique()->implode(', '),
            ];
        });

        if ($place === null) {
            Cache::forget('lead-finder-place-v2:' . $request->id);

            return response()->json(['message' => 'Could not read this place.'], 502);
        }

        return response()->json(['place' => $place]);
    }

    private const SAVED_KINDS = ['target', 'saved_project', 'target_project'];

    /** The user's My Targets, Saved Projects and Target Projects (newest first). */
    public function saved()
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        return response()->json(['saved' => $this->savedLists()]);
    }

    /** "Save target" / "Save project" / "Target project": stores the company or project (again = updates it). */
    public function save(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate([
            'kind' => 'required|in:' . implode(',', self::SAVED_KINDS),
            'key' => 'required|string|max:191',
            'data' => 'required|array',
            'data.name' => 'required|string|max:250',
        ]);

        $data = json_encode($request->input('data'), JSON_UNESCAPED_UNICODE);

        if (strlen($data) > 200000) {
            return response()->json(['message' => 'This item is too large to save.'], 422);
        }

        DB::table('lead_finder_saved')->updateOrInsert(
            ['user_id' => user()->id, 'kind' => $request->kind, 'item_key' => $request->key],
            ['company_id' => company()?->id, 'data' => $data, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['saved' => $this->savedLists()]);
    }

    /** Removes a company or project from the user's list. */
    public function unsave(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate(['kind' => 'required|in:' . implode(',', self::SAVED_KINDS), 'key' => 'required|string|max:191']);

        DB::table('lead_finder_saved')->where('user_id', user()->id)->where('kind', $request->kind)->where('item_key', $request->key)->delete();

        return response()->json(['saved' => $this->savedLists()]);
    }

    private function savedLists(): array
    {
        $rows = DB::table('lead_finder_saved')->where('user_id', user()->id)->orderByDesc('updated_at')->orderByDesc('id')->get();

        return collect(self::SAVED_KINDS)->mapWithKeys(fn ($kind) => [$kind => $rows->where('kind', $kind)->map(fn ($r) => [
            'key' => $r->item_key,
            'data' => json_decode($r->data, true),
            'saved_at' => \Carbon\Carbon::parse($r->updated_at)->format('d M Y'),
        ])->values()->all()])->all();
    }

    /** Contacts (sidebar): every company and contact detail added from company pages, newest first. */
    public function contacts()
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        return response()->json(['contacts' => $this->contactList()]);
    }

    /**
     * "Add to contacts" (the company only) and "Add contact details" (the person, notes, follow-up and the projects
     * discussed) on a company page.
     */
    public function addContact(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate([
            'company' => 'required|array',
            'company.name' => 'required|string|max:250',
            'contact_name' => 'nullable|string|max:150',
            'designation' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:60',
            'email' => 'nullable|email|max:191',
            'notes' => 'nullable|string|max:5000',
            'follow_up' => 'boolean',
            'follow_up_date' => 'nullable|required_if:follow_up,true|date',
            'projects' => 'nullable|array|max:20',
            'projects.*.name' => 'required|string|max:191',
            'projects.*.details' => 'nullable|string|max:2000',
        ], ['follow_up_date.required_if' => 'Choose the follow-up date.', 'projects.*.name.required' => 'Enter the name of every project.']);

        $company = json_encode(collect($request->input('company'))->only(['id', 'name', 'short', 'industry', 'size', 'location', 'intent', 'website', 'description'])->all(), JSON_UNESCAPED_UNICODE);
        $key = Str::limit(trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower($request->input('company.name')))), 180, '');
        $details = collect(['contact_name', 'designation', 'phone', 'email', 'notes'])->contains(fn ($f) => $request->filled($f)) || $request->boolean('follow_up') || $request->filled('projects');

        // "Add to contacts" once per company (contact details can be added any number of times)
        if (!$details && DB::table('lead_finder_contacts')->where('company_id', company()?->id)->where('company_key', $key)->exists()) {
            return response()->json(['message' => 'This company is already in Contacts.', 'already' => true, 'contacts' => $this->contactList()]);
        }

        DB::table('lead_finder_contacts')->insert([
            'company_id' => company()?->id,
            'added_by' => user()?->id,
            'company_key' => $key,
            'company' => $company,
            'contact_name' => $request->contact_name,
            'designation' => $request->designation,
            'phone' => $request->phone,
            'email' => $request->email,
            'notes' => $request->notes,
            'follow_up' => $request->boolean('follow_up'),
            'follow_up_date' => $request->boolean('follow_up') ? $request->follow_up_date : null,
            'projects' => $request->filled('projects') ? json_encode(array_values($request->input('projects')), JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => $details ? 'Contact saved successfully.' : 'Added to contacts successfully.',
            'contacts' => $this->contactList(),
        ]);
    }

    /** Follow-ups: marks a follow-up done, or not done again. */
    public function followUpDone(Request $request, int $id)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate(['done' => 'required|boolean']);

        DB::table('lead_finder_contacts')->where('company_id', company()?->id)->where('id', $id)->where('follow_up', true)
            ->update(['follow_up_done' => $request->boolean('done'), 'follow_up_done_at' => $request->boolean('done') ? now() : null, 'updated_at' => now()]);

        return response()->json(['contacts' => $this->contactList()]);
    }

    /** Removes an entry from Contacts. */
    public function deleteContact(int $id)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        DB::table('lead_finder_contacts')->where('company_id', company()?->id)->where('id', $id)->delete();

        return response()->json(['contacts' => $this->contactList()]);
    }

    private function contactList(): array
    {
        $tz = company()->timezone ?? config('app.timezone');

        return DB::table('lead_finder_contacts as c')->leftJoin('users as u', 'u.id', '=', 'c.added_by')
            ->where('c.company_id', company()?->id)->orderByDesc('c.created_at')->orderByDesc('c.id')
            ->get(['c.*', 'u.name as added_by_name'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'key' => $r->company_key,
                'company' => json_decode($r->company, true),
                'contact_name' => $r->contact_name,
                'designation' => $r->designation,
                'phone' => $r->phone,
                'email' => $r->email,
                'notes' => $r->notes,
                'follow_up' => (bool) $r->follow_up,
                'follow_up_date' => $r->follow_up_date ? \Carbon\Carbon::parse($r->follow_up_date)->format('d M Y') : null,
                'follow_up_on' => $r->follow_up_date ? \Carbon\Carbon::parse($r->follow_up_date)->format('Y-m-d') : null,
                'follow_up_done' => (bool) ($r->follow_up_done ?? false),
                'follow_up_done_at' => $r->follow_up_done_at ? \Carbon\Carbon::parse($r->follow_up_done_at)->timezone($tz)->format('d M Y') : null,
                'projects' => json_decode($r->projects ?? '[]', true) ?: [],
                'added_at' => \Carbon\Carbon::parse($r->created_at)->timezone($tz)->format('d M Y, h:i A'),
                'added_by' => $r->added_by_name,
            ])->all();
    }

    private function listType(Request $request): string
    {
        return in_array($request->input('type'), ['signal', 'industry', 'project_type'], true) ? $request->input('type') : 'project';
    }

    private function listNames(string $type): array
    {
        return DB::table('lead_finder_projects')->where('company_id', company()?->id)->where('type', $type)
            ->orderBy('name')->pluck('name')->all();
    }

    /** Social networks shown on the company page: key => [name, the domains its links must be on]. */
    private const SOCIAL = [
        'linkedin' => ['LinkedIn', ['linkedin.com']],
        'facebook' => ['Facebook', ['facebook.com', 'fb.com']],
        'instagram' => ['Instagram', ['instagram.com']],
        'x' => ['X (Twitter)', ['x.com', 'twitter.com']],
        'youtube' => ['YouTube', ['youtube.com']],
    ];

    /**
     * The company page's header: registered address, email, phone, official website, logo and social media accounts.
     * ChatGPT finds them (web search); the portal keeps only links on the right sites, checks that they open and takes the logo from the
     * official website. Cached 7 days per company.
     */
    public function profile(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        $request->validate(['name' => 'required|string|max:150', 'location' => 'nullable|string|max:200', 'website' => 'nullable|string|max:300']);

        if (!config('services.openai.key')) {
            return response()->json(['message' => 'OpenAI is not configured (OPENAI_API_KEY in .env).'], 503);
        }

        $cacheKey = 'lead-finder-profile-v8:' . md5(Str::lower($request->name . '|' . $request->location));

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        if ($profile = Cache::get($cacheKey)) {
            return response()->json(['profile' => $profile]);
        }

        $company = collect(['Company' => $request->name, 'Location' => $request->location, 'A link seen for it (may be a job portal, not its own site)' => $request->website])
            ->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $prompt = <<<TXT
Use web search to find the OFFICIAL online presence of this company:
{$company}

Reply with ONLY a JSON object (no markdown, no code fences):
{"tagline":"","company_type":"","cin":"","incorporated":"","status":"","directors":[""],"address":"","address_source":"","email":"","phone":"","website":"","linkedin":"",
"linkedin_job":"","job":{"title":"","location":"","type":"","site":"","url":""},"facebook":"","instagram":"","x":"",
"youtube":"","other":[{"name":"","url":""}]}
"tagline" = what the company does in 2 to 4 short parts joined by " | " (e.g. "Engineering | Design | Construction").
"company_type" = its legal form as registered (e.g. Private Limited, Public Limited, LLP, Partnership, Government body).
"cin", "incorporated" (date), "status" (Active / Strike off …) and "directors" (names) as in the company registry
(MCA data on Tofler, ZaubaCorp, The Company Check …); empty when not found.
"job" = its most recent job post (LinkedIn first, else Naukri, Indeed, Apna or its own careers page): title, location,
type (Full-time, Internship …), site name and the post's URL; empty strings when none.
"address" = the registered office address as published (company registry sites such as ZaubaCorp, Tofler, The
Company Check, MCA data, or the company's own site), with "address_source" = the page it is on. "email" and "phone" =
the company's official general email and phone exactly as published (registry or own site), else empty.
"linkedin_job" = a LinkedIn job post of this company (linkedin.com/jobs/view/…), if any: search LinkedIn for the
company name (company page and job posts) and look for its social handles (e.g. @name) as well.
"website" = the company's own website home page (its own domain; never a job portal, directory, marketplace or news
site). The others = the company's own official accounts (LinkedIn company page, Facebook page, Instagram, X / Twitter,
YouTube channel; "other" for any further official account such as a Google Business page). Only accounts that clearly
belong to THIS company (same name and place); an empty string when not found. Every URL must be the full address
starting with https:// copied from your search results; never make one up.
TXT;

        $answer = $this->ask($prompt, 2500);

        if ($answer instanceof \Illuminate\Http\JsonResponse) {
            return $answer;
        }

        $data = $answer[0];
        $links = [];

        $website = $this->cleanUrl($data['website'] ?? '');
        if ($this->link($website) && !$this->onDomains($website, ['linkedin.com', 'facebook.com', 'instagram.com', 'x.com', 'twitter.com', 'youtube.com', 'apna.co', 'naukri.com', 'indeed.com', 'justdial.com', 'indiamart.com', 'glassdoor.com', 'zaubacorp.com', 'tofler.in'])) {
            $links[] = ['key' => 'website', 'name' => 'Website', 'url' => $website];
        }

        foreach (self::SOCIAL as $key => [$name, $domains]) {
            $url = $this->cleanUrl($data[$key] ?? '');
            if ($this->link($url) && $this->onDomains($url, $domains)) {
                $links[] = ['key' => $key, 'name' => $name, 'url' => $url];
            }
        }

        // no LinkedIn company page: a LinkedIn job post of the company still leads to it
        $job = $this->cleanUrl($data['linkedin_job'] ?? '');
        if (!collect($links)->contains('key', 'linkedin') && $this->link($job) && $this->onDomains($job, ['linkedin.com'])) {
            $links[] = ['key' => 'linkedin', 'name' => 'LinkedIn (job post)', 'url' => $job];
        }

        foreach (array_slice((array) ($data['other'] ?? []), 0, 3) as $other) {
            $url = $this->cleanUrl($other['url'] ?? '');
            if (is_array($other) && $this->link($url) && !collect($links)->contains('url', $url)) {
                $links[] = ['key' => 'other', 'name' => Str::limit(trim((string) ($other['name'] ?? '')) ?: $this->host($url), 40, ''), 'url' => $url];
            }
        }

        // does each link open? (LinkedIn answers 999 and others 403 to automated checks: the page exists)
        $responses = $links ? Http::pool(fn ($pool) => collect($links)->map(fn ($l) => $pool->as($l['url'])->timeout(6)->connectTimeout(4)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'])
            ->withOptions(['allow_redirects' => true, 'verify' => false])->get($l['url']))->all()) : [];

        foreach ($links as &$link) {
            $r = $responses[$link['url']] ?? null;
            $link['verified'] = $r instanceof \Illuminate\Http\Client\Response && ($r->successful() || in_array($r->status(), [401, 403, 405, 429, 999]));
        }
        unset($link);

        // the official website must open, or it is not shown
        $links = array_values(array_filter($links, fn ($l) => $l['key'] !== 'website' || $l['verified']));
        $site = collect($links)->firstWhere('key', 'website');
        $siteResponse = $site ? ($responses[$site['url']] ?? null) : null;

        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $addressSource = $this->cleanUrl($data['address_source'] ?? '');

        $job = is_array($data['job'] ?? null) ? $data['job'] : [];
        $jobUrl = $this->cleanUrl($job['url'] ?? '');

        $profile = [
            'tagline' => Str::limit(trim((string) ($data['tagline'] ?? '')), 120, ''),
            'company_type' => Str::limit(trim((string) ($data['company_type'] ?? '')), 60, ''),
            // registry facts (MCA data)
            'cin' => preg_match('/^[LUF]\d{5}[A-Z]{2}\d{4}[A-Z]{3}\d{6}$/', strtoupper(trim((string) ($data['cin'] ?? '')))) ? strtoupper(trim($data['cin'])) : '',
            'incorporated' => Str::limit(trim((string) ($data['incorporated'] ?? '')), 40, ''),
            'status' => Str::limit(trim((string) ($data['status'] ?? '')), 40, ''),
            'directors' => collect((array) ($data['directors'] ?? []))->map(fn ($d) => Str::limit(trim((string) $d), 80, ''))->filter()->take(6)->values()->all(),
            // latest job post (any job site)
            'job' => $this->link($jobUrl) && trim((string) ($job['title'] ?? '')) !== '' ? [
                'title' => Str::limit(trim($job['title']), 120, ''),
                'location' => Str::limit(trim((string) ($job['location'] ?? '')), 120, ''),
                'type' => Str::limit(trim((string) ($job['type'] ?? '')), 40, ''),
                'site' => Str::limit(trim((string) ($job['site'] ?? '')) ?: $this->host($jobUrl), 40, ''),
                'url' => $jobUrl,
            ] : null,
            // contact details as published (registry or own site); shown under the company name
            'address' => Str::limit(trim(preg_replace('/\s+/u', ' ', (string) ($data['address'] ?? ''))), 300, ''),
            'address_source' => $this->link($addressSource) ? $addressSource : '',
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? Str::lower($email) : '',
            'phone' => '',
            'website' => $site['url'] ?? '',
            'logo' => '',
            'links' => $links,
        ];

        // the official website itself: its phone numbers, email and logo (also from the script of JavaScript sites)
        $extras = $site ? $this->siteExtras($site['url'], $siteResponse instanceof \Illuminate\Http\Client\Response ? $siteResponse->body() : '') : ['phones' => [], 'emails' => [], 'logo' => ''];

        $phones = collect($this->phonesIn($phone))->merge($extras['phones'])->unique(fn ($p) => substr(preg_replace('/\D/', '', $p), -10))->take(4)->values()->all();
        $profile['phones'] = $phones;
        $profile['phone'] = implode(' / ', $phones);
        if (!$profile['email'] && $extras['emails']) {
            $profile['email'] = $extras['emails'][0];
        }
        $profile['logo'] = $site ? ($extras['logo'] ?: $this->logoFrom($site['url'], $siteResponse instanceof \Illuminate\Http\Client\Response ? $siteResponse->body() : '')) : '';

        $found = $links || $profile['address'] || $profile['email'] || $profile['phone'] || $profile['job'];
        Cache::put($cacheKey, $profile, $found ? now()->addDays(7) : now()->addDay()); // nothing found: look again tomorrow

        return response()->json(['profile' => $profile]);
    }

    private function onDomains(string $url, array $domains): bool
    {
        $host = $this->host($url);

        return collect($domains)->contains(fn ($d) => $host === $d || Str::endsWith($host, '.' . $d));
    }

    /** Phone numbers in a text such as "+91 98213 67617 / +91 75693 29819" (each one checked). */
    private function phonesIn(string $text): array
    {
        return collect(preg_split('#\s*(?:/|,|;|\||\bor\b)\s*#i', $text))
            ->map(fn ($p) => trim($p))
            ->filter(function ($p) {
                $digits = preg_replace('/\D/', '', $p);

                return preg_match('/^\+?[\d\s().-]{8,20}$/', $p) && strlen($digits) >= 8 && strlen($digits) <= 13
                    && !preg_match('/(\d)\1{6,}/', $digits); // not a placeholder such as +91 0000000000
            })->values()->all();
    }

    /**
     * What the official website itself publishes: phone numbers, emails (on its own domain) and its logo image. Read
     * from the page and, for JavaScript sites whose page is an empty shell, from the site's own script files.
     */
    private function siteExtras(string $site, string $html): array
    {
        $host = $this->host($site);
        $base = parse_url($site);
        $origin = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '');
        $absolute = fn ($path) => preg_match('#^https?://#i', $path) ? $path : (Str::startsWith($path, '//') ? 'https:' . $path : $origin . '/' . ltrim($path, '/'));
        $text = $html;

        // the contact page(s): "Contact" links on the home page, else the usual addresses (up to 3, fetched together)
        preg_match_all('/href\s*=\s*["\']([^"\'#]*contact[^"\'#]*)["\']/i', $html, $cl);
        $contactPages = collect($cl[1])->map($absolute)->filter(fn ($u) => $this->host($u) === $host && !preg_match('/\.(png|jpe?g|pdf|svg)$/i', $u))
            ->merge(collect(['contact', 'contact-us', 'contactus', 'contact-us.html', 'contact.html', 'contact.php'])->map(fn ($p) => $origin . '/' . $p))
            ->map(fn ($u) => rtrim($u, '/'))->unique()->reject(fn ($u) => $u === rtrim($site, '/'))->take(3)->values();
        $pages = $contactPages->isEmpty() ? [] : Http::pool(fn ($pool) => $contactPages->map(fn ($u) => $pool->as($u)->timeout(6)->connectTimeout(4)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'])
            ->withOptions(['allow_redirects' => true, 'verify' => false])->get($u))->all());
        foreach ($pages as $r) {
            if ($r instanceof \Illuminate\Http\Client\Response && $r->successful() && strlen($r->body()) < 3000000) {
                $text .= "\n" . $r->body();
            }
        }
        $pageText = $text; // pages only (not scripts): numbers written after "Phone:", "Mobile:" …

        // a JavaScript site: the content is in its script files (same site, up to 2, 4 MB each)
        if (strlen(strip_tags($html)) < 1500 && preg_match_all('/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+\.js)["\']/i', $html, $m)) {
            $scripts = collect($m[1])->map($absolute)->filter(fn ($u) => $this->host($u) === $host)->take(2);
            foreach ($scripts as $url) {
                try {
                    $r = Http::timeout(8)->withOptions(['verify' => false])->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
                    if ($r->successful() && strlen($r->body()) < 4000000) {
                        $text .= "\n" . $r->body();
                    }
                } catch (\Throwable $e) {
                    // the page alone then
                }
            }
        }

        // phones: tel: links and numbers written with a country code
        preg_match_all('/tel:([+\d][\d\s().-]{7,20})/i', $text, $tel);
        preg_match_all('/(?<![\w+])\+\d{1,3}[\s-]?\(?\d{2,5}\)?[\s-]?\d{3,5}[\s-]?\d{3,5}(?![\w])/', $text, $written);
        $plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $pageText))));
        preg_match_all('/(?:phone|ph|tel|telephone|mobile|mob|call|contact(?: no| number)?)\s*(?:no\.?|number)?\s*[:.\-]?\s*(\+?\d[\d\s().-]{8,18}\d)/i', $plain, $labelled);
        $phones = collect($tel[1])->merge($written[0])->merge($labelled[1])->map(fn ($p) => trim(preg_replace('/\s+/', ' ', $p)))
            ->filter(fn ($p) => $this->phonesIn($p))->unique(fn ($p) => substr(preg_replace('/\D/', '', $p), -10))->take(4)->values()->all();

        // emails on the site's own domain
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text, $em);
        $emails = collect($em[0])->map(fn ($e) => Str::lower($e))->filter(fn ($e) => Str::endsWith($e, '@' . $host) || Str::endsWith($e, '.' . $host))
            ->unique()->take(3)->values()->all();

        // logo: an image named logo… (the brand's own, not e.g. software/autocad logo.png)
        preg_match_all('#["\'(]((?:https?:)?/?[^"\'()\s]*?/?logo[^"\'()/\s]*\.(?:png|svg|webp|jpe?g))["\')]#i', $text, $lg);
        $logo = collect($lg[1])->filter(fn ($u) => preg_match('#(^|/)logo[^/]*$#i', $u))->sortBy(fn ($u) => substr_count($u, '/') * 100 + strlen($u))->first();

        // or an <img> on the home page marked as the logo (class, id or alt), not a client / partner / award logo
        if (!$logo && preg_match_all('/<img\b[^>]*>/i', $html, $imgs)) {
            $logo = collect($imgs[0])->filter(fn ($t) => preg_match('/\b(class|id|alt)\s*=\s*["\'][^"\']*logo/i', $t) && !preg_match('/client|partner|award|certif|member|brand-?list/i', $t))
                ->map(fn ($t) => preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $t, $m) ? $m[1] : null)->filter(fn ($u) => $u && !Str::startsWith($u, 'data:'))->first();
        }

        return ['phones' => $phones, 'emails' => $emails, 'logo' => $logo ? $absolute($logo) : ''];
    }

    /** The logo on a website: its apple-touch-icon or largest icon, else Google's favicon of the domain. */
    private function logoFrom(string $site, string $html): string
    {
        $best = null;
        $bestSize = -1;

        if (preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (!preg_match('/\brel\s*=\s*["\']([^"\']+)["\']/i', $tag, $rel) || !preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $href)) {
                    continue;
                }
                $rel = Str::lower($rel[1]);
                if (!Str::contains($rel, ['apple-touch-icon', 'icon'])) {
                    continue;
                }
                $size = preg_match('/\bsizes\s*=\s*["\'](\d+)/i', $tag, $m) ? (int) $m[1] : (Str::contains($rel, 'apple-touch-icon') ? 180 : 16);
                if ($size > $bestSize) {
                    [$best, $bestSize] = [html_entity_decode($href[1]), $size];
                }
            }
        }

        if ($best && $bestSize >= 32) {
            if (Str::startsWith($best, '//')) {
                return 'https:' . $best;
            }
            if (preg_match('#^https?://#i', $best)) {
                return $best;
            }
            $base = parse_url($site);

            return ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . (Str::startsWith($best, '/') ? '' : '/') . $best;
        }

        return 'https://www.google.com/s2/favicons?sz=128&domain=' . urlencode($this->host($site));
    }

    /**
     * All projects of one company, for its page (lead-generation-bot.finder-company): ChatGPT searches only this
     * company and lists up to 20 projects, each with its source and official notification, in the same format as
     * the search results.
     */
    public function projects(Request $request)
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        $request->validate([
            'name' => 'required|string|max:150',
            'location' => 'nullable|string|max:200',
            'website' => 'nullable|string|max:200',
        ]);

        if (!config('services.openai.key')) {
            return response()->json(['message' => 'OpenAI is not configured (OPENAI_API_KEY in .env).'], 503);
        }

        $company = collect(['Company' => $request->name, 'Location' => $request->location, 'Website' => $request->website])
            ->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");
        $signalTypes = implode(', ', array_keys(self::ICONS));

        $prompt = <<<TXT
Use web search to find ALL current and recent projects (last 24 months) of this company. Run several searches
(the company's own site and press releases, news, tender and procurement portals, government notifications):
{$company}

Projects are new offices, plants and facilities, expansions, tenders won or issued, contracts and orders,
technology programs and other announced investments. List up to 20 different projects, newest first. Never
invent a project, number or URL.

Reply with ONLY a JSON object (no markdown, no code fences):
{"projects":[{"name":"project name","type":"one of: {$signalTypes}","status":"Announced|Tender open|Awarded|In progress|Completed",
"date":"","location":"","value":"budget / contract value if published, else empty","description":"1-2 sentences",
"source":{"title":"","publisher":"","url":"page where you found the project","published":"date that page was published (e.g. 14 May 2026)"},
"notification":{"title":"","organization":"","date":"","url":""} or null}]}
"notification" = the OFFICIAL notice for that specific project (tender notice, order, government notification,
the company's press release) if one exists, else null. Every "url" must be the FULL web address starting with
https:// copied from your search results, never a citation reference such as "turn0search1".
TXT;

        $answer = $this->ask($prompt, 16000);

        if ($answer instanceof \Illuminate\Http\JsonResponse) {
            return $answer;
        }

        $projects = collect($answer[0]['projects'] ?? [])->filter(fn ($x) => is_array($x) && trim((string) ($x['name'] ?? '')) !== '')
            ->take(20)->map(fn ($x) => $this->project($this->cleanProject($x)))->values()->all();

        $checked = $this->verifyLinks([['website' => (string) $request->website, 'sources' => [], 'notifications' => [], 'projects' => $projects]]);

        return response()->json(['projects' => $checked[0]['projects']]);
    }

    /**
     * Ask ChatGPT (OpenAI Responses API with web search). Returns [the JSON object in its answer, the pages it cited]
     * or an error response for the page. An answer that cannot be read even after repair is asked for once more.
     */
    private function ask(string $prompt, int $maxTokens, int $attempt = 1)
    {
        set_time_limit(200);

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->withHeaders(array_filter([
                    'OpenAI-Organization' => config('services.openai.organization'),
                    'OpenAI-Project' => config('services.openai.project'),
                ]))
                ->timeout(180)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.finder_model'),
                    'tools' => [['type' => 'web_search']],
                    'input' => $prompt,
                    'max_output_tokens' => $maxTokens,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['message' => 'Could not reach OpenAI: ' . $e->getMessage()], 502);
        }

        if ($response->failed()) {
            return response()->json(['message' => 'OpenAI error: ' . ($response->json('error.message') ?: 'HTTP ' . $response->status())], 502);
        }

        $text = collect($response->json('output') ?? [])->where('type', 'message')
            ->flatMap(fn ($o) => $o['content'] ?? [])->where('type', 'output_text')->pluck('text')->implode("\n");

        $data = $this->decodeJson($text);

        if (!is_array($data)) {
            \Illuminate\Support\Facades\Log::warning('Lead finder: unreadable ChatGPT answer', [
                'status' => $response->json('status'), 'reason' => $response->json('incomplete_details.reason'),
                'attempt' => $attempt, 'length' => strlen($text), 'end' => mb_substr($text, -300),
            ]);

            if ($attempt === 1) {
                return $this->ask($prompt . "\n\nReply with complete, valid JSON only: no placeholders such as … and no extra brackets.", $maxTokens, 2);
            }

            return response()->json(['message' => 'ChatGPT returned an answer that could not be read. Please try again.'], 502);
        }

        // Pages ChatGPT cited (real URLs), used when it wrote a citation reference instead of a link
        $cited = collect($response->json('output') ?? [])->where('type', 'message')->flatMap(fn ($o) => $o['content'] ?? [])
            ->flatMap(fn ($c) => $c['annotations'] ?? [])->where('type', 'url_citation')
            ->filter(fn ($a) => $this->link($a['url'] ?? null))->unique('url')
            ->map(fn ($a) => ['title' => (string) ($a['title'] ?? ''), 'url' => preg_replace('/[?&]utm_source=openai$/', '', $a['url'])])->values();

        return [$data, $cited];
    }

    /**
     * The JSON object in ChatGPT's answer. ChatGPT sometimes closes a bracket too many (…}]}]}]}) or leaves one
     * open; then the brackets are put right (stray closers dropped, missing ones added) and it is read again.
     */
    private function decodeJson(string $text): ?array
    {
        $start = strpos($text, '{');

        if ($start === false) {
            return null;
        }

        $end = strrpos($text, '}');
        $data = $end !== false ? json_decode(substr($text, $start, $end - $start + 1), true) : null;

        if (is_array($data)) {
            return $data;
        }

        $out = '';
        $stack = [];
        $inString = false;
        $escaped = false;

        foreach (str_split(substr($text, $start)) as $ch) {
            if ($inString) {
                $out .= $ch;
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                $escaped = false;
            } elseif (ord($ch) > 127 || ($ch === '.' && !preg_match('/\d$/', $out))) {
                continue; // a placeholder such as … or ... outside any text
            } elseif ($ch === '{' || $ch === '[') {
                $stack[] = $ch === '{' ? '}' : ']';
            } elseif ($ch === '}' || $ch === ']') {
                if (end($stack) !== $ch) {
                    continue; // a closer that closes nothing
                }
                array_pop($stack);
                $out .= $ch;
                if (!$stack) {
                    break; // the whole object is read
                }
                continue;
            }

            $out .= $ch;
        }

        $out = preg_replace('/,\s*$/', '', $out) . implode('', array_reverse($stack));
        $data = json_decode($out, true);

        return is_array($data) ? $data : null;
    }

    /** A real web address (not a citation placeholder such as "turn0search3", nor one cut short with "..."). */
    private function link($url): bool
    {
        return is_string($url) && preg_match('#^https?://[^\s]+\.[^\s]+#i', trim($url)) && !Str::contains($url, ['...', '…']);
    }

    /** ChatGPT sometimes writes typographic dashes (‑ – —) inside URLs, which breaks them. */
    private function cleanUrl($url): string
    {
        return trim(str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}"], '-', (string) $url));
    }

    /**
     * Verification status of every source and notification link, checked by the portal itself (not by ChatGPT):
     *   official   - the link opens and is a government / public body site or the company's own website
     *   verified   - the link opens (news, job portal, directory …)
     *   unverified - the link did not open; check it manually
     *   no-link    - no link was given
     * All links are fetched in parallel, a few seconds each at most.
     */
    private function verifyLinks(array $companies): array
    {
        $urls = collect($companies)->flatMap(fn ($c) => collect($c['sources'])->pluck('url')->merge(collect($c['notifications'])->pluck('url'))
                ->merge(collect($c['projects'])->pluck('source.url'))->merge(collect($c['projects'])->pluck('notification.url')))
            ->filter(fn ($u) => preg_match('#^https?://#i', (string) $u))->unique()->take(60)->values();

        $ok = [];

        if ($urls->isNotEmpty()) {
            $responses = Http::pool(fn ($pool) => $urls->map(fn ($u) => $pool->as($u)->timeout(6)->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'])
                ->withOptions(['allow_redirects' => true, 'verify' => false])->get($u))->all());

            foreach ($responses as $u => $r) {
                // 401/403/405/429: the page exists but refuses automated checks
                $ok[$u] = $r instanceof \Illuminate\Http\Client\Response && ($r->successful() || in_array($r->status(), [401, 403, 405, 429]));
            }
        }

        $status = function ($url, $company) use ($ok) {
            if (!preg_match('#^https?://#i', (string) $url)) {
                return ['key' => 'no-link', 'label' => 'No link given'];
            }

            if (!($ok[$url] ?? false)) {
                return ['key' => 'unverified', 'label' => 'Not verified · link did not open'];
            }

            return $this->isOfficial($url, $company)
                ? ['key' => 'official', 'label' => 'Verified · official source']
                : ['key' => 'verified', 'label' => 'Link verified'];
        };

        foreach ($companies as &$company) {
            foreach ($company['sources'] as &$source) {
                $source['verification'] = $status($source['url'], $company);
            }
            unset($source);

            foreach ($company['notifications'] as &$notice) {
                $notice['verification'] = $status($notice['url'], $company);
            }
            unset($notice);

            foreach ($company['projects'] as &$project) {
                $project['source']['verification'] = $status($project['source']['url'], $company);
                if ($project['notification']) {
                    $project['notification']['verification'] = $status($project['notification']['url'], $company);
                }
            }
            unset($project);
        }

        return $companies;
    }

    /** A government / public body domain, or the company's own website. */
    private function isOfficial(string $url, array $company): bool
    {
        $host = $this->host($url);

        if (preg_match('/(\.gov|\.gov\.[a-z]{2}|\.nic\.in|\.gouv\.[a-z]{2}|\.mil|\.ac\.in|\.edu)$/i', $host)) {
            return true;
        }

        $own = $this->host($company['website'] ?? '');

        return $own !== '' && ($host === $own || Str::endsWith($host, '.' . $own));
    }

    private function host(?string $url): string
    {
        return preg_replace('/^www\./i', '', strtolower((string) parse_url((string) $url, PHP_URL_HOST)));
    }

    /** Cited pages that mention the company (by its first word); all of them if only one company could match. */
    private function citedFor(string $name, $cited)
    {
        $word = Str::lower(Str::before($name, ' '));

        return $cited->filter(fn ($a) => Str::contains(Str::lower($a['title'] . ' ' . $a['url']), $word))
            ->map(fn ($a) => ['title' => $a['title'], 'publisher' => $this->host($a['url']), 'url' => $a['url']])->values();
    }

    /** Repairs the source and notification links of one project as ChatGPT wrote it. */
    private function cleanProject(array $x): array
    {
        foreach (['source', 'notification'] as $f) {
            if (is_array($x[$f] ?? null) && isset($x[$f]['url'])) {
                $x[$f]['url'] = $this->cleanUrl($x[$f]['url']);
            }
        }

        return $x;
    }

    /** Search Projects' places to match: "Gajuwaka, Visakhapatnam, Andhra Pradesh, India" → Gajuwaka, Visakhapatnam (not the state or country). */
    private function places(string $location): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $location))));
        if (count($parts) > 1) {
            array_pop($parts); // country
        }
        if (count($parts) > 1) {
            array_pop($parts); // state
        }

        return array_values(array_filter(array_map(fn ($p) => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($p))), $parts)));
    }

    /** A project in the chosen location: ChatGPT says so, or its location (else the company's) names one of the places. */
    private function inPlace(array $x, array $c, array $places): bool
    {
        $said = strtolower(trim((string) ($x['in_location'] ?? '')));
        if ($said === 'no' || !$places) {
            return $said !== 'no';
        }
        if ($said === 'yes') {
            return true;
        }
        $text = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(($x['location'] ?? '') ?: ($c['location'] ?? ''))));

        return collect($places)->contains(fn ($p) => str_contains($text, $p));
    }

    /** A project's date as a range at the precision it was written (day, month or year): its own date, else its page's or notice's. */
    private function projectRange(array $x): ?array
    {
        foreach ([$x['date'] ?? '', $x['source']['published'] ?? '', $x['notification']['date'] ?? ''] as $d) {
            if (is_string($d) && ($r = $this->dateRange($d))) {
                return $r;
            }
        }

        return null;
    }

    private function dateRange(string $s): ?array
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if ($s === '' || !preg_match('/\b(20\d{2})\b/', $s, $year)) {
            return null;
        }
        try {
            if (preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m) && $m[2] >= 1 && $m[2] <= 12) {
                $d = \Carbon\Carbon::create((int) $m[1], (int) $m[2], 1);

                return [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()];
            }
            if (!preg_match('/^\d{4}(-\d{2})?$/', $s)) {
                $d = \Carbon\Carbon::parse($s);
                if ($d->year !== (int) $year[1]) {
                    throw new \RuntimeException('parsed into another year');
                }
                // a day number besides the year: that day; else the month
                return preg_match('/\b\d{1,2}(st|nd|rd|th)?\b/i', preg_replace('/\b\d{4}\b/', '', $s))
                    ? [$d->copy()->startOfDay(), $d->copy()->endOfDay()]
                    : [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()];
            }
        } catch (\Throwable $e) {
            // "Q2 2026", "2025-26", "Tender closes Nov 2026 …": the year
        }
        $d = \Carbon\Carbon::create((int) $year[1], 1, 1);

        return [$d->copy()->startOfYear(), $d->copy()->endOfYear()];
    }

    /** The company's newest project date (0 when none is dated). */
    private function newest(array $c): int
    {
        return (int) collect($c['projects'] ?? [])->map(fn ($x) => ($r = $this->projectRange($x)) ? $r[0]->timestamp : 0)->max();
    }

    /** 0 when the company has the chosen buying signal (in its signals or projects) or none was chosen, else 1. */
    private function hasSignal(array $c, ?string $signal): int
    {
        return $signal && !collect($c['signals'])->pluck('type')->merge(collect($c['projects'])->pluck('type'))
            ->contains(fn ($t) => strcasecmp($t, $signal) === 0) ? 1 : 0;
    }

    /** One project in the page's format: details, the page it came from and its official notification (or null). */
    private function project(array $x): array
    {
        return [
            'name' => Str::limit(trim((string) $x['name']), 200, ''),
            'type' => (string) ($x['type'] ?? ''),
            'icon' => self::ICONS[$x['type'] ?? ''] ?? '✦',
            'status' => (string) ($x['status'] ?? ''),
            'date' => (string) ($x['date'] ?? ''),
            'location' => (string) ($x['location'] ?? ''),
            'value' => (string) ($x['value'] ?? ''),
            'description' => (string) ($x['description'] ?? ''),
            'source' => [
                'title' => (string) ($x['source']['title'] ?? ''),
                'publisher' => (string) (($x['source']['publisher'] ?? '') ?: $this->host($x['source']['url'] ?? '')),
                'url' => $this->link($x['source']['url'] ?? null) ? (string) $x['source']['url'] : '',
                'published' => Str::limit(trim((string) ($x['source']['published'] ?? '')), 40, ''),
            ],
            'notification' => is_array($x['notification'] ?? null) && !empty($x['notification']['title']) ? [
                'title' => Str::limit(trim((string) $x['notification']['title']), 250, ''),
                'organization' => (string) ($x['notification']['organization'] ?? ''),
                'date' => (string) ($x['notification']['date'] ?? ''),
                'url' => $this->link($x['notification']['url'] ?? null) ? (string) $x['notification']['url'] : '',
            ] : null,
        ];
    }

    /** One company in the page's format (same fields as its sample data). */
    private function company($c, int $i, $cited): ?array
    {
        if (!is_array($c) || trim((string) ($c['name'] ?? '')) === '') {
            return null;
        }

        foreach (['sources', 'notifications', 'signals'] as $list) {
            foreach ((array) ($c[$list] ?? []) as $k => $x) {
                foreach (['url', 'source'] as $f) {
                    if (is_array($x) && isset($x[$f])) {
                        $c[$list][$k][$f] = $this->cleanUrl($x[$f]);
                    }
                }
            }
        }
        $c['projects'] = collect($c['projects'] ?? [])->filter(fn ($x) => is_array($x))->map(fn ($x) => $this->cleanProject($x))->all();
        $c['website'] = $this->cleanUrl($c['website'] ?? '');

        $name = Str::limit(trim($c['name']), 120, '');
        $intent = ucfirst(strtolower((string) ($c['intent'] ?? '')));

        return [
            'id' => Str::slug($name) . '-' . $i,
            'name' => $name,
            'short' => Str::limit(collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->implode(''), 4, ''),
            'industry' => (string) ($c['industry'] ?? ''),
            'size' => (string) ($c['size'] ?? 'Not known'),
            'location' => (string) ($c['location'] ?? ''),
            'intent' => in_array($intent, ['High', 'Medium', 'Low'], true) ? $intent : 'Medium',
            'description' => trim((string) ($c['description'] ?? '') . ($this->link($c['website'] ?? null) ? ' Website: ' . $c['website'] : '')),
            'signals' => collect($c['signals'] ?? [])->filter(fn ($s) => is_array($s) && !empty($s['text']))->take(6)
                ->map(fn ($s) => [
                    'type' => (string) ($s['type'] ?? 'New project'),
                    'icon' => self::ICONS[$s['type'] ?? ''] ?? '✦',
                    'date' => (string) ($s['date'] ?? ''),
                    'text' => trim($s['text'] . ($this->link($s['source'] ?? null) ? ' (Source: ' . $s['source'] . ')' : '')),
                ])->values(),
            'website' => (string) ($c['website'] ?? ''),
            'sources' => collect($c['sources'] ?? [])->filter(fn ($x) => is_array($x) && $this->link($x['url'] ?? null))
                ->whenEmpty(fn () => $this->citedFor($name, $cited))->take(10)
                ->map(fn ($x) => [
                    'title' => Str::limit(trim((string) ($x['title'] ?? '')) ?: $this->host($x['url']), 200, ''),
                    'publisher' => (string) ($x['publisher'] ?? $this->host($x['url'])),
                    'url' => (string) $x['url'],
                ])->unique('url')->values()->all(),
            'notifications' => collect($c['notifications'] ?? [])->filter(fn ($x) => is_array($x) && !empty($x['title']))->take(10)
                ->map(fn ($x) => [
                    'title' => Str::limit(trim((string) $x['title']), 250, ''),
                    'organization' => (string) ($x['organization'] ?? ''),
                    'date' => (string) ($x['date'] ?? ''),
                    'url' => $this->link($x['url'] ?? null) ? (string) $x['url'] : '',
                ])->values()->all(),
            'projects' => collect($c['projects'] ?? [])->filter(fn ($x) => is_array($x) && trim((string) ($x['name'] ?? '')) !== '')
                // none listed: the buying signals are the company's projects
                ->whenEmpty(fn () => collect($c['signals'] ?? [])->filter(fn ($x) => is_array($x) && !empty($x['text']))->map(fn ($x) => [
                    'name' => Str::limit($x['text'], 120), 'type' => $x['type'] ?? '', 'date' => $x['date'] ?? '',
                    'description' => $x['text'], 'source' => ['url' => $x['source'] ?? ''],
                ]))->take(8)->map(fn ($x) => $this->project($x))->values()->all(),
            'contacts' => collect($c['contacts'] ?? [])->filter(fn ($p) => is_array($p) && (!empty($p['name']) || !empty($p['role'])))->take(6)
                ->map(fn ($p) => [
                    'name' => (string) ($p['name'] ?? $p['role']),
                    'role' => (string) ($p['role'] ?? ''),
                    'email' => (string) (($p['email'] ?? '') ?: 'Not public'),
                    'phone' => (string) (($p['phone'] ?? '') ?: 'Not public'),
                    'note' => (string) ($p['note'] ?? ''),
                ])->values(),
        ];
    }

}
