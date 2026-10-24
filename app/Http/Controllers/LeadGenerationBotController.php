<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Leads > Lead Generation Bot (administrators only).
 *
 * Claude, with the server-side web search tool, looks for companies that are
 * running or buying projects in the selected category: tenders, RFPs, hiring,
 * expansion news. The API key stays on the server (.env: ANTHROPIC_API_KEY).
 */
class LeadGenerationBotController extends AccountBaseController
{

    public const CATEGORIES = [
        'GIS Projects' => 'GIS, geospatial, mapping, remote sensing, land/property survey and spatial data digitization',
        'AutoCAD Projects' => 'AutoCAD / CAD drafting, engineering drawings, architectural and MEP design documentation',
        'Web Development Projects' => 'website development, web application development, portal modernization',
        'Mobile App Development' => 'Android / iOS mobile app development and API integration',
        'Cloud & AWS Projects' => 'AWS / cloud migration, cloud architecture, DevOps',
        'Data Science & AI Projects' => 'AI, machine learning, data engineering and analytics',
    ];

    /** Company type dropdown, grouped; "Any company" sits above the groups. */
    public const COMPANY_TYPE_GROUPS = [
        'By size' => [
            'Startups',
            'Small businesses (SMEs)',
            'Mid-size companies',
            'Large enterprises',
            'Multinational corporations (MNCs)',
        ],
        'By ownership / sector' => [
            'Central government ministries and departments',
            'State government departments',
            'Municipal corporations and urban local bodies',
            'Development authorities',
            'Public sector undertakings (PSUs)',
            'Defence and armed forces',
            'Private limited companies',
            'Public listed companies',
            'Family-owned businesses',
            'Partnership and LLP firms',
            'NGOs and non-profits',
            'International organizations (World Bank, ADB, UN)',
            'Educational institutions and universities',
            'Research institutes',
        ],
        'By industry' => [
            'Engineering, procurement and construction (EPC)',
            'Infrastructure and real estate developers',
            'Architecture and design firms',
            'Engineering consultancies',
            'Survey and mapping agencies',
            'Utilities (power, water, gas)',
            'Telecom companies',
            'Transport, railways and metro',
            'Airports and ports',
            'Oil, gas and mining',
            'Manufacturing',
            'Automotive',
            'Agriculture and agritech',
            'Environment and forestry',
            'Healthcare and hospitals',
            'Pharmaceuticals and life sciences',
            'Banking, financial services and insurance (BFSI)',
            'Fintech',
            'Retail and e-commerce',
            'Logistics and supply chain',
            'Media and entertainment',
            'Hospitality and travel',
            'Education and edtech',
            'IT services and software companies',
            'SaaS product companies',
            'Smart city SPVs',
        ],
    ];

    public const COMPANY_TYPES = [
        'Any company',
        ...self::COMPANY_TYPE_GROUPS['By size'],
        ...self::COMPANY_TYPE_GROUPS['By ownership / sector'],
        ...self::COMPANY_TYPE_GROUPS['By industry'],
    ];

    /** Location suggestions shown before the countries table: Indian states / UTs, then major cities. */
    private const INDIA_STATES = [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
        'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
        'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana',
        'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
    ];

    private const INDIA_CITIES = [
        'Hyderabad' => 'Telangana', 'Warangal' => 'Telangana', 'Visakhapatnam' => 'Andhra Pradesh',
        'Vijayawada' => 'Andhra Pradesh', 'Guntur' => 'Andhra Pradesh', 'Tirupati' => 'Andhra Pradesh',
        'Amaravati' => 'Andhra Pradesh', 'Bengaluru' => 'Karnataka', 'Mysuru' => 'Karnataka', 'Mangaluru' => 'Karnataka',
        'Hubballi' => 'Karnataka', 'Chennai' => 'Tamil Nadu', 'Coimbatore' => 'Tamil Nadu', 'Madurai' => 'Tamil Nadu',
        'Tiruchirappalli' => 'Tamil Nadu', 'Kochi' => 'Kerala', 'Thiruvananthapuram' => 'Kerala', 'Kozhikode' => 'Kerala',
        'Mumbai' => 'Maharashtra', 'Pune' => 'Maharashtra', 'Nagpur' => 'Maharashtra', 'Nashik' => 'Maharashtra',
        'Aurangabad' => 'Maharashtra', 'Thane' => 'Maharashtra', 'New Delhi' => 'Delhi', 'Noida' => 'Uttar Pradesh',
        'Ghaziabad' => 'Uttar Pradesh', 'Lucknow' => 'Uttar Pradesh', 'Kanpur' => 'Uttar Pradesh', 'Varanasi' => 'Uttar Pradesh',
        'Agra' => 'Uttar Pradesh', 'Prayagraj' => 'Uttar Pradesh', 'Gurugram' => 'Haryana', 'Faridabad' => 'Haryana',
        'Ahmedabad' => 'Gujarat', 'Surat' => 'Gujarat', 'Vadodara' => 'Gujarat', 'Rajkot' => 'Gujarat',
        'Gandhinagar' => 'Gujarat', 'Jaipur' => 'Rajasthan', 'Jodhpur' => 'Rajasthan', 'Udaipur' => 'Rajasthan',
        'Kota' => 'Rajasthan', 'Bhopal' => 'Madhya Pradesh', 'Indore' => 'Madhya Pradesh', 'Jabalpur' => 'Madhya Pradesh',
        'Gwalior' => 'Madhya Pradesh', 'Raipur' => 'Chhattisgarh', 'Bilaspur' => 'Chhattisgarh', 'Kolkata' => 'West Bengal',
        'Durgapur' => 'West Bengal', 'Siliguri' => 'West Bengal', 'Bhubaneswar' => 'Odisha', 'Cuttack' => 'Odisha',
        'Patna' => 'Bihar', 'Ranchi' => 'Jharkhand', 'Jamshedpur' => 'Jharkhand', 'Guwahati' => 'Assam',
        'Dehradun' => 'Uttarakhand', 'Shimla' => 'Himachal Pradesh', 'Ludhiana' => 'Punjab', 'Amritsar' => 'Punjab',
        'Mohali' => 'Punjab', 'Srinagar' => 'Jammu and Kashmir', 'Jammu' => 'Jammu and Kashmir', 'Panaji' => 'Goa',
    ];

