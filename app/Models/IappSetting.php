<?php

namespace App\Models;

use App\Traits\HasCompany;

/**
 * Connection and sync configuration for the iApp biometric integration.
 *
 * Credentials live here rather than in .env so they can be changed per
 * company from the settings screen without a deploy. This row is the only
 * source the integration reads — see IappConfig.
 */
class IappSetting extends BaseModel
{

    use HasCompany;

    protected $table = 'iapp_settings';

    protected $guarded = ['id'];

    protected $casts = [
        // Never stored in plaintext; Laravel decrypts transparently on read.
        'client_secret' => 'encrypted',
        'password' => 'encrypted',
        'status' => 'boolean',
        'verify_ssl' => 'boolean',
        'sync_enabled' => 'boolean',
        'auto_promote' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = ['client_secret', 'password'];

    /** Employee identity columns iApp's empId may map to. */
    public const MATCH_FIELDS = [
        'employee_card_id' => 'Employee Card ID',
        'employee_id' => 'Employee ID',
    ];

    /**
     * Which authentication endpoint to use.
     *
     * The live server at this site is provisioned for user credentials;
     * client-secret auth is kept for deployments that use it.
     */
    public const AUTH_USER = 'user';
    public const AUTH_CLIENT = 'client_secret';

    public const AUTH_METHODS = [
        self::AUTH_USER => 'Username & Password',
        self::AUTH_CLIENT => 'Client Secret',
    ];

    public function usesClientSecret(): bool
    {
        return $this->auth_method === self::AUTH_CLIENT;
    }

    public function isConfigured(): bool
    {
        if (empty($this->base_url)) {
            return false;
        }

        return $this->usesClientSecret()
            ? !empty($this->client_secret)
            : (!empty($this->username) && !empty($this->password));
    }

    public function isReadyToSync(): bool
    {
        return $this->status && $this->sync_enabled && $this->isConfigured();
    }

    /**
     * Guards against an unexpected value reaching a query as a column name.
     */
    public function matchField(): string
    {
        return array_key_exists((string) $this->employee_match_field, self::MATCH_FIELDS)
            ? $this->employee_match_field
            : 'employee_card_id';
    }

    /**
     * Token cache window, held below the server's real token lifetime.
     *
     * Measured against the live server: tokens carry a 20-minute exp, so the
     * default of 15 leaves headroom. Never returns more than 19.
     */
    public function tokenCacheMinutes(): int
    {
        return max(1, min(19, (int) ($this->token_cache_minutes ?: 15)));
    }

}
