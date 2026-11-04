<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lead Finder API.
 *
 *   GET /api/lead-finder/projects/search      the Search Projects search (Location, Research project, Project / signal
 *                                             date) as JSON; "Authorization: Bearer <key>", or no key from this
 *                                             computer (services.lead_finder_api.open_ips)
 *   GET /api/lead-finder/companies/search     the Search Companies search (q = company / project, location,
 *                                             project_type = project type or software, date); same access
 *
 * Keys: one per administrator, made on the finder's "API access" page (apiKey / createApiKey / revokeApiKey, behind
 * the portal login). The search itself is TargetCompanyFinderController::search (ChatGPT with web search), run as
 * the key's user.
 */
class LeadFinderApiController extends Controller
{

    /** Search Projects: location, project (research project), date (period) → the companies and their projects. */
    public function searchProjects(Request $request)
    {
        $user = $this->keyUser($request);
        $this->tidy($request, ['location', 'project']);
        $request->validate($this->rules(['location' => 'nullable|string|max:200', 'project' => 'nullable|string|max:191']));

        return $this->run($request, $user, ['location' => $request->location, 'project' => $request->project], [
            'location' => $request->location ?: 'India',
            'project' => $request->project ?: 'any',
        ], true);
    }

    /**
     * Search Companies (Project Finder): q (company or project name / keyword), location, project_type (project type
     * or software), date → construction and engineering companies and their matching projects.
     */
    public function searchCompanies(Request $request)
    {
        $user = $this->keyUser($request);
        $this->tidy($request, ['q', 'location', 'project_type']);
        $request->validate($this->rules([
            'q' => 'nullable|string|max:120',
            'location' => 'nullable|string|max:200',
            'project_type' => 'nullable|string|max:100',
        ]));

        return $this->run($request, $user, [
            'mode' => 'companies',
            'q' => $request->q,
            'location' => $request->location,
            'project_type' => $request->project_type,
        ], [
            'q' => $request->q ?: 'any',
            'location' => $request->location ?: 'all locations',
            'project_type' => $request->project_type ?: 'all project types',
        ]);
    }

    /** Stray spaces around names and values ("?location =Visakhapatnam &date =this_month"; PHP makes "location " "location_"). */
    private function tidy(Request $request, array $fields): void
    {
        foreach ($request->query() as $name => $value) {
            $clean = str_replace(' ', '_', trim(str_replace('_', ' ', (string) $name)));
            if ($clean !== $name && !$request->query->has($clean)) {
                $request->query->set($clean, $value);
                $request->query->remove($name);
            }
        }
        foreach (array_merge($fields, ['date', 'date_from', 'date_to']) as $name) {
            if (is_string($request->query($name))) {
                $request->query->set($name, trim($request->query($name)));
            }
        }
        $request->merge($request->query->all());
    }

