{{-- Upcoming joinings: offer letters whose joining date is in the next 30 days (not withdrawn),
     for whoever makes offer letters. The day itself also rings the bell (offer-letters:joining-reminders). --}}
@if (\App\Http\Controllers\OfferLetterController::canUse() && \App\Support\SchemaCache::hasColumn('offer_letters', 'joining_date'))
    @php
        $today = now(company()->timezone)->startOfDay();
        $joinings = \Illuminate\Support\Facades\DB::table('offer_letters')
            ->where('company_id', company()->id)->whereNull('cancelled_at')
            ->whereBetween('joining_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->orderBy('joining_date')->orderBy('person_name')->limit(15)
            ->get(['id', 'person_name', 'designation', 'location', 'joining_date', 'reference_no']);
    @endphp
    <div class="col-sm-12">
        <x-cards.data class="e-d-info mb-2" title="Upcoming Joinings" padding="false">
            <x-table>
                @forelse ($joinings as $j)
                    @php
                        $date = \Carbon\Carbon::parse($j->joining_date);
                        $days = (int) $today->diffInDays($date);
                    @endphp
                    <tr>
                        <td class="pl-20">
                            <a href="{{ route('offer-letters.show', $j->id) }}" class="text-darkest-grey font-weight-500">{{ $j->person_name }}</a>
                            <div class="f-11 text-lightest">{{ $j->designation }}{{ $j->location ? ' · ' . $j->location : '' }} · Offer {{ $j->reference_no }}</div>
                        </td>
                        <td class="pr-20 text-right text-nowrap">
                            @if ($days === 0)
                                <span class="badge badge-light text-success p-2">@lang('app.today')</span>
                            @elseif ($days <= 7)
                                <span class="badge badge-light text-warning p-2">in {{ $days }} {{ $days === 1 ? 'day' : 'days' }}</span>
                            @else
                                <span class="badge badge-light p-2">in {{ $days }} days</span>
                            @endif
                            <br><span class="f-11">Joins {{ $date->translatedFormat(company()->date_format) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td class="pl-20 py-3 f-13 text-lightest">No joinings in the next 30 days.</td></tr>
                @endforelse
            </x-table>
        </x-cards.data>
    </div>
@endif
