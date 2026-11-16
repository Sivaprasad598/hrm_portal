@php $d = $notification->data; @endphp

<x-cards.notification :notification="$notification"
                      :link="route('offer-letters.show', $d['offer_letter_id'] ?? 0)"
                      :image="user()->image_url"
                      :title="'Joining today: ' . ($d['name'] ?? '')"
                      :text="trim(($d['designation'] ?? '') . (!empty($d['location']) ? ' · ' . $d['location'] : '') . ' · Offer ' . ($d['reference_no'] ?? ''))"
                      :time="$notification->created_at"/>
