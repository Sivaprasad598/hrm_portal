<?php

namespace App\Services;

use App\Models\CustomerMeeting;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Settings of the CRM Notetaker bot (notetaker-bot/.env), edited from Settings → Lead Settings → Notetaker Bot.
 *
 * The bot authenticates with BOT_KEY, which must equal CRM_NOTES_BOT_KEY in the portal's .env, so a new key
 * is written to both files. The bot watches its .env and restarts with the new settings once no meeting
 * is in progress.
 */
class NotetakerBot
{

    public function botEnvPath(): string
    {
        return base_path('notetaker-bot' . DIRECTORY_SEPARATOR . '.env');
    }

    public function settings(): array
    {
        $env = $this->read($this->botEnvPath());

        return [
            'portal_url' => $env['PORTAL_URL'] ?? '',
            'bot_key' => $env['BOT_KEY'] ?? '',
            'bot_name' => $env['BOT_NAME'] ?? 'CRM Notetaker',
        ];
    }

    /**
     * @param  string|null  $botKey  null keeps the current key
     */
    public function save(string $portalUrl, string $botName, ?string $botKey): void
    {
        $values = [
            'PORTAL_URL' => rtrim($portalUrl, '/'),
            'BOT_NAME' => $botName,
        ];

        if ($botKey !== null) {
            $values['BOT_KEY'] = $botKey;
            // Portal side first: if the bot restarts in between, it fails one check instead of being locked out
            $this->write(base_path('.env'), ['CRM_NOTES_BOT_KEY' => $botKey]);
        }

        $this->write($this->botEnvPath(), $values);
    }

    public static function generateKey(): string
    {
        return 'crmbot_' . Str::random(40);
    }

    /** Online if the bot checked in within the last 2 minutes (it checks every 30 seconds). */
    public function status(): array
    {
        $seen = cache('crm-notes-bot-last-seen');
        $seenAt = $seen ? Carbon::parse($seen)->timezone(CustomerMeeting::timezone()) : null;

        return [
            'online' => $seenAt && $seenAt->gt(now()->subMinutes(2)),
            'last_seen' => $seenAt?->format('d M Y, h:i A'),
            'keys_match' => $this->settings()['bot_key'] !== ''
                && hash_equals((string) config('services.openai.bot_key'), $this->settings()['bot_key']),
            'writable' => is_writable($this->botEnvPath()) && is_writable(base_path('.env')),
        ];
    }

    private function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $values = [];

        foreach (preg_split('/\r?\n/', file_get_contents($file)) as $line) {
            if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $line, $m)) {
                $values[$m[1]] = trim($m[2]);
            }
        }

        return $values;
    }

    /** Replaces the given keys in place, appends missing ones and keeps every other line as it was. */
    private function write(string $file, array $values): void
    {
        $content = is_file($file) ? file_get_contents($file) : '';
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

        foreach ($values as $key => $value) {
            $line = $key . '=' . str_replace(["\r", "\n"], '', $value);
            $pattern = '/^' . preg_quote($key, '/') . '=[^\r\n]*/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content);
            } else {
                $content = rtrim($content, "\r\n") . ($content === '' ? '' : $eol) . $line . $eol;
            }
        }

        file_put_contents($file, $content, LOCK_EX);
    }

}
