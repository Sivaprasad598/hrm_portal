<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadChatMessage;

/**
 * Lead Team → Team Chat.
 *
 * $kind:
 *  - message: a new message for you, as a bell notification (sent straight away)
 *  - unread:  you still have unread messages on a lead after 15 minutes, by email (SendLeadChatUnreadEmails);
 *             one email per lead, however many messages are waiting
 */
class LeadChatNotification extends BaseNotification
{

    private Lead $lead;
    private string $kind;
    private ?LeadChatMessage $message;
    private int $unreadCount;

    public function __construct(Lead $lead, string $kind, ?LeadChatMessage $message = null, int $unreadCount = 0)
    {
        $this->lead = $lead;
        $this->kind = $kind;
        $this->message = $message?->loadMissing(['sender', 'conversation', 'attachments']);
        $this->unreadCount = $unreadCount;
        $this->company = Company::find($lead->company_id) ?? Company::first();
    }

    public function via($notifiable)
    {
        return $this->kind === 'unread' ? (filter_var($notifiable->email, FILTER_VALIDATE_EMAIL) ? ['mail'] : []) : ['database'];
    }

    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);
        $lines = [
            'You have ' . $this->unreadCount . ' unread ' . ($this->unreadCount === 1 ? 'message' : 'messages')
                . ' in the Team Chat of lead ' . $this->leadName() . '.',
            '',
            'Open the lead\'s Lead Team tab to read and reply.',
        ];

        $build
            ->subject('💬 Unread Team Chat messages - ' . $this->leadName())
            ->markdown('mail.email', [
                'url' => $this->chatUrl(),
                'content' => implode('<br>', array_map('e', $lines)),
                'themeColor' => $this->company->header_color ?? null,
                'actionText' => 'Open Team Chat',
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    //phpcs:ignore
    public function toArray($notifiable)
    {
        $sender = $this->message?->from_lead ? ($this->lead->client_name . ' (lead)') : ($this->message?->sender?->name ?? 'Someone');
        $where = match ($this->message?->conversation?->type) {
            \App\Models\LeadChatConversation::GROUP => 'team chat',
            \App\Models\LeadChatConversation::LEAD => 'chat with the lead',
            default => 'private message',
        };
        $preview = trim((string) $this->message?->body);

        if ($preview === '' && $this->message?->attachments->isNotEmpty()) {
            $preview = '📎 ' . $this->message->attachments->pluck('filename')->implode(', ');
        }

        return [
            'lead_id' => $this->lead->id,
            // Opens this chat for the recipient: group, lead, or the sender's private chat
            'conversation' => $this->message?->conversation?->isShared()
                ? $this->message->conversation->keyFor(null) : $this->message?->user_id,
            'title' => '💬 ' . $sender . ' · ' . $where . ' · ' . $this->leadName(),
            'text' => mb_strimwidth($preview, 0, 120, '…'),
        ];
    }

    private function leadName(): string
    {
        return $this->lead->client_name . ($this->lead->company_name ? ' (' . $this->lead->company_name . ')' : '');
    }

    private function chatUrl(): string
    {
        return route('lead-contact.show', $this->lead->id) . '?tab=team&view=chat';
    }

}