    /** The search's own fields plus the date fields. */
    private function rules(array $fields): array
    {
        return $fields + [
            'date' => ['nullable', 'string', 'max:40', function ($attribute, $value, $fail) {
                if (!in_array(strtolower(str_replace([' ', '-'], '_', $value)), ['latest', 'all', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year'], true)
                    && !$this->parseDate($value)) {
                    $fail('date must be latest, all, this_week, last_week, this_month, last_month, this_year, a month (July 2026, Jul 2026, 07-2026, 2026-07), a year (2026) or a day (12-30-2026, 30 Dec 2026, 2026-12-30).');
                }
            }],
            'date_from' => 'nullable|date|required_with:date_to',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];
    }

    /** Runs the finder's search (TargetCompanyFinderController::search) as the key's user and answers with JSON. */
    private function run(Request $request, User $user, array $params, array $query, bool $flat = false)
    {
        [$label, $from, $to] = $this->period($request, $user);

        auth()->setUser($user);
        session(['user' => $user, 'user_roles' => $user->roles->pluck('name')->all()]);

        $search = Request::create('/', 'GET', array_filter($params + [
            'date_label' => $label,
            'date_from' => $from?->format('Y-m-d'),
            'date_to' => $to?->format('Y-m-d'),
        ], fn ($v) => $v !== null && $v !== ''));

        $response = app(TargetCompanyFinderController::class)->search($search);
        $data = $response->getData(true);

        if ($response->getStatusCode() !== 200) {
            return response()->json(['status' => 'fail', 'message' => $data['message'] ?? 'The search failed. Please try again.'], $response->getStatusCode());
        }

        $companies = $data['companies'] ?? [];

        return response()->json(array_filter([
            'status' => 'success',
            'query' => $query + ['date' => ['label' => $label, 'from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')]],
            'count' => count($companies),
            'projects_count' => collect($companies)->sum(fn ($c) => count($c['projects'] ?? [])),
            // Search Projects: one entry per project, newest first, with the company that released it (as the page's cards)
            'projects' => $flat ? $this->projectList($companies) : null,
            'companies' => $companies,
        ], fn ($v) => $v !== null));
    }

    /** All projects of the results, newest first, each with "released_by" (the company without its project list). */
    private function projectList(array $companies): array
    {
        return collect($companies)->flatMap(fn ($c) => collect($c['projects'] ?? [])->map(fn ($p) => $p + [
            'released_by' => collect($c)->only(['id', 'name', 'industry', 'size', 'location', 'website', 'intent', 'description'])->all(),
        ]))->sortByDesc(fn ($p) => $this->when($p))->values()->all();
    }

    /** A project's newest date (published, project or notification date), as a timestamp; 0 when it has none. */
    private function when(array $p): int
    {
        return collect([$p['source']['published'] ?? '', $p['date'] ?? '', $p['notification']['date'] ?? ''])->map(function ($d) {
            $d = str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}"], '-', (string) $d); // ChatGPT's typographic hyphens
            if (!preg_match('/\d{1,2}\s+[a-z]{3,9},?\s+\d{4}|[a-z]{3,9}\.?\s+\d{1,2},?\s+\d{4}|\d{4}-\d{2}-\d{2}|[a-z]{3,9}\.?\s+\d{4}|\b(?:19|20)\d{2}\b/i', (string) $d, $m)) {
                return 0;
            }

            return strtotime(str_replace(',', '', preg_match('/^\d{4}$/', $m[0]) ? $m[0] . '-01-01' : $m[0])) ?: 0;
        })->max() ?: 0;
    }

