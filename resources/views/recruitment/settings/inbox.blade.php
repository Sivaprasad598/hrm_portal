@extends('layouts.app')

@section('content')
@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
@endphp
<div class="content-wrapper">

    <form method="GET" action="{{ route('recruitment-settings.inbox') }}" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">From</label>
                <select name="channel" class="form-control select-picker">
                    <option value="">All</option>
                    <option value="email" @selected(request('channel') === 'email')>Careers mailbox</option>
                    <option value="api" @selected(request('channel') === 'api')>Website</option>
                </select>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Outcome</label>
                <select name="status" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach (\App\Models\RecruitInbound::STATUSES as $key => [$label])
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Sender, subject">
            </div>
            <div class="col-md-3 mb-2 text-right">
                <button type="submit" class="btn btn-primary rounded f-14"><i class="fa fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('recruitment-settings.index') }}" class="btn btn-secondary rounded f-14">Settings</a>
            </div>
        </div>
    </form>

    <p class="f-13 text-dark-grey">Everything read from the careers mailbox or received from the website, as it arrived, and what was done with it.
        Emails that were not job applications are listed too, with the reason, so nothing is missed silently.</p>

    <div class="bg-white rounded b-shadow-4 table-responsive">
        <table class="table table-hover mb-0 f-14">
            <thead class="bg-light"><tr><th>Received</th><th>From</th><th>Subject</th><th>Outcome</th><th>Candidate</th><th></th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="f-13 text-nowrap"><i class="fa fa-{{ $item->channel === 'email' ? 'envelope' : 'globe' }} mr-1 text-dark-grey"></i>{{ ($item->received_at ?? $item->created_at)->tz($tz)->format($fmt . ' h:i A') }}</td>
                    <td class="f-13">{{ $item->from_name }}<div class="text-dark-grey">{{ $item->from_email }}</div></td>
                    <td class="f-13">{{ \Illuminate\Support\Str::limit($item->subject, 80) }}</td>
                    <td>{!! $item->statusBadge() !!}@if ($item->reason)<div class="f-12 text-dark-grey">{{ $item->reason }}</div>@endif</td>
                    <td class="f-13">@if ($item->candidate)<a href="{{ route('candidates.show', $item->candidate_id) }}">{{ $item->candidate->name }}</a>@else -- @endif</td>
                    <td class="text-right"><a href="{{ route('recruitment-settings.inbox-show', $item->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-cards.no-record icon="inbox" message="Nothing received yet." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
