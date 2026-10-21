<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\CustomerMeeting;
use App\Models\User;

/**
 * Customer meeting emails / bell notifications (Customers → Meetings).
 *
 * $kind:
 *  - scheduled: sent when the meeting is created          (customer + lead owner)
 *  - reminder:  15 minutes before the start time          (customer + lead owner)
 *  - ended:     at the end time, asks for the meeting result (lead owner)
 *
 * The customer is not a portal user, so they get email only (on-demand notifiable);
 * the lead owner gets email and the bell notification.
 */
class CustomerMeetingNotification extends BaseNotification
{
    public const REMINDER_MINUTES = 15;

    private CustomerMeeting $meeting;
    private string $kind;

    public function __construct(CustomerMeeting $meeting, string $kind)
    {
        $this->meeting = $meeting->loadMissing(['customer', 'lead', 'client']);
        $this->kind = $kind;
        $this->company = Company::find($meeting->contact->company_id) ?? Company::first();
    }

    public function via($notifiable)
    {
        $via = [];

        // Bell notification only for portal users (the lead owner)
        if ($notifiable instanceof User) {
            $via[] = 'database';
        }

        if ($this->emailFor($notifiable)) {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);
        $lines = $this->lines();
        $link = $this->meeting->meeting_link;

        if ($this->kind === 'ended') {
            $url = route('customers.meetings.result', $this->meeting->id);
            $actionText = 'Update Meeting Result';
        } elseif ($link) {
            $url = $link;
            $actionText = $this->meeting->provider === 'Microsoft Teams' ? 'Join Microsoft Teams Meeting' : 'Join Google Meet';
        } else {
            $url = null;
            $actionText = null;
        }

        $build
            ->subject($this->title() . ' - ' . $this->companyName())
            ->markdown('mail.email', [
                'url' => $url,
                'content' => implode('<br>', array_map('e', $lines)),
                'themeColor' => $this->company->header_color ?? null,
                'actionText' => $actionText,
                'notifiableName' => $notifiable instanceof User ? $notifiable->name : ($this->meeting->contact->name ?? ''),
            ]);

        parent::resetLocale();

        return $build;
    }

    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->meeting->id,
            'kind' => $this->kind,
            'title' => $this->title(),
            'text' => $this->companyName() . ' · ' . $this->timeRange(),
        ];
    }

    private function emailFor($notifiable): ?string
    {
        $email = $notifiable instanceof User ? $notifiable->email : $notifiable->routeNotificationFor('mail', $this);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function title(): string
    {
        return [
            'scheduled' => '📅 Meeting Scheduled',
            'reminder' => '🔔 Meeting Reminder',
            'ended' => '🔔 Meeting Ended',
        ][$this->kind];
    }

    private function companyName(): string
    {
        return $this->meeting->contact->company_name ?? 'the customer';
    }

    private function timeRange(): string
    {
        $start = $this->meeting->startsAt();
        $end = $this->meeting->endsAt();

        return ($start ? $start->format('h:i A') : '') . ' – ' . ($end ? $end->format('h:i A') : '');
    }

    /**
     * Email body lines, following the requested message formats.
     */
    private function lines(): array
    {
        $start = $this->meeting->startsAt();
        $end = $this->meeting->endsAt();
        $joinLabel = $this->meeting->provider === 'Microsoft Teams' ? 'Join Microsoft Teams Meeting:' : 'Join Google Meet:';
        $where = $this->meeting->meeting_type === 'online'
            ? ($this->meeting->meeting_link ? [$joinLabel, $this->meeting->meeting_link] : [])
            : ($this->meeting->location ? ['Location: ' . $this->meeting->location] : []);

        if ($this->kind === 'ended') {
            return array_merge([
                'The scheduled meeting with ' . $this->companyName() . ' has reached its end time.',
                '',
                'Meeting Time:',
                $this->timeRange() . ($start ? ' (' . $start->format('d M Y') . ')' : ''),
                '',
                'Please update the meeting result:',
                '• Meeting Result (tick "Follow up required" if a follow-up is needed)',
                '• Discussion Ended',
            ]);
        }

        $intro = $this->kind === 'reminder'
            ? ['Your meeting with ' . $this->companyName() . ' will start in ' . self::REMINDER_MINUTES . ' minutes.']
            : ['A meeting with ' . $this->companyName() . ' has been scheduled.'];

        return array_merge($intro, [
            '',
            'Date: ' . ($start ? $start->format('d M Y') : '-'),
            'Start Time: ' . ($start ? $start->format('h:i A') : '-'),
            'End Time: ' . ($end ? $end->format('h:i A') : '-'),
        ], $this->meeting->purpose ? ['Purpose: ' . $this->meeting->purpose] : [], $where ? array_merge([''], $where) : []);
    }
}
