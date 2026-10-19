<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One call to whichever AI provider the company has chosen: hand it a PDF and
 * a JSON schema, get back data that fits the schema.
 *
 * All three providers read a PDF directly, so a scanned resume works as well
 * as a typed one and nothing has to be parsed here first:
 *   OpenAI    - Chat Completions, file content part, json_schema response format
 *   Claude    - Messages API, base64 document block, json_schema output
 *   Gemini    - generateContent, inline_data part, responseSchema
 */
class AiClient
{
    public function __construct(protected AiSetting $setting)
    {
    }

    public static function forCurrentCompany(): self
    {
        return new self(AiSetting::forCompany(company()?->id));
    }

    public function setting(): AiSetting
    {
        return $this->setting;
    }

    /**
     * @param  array<string, mixed>  $schema  JSON Schema, every property listed in "required"
     *                                        (optional values are typed ["string", "null"])
     * @return array<string, mixed>
     */
    public function extractFromPdf(string $pdfBytes, string $filename, string $instructions, array $schema): array
    {
        if (!$this->setting->enabled) {
            throw new RuntimeException('AI features are turned off. Enable them on Settings → AI Settings.');
        }

        $provider = $this->setting->provider;
        $key = $this->setting->keyFor($provider);

        if (!$key) {
            throw new RuntimeException('No API key is saved for ' . (AiSetting::PROVIDERS[$provider] ?? $provider)
                . '. Add one on Settings → AI Settings.');
        }

        $model = $this->setting->modelFor($provider);

        $json = match ($provider) {
            'openai' => $this->openAi($key, $model, $pdfBytes, $filename, $instructions, $schema),
            'anthropic' => $this->anthropic($key, $model, $pdfBytes, $instructions, $schema),
            'gemini' => $this->gemini($key, $model, $pdfBytes, $instructions, $schema),
            default => throw new RuntimeException('Unknown AI provider: ' . $provider),
        };

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('The AI reply could not be read as data. Try again, or fill the form in by hand.');
        }

