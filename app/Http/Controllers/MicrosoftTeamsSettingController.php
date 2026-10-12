<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\MicrosoftTeams;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Settings → Microsoft Teams: app registration details and the work account that owns CRM Teams meetings.
 */
class MicrosoftTeamsSettingController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Microsoft Teams';
        $this->activeSettingMenu = 'microsoft_teams_settings';
        $this->middleware(function ($request, $next) {
            abort_403(!(user()->permission('manage_google_calendar_setting') == 'all'));

            return $next($request);
        });
    }

    public function index()
    {
        $this->teams = MicrosoftTeams::settings();
        $this->redirectUri = MicrosoftTeams::redirectUri();

        return view('microsoft-teams-settings.index', $this->data);
    }

    public function store(Request $request)
    {
        $settings = MicrosoftTeams::settings();

        $request->validate([
            'tenant_id' => ['required', 'string', 'max:100'],
            'client_id' => ['required', 'string', 'max:100'],
            // Kept as is when left empty (it is never shown again)
            'client_secret' => [$settings->client_secret ? 'nullable' : 'required', 'string', 'max:500'],
        ], [
            'tenant_id.required' => 'Enter the Directory (tenant) ID.',
            'client_id.required' => 'Enter the Application (client) ID.',
            'client_secret.required' => 'Enter the client secret value.',
        ]);

        $changed = $settings->tenant_id !== trim($request->tenant_id) || $settings->client_id !== trim($request->client_id);

        $settings->tenant_id = trim($request->tenant_id);
        $settings->client_id = trim($request->client_id);

        if ($request->filled('client_secret')) {
            $settings->client_secret = trim($request->client_secret);
            $changed = true;
        }

        $settings->save();

        // A different app registration: the old sign-in no longer applies
        if ($changed && $settings->connected()) {
            MicrosoftTeams::disconnect();
        }

        return Reply::success('Microsoft Teams settings saved. Click Connect to sign in with the Microsoft 365 work account.');
    }

    /**
     * "Connect" (redirect to Microsoft sign-in) and the sign-in callback (?code=...).
     */
    public function auth(Request $request)
    {
        if (!MicrosoftTeams::settings()->configured()) {
            return redirect()->route('microsoft-teams-settings.index');
        }

        if (!$request->has('code') && !$request->has('error')) {
            $state = Str::random(32);
            session(['microsoft_teams_oauth_state' => $state]);

            return redirect(MicrosoftTeams::authorizeUrl($state));
        }

        if ($request->has('error')) {
            return redirect()->route('microsoft-teams-settings.index')
                ->with('teams_error', $request->query('error_description') ?: $request->query('error'));
        }

        if (!hash_equals((string) session()->pull('microsoft_teams_oauth_state'), (string) $request->query('state'))) {
            return redirect()->route('microsoft-teams-settings.index')->with('teams_error', 'Sign-in expired. Click Connect again.');
        }

        try {
            MicrosoftTeams::connectWithCode($request->query('code'));
        } catch (\Throwable $e) {
            return redirect()->route('microsoft-teams-settings.index')->with('teams_error', $e->getMessage());
        }

        return redirect()->route('microsoft-teams-settings.index')->with('teams_success', 'Microsoft Teams connected.');
    }

    public function disconnect()
    {
        MicrosoftTeams::disconnect();

        return Reply::success('Microsoft Teams disconnected.');
    }
}
