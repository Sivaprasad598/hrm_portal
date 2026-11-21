{{-- To the employee who referred a candidate (App\Notifications\RecruitReferralNotice). --}}
<x-cards.notification :notification="$notification"
                      :link="route('dashboard')"
                      :image="user()->image_url"
                      :title="($notification->data['type'] ?? 'added') === 'joined' ? 'Your referral has joined' : 'Referral received'"
                      :text="$notification->data['text'] ?? ($notification->data['candidate'] ?? '')"
                      :time="$notification->created_at"/>
