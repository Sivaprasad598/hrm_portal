<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Recruitment → Settings for one company: the careers mailbox the job
 * applications are read from, the key the website sends its submissions
 * with, whether AI reads incoming resumes, and the lists HR edits
 * (rejection reasons, interview rounds, scorecard questions).
 *
 * The mailbox password is encrypted at rest. The API key is never stored:
 * only its SHA-256 and last four characters, so it is shown once, when made.
 */
class RecruitSetting extends Model
{

    protected $table = 'recruit_settings';

    protected $guarded = ['id'];

    protected $hidden = ['imap_password', 'api_key_hash'];

    protected $casts = [
        'mailbox_enabled' => 'boolean',
        'mail_require_attachment' => 'boolean',
        'api_enabled' => 'boolean',
        'ai_autofill' => 'boolean',
        'mail_last_fetched_at' => 'datetime',
        'api_key_created_at' => 'datetime',
    ];

    public const ENCRYPTIONS = ['ssl' => 'SSL (port 993)', 'tls' => 'TLS', 'starttls' => 'STARTTLS (port 143)', 'notls' => 'None'];

    public const DEFAULT_KEYWORDS = 'application, applying, apply, resume, cv, curriculum vitae, job, career, opening, vacancy, position, candidature, opportunity, fresher, experienced';

    public const DEFAULT_IGNORE = 'noreply, no-reply, mailer-daemon, postmaster, notifications@, newsletter';

    public const DEFAULT_REJECTION_REASONS = "Not enough experience\nSkills do not match\nSalary expectation too high\nNotice period too long\nDid not clear the interview\nNot reachable\nPosition filled\nCandidate not interested";

    public const DEFAULT_ROUNDS = "HR Round\nTechnical Round\nManager Round\nFinal Round";

    public const DEFAULT_QUESTIONS = "Technical knowledge\nCommunication\nRelevant experience\nAttitude and culture fit";

    public static function forCompany(?int $companyId): self
    {
        $setting = static::firstOrNew(['company_id' => $companyId]);

        if (!$setting->exists) {
            $setting->fill([
                'imap_host' => 'imap.gmail.com', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'imap_folder' => 'INBOX',
                'mail_keywords' => self::DEFAULT_KEYWORDS, 'mail_ignore_senders' => self::DEFAULT_IGNORE,
                'rejection_reasons' => self::DEFAULT_REJECTION_REASONS, 'interview_rounds' => self::DEFAULT_ROUNDS,
                'scorecard_questions' => self::DEFAULT_QUESTIONS, 'ai_autofill' => true, 'mail_lookback_days' => 7,
            ]);
        }

        return $setting;
    }

    public function password(): ?string
    {
        if (!$this->imap_password) {
            return null;
        }

        try {
            return Crypt::decryptString($this->imap_password);
        } catch (DecryptException) {
            return null;   // written under another APP_KEY - treat as not set
        }
    }

    public function setPassword(?string $plain): void
    {
        $this->imap_password = $plain ? Crypt::encryptString($plain) : null;
    }

    public function mailboxReady(): bool
    {
        return $this->mailbox_enabled && $this->imap_host && $this->imap_username && $this->password();
    }

    /** A new website API key: saved as a hash, returned in full this once. */
    public function newApiKey(): string
    {
        $key = 'rk_' . Str::random(40);
        $this->api_key_hash = hash('sha256', $key);
        $this->api_key_hint = '…' . substr($key, -4);
        $this->api_key_created_at = now();

        return $key;
    }

    public static function findByApiKey(?string $key): ?self
    {
        if (!$key || strlen($key) < 20) {
            return null;
        }

        return static::where('api_enabled', true)->where('api_key_hash', hash('sha256', $key))->first();
    }

    /** @return string[] */
    public function keywords(): array
    {
        return self::splitList($this->mail_keywords, ',');
    }

    /** @return string[] */
    public function ignoredSenders(): array
    {
        return self::splitList($this->mail_ignore_senders, ',');
    }

    /** @return string[] */
    public function rejectionReasons(): array
    {
        return self::splitList($this->rejection_reasons ?? self::DEFAULT_REJECTION_REASONS, "\n");
    }

    /** @return string[] */
    public function rounds(): array
    {
        return self::splitList($this->interview_rounds ?? self::DEFAULT_ROUNDS, "\n");
    }

    /** @return string[] */
    public function questions(): array
    {
        return self::splitList($this->scorecard_questions ?? self::DEFAULT_QUESTIONS, "\n");
    }

    /** @return string[] */
    private static function splitList(?string $value, string $separator): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode($separator, str_replace("\r", '', (string) $value))))));
    }

}
