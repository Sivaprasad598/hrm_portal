<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Microsoft Teams (Microsoft Graph) connection used to create Teams links for customer meetings.
 */
class MicrosoftTeamsSetting extends Model
{
    protected $table = 'microsoft_teams_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'client_secret' => 'encrypted',
        'token' => 'encrypted:array',
        'connected_at' => 'datetime',
    ];

    protected $hidden = ['client_secret', 'token'];

    public static function current(): self
    {
        return self::firstOrCreate([]);
    }

    public function configured(): bool
    {
        return $this->tenant_id && $this->client_id && $this->client_secret;
    }

    public function connected(): bool
    {
        return $this->configured() && !empty($this->token['refresh_token']);
    }
}