    /**
     * A day, month or year written in common ways: [label, from, to], or null.
     *   day:   12-30-2026, 12/30/2026 (month first), 30-12-2026 when the first number is over 12, 2026-12-30, 30 Dec 2026,
     *          December 30, 2026
     *   month: July 2026, Jul 2026, 07-2026, 07/2026, 2026-07
     *   year:  2026
     */
    private function parseDate(string $value): ?array
    {
        $v = trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $value)));
        $day = fn (Carbon $d) => [$d->format('d M Y'), $d->copy()->startOfDay(), $d->copy()->startOfDay()];
        $month = fn (Carbon $d) => [$d->format('F Y'), $d->copy()->startOfMonth()->startOfDay(), $d->copy()->endOfMonth()->startOfDay()];

        try {
            if (preg_match('/^(19|20)\d{2}$/', $v)) {
                $d = Carbon::create((int) $v, 1, 1);

                return [$v, $d->copy(), $d->copy()->endOfYear()->startOfDay()];
            }
            if (preg_match('/^(\d{4})[-\/.](\d{1,2})$/', $v, $m) || (preg_match('/^(\d{1,2})[-\/.](\d{4})$/', $v, $n) && $m = [0, $n[2], $n[1]])) {
                return checkdate((int) $m[2], 1, (int) $m[1]) ? $month(Carbon::create((int) $m[1], (int) $m[2], 1)) : null;
            }
            if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $v, $m)) {
                return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $day(Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])) : null;
            }
            if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $v, $m)) {
                // month first (12-30-2026); day first only when the first number cannot be a month (30-12-2026)
                [$mo, $d] = (int) $m[1] > 12 ? [(int) $m[2], (int) $m[1]] : [(int) $m[1], (int) $m[2]];

                return checkdate($mo, $d, (int) $m[3]) ? $day(Carbon::create((int) $m[3], $mo, $d)) : null;
            }
            if (preg_match('/^[a-z]{3,9} \d{4}$/i', $v)) {
                $d = Carbon::createFromFormat('!F Y', ucfirst(strtolower($v))) ?: null;
                $d = $d ?: Carbon::createFromFormat('!M Y', ucfirst(strtolower($v)));

                return $month($d);
            }
            if (preg_match('/^(\d{1,2} [a-z]{3,9} \d{4}|[a-z]{3,9} \d{1,2} \d{4})$/i', $v)) {
                return $day(Carbon::parse($v));
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /** The date filter as on the page: a named period, a month (YYYY-MM) or date_from / date_to. */
    private function period(Request $request, User $user): array
    {
        $today = Carbon::now($user->company?->timezone ?? config('app.timezone'))->startOfDay();

        if ($request->filled('date_from')) {
            $from = Carbon::parse($request->date_from);
            $to = Carbon::parse($request->date_to ?: $request->date_from);

            return [$from->format('d M Y') . ' to ' . $to->format('d M Y'), $from, $to];
        }

        $date = strtolower(str_replace([' ', '-'], '_', $request->input('date', 'latest')));

        if ($parsed = $this->parseDate($request->input('date', ''))) {
            return $parsed;
        }

        return match ($date) {
            'this_week' => ['This week', $today->copy()->startOfWeek(Carbon::MONDAY), $today],
            'last_week' => ['Last week', $today->copy()->startOfWeek(Carbon::MONDAY)->subWeek(), $today->copy()->startOfWeek(Carbon::MONDAY)->subDay()],
            'this_month' => ['This month', $today->copy()->startOfMonth(), $today],
            'last_month' => ['Last month', $today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'this_year' => ['This year', $today->copy()->startOfYear(), $today],
            'all' => ['All dates', null, null],
            default => ['Latest first', null, null],
        };
    }

    /**
     * The administrator the Bearer key belongs to (401 / 403 as JSON otherwise). Without a key, calls from the
     * addresses in services.lead_finder_api.open_ips (default: this computer) run as the first active administrator.
     */
    private function keyUser(Request $request): User
    {
        $token = (string) $request->bearerToken();

        if ($token === '' && in_array($request->ip(), config('services.lead_finder_api.open_ips', []), true)) {
            $admin = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->orderBy('id')->first();

            if ($admin) {
                return $admin;
            }
        }

        $row = $token !== '' ? DB::table('lead_finder_api_keys')->where('key_hash', hash('sha256', $token))->first() : null;
        $user = $row ? User::find($row->user_id) : null;

        if (!$user) {
            throw new HttpResponseException(response()->json([
                'status' => 'fail',
                'message' => 'Invalid or missing API key. Send "Authorization: Bearer <key>"; create a key in Lead Generation Bot → API access.',
            ], 401));
        }

        if (!$user->roles->pluck('name')->contains('admin') || $user->status !== 'active') {
            throw new HttpResponseException(response()->json(['status' => 'fail', 'message' => 'This key\'s user is not an active administrator.'], 403));
        }

        DB::table('lead_finder_api_keys')->where('id', $row->id)->update(['last_used_at' => now()]);

        return $user;
    }

    /* ---------- the "API access" page (portal login, administrators) ---------- */

    /** Whether the user has a key (only its last 4 characters), when it was last used, and the API's address. */
    public function apiKey()
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        return response()->json($this->keyInfo());
    }

    /** A new key (replaces the old one). The key itself is shown only in this response. */
    public function createApiKey()
    {
        abort_403(!in_array('admin', user_roles() ?? []));

        $key = 'lf_' . Str::random(40);

        DB::table('lead_finder_api_keys')->updateOrInsert(
            ['user_id' => user()->id],
            ['company_id' => company()?->id, 'key_hash' => hash('sha256', $key), 'last_four' => substr($key, -4), 'last_used_at' => null, 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['key' => $key] + $this->keyInfo());
    }

    /** Removes the user's key (calls with it stop working). */
    public function revokeApiKey()
    {
        abort_403(!in_array('admin', user_roles() ?? []));
        DB::table('lead_finder_api_keys')->where('user_id', user()->id)->delete();

        return response()->json($this->keyInfo());
    }

    private function keyInfo(): array
    {
        $row = DB::table('lead_finder_api_keys')->where('user_id', user()->id)->first();
        $tz = company()->timezone ?? config('app.timezone');

        return [
            'endpoint' => url('api/lead-finder/projects/search'),
            'has_key' => (bool) $row,
            'last_four' => $row->last_four ?? null,
            'created_at' => $row ? Carbon::parse($row->created_at)->timezone($tz)->format('d M Y, h:i A') : null,
            'last_used_at' => $row && $row->last_used_at ? Carbon::parse($row->last_used_at)->timezone($tz)->format('d M Y, h:i A') : null,
        ];
    }

}