    private const SYSTEM_PROMPT = <<<TXT
You are a B2B lead-generation researcher for an IT and engineering services company.
Use web search to find REAL, currently active opportunities: tenders, RFPs, published projects,
hiring for project roles, funding or expansion news, or other buying signals that show an
organization needs outside help with the requested kind of project. Prefer sources from the last
12 months. Never invent companies, people, URLs or contact details; only report what the search
results support. If you find fewer good leads than requested, return fewer.
TXT;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Lead Generation Bot';
    }

    public function index()
    {
        abort_403(!in_array('admin', user_roles()));

        $this->categories = array_keys(self::CATEGORIES);
        $this->companyTypeGroups = self::COMPANY_TYPE_GROUPS;
        $this->locations = $this->locations();

        return view('lead-generation-bot.index', $this->data);
    }

    public function search(Request $request)
    {
        abort_403(!in_array('admin', user_roles()));

        $request->validate([
            'category' => ['required', 'in:' . implode(',', array_keys(self::CATEGORIES))],
            'company' => ['nullable', 'string', 'max:120'],
            'location' => ['required_without:company', 'nullable', 'string', 'max:80'],
            'companyType' => ['nullable', \Illuminate\Validation\Rule::in(self::COMPANY_TYPES)],
            'exclude' => ['nullable', 'array', 'max:50'],
            'exclude.*' => ['string', 'max:191'],
        ], [
            'location.required_without' => 'Enter a target location or a company name.',
        ]);

        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            return response()->json(['error' => 'ANTHROPIC_API_KEY is not set in .env.'], 500);
        }

        $category = $request->category;
        $scope = self::CATEGORIES[$category];
        $location = trim((string) $request->location) ?: 'Worldwide';
        $companyType = $request->companyType ?: 'Any company';
        $company = trim((string) $request->company);
        $exclude = array_filter((array) $request->exclude);
        $excludeLine = $exclude ? "\nSkip these companies, already found: " . implode('; ', $exclude) . '.' : '';

        // A named company: research that one organization (and its group companies) anywhere in the
        // world, instead of finding new companies in a location.
        $task = $company !== ''
            ? <<<TXT
Research this specific company: {$company}
Find up to 8 of its current or upcoming projects, tenders, RFPs, hiring or other buying signals for:
{$category} ({$scope}). Search worldwide; its subsidiaries and group companies count.
Location preference: {$location}
TXT
            : <<<TXT
Find up to 8 target companies/organizations for: {$category} ({$scope}).
Location: {$location}
Company type: {$companyType}{$excludeLine}
TXT;

        $prompt = <<<TXT
{$task}

