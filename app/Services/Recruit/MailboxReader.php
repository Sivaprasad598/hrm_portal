<?php

namespace App\Services\Recruit;

use App\Models\RecruitInbound;
use App\Models\RecruitSetting;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Reads the careers mailbox (Recruitment -> Settings) and turns job
 * applications into candidates.
 *
 * The mailbox is only read: nothing is marked read, moved or deleted, so the
 * inbox looks the same in Gmail afterwards. Each email is looked at once - its
 * Message-ID goes into recruit_inbound whatever the outcome - and kept there
 * as received (sender, subject, body, attachment names) as the proof.
 *
 * What counts as a job application (all from the settings):
 *   - the sender is not on the ignore list (noreply, newsletters ...);
 *   - the subject, the body or an attachment name has one of the keywords
 *     (application, resume, CV, job ...);
 *   - when "needs an attachment" is on, a PDF / Word file is attached.
 *
 * A forwarded email (HR or a colleague passing a resume on) is the original
 * sender's application, not the forwarder's.
 */
class MailboxReader
{

    /** Emails looked at in one run, so a large first run cannot time out. */
    public const BATCH = 100;

    public function __construct(protected CandidateIntake $intake)
    {
    }

    /** Connect and open the folder, to prove the settings work. @return string what was found */
    public function test(RecruitSetting $setting): string
    {
        $client = $this->client($setting);
        $client->connect();

        try {
            $folder = $client->getFolderByPath($setting->imap_folder ?: 'INBOX');

            if (!$folder) {
                throw new \RuntimeException('Connected, but the folder "' . $setting->imap_folder . '" was not found.');
            }

            $count = $folder->query()->since(now()->subDays(max(1, (int) $setting->mail_lookback_days)))->leaveUnread()->setFetchBody(false)->count();

            return 'Connected to ' . $setting->imap_host . '. ' . $count . ' email(s) in "' . $folder->path . '" in the last '
                . (int) $setting->mail_lookback_days . ' day(s).';
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Read new emails and bring the job applications in.
     *
     * @return array{read: int, imported: int, updated: int, skipped: int, failed: int, seen: int}
     */
    public function fetch(RecruitSetting $setting): array
    {
        $result = ['read' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'seen' => 0];
        $companyId = $setting->company_id;

        // From a day before the last run (mail can arrive late), or the look-back on the first run.
        $since = $setting->mail_last_fetched_at
            ? $setting->mail_last_fetched_at->copy()->subDay()
            : now()->subDays(max(1, (int) $setting->mail_lookback_days));

        $client = $this->client($setting);
        $client->connect();

        try {
            $folder = $client->getFolderByPath($setting->imap_folder ?: 'INBOX');

            if (!$folder) {
                throw new \RuntimeException('The folder "' . $setting->imap_folder . '" was not found in the mailbox.');
            }

            $messages = $folder->query()->since($since->startOfDay())->leaveUnread()->setFetchBody(true)->softFail()->get();
            $full = false;

            foreach ($messages as $message) {
                if ($result['read'] >= self::BATCH) {
                    $full = true;   // more left: the next run starts from the same point
                    break;
                }

                $externalId = $this->messageId($message);

                if (RecruitInbound::withoutGlobalScopes()->where('company_id', $companyId)->where('channel', 'email')
                    ->where('external_id', $externalId)->exists()) {
                    $result['seen']++;
                    continue;
                }

                $result['read']++;
                $result[$this->handle($message, $externalId, $setting)]++;
            }
        } finally {
            $client->disconnect();
        }

        // Moved on only when everything since was looked at; with a batch cut short the next run
        // reads from the same day again (the emails done are skipped by their Message-ID).
        if (!$full) {
            $setting->mail_last_fetched_at = now();
        }

        $setting->mail_last_result = $this->summary($result) . ($full ? ' More to read: the next run continues.' : '');
        $setting->save();

        return $result;
    }

    /** @return string imported | updated | skipped | failed */
    protected function handle(Message $message, string $externalId, RecruitSetting $setting): string
    {
        $from = $message->getFrom()->first();
        $fromEmail = strtolower(trim((string) ($from->mail ?? '')));
        $fromName = trim((string) ($from->personal ?? ''));
        $subject = trim((string) $message->getSubject()->toString());
        $body = $this->body($message);
        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            $name = (string) ($attachment->getName() ?: $attachment->filename ?: 'attachment');
            // The type the email claims is not used: files are kept by their extension and content
            $attachments[] = ['name' => $name, 'content' => (string) $attachment->getContent()];
        }

        $record = RecruitInbound::claim([
            'company_id' => $setting->company_id,
            'channel' => 'email',
            'external_id' => $externalId,
            'from_email' => mb_substr($fromEmail, 0, 191),
            'from_name' => mb_substr($fromName, 0, 191),
            'subject' => mb_substr($subject, 0, 500),
            'body' => mb_substr($body, 0, 50000),
            'payload' => ['attachments' => array_map(fn ($a) => ['name' => $a['name'], 'size' => strlen($a['content'])], $attachments)],
            'received_at' => $this->date($message),
        ]);

        if (!$record) {
            return 'seen';   // another run (Fetch now / the 15-minute run) has this email
        }

        $why = $this->notAnApplication($setting, $fromEmail, $subject, $body, $attachments);

        // Forwarded to the careers mailbox (by HR, a colleague, a referral): the applicant is the
        // original sender in the forwarded part, not the person who forwarded it
        $forwardedBy = null;

        if ($why === null && ($original = $this->forwarded($body))) {
            if (strcasecmp($original['email'], (string) $setting->imap_username) !== 0 && strcasecmp($original['email'], $fromEmail) !== 0) {
                $forwardedBy = $fromName ?: $fromEmail;
                [$fromEmail, $fromName, $body] = [$original['email'], $original['name'], $original['body']];
            }
        }

        if ($why !== null) {
            $record->status = 'skipped';
            $record->reason = $why;
            $record->save();

            return 'skipped';
        }

        try {
            $outcome = $this->intake->receive([
                'name' => $fromName ?: null,
                'email' => $fromEmail,
                'mobile' => $this->mobileIn($body),
                'message' => $this->coverNote($subject, $body),
                'source_detail' => mb_substr(($forwardedBy ? 'Email forwarded by ' . $forwardedBy . ': ' : 'Email: ') . ($subject ?: '(no subject)'), 0, 191),
            ], $attachments, 'email', $setting->company_id, $subject . "\n" . mb_substr($body, 0, 3000));

            $record->status = $outcome['status'];
            $record->candidate_id = $outcome['candidate']->id;
            $record->job_application_id = $outcome['application']->id;
            $record->reason = trim(($forwardedBy ? 'Forwarded by ' . $forwardedBy . '; applicant ' . $fromEmail . '. ' : '')
                . ($outcome['position_matched'] ? '' : 'No open position named - waiting under Unassigned.')) ?: null;
            $record->save();

            // AI reading takes a while per resume: inline only in the scheduled run, never while
            // HR waits on "Fetch now" (aiPending() picks those up on the next run)
            if (app()->runningInConsole()) {
                $this->intake->aiFill($outcome['candidate']);
            }

            return $outcome['status'];
        } catch (\Throwable $e) {
            logger()->warning('Careers mailbox import failed', ['message_id' => $externalId, 'error' => $e->getMessage()]);
            $record->status = 'failed';
            $record->reason = mb_substr($e->getMessage(), 0, 500);
            $record->save();

            return 'failed';
        }
    }

    /** Why this email is not taken as a job application, or null when it is one. */
    public function notAnApplication(RecruitSetting $setting, string $fromEmail, string $subject, string $body, array $attachments): ?string
    {
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return 'No sender address.';
        }

        if (strcasecmp($fromEmail, (string) $setting->imap_username) === 0) {
            return 'Sent from the careers mailbox itself.';
        }

        foreach ($setting->ignoredSenders() as $ignore) {
            if ($ignore !== '' && str_contains($fromEmail, strtolower($ignore))) {
                return 'Sender is on the ignore list ("' . $ignore . '").';
            }
        }

        $resumeFiles = array_filter($attachments, fn ($a) => in_array(strtolower(pathinfo($a['name'], PATHINFO_EXTENSION)), CandidateIntake::RESUME_EXTENSIONS, true));

        if ($setting->mail_require_attachment && !$resumeFiles) {
            return 'No resume attached (PDF or Word), and the settings ask for one.';
        }

        $haystack = mb_strtolower($subject . ' ' . mb_substr($body, 0, 5000) . ' ' . implode(' ', array_column($attachments, 'name')));

        foreach ($setting->keywords() as $keyword) {
            $keyword = mb_strtolower($keyword);

            if ($keyword !== '' && preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $haystack)) {
                return null;
            }
        }

