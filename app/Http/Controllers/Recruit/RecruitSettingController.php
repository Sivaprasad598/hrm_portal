<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Models\AiSetting;
use App\Models\RecruitInbound;
use App\Models\RecruitSetting;
use App\Services\Recruit\MailboxReader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recruitment -> Settings: the careers mailbox (test, fetch now), the key the
 * website sends applications with, AI reading of resumes, and the lists HR
 * keeps (rejection reasons, interview rounds, scorecard questions). Also the
 * log of everything that came in by email or from the website.
 */
class RecruitSettingController extends RecruitBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->activeSettingMenu = 'recruitment_settings';

        $this->middleware(function ($request, $next) {
            abort_403(!$this->canSettings);

            return $next($request);
        });
    }

    public function index()
    {
        $this->pageTitle = 'Recruitment Settings';
        $this->setting = RecruitSetting::forCompany(company()->id);
        $this->aiReady = AiSetting::forCompany(company()->id)->ready();
        $this->newKey = session('recruit_new_api_key');
        $this->apiUrl = url('/api/recruit/v1/applications');
        $this->plan = \App\Models\RecruitRound::planFor(null, company()->id);
        $this->employees = $this->employees();
        $this->inboundCounts = RecruitInbound::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('channel, status, count(*) as n')->groupBy('channel', 'status')->get();

        return view('recruitment.settings.index', $this->data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'section' => 'required|in:mailbox,api,lists,rounds',
            'slot_lead_hours' => 'nullable|integer|min:0|max:336',
            'slot_horizon_days' => 'nullable|integer|min:1|max:60',
            'reminder_hours' => 'nullable|integer|min:1|max:168',
            'escalate_hours' => 'nullable|integer|min:1|max:720',
            'imap_host' => 'nullable|required_if:mailbox_enabled,1|string|max:191',
            'imap_port' => 'nullable|required_if:mailbox_enabled,1|integer|min:1|max:65535',
            'imap_encryption' => ['nullable', Rule::in(array_keys(RecruitSetting::ENCRYPTIONS))],
            'imap_username' => 'nullable|required_if:mailbox_enabled,1|string|max:191',
            'imap_password' => 'nullable|string|max:500',
            'imap_folder' => 'nullable|string|max:191',
            'mail_keywords' => 'nullable|string|max:2000',
            'mail_ignore_senders' => 'nullable|string|max:2000',
            'mail_lookback_days' => 'nullable|integer|min:1|max:90',
            'rejection_reasons' => 'nullable|string|max:5000',
            'interview_rounds' => 'nullable|string|max:2000',
            'scorecard_questions' => 'nullable|string|max:2000',
        ], [
            'imap_host.required_if' => 'Enter the mail server (for Gmail: imap.gmail.com).',
            'imap_username.required_if' => 'Enter the mailbox address.',
        ]);

        $setting = RecruitSetting::forCompany(company()->id);

        if ($request->section === 'mailbox') {
            $setting->fill([
                'mailbox_enabled' => $request->boolean('mailbox_enabled'),
                'imap_host' => trim((string) $request->imap_host) ?: null,
                'imap_port' => $request->imap_port ?: 993,
                'imap_encryption' => $request->imap_encryption ?: 'ssl',
                'imap_username' => trim((string) $request->imap_username) ?: null,
                'imap_folder' => trim((string) $request->imap_folder) ?: 'INBOX',
                'mail_keywords' => trim((string) $request->mail_keywords) ?: RecruitSetting::DEFAULT_KEYWORDS,
                'mail_ignore_senders' => trim((string) $request->mail_ignore_senders),
                'mail_require_attachment' => $request->boolean('mail_require_attachment'),
                'mail_lookback_days' => $request->mail_lookback_days ?: 7,
                'ai_autofill' => $request->boolean('ai_autofill'),
            ]);

            if ($request->boolean('remove_password')) {
                $setting->setPassword(null);
            } elseif ($request->filled('imap_password')) {
                // Gmail shows app passwords in groups of four; the spaces are not part of it.
                $setting->setPassword(str_replace(' ', '', trim($request->imap_password)));
            }

            if ($setting->mailbox_enabled && !$setting->password()) {
                return Reply::error('Enter the mailbox password (for Gmail, an app password) before switching the mailbox on.');
            }
        } elseif ($request->section === 'rounds') {
            // The default round plan (positions without their own use it) and the timings
            $rows = $this->planRows($request);

            if (!$rows) {
                return Reply::error('Keep at least one round in the default plan.');
            }

            try {
                \App\Models\RecruitRound::savePlan(null, $rows, company()->id);
            } catch (\RuntimeException $e) {
                return Reply::error($e->getMessage());
            }
            $setting->fill([
                'slot_lead_hours' => (int) ($request->slot_lead_hours ?? 24),
                'slot_horizon_days' => (int) ($request->slot_horizon_days ?: 14),
                'reminder_hours' => (int) ($request->reminder_hours ?: 24),
                'escalate_hours' => (int) ($request->escalate_hours ?: 48),
            ]);
            app(\App\Services\Recruit\InterviewScheduler::class)->forgetMenus(collect($rows)->pluck('interviewer_ids')->flatten()->all());
        } elseif ($request->section === 'api') {
            $setting->api_enabled = $request->boolean('api_enabled');

            if ($setting->api_enabled && !$setting->api_key_hash) {
                return Reply::error('Generate a key first, then switch the website API on.');
            }
        } else {
            $setting->fill([
                'rejection_reasons' => trim((string) $request->rejection_reasons) ?: RecruitSetting::DEFAULT_REJECTION_REASONS,
                'interview_rounds' => trim((string) $request->interview_rounds) ?: RecruitSetting::DEFAULT_ROUNDS,
                'scorecard_questions' => trim((string) $request->scorecard_questions) ?: RecruitSetting::DEFAULT_QUESTIONS,
            ]);
        }

        $setting->updated_by = user()->id;
        $setting->save();

        return Reply::success('Settings saved.');
    }

    /** Connect to the mailbox with the saved settings. */
    public function testMailbox(MailboxReader $reader)
    {
        $setting = RecruitSetting::forCompany(company()->id);

        if (!$setting->imap_host || !$setting->imap_username || !$setting->password()) {
            return Reply::error('Save the mail server, the mailbox address and the password first.');
        }

        try {
            return Reply::success($reader->test($setting));
        } catch (\Throwable $e) {
            return Reply::error('Could not connect: ' . $this->explain($e->getMessage()));
        }
    }

    /** Read the mailbox now, instead of waiting for the scheduled run. */
    public function fetch(MailboxReader $reader)
    {
        $setting = RecruitSetting::forCompany(company()->id);

        if (!$setting->mailboxReady()) {
            return Reply::error('Switch the careers mailbox on and save its details first.');
        }

        @set_time_limit(300);

        try {
            $result = $reader->fetch($setting);
        } catch (\Throwable $e) {
            $setting->mail_last_result = now()->format('d M Y h:i A') . ': failed — ' . mb_substr($this->explain($e->getMessage()), 0, 400);
            $setting->save();

            return Reply::error('Could not read the mailbox: ' . $this->explain($e->getMessage()));
        }

        return Reply::successWithData($reader->summary($result), ['redirectUrl' => route('recruitment-settings.index')]);
    }

    /** A new website API key; the old one stops working. Shown once. */
    public function apiKey()
    {
        $setting = RecruitSetting::forCompany(company()->id);
        $key = $setting->newApiKey();
        $setting->updated_by = user()->id;
        $setting->save();

        session()->flash('recruit_new_api_key', $key);

        return Reply::successWithData('New key generated. Copy it now - it will not be shown again.', ['redirectUrl' => route('recruitment-settings.index') . '#website-api']);
    }

    /** Everything that came in by email or from the website, with what was done with it. */
    public function inbox(Request $request)
    {
        $this->pageTitle = 'Incoming Applications';
        $this->items = RecruitInbound::with('candidate')
            ->when($request->channel, fn ($q, $c) => $q->where('channel', $c))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('from_email', 'like', "%$s%")
                ->orWhere('from_name', 'like', "%$s%")->orWhere('subject', 'like', "%$s%")))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('recruitment.settings.inbox', $this->data);
    }

    public function inboxShow($id)
    {
        $this->item = RecruitInbound::with('candidate')->findOrFail($id);

        return $this->panel('recruitment.settings.inbox-show', 'Received — ' . ($this->item->subject ?: $this->item->from_email));
    }

    /** Plainer words for the usual mailbox errors. */
    private function explain(string $error): string
    {
        $lower = strtolower($error);

        if (str_contains($lower, 'authenticat') || str_contains($lower, 'invalid credentials') || str_contains($lower, 'login')) {
            return $error . ' — For Gmail, use an app password (Google Account → Security → 2-Step Verification → App passwords), not the normal password, and make sure IMAP is on in Gmail settings.';
        }

        if (str_contains($lower, 'certificate') || str_contains($lower, 'ssl')) {
            return $error . ' — The secure connection failed; check the port (993 for SSL) and the encryption.';
        }

        if (str_contains($lower, 'connection') || str_contains($lower, 'timed out') || str_contains($lower, 'refused')) {
            return $error . ' — The server could not be reached; check the server name and port.';
        }

        return $error;
    }

}
