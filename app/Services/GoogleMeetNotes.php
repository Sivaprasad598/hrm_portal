<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomerMeeting;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Log;

/**
 * Google Meet automatic Smart Notes (Gemini) for CRM meetings.
 *
 * The company Google account (Settings → Google Calendar) owns every CRM meeting, so:
 *  - enableAutoNotes(): sets the Meet space's autoSmartNotesGeneration = ON (Meet REST API)
 *  - fetchNotes():      after the meeting, reads the Smart Notes Doc and returns its text
 *
 * The installed google/apiclient-services has no Meet service, so the Meet and Drive REST endpoints
 * are called through the Google client's authorized HTTP client (same token and refresh as Calendar).
 * Smart Notes need a Gemini-enabled plan on the company Google account.
 */
class GoogleMeetNotes
{
    private const MEET_API = 'https://meet.googleapis.com';
    // Smart Notes settings/resources may only be in the beta API version for some projects
    private const MEET_VERSIONS = ['v2', 'v2beta'];

    public static function connected(): bool
    {
        $company = Company::first();

        return $company
            && $company->google_calendar_status == 'active'
            && $company->google_calendar_verification_status == 'verified'
            && $company->token;
    }

    /**
     * Meeting code from a Meet link, e.g. https://meet.google.com/kxg-mboi-atr → kxg-mboi-atr
     */
    public static function meetingCode(?string $link): ?string
    {
        return $link && preg_match('#meet\.google\.com/([a-z]{3,}-[a-z]{3,}-[a-z]{3,})#i', $link, $m) ? strtolower($m[1]) : null;
    }

