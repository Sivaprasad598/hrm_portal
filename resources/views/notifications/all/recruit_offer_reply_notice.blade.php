{{-- To HR: a candidate answered their offer online (App\Notifications\RecruitOfferReplyNotice). --}}
<x-cards.notification :notification="$notification"
                      :link="route('candidates.show', $notification->data['id'] ?? 0)"
                      :image="user()->image_url"
                      :title="($notification->data['answer'] ?? '') === 'accepted' ? 'Offer accepted' : 'Offer declined'"
                      :text="$notification->data['text'] ?? ($notification->data['candidate'] ?? '')"
                      :time="$notification->created_at"/>
