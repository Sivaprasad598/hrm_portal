<?php

namespace App\Services\Iapp;

use App\Models\IappSetting;

/**
 * Resolves iApp connection settings.
 *
 * The settings screen is the only source: everything comes from the settings
 * row, so what an administrator sees and saves is exactly what the sync uses.
 * .env is deliberately not consulted — a value there could silently override
 * the screen and leave the two disagreeing, which is what happened when the
 * endpoint on the screen and the endpoint being called were different.
 */
class IappConfig
{

    protected ?IappSetting $setting = null;

    public function __construct(?IappSetting $setting = null)
    {
        $this->setting = $setting ?? $this->loadSetting();
    }

    protected function loadSetting(): ?IappSetting
    {
        // Missing table (migrations not yet run) must not be fatal — fall back
        // to config instead.
        try {
            return IappSetting::query()->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function setting(): ?IappSetting
    {
        return $this->setting;
    }

    protected function value(string $key, $default = null)
    {
        $fromSetting = $this->setting?->{$key};

        return ($fromSetting !== null && $fromSetting !== '') ? $fromSetting : $default;
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->value('base_url'), '/');
    }

    /**
     * Whether to verify the server's TLS certificate.
     *
     * The deployment is reached by IP address, which cannot hold a publicly
     * trusted certificate, so the server presents a self-signed one and
     * verification has to be disabled for it. Defaults to verifying, so a
     * properly certificated host is never downgraded silently.
     */
    public function verifySsl(): bool
    {
        $value = $this->value('verify_ssl', true);

        return !in_array(
            is_string($value) ? strtolower(trim($value)) : $value,
            ['false', '0', 0, false, 'no', 'off'],
            true
        );
    }

    public function authMethod(): string
    {
        return $this->value('auth_method', IappSetting::AUTH_USER) === IappSetting::AUTH_CLIENT
            ? IappSetting::AUTH_CLIENT
            : IappSetting::AUTH_USER;
    }

    public function usesClientSecret(): bool
    {
        return $this->authMethod() === IappSetting::AUTH_CLIENT;
    }

    public function username(): ?string
    {
        return $this->value('username');
    }

    public function password(): ?string
    {
        return $this->value('password');
    }

    public function clientSecret(): ?string
    {
        return $this->value('client_secret');
    }

    public function companyCode(): ?string
    {
        return $this->value('company_code');
    }

    public function locationCode(): ?string
    {
        return $this->value('location_code');
    }

    /**
     * Held below the server's real token lifetime, measured at 20 minutes.
     */
    public function tokenCacheMinutes(): int
    {
        return max(1, min(19, (int) $this->value('token_cache_minutes', 15)));
    }

    /**
     * Company the synced data belongs to.
     *
     * Falls back to the session company, then to the first company on record.
     * The global company() helper returns false rather than null when there is
     * no session (a scheduled command, for instance), so its result is checked
     * for an object rather than for null.
     */
    public function companyId(): ?int
    {
        if ($this->setting?->company_id) {
            return (int) $this->setting->company_id;
        }

        if (function_exists('company')) {
            $company = company();

            if (is_object($company) && !empty($company->id)) {
                return (int) $company->id;
            }
        }

        try {
            $id = \Illuminate\Support\Facades\DB::table('companies')->orderBy('id')->value('id');

            return $id ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Which employee_details column iApp's employee code maps to. Defaults to
     * employee_card_id — the codes observed on this server (ACPL0078) are card
     * IDs, and the Excel punch import already matches on that column.
     */
    public function matchField(): string
    {
        $field = $this->setting?->matchField();

        return in_array($field, array_keys(IappSetting::MATCH_FIELDS), true)
            ? $field
            : 'none';
    }

    public function isConfigured(): bool
    {
        if ($this->baseUrl() === '') {
            return false;
        }

        return $this->usesClientSecret()
            ? !empty($this->clientSecret())
            : (!empty($this->username()) && !empty($this->password()));
    }

    /**
     * The controller codes to pull, or null for every device.
     *
     * "Selected" with nothing ticked is treated as nothing to pull rather than
     * everything, so unticking the last device never quietly widens the pull.
     *
     * @return string[]|null
     */
    public function pullDevices(): ?array
    {
        $setting = $this->setting();

        if (!$setting || ($setting->device_scope ?? 'all') !== \App\Models\IappSetting::SCOPE_SELECTED) {
            return null;
        }

        return array_values(array_filter(array_map('strval', (array) ($setting->pull_devices ?? []))));
    }

    /** Cache key is per base URL so several environments never share a token. */
    public function tokenCacheKey(): string
    {
        return 'iapp_token_' . md5($this->baseUrl() . '|' . ($this->username() ?? 'client'));
    }

}
