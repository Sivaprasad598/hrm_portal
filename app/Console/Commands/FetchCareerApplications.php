<?php

namespace App\Console\Commands;

use App\Models\RecruitSetting;
use App\Services\Recruit\MailboxReader;
use Illuminate\Console\Command;

/**
 * Read every company's careers mailbox (Recruitment -> Settings) and bring
 * the job applications in as candidates. Scheduled every fifteen minutes;
 * HR can also press "Fetch now" on the settings page.
 */
class FetchCareerApplications extends Command
{

    protected $signature = 'recruit:fetch-mail';

    protected $description = 'Read the careers mailbox and add the job applications as candidates';

    public function handle(MailboxReader $reader): int
    {
        $settings = RecruitSetting::where('mailbox_enabled', true)->get();

        foreach ($settings as $setting) {
            if (!$setting->mailboxReady()) {
                continue;
            }

            try {
                $result = $reader->fetch($setting);
                $this->info('Company ' . $setting->company_id . ': ' . $reader->summary($result));
            } catch (\Throwable $e) {
                $setting->mail_last_result = now()->format('d M Y h:i A') . ': failed — ' . mb_substr($e->getMessage(), 0, 400);
                $setting->save();
                logger()->warning('Careers mailbox fetch failed', ['company' => $setting->company_id, 'error' => $e->getMessage()]);
                $this->error('Company ' . $setting->company_id . ': ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

}
