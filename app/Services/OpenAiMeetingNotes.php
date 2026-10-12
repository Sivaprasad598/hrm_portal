<?php

namespace App\Services;

use App\Models\CustomerMeeting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * CRM Notes bot: OpenAI speech-to-text for the recorded call audio, then meeting notes from the transcript.
 * Settings: config('services.openai') / OPENAI_* in .env.
 */
class OpenAiMeetingNotes
{
    private const API = 'https://api.openai.com/v1';

    public static function configured(): bool
    {
        return (bool) config('services.openai.key');
    }

    /**
     * Transcribe one audio file (a recorded segment of the call).
     */
    public static function transcribe(string $path, string $filename): string
    {
        $response = self::client(300)
            // File contents, not a stream: an open handle would stop the audio file being deleted on Windows
            ->attach('file', file_get_contents($path), $filename)
            ->post(self::API . '/audio/transcriptions', [
                'model' => config('services.openai.transcribe_model'),
                'response_format' => 'text',
            ]);

        self::check($response, 'Transcription');

        return trim($response->body());
    }

    /**
     * Meeting notes (plain text with headings) from the full transcript.
     */
    public static function notes(CustomerMeeting $meeting, string $transcript): string
    {
        $start = $meeting->startsAt();
        $end = $meeting->endsAt();

        $context = implode("\n", array_filter([
            'Customer: ' . ($meeting->customer->company_name ?? '-') . ($meeting->customer->customer_name ?? null ? ' (' . $meeting->customer->customer_name . ')' : ''),
            'Lead owner: ' . ($meeting->lead_owner ?: '-'),
            'Purpose: ' . ($meeting->purpose ?: '-'),
            'Scheduled: ' . ($start ? $start->format('d M Y, h:i A') : '-') . ($end ? ' – ' . $end->format('h:i A') : ''),
            $meeting->description ? 'Agenda: ' . $meeting->description : null,
        ]));

        $response = self::client(180)->post(self::API . '/chat/completions', [
            'model' => config('services.openai.notes_model'),
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You write meeting notes for a sales CRM from an automatic call transcript between our lead owner and a customer.\n"
                        . "Write in English even if the transcript is in another language or mixes languages.\n"
                        . "Use plain text (no markdown symbols like # or **) with exactly these headings, each on its own line:\n"
                        . "Summary\nDiscussion Points\nCustomer Requirements\nDecisions\nAction Items\nNext Follow-up\n"
                        . "Under each heading use short lines starting with \"- \". For action items, name who is responsible when the transcript says so.\n"
                        . "Only use facts from the transcript; if a section has nothing, write \"- None mentioned\".\n"
                        . "The transcript has no speaker names and may contain recognition errors; do not invent names, prices or dates.",
                ],
                [
                    'role' => 'user',
                    'content' => "Meeting details:\n{$context}\n\nTranscript:\n{$transcript}",
                ],
            ],
        ]);

        self::check($response, 'Notes');

        $notes = trim((string) $response->json('choices.0.message.content'));

        if ($notes === '') {
            throw new RuntimeException('Notes: the model returned no text.');
        }

        return $notes;
    }

    private static function client(int $timeout): PendingRequest
    {
        return Http::withToken(config('services.openai.key'))
            ->withHeaders(array_filter([
                'OpenAI-Organization' => config('services.openai.organization'),
                'OpenAI-Project' => config('services.openai.project'),
            ]))
            ->timeout($timeout)
            ->retry(2, 2000, throw: false);
    }

    private static function check($response, string $step): void
    {
        if ($response->failed()) {
            throw new RuntimeException($step . ' failed (' . $response->status() . '): ' . ($response->json('error.message') ?: substr($response->body(), 0, 300)));
        }
    }
}