    /**
     * Turn on automatic Smart Notes for the meeting's Meet space. Never throws; returns true when set.
     */
    public static function enableAutoNotes(string $meetLink): bool
    {
        $code = self::meetingCode($meetLink);

        if (!$code || !self::connected()) {
            return false;
        }

        $body = ['config' => ['artifactConfig' => ['smartNotesConfig' => ['autoSmartNotesGeneration' => 'ON']]]];
        $lastError = null;

        foreach (self::MEET_VERSIONS as $version) {
            try {
                // The meeting code works as an alias for the space; PATCH needs the real resource name
                $space = self::json('GET', self::MEET_API . "/{$version}/spaces/{$code}");
                self::json('PATCH', self::MEET_API . "/{$version}/{$space['name']}", [
                    'query' => ['updateMask' => 'config.artifactConfig.smartNotesConfig.autoSmartNotesGeneration'],
                    'json' => $body,
                ]);

                return true;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        Log::warning("Google Meet auto Smart Notes could not be enabled for {$code}: {$lastError}");

        return false;
    }

    /**
     * Notes for a finished meeting, or null when Google has none (yet):
     *  ['text' => ..., 'url' => ...]                    notes read from the Doc
     *  ['text' => null, 'url' => ..., 'unreadable' => true]  the Doc exists but the CRM may not read it
     *    (notes started in the call from another Google account are saved in that account's Drive)
     */
    public static function fetchNotes(CustomerMeeting $meeting): ?array
    {
        $code = self::meetingCode($meeting->meeting_link);

        if (!$code || !self::connected()) {
            return null;
        }

        $documentId = self::smartNotesDocumentId($code) ?? self::calendarNotesDocumentId($meeting);

        if (!$documentId) {
            return null;
        }

        $url = "https://docs.google.com/document/d/{$documentId}/edit";

        try {
            $text = (string) self::request('GET', "https://www.googleapis.com/drive/v3/files/{$documentId}/export", [
                'query' => ['mimeType' => 'text/plain'],
            ])->getBody();
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403 && str_contains($e->getMessage(), 'has not granted the app')) {
                return ['text' => null, 'url' => $url, 'unreadable' => true];
            }

            throw $e;
        }

        // Drive's plain-text export starts with a BOM
        $text = trim(preg_replace('/^\xEF\xBB\xBF/', '', $text));

        return $text === '' ? null : ['text' => $text, 'url' => $url];
    }

    /**
     * Live call state for a Meet link: who is in the call right now and whether Gemini notes are running.
     * Returns ['live' => false] when no call is in progress.
     */
    public static function liveStatus(string $meetLink): array
    {
        $code = self::meetingCode($meetLink);

        if (!$code || !self::connected()) {
            return ['live' => false];
        }

        $records = self::json('GET', self::MEET_API . '/v2/conferenceRecords', [
            'query' => ['filter' => 'space.meeting_code = "' . $code . '" AND end_time IS NULL'],
        ])['conferenceRecords'] ?? [];

        if (!$records) {
            return ['live' => false];
        }

        $record = $records[0];

        $participants = self::json('GET', self::MEET_API . "/v2/{$record['name']}/participants", [
            'query' => ['filter' => 'latest_end_time IS NULL', 'pageSize' => 100],
        ])['participants'] ?? [];

        $names = array_map(fn ($p) => $p['signedinUser']['displayName']
            ?? $p['anonymousUser']['displayName']
            ?? $p['phoneUser']['displayName']
            ?? 'Guest', $participants);

        $notes = self::json('GET', self::MEET_API . "/v2/{$record['name']}/smartNotes")['smartNotes'] ?? [];

        return [
            'live' => true,
            'started_at' => $record['startTime'] ?? null,
            'count' => count($names),
            'names' => array_values($names),
            // A smart notes entry exists once Gemini starts taking notes in this call
            'notes_running' => (bool) array_filter($notes, fn ($n) => in_array($n['state'] ?? '', ['STARTED', 'ENDED', 'FILE_GENERATED'])),
        ];
    }

    /**
     * Smart Notes Doc from the Meet API: latest conference of this meeting code → its generated smart notes.
     */
    private static function smartNotesDocumentId(string $code): ?string
    {
        foreach (self::MEET_VERSIONS as $version) {
            try {
                $records = self::json('GET', self::MEET_API . "/{$version}/conferenceRecords", [
                    'query' => ['filter' => 'space.meeting_code = "' . $code . '"'],
                ])['conferenceRecords'] ?? [];

                // Newest conference first (a meeting link can be joined more than once)
                usort($records, fn ($a, $b) => strcmp($b['startTime'] ?? '', $a['startTime'] ?? ''));

                foreach ($records as $record) {
                    $notes = self::json('GET', self::MEET_API . "/{$version}/{$record['name']}/smartNotes")['smartNotes'] ?? [];

                    foreach ($notes as $note) {
                        if (($note['state'] ?? '') === 'FILE_GENERATED' && !empty($note['docsDestination']['document'])) {
                            return $note['docsDestination']['document'];
                        }
                    }
                }

                return null;
            } catch (\Throwable $e) {
                Log::info("Google Meet smart notes lookup ({$version}) for {$code}: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Fallback: Meet attaches the "Notes by Gemini" Doc to the Calendar event.
     */
    private static function calendarNotesDocumentId(CustomerMeeting $meeting): ?string
    {
        if (!$meeting->google_event_id) {
            return null;
        }

        try {
            $event = (new Google())->connectUsing(Company::first()->token)
                ->service('Calendar')->events->get('primary', $meeting->google_event_id);

            foreach ($event->getAttachments() ?: [] as $attachment) {
                if ($attachment->getMimeType() === 'application/vnd.google-apps.document' && $attachment->getFileId()) {
                    return $attachment->getFileId();
                }
            }
        } catch (\Throwable $e) {
            Log::info('Calendar notes attachment lookup failed for meeting ' . $meeting->id . ': ' . $e->getMessage());
        }

        return null;
    }

    private static function http(): ClientInterface
    {
        return (new Google())->connectUsing(Company::first()->token)->authorize();
    }

    private static function request(string $method, string $url, array $options = [])
    {
        $response = self::http()->request($method, $url, $options);

        // Google's HTTP client does not throw on 4xx/5xx, so failed calls must be turned into errors here
        if ($response->getStatusCode() >= 400) {
            $error = json_decode((string) $response->getBody(), true)['error'] ?? [];

            throw new \RuntimeException($method . ' ' . parse_url($url, PHP_URL_PATH) . ' → ' . $response->getStatusCode()
                . ' ' . ($error['status'] ?? '') . ': ' . ($error['message'] ?? 'request failed'), $response->getStatusCode());
        }

        return $response;
    }

    private static function json(string $method, string $url, array $options = []): array
    {
        return json_decode((string) self::request($method, $url, $options)->getBody(), true) ?: [];
    }
}
