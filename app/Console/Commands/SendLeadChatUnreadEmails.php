<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadChatMessageRead;
use App\Models\User;
use App\Notifications\LeadChatNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lead Team → Team Chat: email people whose Team Chat messages are still unread 15 minutes after they were sent.
 * One email per person per lead, however many messages are waiting; each message is emailed about once.
 * Runs every 5 minutes.
 */
class SendLeadChatUnreadEmails extends Command
{

    protected $signature = 'send-lead-chat-unread-emails';

    protected $description = 'Email Team Chat messages that are still unread after 15 minutes';

    private const UNREAD_MINUTES = 15;

    // Messages older than this are not emailed about (e.g. the scheduler was off for days)
    private const GIVE_UP_DAYS = 2;

    public function handle()
    {
        $pending = LeadChatMessageRead::query()
            ->whereNull('read_at')->whereNull('emailed_at')
            ->join('lead_chat_messages', 'lead_chat_messages.id', '=', 'lead_chat_message_reads.message_id')
            ->whereBetween('lead_chat_messages.created_at', [now()->subDays(self::GIVE_UP_DAYS), now()->subMinutes(self::UNREAD_MINUTES)])
            ->get(['lead_chat_message_reads.id', 'lead_chat_message_reads.user_id', 'lead_chat_messages.lead_id']);

        $sent = 0;

        foreach ($pending->groupBy(fn ($r) => $r->user_id . ':' . $r->lead_id) as $rows) {
            $user = User::where('status', 'active')->find($rows->first()->user_id);
            $lead = Lead::find($rows->first()->lead_id);

            try {
                if ($user && $lead) {
                    $user->notify(new LeadChatNotification($lead, 'unread', null, $rows->count()));
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::error('Team Chat unread email failed for user ' . $rows->first()->user_id . ': ' . $e->getMessage());
            }

            // Marked even when the person has no email or is inactive, so they are not retried every run
            LeadChatMessageRead::whereIn('id', $rows->pluck('id'))->update(['emailed_at' => now()]);
        }

        $this->info('Team Chat unread emails sent: ' . $sent);

        return Command::SUCCESS;
    }

}