        return $data;
    }

    /**
     * A short request that proves the key and model work.
     *
     * @return string  what the provider answered
     */
    public function test(string $provider, string $key, string $model): string
    {
        $prompt = 'Reply with the single word OK.';

        return match ($provider) {
            'openai' => $this->openAiText($key, $model, $prompt),
            'anthropic' => $this->anthropicText($key, $model, $prompt),
            'gemini' => $this->geminiText($key, $model, $prompt),
            default => throw new RuntimeException('Unknown AI provider: ' . $provider),
        };
    }

    // ---- OpenAI ---------------------------------------------------------------

    protected function openAi(string $key, string $model, string $pdf, string $filename, string $instructions, array $schema): string
    {
        $response = Http::withToken($key)->timeout(120)->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $instructions],
                    ['role' => 'user', 'content' => [
                        ['type' => 'file', 'file' => [
                            'filename' => $filename ?: 'resume.pdf',
                            'file_data' => 'data:application/pdf;base64,' . base64_encode($pdf),
                        ]],
                        ['type' => 'text', 'text' => 'Extract the details from this resume.'],
                    ]],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'resume', 'strict' => true, 'schema' => $schema],
                ],
            ]);

        $this->failUnlessOk($response, 'OpenAI');

        $choice = $response->json('choices.0');

        if (!empty($choice['message']['refusal'])) {
            throw new RuntimeException('OpenAI declined to read this file: ' . $choice['message']['refusal']);
        }

        return (string) ($choice['message']['content'] ?? '');
    }

    protected function openAiText(string $key, string $model, string $prompt): string
    {
        $response = Http::withToken($key)->timeout(30)->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        $this->failUnlessOk($response, 'OpenAI');

        return trim((string) $response->json('choices.0.message.content'));
    }

    // ---- Claude (Anthropic) ---------------------------------------------------

    protected function anthropic(string $key, string $model, string $pdf, string $instructions, array $schema): string
    {
        $response = $this->anthropicRequest($key, [
            'model' => $model,
            'max_tokens' => 16000,
            'system' => $instructions,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'document', 'source' => [
                        'type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($pdf),
                    ]],
                    ['type' => 'text', 'text' => 'Extract the details from this resume.'],
                ],
            ]],
            // Reading a resume is routine work, so low effort is enough.
            'output_config' => [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
        ], 120);

        if ($response['stop_reason'] === 'refusal') {
            throw new RuntimeException('Claude declined to read this file. Fill the form in by hand.');
        }

        if ($response['stop_reason'] === 'max_tokens') {
            throw new RuntimeException('The resume was too long to read in one pass. Fill the remaining fields by hand.');
        }

        return $response['text'];
    }

    protected function anthropicText(string $key, string $model, string $prompt): string
    {
        return trim($this->anthropicRequest($key, [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'output_config' => ['effort' => 'low'],
        ], 30)['text']);
    }

    /**
     * POST /v1/messages. The official PHP SDK is the preferred way in, but
     * composer cannot resolve new packages in this project without GitHub
     * credentials for its private repositories, so this speaks the documented
     * REST shape until the SDK can be added.
     *
     * Server-side fallback (if the model declines, the API retries on a
     * fallback model within the same call) is on for the models that offer
     * it; Haiku does not, and rejects the parameter.
     *
     * @return array{text: string, stop_reason: ?string}
     */
    protected function anthropicRequest(string $key, array $body, int $timeout): array
    {
        $headers = ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];

        if (preg_match('/^claude-(opus-5|fable-5|sonnet-5-5)/', (string) $body['model'])) {
            $body['fallbacks'] = 'default';
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)->acceptJson()->post('https://api.anthropic.com/v1/messages', $body);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException('Could not reach Claude: ' . $e->getMessage());
        }

        $this->failUnlessOk($response, 'Claude');

        $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');

        return ['text' => (string) $text, 'stop_reason' => $response->json('stop_reason')];
    }

    // ---- Gemini ---------------------------------------------------------------

    protected function gemini(string $key, string $model, string $pdf, string $instructions, array $schema): string
    {
        $response = Http::withHeaders(['x-goog-api-key' => $key])->timeout(120)->acceptJson()
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $instructions]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode($pdf)]],
                        ['text' => 'Extract the details from this resume.'],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->geminiSchema($schema),
                ],
            ]);

        $this->failUnlessOk($response, 'Gemini');

        $finish = $response->json('candidates.0.finishReason');

        if (in_array($finish, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST'], true)) {
            throw new RuntimeException('Gemini declined to read this file (' . $finish . ').');
        }

        return (string) collect($response->json('candidates.0.content.parts', []))->pluck('text')->implode('');
    }

    protected function geminiText(string $key, string $model, string $prompt): string
    {
        $response = Http::withHeaders(['x-goog-api-key' => $key])->timeout(30)->acceptJson()
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            ]);

        $this->failUnlessOk($response, 'Gemini');

        return trim((string) collect($response->json('candidates.0.content.parts', []))->pluck('text')->implode(''));
    }

    /**
     * Gemini's responseSchema is the OpenAPI subset: "nullable": true instead
     * of a ["string", "null"] type list, and no additionalProperties.
     */
    protected function geminiSchema(array $schema): array
    {
        $out = [];

        foreach ($schema as $key => $value) {
            if ($key === 'additionalProperties') {
                continue;
            }

            if ($key === 'type' && is_array($value)) {
                $types = array_values(array_diff($value, ['null']));
                $out['type'] = strtoupper($types[0] ?? 'string');

                if (count($types) < count($value)) {
                    $out['nullable'] = true;
                }

                continue;
            }

            if ($key === 'type') {
                $out['type'] = strtoupper($value);
                continue;
            }

            if ($key === 'properties') {
                $out['properties'] = array_map(fn ($p) => $this->geminiSchema($p), $value);
                continue;
            }

            if ($key === 'items') {
                $out['items'] = $this->geminiSchema($value);
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    // ---- shared ---------------------------------------------------------------

    protected function failUnlessOk(\Illuminate\Http\Client\Response $response, string $who): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('error.message') ?? $response->json('error') ?? $response->body();
        $detail = is_string($detail) ? $detail : json_encode($detail);

        $hint = match ($response->status()) {
            401, 403 => ' - check the API key on Settings → AI Settings.',
            404 => ' - check the model name on Settings → AI Settings.',
            429 => ' - the account is out of quota or rate-limited; try again shortly.',
            default => '',
        };

        throw new RuntimeException($who . ' returned HTTP ' . $response->status() . ': '
            . mb_strimwidth((string) $detail, 0, 300, '…') . $hint);
    }
}
