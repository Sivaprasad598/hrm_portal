<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\IappSetting;
use App\Services\Iapp\DeviceEndpoint;
use App\Services\Iapp\DeviceTransEndpoint;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use Illuminate\Http\Request;

/**
 * Connection and sync settings for the biometric integration.
 *
 * Credentials live in the database rather than .env so they can be changed
 * per company without a deploy; `client_secret` and `password` are encrypted
 * at rest by the model. These settings are the only source the sync reads;
 * .env is not consulted, so the screen and the sync can never disagree.
 *
 * Nothing here writes to the biometric platform — the Test Connection action
 * only authenticates and reads.
 */
class BiometricSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.biometricSettings';
        $this->activeSettingMenu = 'biometric_settings';

        $this->middleware(function ($request, $next) {
            // This is the attendance devices' own configuration, not the
            // company's. Gating it on manage_company_setting meant whoever ran
            // the devices needed every company setting with it — HR and the
            // Admin could work the biometric screens but not reach the page
            // that connects them. manage_attendance_setting is the permission
            // that matches what this page is.
            abort_403(user()->permission('manage_attendance_setting') !== 'all');
            // The device connection, the nightly run and the automation: technical.
            abort_403(user()->permission('manage_biometric_system') !== 'all');

            return $next($request);
        });
    }

    public function index()
    {
        $this->setting = IappSetting::firstOrNew(['company_id' => company()->id]);

        // What the module would use right now, settings row or .env fallback.
        $config = new IappConfig($this->setting->exists ? $this->setting : null);

        $this->effective = [
            'base_url' => $config->baseUrl(),
            'auth_method' => $config->authMethod(),
            'username' => $config->username(),
            'company_code' => $config->companyCode(),
            'location_code' => $config->locationCode(),
            'verify_ssl' => $config->verifySsl(),
            'token_cache_minutes' => $config->tokenCacheMinutes(),
            'match_field' => $config->matchField(),
            'configured' => $config->isConfigured(),
            'from_env' => !$this->setting->exists,
        ];

        $this->matchFields = IappSetting::MATCH_FIELDS;
        $this->deviceOptions = BiometricController::deviceOptions()->all();
        $this->authMethods = IappSetting::AUTH_METHODS;

        return view('biometric-settings.index', $this->data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'base_url' => 'required|url|max:255',
            'auth_method' => 'required|in:user,client_secret',
            'username' => 'required_if:auth_method,user|nullable|max:100',
            'password' => 'nullable|max:191',
            'client_secret' => 'nullable|max:191',
            'company_code' => 'nullable|max:10',
            'location_code' => 'nullable|max:5',
            'employee_match_field' => 'required|in:' . implode(',', array_keys(IappSetting::MATCH_FIELDS)),
            'resync_days' => 'required|integer|min:0|max:60',
            'device_scope' => 'required|in:all,selected',
            'pull_devices' => 'nullable|array',
            'pull_devices.*' => 'string|max:30',
            'register_locations' => 'nullable|string|max:191',
            'pull_time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            'build_time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            'reconcile_time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            'notify_time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            // Held below the server's real 20-minute token lifetime.
            'token_cache_minutes' => 'required|integer|min:1|max:19',
        ]);

        $devices = array_values(array_intersect(
            array_map('strval', (array) $request->pull_devices),
            array_map('strval', BiometricController::deviceOptions()->keys()->all())
        ));

        if ($request->device_scope === IappSetting::SCOPE_SELECTED && $devices === []) {
            return Reply::error('Tick at least one device to pull from, or choose All devices.');
        }

        // Each step needs the one before it to have finished.
        if (!($request->pull_time < $request->build_time && $request->build_time < $request->reconcile_time)) {
            return Reply::error('The times must run in order: pull, then build, then write & cross-check.');
        }

        $setting = IappSetting::firstOrNew(['company_id' => company()->id]);

        $setting->fill([
            'company_id' => company()->id,
            'base_url' => rtrim($request->base_url, '/'),
            'auth_method' => $request->auth_method,
            'username' => $request->username,
            'company_code' => $request->company_code,
            'location_code' => $request->location_code,
            'employee_match_field' => $request->employee_match_field,
            'resync_days' => (int) $request->resync_days,
            'device_scope' => $request->device_scope,
            'pull_devices' => $devices,
            'only_known_employees' => $request->boolean('only_known_employees'),
            'register_locations' => array_values(array_filter(array_map('trim', explode(',', (string) $request->register_locations)))),
            'pull_time' => $request->pull_time,
            'build_time' => $request->build_time,
            'reconcile_time' => $request->reconcile_time,
            'notify_time' => $request->notify_time,
            'token_cache_minutes' => (int) $request->token_cache_minutes,
            'verify_ssl' => $request->boolean('verify_ssl'),
            'status' => $request->boolean('status'),
            'sync_enabled' => $request->boolean('sync_enabled'),
            'auto_promote' => $request->boolean('auto_promote'),
            'auto_generate_payslips' => $request->boolean('auto_generate_payslips'),
            'notify_employees' => $request->boolean('notify_employees'),
        ]);

        // Secrets are only overwritten when a new value is actually typed, so
        // saving the form without re-entering them does not wipe them.
        if (filled($request->password)) {
            $setting->password = $request->password;
        }

        if (filled($request->client_secret)) {
            $setting->client_secret = $request->client_secret;
        }

        $setting->save();

        return Reply::success(__('messages.updateSuccess'));
    }

    /**
     * Authenticate and read, reporting what each step returned.
     *
     * Uses whatever is currently saved rather than the unsaved form, so the
     * result reflects what the scheduled sync would actually do.
     */
    public function testConnection()
    {
        $config = new IappConfig();

        if (!$config->isConfigured()) {
            return Reply::error('Connection is not configured yet. Save the settings first.');
        }

        // Everything below talks to the device, and a call can sit for 90
        // seconds before timing out, three times over. PHP holds an exclusive
        // lock on the session file for the whole of a request, so while this
        // one waits, every other request from the same browser waits with it —
        // which is why pressing Save during a test looked like Save was dead.
        // Writing the session now releases that lock; nothing below writes to
        // it again.
        session()->save();

        $client = new IappClient($config);
        $steps = [];

        $started = microtime(true);
        $token = $client->token(true);
        $steps[] = [
            'step' => 'Authentication',
            'ok' => (bool) $token,
            'detail' => $token
                ? sprintf('Token received in %d ms', round((microtime(true) - $started) * 1000))
                : 'No token returned — check the URL and credentials.',
        ];

        if (!$token) {
            return Reply::dataOnly(['status' => 'fail', 'steps' => $steps]);
        }

        // Devices: needs a company code, so it also validates that setting.
        $companyCode = $config->companyCode();

        if ($companyCode) {
            $response = (new DeviceEndpoint($client))->forLocation($companyCode, $config->locationCode() ?: '01');
            $steps[] = [
                'step' => 'Device list',
                'ok' => $response->successful(),
                'detail' => $response->successful()
                    ? count($response->rows()) . ' gate record(s) returned'
                    : ($response->message() ?: 'No response'),
            ];
        } else {
            $steps[] = ['step' => 'Device list', 'ok' => false, 'detail' => 'No company code configured.'];
        }

        $punches = (new DeviceTransEndpoint($client))->after(now()->startOfDay());
        $steps[] = [
            'step' => 'Punch feed',
            'ok' => $punches->successful() || $punches->isEmptyResult(),
            'detail' => $punches->successful()
                ? count($punches->rows()) . ' punch(es) returned for today'
                : ($punches->isEmptyResult() ? 'Reachable — no punches yet today' : $punches->message()),
        ];

        $failed = array_filter($steps, fn ($step) => !$step['ok']);

        return Reply::dataOnly([
            'status' => $failed === [] ? 'success' : 'partial',
            'steps' => $steps,
        ]);
    }

}