        return 'None of the keywords in the subject, body or attachment names.';
    }

    protected function client(RecruitSetting $setting): Client
    {
        $sslOptions = [];

        // PHP here may have no CA bundle configured (see AppServiceProvider); use composer's.
        if (!ini_get('openssl.cafile') && class_exists(\Composer\CaBundle\CaBundle::class)) {
            $sslOptions['cafile'] = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        }

        return (new ClientManager())->make([
            'host' => $setting->imap_host,
            'port' => (int) ($setting->imap_port ?: 993),
            'encryption' => $setting->imap_encryption ?: 'ssl',
            'validate_cert' => true,
            'username' => $setting->imap_username,
            'password' => $setting->password(),
            'protocol' => 'imap',
            'timeout' => 30,
            'ssl_options' => $sslOptions,
        ]);
    }

    protected function messageId(Message $message): string
    {
        $id = trim((string) $message->getMessageId()->toString(), " <>\t\n");

        if ($id === '') {
            $from = $message->getFrom()->first();
            $id = 'nomid-' . sha1(($from->mail ?? '') . '|' . $message->getSubject()->toString() . '|' . (string) $message->getDate()->toString());
        }

        return mb_substr($id, 0, 191);
    }

    protected function body(Message $message): string
    {
        if ($message->hasTextBody()) {
            $text = $message->getTextBody();
        } else {
            $html = $message->hasHTMLBody() ? $message->getHTMLBody() : '';
            $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr)[^>]*>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', (string) $text)));
    }

    protected function date(Message $message)
    {
        try {
            return $message->getDate()->toDate();
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * The original sender of a forwarded email (Gmail's "Forwarded message",
     * Outlook's "Original Message" / a From: line after a rule) and the text
     * from there on. null when the email was not forwarded.
     *
     * @return array{email: string, name: ?string, body: string}|null
     */
    public function forwarded(string $body): ?array
    {
        if (!preg_match('/^[ >]*(?:-{3,}\s*(?:Forwarded message|Original Message)\s*-{3,}|_{10,})\s*$/mi', $body, $marker, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $rest = substr($body, $marker[0][1] + strlen($marker[0][0]));

        // The first From: line of the forwarded part: "Name <mail>", "mail", or "Name [mailto:mail]"
        if (!preg_match('/^[ >*]*From:\s*\**\s*(.*?)\s*[<\[](?:mailto:)?([^\s<>\[\]]+@[^\s<>\[\]]+)[>\]]|^[ >*]*From:\s*\**\s*([^\s<>]+@[^\s<>]+)/mi', substr($rest, 0, 2000), $from)) {
            return null;
        }

        $email = strtolower(trim($from[2] ?? '') ?: trim($from[3] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $name = trim(trim((string) ($from[1] ?? '')), " \"'*");

        return ['email' => $email, 'name' => $name !== '' && !str_contains($name, '@') ? mb_substr($name, 0, 191) : null,
            'body' => trim(preg_replace('/^[ >]+/m', '', $rest))];
    }

    /** A phone number in the email text, when there is one (without AI this is how the mobile is found). */
    public function mobileIn(string $text): ?string
    {
        if (preg_match('/(?:\+?91[\s-]?|0)?([6-9]\d{4}[\s-]?\d{5})\b/', $text, $m)) {
            return preg_replace('/\D/', '', $m[1]);
        }

        if (preg_match('/\+\d[\d\s-]{8,16}\d/', $text, $m)) {
            return preg_replace('/[^\d+]/', '', $m[0]);
        }

        return null;
    }

    protected function coverNote(string $subject, string $body): string
    {
        // Quoted replies and signatures after "--" are left out.
        $body = preg_split('/\n(--\s*\n|On .{5,120} wrote:)/', $body)[0] ?? $body;

        return trim(($subject !== '' ? 'Subject: ' . $subject . "\n\n" : '') . Str::limit(trim($body), 5000));
    }

    public function summary(array $result): string
    {
        return now()->format('d M Y h:i A') . ': ' . $result['read'] . ' new email(s) read — ' . $result['imported'] . ' new candidate(s), '
            . $result['updated'] . ' added to existing, ' . $result['skipped'] . ' not applications' . ($result['failed'] ? ', ' . $result['failed'] . ' failed' : '') . '.';
    }

}
