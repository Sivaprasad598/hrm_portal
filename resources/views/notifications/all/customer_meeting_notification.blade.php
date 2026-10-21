{{-- Customers → Meetings: scheduled / 15-minute reminder / ended (opens Meeting Result) --}}
@php
    // Scheduled / reminder: the Meetings page of the meeting's lead or client (Lead Contacts or Clients), else Customers
    $notifiedMeeting = \App\Models\CustomerMeeting::select(['id', 'lead_id', 'client_id'])->find($notification->data['id'] ?? null);
    $meetingsFrom = $notifiedMeeting ? ($notifiedMeeting->client_id ? 'client' : ($notifiedMeeting->lead_id ? 'lead-contact' : null)) : null;
@endphp
<x-cards.notification :notification="$notification"
                      :link="($notification->data['kind'] ?? '') === 'ended'
                          ? route('customers.meetings.result', $notification->data['id'])
                          : route('customers.index', array_filter(['from' => $meetingsFrom])) . '#meetings'"
                      :image="user()->image_url" :title="$notification->data['title'] ?? 'Customer Meeting'"
                      :text="$notification->data['text'] ?? ''" :time="$notification->created_at"/>
