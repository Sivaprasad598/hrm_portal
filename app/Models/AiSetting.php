<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * The company's AI connection: whether it is on, which provider answers, and
 * a key and model for each provider. Keys are encrypted at rest and never
 * sent back to the browser - the settings page only says whether one is set.
 */
class AiSetting extends Model
{
    public const PROVIDERS = [
        'openai' => 'OpenAI (ChatGPT)',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google (Gemini)',
    ];

    /** The model each provider uses when none is chosen: the low-cost one, ample for reading a resume. */
    public const DEFAULT_MODELS = [
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-haiku-5-5',
        'gemini' => 'gemini-2.5-flash',
    ];

    protected $table = 'ai_settings';

    protected $guarded = ['id'];

    protected $hidden = ['openai_key', 'anthropic_key', 'gemini_key'];

    protected $casts = [
        'enabled' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public static function forCompany(?int $companyId): self
    {
        return static::firstOrNew(['company_id' => $companyId]);
    }

    /** The decrypted key for a provider, or null when none is set. */
    public function keyFor(string $provider): ?string
    {
        $stored = $this->{$provider . '_key'} ?? null;

        if (!$stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;   // written under another APP_KEY - treat as not set
        }
    }

    public function setKey(string $provider, ?string $plain): void
    {
        $this->{$provider . '_key'} = $plain ? Crypt::encryptString($plain) : null;
    }

    public function modelFor(string $provider): string
    {
        return $this->{$provider . '_model'} ?: self::DEFAULT_MODELS[$provider];
    }

    /** On, with a key for the chosen provider: the features that use AI can run. */
    public function ready(): bool
    {
        return $this->enabled && isset(self::PROVIDERS[$this->provider]) && $this->keyFor($this->provider) !== null;
    }

    /** "sk-…a1b2", for showing that a key is set without showing it. */
    public function maskedKey(string $provider): ?string
    {
        $key = $this->keyFor($provider);

        return $key ? substr($key, 0, 4) . '…' . substr($key, -4) : null;
    }
}