When you are done researching, reply with ONLY a JSON object (no prose, no code fences) shaped like:
{"companies":[{"company":"","domain":"","title":"","snippet":"","url":"","signal":"","contact":""}]}
- company: organization name
- domain: its website domain, or "" if unknown
- title: short title of the project/opportunity
- snippet: 1-2 sentences on what they need and why they are a fit
- url: the source page for the opportunity
- signal: buying signal type, e.g. "Tender", "RFP", "Hiring", "Expansion", "Funding", "Project announcement"
- contact: a department, role or public contact point named in the source, or ""
TXT;

        $messages = [['role' => 'user', 'content' => $prompt]];
        set_time_limit(330);

        // Web search can pause a long turn (stop_reason "pause_turn"); resend to let it continue.
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $response = Http::timeout(300)
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                        'anthropic-beta' => 'server-side-fallback-2026-07-01',
                    ])
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => config('services.anthropic.model'),
                        'max_tokens' => 16000,
                        'system' => self::SYSTEM_PROMPT,
                        'messages' => $messages,
                        'output_config' => ['effort' => 'medium'],
                        'tools' => [[
                            'type' => 'web_search_20260209',
                            'name' => 'web_search',
                            'max_uses' => 8,
                        ]],
                        'fallbacks' => 'default',
                    ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return response()->json(['error' => 'Could not reach the Claude API: ' . $e->getMessage()], 502);
            }

            if (!$response->successful()) {
                return response()->json(['error' => 'Claude API error: ' . $response->json('error.message', 'HTTP ' . $response->status())], 502);
            }

            $result = $response->json();

            if (($result['stop_reason'] ?? '') !== 'pause_turn') {
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $result['content']];
        }

        if (($result['stop_reason'] ?? '') === 'refusal') {
            return response()->json(['error' => 'Claude declined this search. Try a different category or location.'], 502);
        }

        $companies = $this->companies($result['content'] ?? []);

        if ($companies === null) {
            return response()->json(['error' => 'Claude returned an answer that could not be read. Please try again.'], 502);
        }

        return response()->json(['companies' => $companies]);
    }

    /**
     * Company name box: AI suggestions of real companies worldwide that match what was typed.
     * A fast model without web search keeps this quick; results are cached per query for a day.
     */
    public function suggestCompanies(Request $request)
    {
        abort_403(!in_array('admin', user_roles()));

        $q = trim(preg_replace('/\s+/', ' ', (string) $request->q));

        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            return response()->json(['companies' => []]);
        }

        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            return response()->json(['error' => 'ANTHROPIC_API_KEY is not set in .env.'], 500);
        }

        $apiError = null;
        $companies = cache()->remember('lead-bot-companies:' . md5(mb_strtolower($q)), now()->addDay(), function () use ($q, $apiKey, &$apiError) {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.suggest_model'),
                    'max_tokens' => 1024,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "List up to 8 real, existing companies or organizations anywhere in the world whose name "
                            . "starts with or closely matches \"{$q}\". Best-known first. Only include organizations you are "
                            . "confident exist; return an empty list if none.",
                    ]],
                    'output_config' => ['format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['companies' => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'country' => ['type' => 'string', 'description' => 'Headquarters country'],
                                    'industry' => ['type' => 'string', 'description' => '2-4 words'],
                                ],
                                'required' => ['name', 'country', 'industry'],
                                'additionalProperties' => false,
                            ]]],
                            'required' => ['companies'],
                            'additionalProperties' => false,
                        ],
                    ]],
                ]);

            if (!$response->successful()) {
                $apiError = $response->json('error.message');

                return null;
            }

            $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');

            return array_slice((array) (json_decode($text, true)['companies'] ?? []), 0, 8);
        });

        if ($companies === null) {
            cache()->forget('lead-bot-companies:' . md5(mb_strtolower($q)));

            return response()->json(['error' => 'Company suggestions are unavailable: ' . ($apiError ?: 'Claude API error.')], 502);
        }

        return response()->json(['companies' => $companies]);
    }

    /**
     * Suggestions for the Target location box: India first, then its states,
     * major cities, and every other country from the countries table.
     */
    private function locations(): array
    {
        $locations = [['name' => 'India', 'type' => 'Country']];

        foreach (self::INDIA_STATES as $state) {
            $locations[] = ['name' => $state . ', India', 'type' => 'State'];
        }

        foreach (self::INDIA_CITIES as $city => $state) {
            $locations[] = ['name' => $city . ', ' . $state, 'type' => 'City'];
        }

        $countries = \Illuminate\Support\Facades\DB::table('countries')->where('nicename', '!=', 'India')
            ->orderBy('nicename')->pluck('nicename');

        foreach ($countries as $country) {
            $locations[] = ['name' => $country, 'type' => 'Country'];
        }

        return $locations;
    }

    /**
     * The JSON answer is the text written after the last search result.
     * Null when that text holds no readable answer.
     */
    private function companies(array $content): ?array
    {
        $text = '';

        foreach ($content as $block) {
            if ($block['type'] === 'web_search_tool_result') {
                $text = '';
            } elseif ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $data = ($start !== false && $end !== false) ? json_decode(substr($text, $start, $end - $start + 1), true) : null;

        if (!is_array($data) || !is_array($data['companies'] ?? null)) {
            return null;
        }

        $companies = [];

        foreach ($data['companies'] as $c) {
            $url = (string) ($c['url'] ?? '');

            if (!is_array($c) || empty($c['company']) || !preg_match('#^https?://#i', $url)) {
                continue;
            }

            $companies[] = [
                'company' => (string) $c['company'],
                'domain' => (string) ($c['domain'] ?? ''),
                'title' => (string) ($c['title'] ?? ''),
                'snippet' => (string) ($c['snippet'] ?? ''),
                'url' => $url,
                'signal' => (string) ($c['signal'] ?? ''),
                'contact' => (string) ($c['contact'] ?? ''),
            ];
        }

        return $companies;
    }

}
