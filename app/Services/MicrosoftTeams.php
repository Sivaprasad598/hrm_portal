<?php

namespace App\Services;

use App\Models\MicrosoftTeamsSetting;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Teams links for customer meetings, through Microsoft Graph.
 *
 * Settings → Microsoft Teams holds the app registration (tenant, client id, secret) and the work account
 * signed in once with "Connect". Meetings are created with POST /me/onlineMeetings (needs a Microsoft 365
 * work account: Graph does not support onlineMeetings for personal Microsoft accounts).
 */
class MicrosoftTeams
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';
    private const SCOPES = 'offline_access openid profile User.Read OnlineMeetings.ReadWrite';

    public static function settings(): MicrosoftTeamsSetting
    {
        return MicrosoftTeamsSetting::current();
    }

    public static function connected(): bool
    {
        return self::settings()->connected();
    }

    public static function redirectUri(): string
    {
        return route('microsoftAuth');
    }

    public static function authorizeUrl(string $state): string
    {
        $s = self::settings();

        return "https://login.microsoftonline.com/{$s->tenant_id}/oauth2/v2.0/authorize?" . http_build_query([
            'client_id' => $s->client_id,
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * "Connect": exchange the sign-in code for tokens and remember the work account.
     */
    public static function connectWithCode(string $code): void
    {
        $s = self::settings();
        $token = self::tokenRequest(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirectUri()]);

        $me = Http::withToken($token['access_token'])->get(self::GRAPH . '/me')->json();

        $s->fill([
            'token' => $token,
            'account_email' => $me['mail'] ?? $me['userPrincipalName'] ?? null,
            'account_name' => $me['displayName'] ?? null,
            'connected_at' => now(),
        ])->save();
    }

    public static function disconnect(): void
    {
        self::settings()->fill(['token' => null, 'account_email' => null, 'account_name' => null, 'connected_at' => null])->save();
    }

    /**
     * Create a Teams meeting for the slot. Returns [join url, onlineMeeting id].
     */
    public static function createMeeting(string $subject, Carbon $start, Carbon $end): array
    {
        $meeting = self::check(self::graph()->post(self::GRAPH . '/me/onlineMeetings', [
            'subject' => $subject,
            'startDateTime' => $start->toIso8601String(),
            'endDateTime' => $end->toIso8601String(),
        ]), 'Create Teams meeting')->json();

        if (empty($meeting['joinWebUrl'])) {
            throw new RuntimeException('Create Teams meeting: Microsoft returned no join link.');
        }

        return [$meeting['joinWebUrl'], $meeting['id']];
    }

    public static function updateMeeting(string $id, string $subject, Carbon $start, Carbon $end): void
    {
        self::check(self::graph()->patch(self::GRAPH . '/me/onlineMeetings/' . rawurlencode($id), [
            'subject' => $subject,
            'startDateTime' => $start->toIso8601String(),
            'endDateTime' => $end->toIso8601String(),
        ]), 'Update Teams meeting');
    }

    public static function joinUrl(string $id): ?string
    {
        return self::check(self::graph()->get(self::GRAPH . '/me/onlineMeetings/' . rawurlencode($id)), 'Read Teams meeting')
            ->json('joinWebUrl');
    }

    public static function deleteMeeting(string $id): void
    {
        $response = self::graph()->delete(self::GRAPH . '/me/onlineMeetings/' . rawurlencode($id));

        // Already gone in Teams: nothing to do
        if ($response->status() !== 404) {
            self::check($response, 'Delete Teams meeting');
        }
    }

    /**
     * Graph client with a fresh access token (refreshed with the stored refresh token when needed).
     */
    private static function graph(): PendingRequest
    {
        $s = self::settings();

        if (!$s->connected()) {
            throw new RuntimeException('Microsoft Teams is not connected. Connect it in Settings → Microsoft Teams.');
        }

        $token = $s->token;

        if (empty($token['expires_at']) || $token['expires_at'] < time() + 120) {
            $token = self::tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $token['refresh_token']]) + $token;
            $s->fill(['token' => $token])->save();
        }

        return Http::withToken($token['access_token'])->acceptJson()->timeout(30);
    }

    private static function tokenRequest(array $params): array
    {
        $s = self::settings();

        $response = Http::asForm()->timeout(30)->post("https://login.microsoftonline.com/{$s->tenant_id}/oauth2/v2.0/token", $params + [
            'client_id' => $s->client_id,
            'client_secret' => $s->client_secret,
            'scope' => self::SCOPES,
        ]);

        if ($response->failed() || !$response->json('access_token')) {
            throw new RuntimeException('Microsoft sign-in failed: ' . ($response->json('error_description') ?: $response->body()));
        }

        $token = $response->json();
        $token['expires_at'] = time() + (int) ($token['expires_in'] ?? 3600);

        return array_intersect_key($token, array_flip(['access_token', 'refresh_token', 'expires_at', 'scope']));
    }

    private static function check($response, string $step)
    {
        if ($response->failed()) {
            $message = $response->json('error.message') ?: substr($response->body(), 0, 300);
            throw new RuntimeException("{$step} failed ({$response->status()}): {$message}");
        }

        return $response;
    }
}
