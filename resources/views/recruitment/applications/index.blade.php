@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $query = request()->except(['view', 'page']);
@endphp
<div class="content-wrapper" data-swap-links>

    <form method="GET" action="{{ route('job-applications.index') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <input type="hidden" name="view" value="{{ $board ? 'board' : 'list' }}">
        <div class="row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Position</label>
                <select name="position_id" class="form-control select-picker" data-live-search="true">
                    <option value="">All positions</option>
                    <option value="unassigned" @selected(request('position_id') === 'unassigned')>Unassigned ({{ $unassignedCount }})</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->title }} ({{ $position->code }}){{ $position->status === 'closed' ? ' — closed' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2 rc-col-wide">
                <label class="f-13 text-dark-grey mb-1">Stage</label>
                {{-- Active = still in the hiring process (New … Offer Accepted); Closed = out of it --}}
                <select name="stage" class="form-control select-picker" data-size="12">
                    <option value="active" @selected($stage === 'active') data-content="<span class='rc-stage-opt'><i class='fa fa-stream mr-2'></i>Active stages</span>">Active stages</option>
                    @unless ($board)<option value="all" @selected($stage === 'all') data-content="<span class='rc-stage-opt'><i class='fa fa-layer-group mr-2'></i>All stages</span>">All stages</option>@endunless
                    @foreach (['Active' => array_merge(\App\Models\JobApplication::ACTIVE, ['joined']), 'Closed' => \App\Models\JobApplication::CLOSING] as $group => $keys)
                        <optgroup label="{{ $group }}">
                            @foreach (\App\Models\JobApplication::STAGES as $key => [$label, $class, $icon])
                                @continue(!in_array($key, $keys, true))
                                <option value="{{ $key }}" @selected($stage === $key)
                                    data-content="<span class='rc-stage-opt'><i class='fa {{ $icon }} text-{{ $class }} mr-2'></i>{{ $label }}</span>">{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Source</label>
                <select name="source" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach (\App\Models\Candidate::SOURCES as $key => [$label])
                        <option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @include('recruitment.partials.referrer-filter', ['col' => 'col-md-2 col-6'])
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Name, email, mobile, skill">
            </div>
            <div class="col-md-2 mb-2 text-right">
                <button type="submit" class="btn btn-primary rounded f-14"><i class="fa fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('job-applications.index', ['view' => $board ? 'board' : 'list']) }}" class="btn btn-secondary rounded f-14">Clear</a>
            </div>
        </div>
    </form>

    {{-- Applicants by position: HR shortlists within a position --}}
    @php $tabQuery = request()->except(['position_id', 'page']); @endphp
    <div class="rc-pos-tabs mb-2">
        <a href="{{ route('job-applications.index', $tabQuery) }}" class="{{ request('position_id') ? '' : 'active' }}">All<span>{{ $positionCounts->sum() + $unassignedCount }}</span></a>
        @foreach ($positions->where('status', '<>', 'closed') as $position)
            <a href="{{ route('job-applications.index', $tabQuery + ['position_id' => $position->id]) }}"
               class="{{ request('position_id') == $position->id ? 'active' : '' }}">{{ $position->title }}<span>{{ $positionCounts[$position->id] ?? 0 }}</span></a>
        @endforeach
        @if ($unassignedCount)
            <a href="{{ route('job-applications.index', $tabQuery + ['position_id' => 'unassigned']) }}"
               class="{{ request('position_id') === 'unassigned' ? 'active' : '' }}">Unassigned<span>{{ $unassignedCount }}</span></a>
        @endif
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="btn-group mb-2" data-rc-bar>
            <a href="{{ route('job-applications.index', $query + ['view' => 'board']) }}" class="btn btn-sm {{ $board ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fa fa-columns mr-1"></i>Board</a>
            <a href="{{ route('job-applications.index', $query + ['view' => 'list']) }}" class="btn btn-sm {{ $board ? 'btn-outline-secondary' : 'btn-primary' }}"><i class="fa fa-list mr-1"></i>List</a>
        </div>
        @if ($canManage)
            <a href="{{ route('candidates.create', ['position_id' => is_numeric(request('position_id')) ? request('position_id') : null]) }}" class="btn btn-primary rounded f-14 openRightModal mb-2"><i class="fa fa-user-plus mr-1"></i>Add Candidate</a>
        @endif
    </div>

    @if ($board)
        <div class="rc-board">
            @foreach ($columns as $column)
                @php $cards = $applications->get($column, collect()); @endphp
                <div class="rc-col {{ $cards->isEmpty() ? 'rc-col--empty' : '' }}" data-stage="{{ $column }}">
                    <div class="rc-col-head"><span>{!! \App\Models\JobApplication::badge($column) !!}</span><span class="text-dark-grey">{{ $cards->count() }}</span></div>
                    <div class="rc-col-body">
                    @forelse ($cards as $application)
                        {{-- Click a card for the applicant's details and actions in a side panel --}}
                        <div class="rc-card rc-card--quick" title="Click for details, or drag to another stage"
                             @if ($canManage) draggable="true" @endif data-id="{{ $application->id }}" data-stage="{{ $application->stage }}"
                             data-choices='@json(\App\Services\Recruit\Pipeline::choices($application))'
                             @if ($application->offer) data-offer-id="{{ $application->offer->id }}" data-offer-status="{{ $application->offer->status }}" @endif>
                            <a href="{{ route('job-applications.quick', $application->id) }}" class="openRightModal rc-quick-link d-none" aria-hidden="true"></a>
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('candidates.show', $application->candidate_id) }}" class="rc-name">{{ $application->candidate->name }}</a>
                                @if ($canManage)
                                    <div class="dropdown">
                                        <a href="#" class="text-dark-grey px-1" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu dropdown-menu-right f-13">
                                            @if ($application->stage === 'new' && $application->job_position_id)
                                                <a class="dropdown-item shortlist-app" href="javascript:;" data-id="{{ $application->id }}"><i class="fa fa-star mr-1"></i>Shortlist</a>
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            @foreach (\App\Services\Recruit\Pipeline::choices($application) as $to)
                                                <a class="dropdown-item openRightModal" href="{{ route('job-applications.move-form', [$application->id, 'to' => $to]) }}">Move to {{ \App\Models\JobApplication::STAGES[$to][0] }}</a>
                                            @endforeach
                                            @if (in_array($application->stage, ['new', 'shortlisted', 'interview', 'on_hold']))
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item openRightModal" href="{{ route('recruit-interviews.create', ['application' => $application->id]) }}">Schedule interview</a>
                                            @endif
                                            @if ($application->stage === 'selected' && $application->offer?->status === 'draft')
                                                <a class="dropdown-item openRightModal" href="{{ route('job-offers.respond-form', [$application->offer->id, 'to' => 'sent']) }}">Mark offer sent</a>
                                                <a class="dropdown-item openRightModal" href="{{ route('job-offers.edit', $application->offer->id) }}">Edit offer</a>
                                            @elseif ($application->stage === 'selected' && !$application->offer)
                                                <a class="dropdown-item openRightModal" href="{{ route('job-offers.create', ['application' => $application->id]) }}">Make offer</a>
                                            @endif
                                            @if ($application->stage === 'offer_accepted')
                                                <a class="dropdown-item no-swap" href="{{ route('candidates.convert', $application->candidate_id) }}">Register as Employee</a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="rc-meta">{{ $application->positionLabel() }}</div>
                            @if ($p = $progress[$application->id] ?? null)
                                <div class="rc-progress">
                                    @if ($p['at'] && in_array($application->stage, ['shortlisted', 'interview']))<b>Round {{ $p['at'] }} of {{ $p['of'] }}</b>@if ($p['proposed']) · <span class="text-info">slot to confirm</span>@endif @endif
                                    @if ($p['scores']) · {{ implode(' · ', $p['scores']) }} @endif
                                </div>
                            @endif
                            <div class="rc-meta">{{ $application->candidate->experienceText() }}{{ $application->candidate->current_location ? ' · ' . $application->candidate->current_location : '' }}@if ($application->candidate->expected_ctc) · <span class="text-nowrap">expects ₹{{ rtrim(rtrim(number_format($application->candidate->expected_ctc / 100000, 2), '0'), '.') }}&nbsp;L</span>@endif</div>
                            @if ($canManage && $application->stage === 'new' && $application->job_position_id)
                                {{-- Screening the New column straight from the board --}}
                                <div class="rc-quick-actions">
                                    <button type="button" class="btn btn-primary shortlist-app" data-id="{{ $application->id }}"><i class="fa fa-star mr-1"></i>Shortlist</button>
                                    <a href="{{ route('job-applications.move-form', [$application->id, 'to' => 'rejected']) }}" class="btn btn-outline-danger openRightModal"><i class="fa fa-times mr-1"></i>Reject</a>
                                </div>
                            @endif
                            {{-- Selected with an offer drafted: it is Offered once it is sent --}}
                            @if ($application->stage === 'selected' && $application->offer?->status === 'draft')
                                <div class="rc-offer-draft"><i class="fa fa-file-signature mr-1"></i>Offer drafted{{ $application->offer->reference_no ? ' · ' . $application->offer->reference_no : '' }} · not sent yet</div>
                                @if ($canManage)
                                    <div class="rc-quick-actions">
                                        <a href="{{ route('job-offers.respond-form', [$application->offer->id, 'to' => 'sent']) }}" class="btn btn-primary openRightModal"><i class="fa fa-paper-plane mr-1"></i>Mark sent</a>
                                        <a href="{{ route('job-offers.edit', $application->offer->id) }}" class="btn btn-outline-secondary openRightModal">Edit offer</a>
                                    </div>
                                @endif
                            @endif
                            {{-- Offered: waiting for the candidate's answer, which HR records with its proof --}}
                            @if ($application->stage === 'offered' && $application->offer?->status === 'sent')
                                @php $sentDays = $application->offer->sent_at ? (int) $application->offer->sent_at->diffInDays(now()) : null; @endphp
                                <div class="rc-offer-draft rc-offer-sent"><i class="fa fa-paper-plane mr-1"></i>Offer sent{{ $application->offer->sent_at ? ' ' . $application->offer->sent_at->format(company()->date_format) : '' }}{{ $sentDays ? ' · ' . $sentDays . ' day' . ($sentDays > 1 ? 's' : '') . ' ago' : '' }} · waiting for the reply{{ $application->offer->emailed_at ? ' · emailed, they can answer online' : '' }}</div>
                                @if ($canManage)
                                    <div class="rc-quick-actions">
                                        <a href="{{ route('job-offers.respond-form', [$application->offer->id, 'to' => 'accepted']) }}" class="btn btn-success openRightModal"><i class="fa fa-check mr-1"></i>Accepted</a>
                                        <a href="{{ route('job-offers.respond-form', [$application->offer->id, 'to' => 'declined']) }}" class="btn btn-outline-danger openRightModal">Declined</a>
                                        @if ($application->candidate->email)
                                            <button type="button" class="btn btn-outline-secondary rc-email-offer" data-id="{{ $application->offer->id }}"
                                                title="Email the offer with a link to accept or decline online"><i class="fa fa-envelope mr-1"></i>{{ $application->offer->emailed_at ? 'Resend' : 'Email offer' }}</button>
                                        @endif
                                    </div>
                                @endif
                            @endif
                            @if ($application->stage === 'offer_accepted')
                                <div class="rc-quick-actions">@include('recruitment.partials.register-button', ['candidate' => $application->candidate, 'regButtonClass' => 'btn btn-success'])</div>
                            @endif
                            <div class="rc-meta d-flex justify-content-between mt-1">
                                <span><i class="fa {{ \App\Models\Candidate::SOURCES[$application->source][1] ?? 'fa-circle' }} mr-1"></i>{{ ($application->stage_changed_at ?? $application->created_at)->diffForHumans(null, true) }}</span>
                                @if ($application->candidate->resume)
                                    <a href="{{ $application->candidate->resume->url }}" target="_blank" title="Resume"><i class="fa fa-file-alt"></i></a>
                                @endif
                            </div>
                            @if ($ref = $application->candidate->referrerLabel(false))
                                <div class="rc-meta rc-referred mt-1" title="Referred by {{ $application->candidate->referrerLabel() }}"><i class="fa fa-user-friends mr-1"></i>Referred by {{ $ref }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="rc-empty">No one here yet{{ $canManage ? ' — drag a card in' : '' }}</div>
                    @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded b-shadow-4 table-responsive">
            <table class="table table-hover mb-0 f-14">
                <thead class="bg-light">
                    <tr><th>Candidate</th><th>Position</th><th>Stage</th><th>Source</th><th>Applied</th><th>Rounds</th><th class="text-right pr-4">Action</th></tr>
                </thead>
                <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>
                            <a href="{{ route('candidates.show', $application->candidate_id) }}" class="text-darkest-grey font-weight-600">{{ $application->candidate->name }}</a>
                            <div class="f-12 text-dark-grey">{{ $application->candidate->email }}{{ $application->candidate->mobile ? ' · ' . $application->candidate->mobile : '' }}</div>
                        </td>
                        <td class="f-13">{!! $application->position ? e($application->position->title) : '<span class="text-warning">Unassigned</span>' !!}</td>
                        <td>{!! $application->stageBadge() !!}
                            @if ($application->stage_reason)<div class="f-12 text-dark-grey">{{ $application->stage_reason }}</div>@endif</td>
                        <td class="f-13">{{ \App\Models\Candidate::SOURCES[$application->source][0] ?? $application->source }}
                            @if ($ref = $application->candidate->referrerLabel())<div class="f-12 text-dark-grey"><i class="fa fa-user-friends mr-1"></i>{{ $ref }}</div>@endif</td>
                        <td class="f-13">{{ $application->created_at->format($fmt) }}</td>
                        <td class="f-12">
                            @if ($p = $progress[$application->id] ?? null)
                                @if ($p['at'] && in_array($application->stage, ['shortlisted', 'interview']))<b>{{ $p['at'] }} of {{ $p['of'] }}</b>@endif
                                {{ implode(' · ', $p['scores']) }}
                            @else
                                {{ $application->interviews_done }} done
                            @endif
                        </td>
                        <td class="text-right pr-4">
                            @if ($canManage && $application->stage !== 'joined')
                                <a href="{{ route('job-applications.move-form', $application->id) }}" class="btn btn-sm btn-outline-secondary openRightModal">{{ $application->position ? 'Move' : 'Assign' }}</a>
                            @endif
                            @if ($application->stage === 'offer_accepted')
                                @include('recruitment.partials.register-button', ['candidate' => $application->candidate])
                            @endif
                            <a href="{{ route('candidates.show', $application->candidate_id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-cards.no-record icon="user" message="No applications here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $applications->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
    // Drag a card to another stage. Stages needing details (a reason, an offer) open their form
    // first and move once it is saved; the others move straight away.
    $(function () {
        var board = document.querySelector('.rc-board');
        if (!board) { return; }
        board.scrollLeft = 0;
        var names = @json(collect(\App\Models\JobApplication::STAGES)->map(fn ($s) => $s[0]));
        // Placeholders only in the path: in the query string route() encodes them (":id" -> "%3Aid"),
        // so the query is added here with the real values
        var moveFormBase = "{{ route('job-applications.move-form', ':id') }}";
        var moveUrl = "{{ route('job-applications.move', ':id') }}";
        var offerBase = "{{ route('job-offers.create') }}";
        var offerSentUrl = "{{ route('job-offers.respond-form', ':id') }}?to=sent";
        function moveFormUrl(id, to) { return moveFormBase.replace(':id', encodeURIComponent(id)) + '?to=' + encodeURIComponent(to); }
        function offerUrlFor(id) { return offerBase + '?application=' + encodeURIComponent(id); }
        var needsForm = ['rejected', 'on_hold', 'withdrawn'];
        var dragged = null;

        function openPanel(url) {
            var a = document.createElement('a');
            a.href = url; a.className = 'openRightModal d-none';
            document.body.appendChild(a); $(a).trigger('click'); a.remove();
        }
        function refuse(text) {
            Swal.fire({ icon: 'info', text: text, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
        }

        board.addEventListener('dragstart', function (e) {
            var card = e.target.closest('.rc-card[draggable]');
            if (!card) { return; }
            dragged = card;
            card.classList.add('rc-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        board.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('rc-dragging'); }
            dragged = null;
            board.querySelectorAll('.rc-drop').forEach(function (c) { c.classList.remove('rc-drop'); });
        });
        board.addEventListener('dragover', function (e) {
            var col = e.target.closest('.rc-col');
            if (!col || !dragged) { return; }
            e.preventDefault();
            board.querySelectorAll('.rc-drop').forEach(function (c) { if (c !== col) { c.classList.remove('rc-drop'); } });
            col.classList.add('rc-drop');
            // scroll the board when dragging near its edges
            var r = board.getBoundingClientRect();
            if (e.clientX > r.right - 60) { board.scrollLeft += 20; } else if (e.clientX < r.left + 60) { board.scrollLeft -= 20; }
        });
        board.addEventListener('drop', function (e) {
            var col = e.target.closest('.rc-col');
            if (!col || !dragged) { return; }
            e.preventDefault();
            var id = dragged.dataset.id, from = dragged.dataset.stage, to = col.dataset.stage;
            var choices = JSON.parse(dragged.dataset.choices || '[]');
            col.classList.remove('rc-drop');
            if (to === from) { return; }

            if (to === 'offered') {
                // an offer already drafted: dropping it on Offered is sending it
                if (from === 'selected' && dragged.dataset.offerStatus === 'draft') { openPanel(offerSentUrl.replace(':id', dragged.dataset.offerId)); }
                else if (from === 'selected') { openPanel(offerUrlFor(id)); }
                else { refuse('Only a Selected applicant can be offered. Move them to Selected first.'); }
                return;
            }
            if (to === 'offer_accepted' && from === 'offered' && dragged.dataset.offerStatus === 'sent') {
                openPanel(offerSentUrl.replace(':id', dragged.dataset.offerId).replace('to=sent', 'to=accepted'));   // record the acceptance and its proof
                return;
            }
            if (to === 'offer_accepted' || to === 'joined') {
                refuse(to === 'joined' ? 'An applicant joins by converting them to an employee, from an accepted offer.'
                    : 'An offer is marked accepted from the offer itself.');
                return;
            }
            if (choices.indexOf(to) === -1) {
                refuse('An applicant in ' + names[from] + ' cannot be moved to ' + names[to] + '.');
                return;
            }
            if (needsForm.indexOf(to) !== -1) {
                // a reason is needed: fill the form, it moves when saved
                openPanel(moveFormUrl(id, to));
                return;
            }
            $.easyAjax({
                url: moveUrl.replace(':id', id), type: 'POST', blockUI: true,
                data: { _token: '{{ csrf_token() }}', stage: to },
                success: function (r) { if (r.status === 'success') { PageSwap.reload(); } }
            });
        });
    });
</script>
<style>
    .rc-card[draggable="true"] { cursor: grab; }
    .rc-offer-draft { margin-top: 6px; padding: 5px 8px; border-radius: 6px; background: #fffaeb; color: #93370d; font-size: 12px; }
    .rc-offer-sent { background: #eff8ff; color: #175cd3; }
    .rc-card.rc-dragging { opacity: .45; }
    .rc-col.rc-drop { outline: 2px dashed var(--epic-blue, #cb6b4f); outline-offset: -4px; background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 6%, #f4f6f9); }
</style>
@endpush
@include('recruitment.partials.standard-bar', ['searchLabel' => $board ? 'Search in the board' : 'Search in the list', 'form' => '#rc-filter-form', 'active' => request()->anyFilled(['stage', 'source', 'referred_by', 'search'])])
@endsection

@push('scripts')
<script>
    // A card opens the applicant's details in the side panel - unless the click
    // was on something of its own (the name, the menu, a button, the resume).
    $('body').on('click', '.rc-card--quick', function (e) {
        if ($(e.target).closest('a, button, .dropdown, .dropdown-menu').length) { return; }
        $(this).find('.rc-quick-link').trigger('click');
    });
    // The name opens the panel too; the full profile is a button inside it
    $('body').on('click', '.rc-card--quick .rc-name', function (e) {
        e.preventDefault();
        $(this).closest('.rc-card--quick').find('.rc-quick-link').trigger('click');
    });

    // HR shortlists; the system then suggests round 1's slot
    $('body').on('click', '.rc-card .shortlist-app', function () {
        $.easyAjax({
            url: '{{ route('job-applications.move', ':id') }}'.replace(':id', $(this).data('id')), type: 'POST', blockUI: true,
            data: {_token: '{{ csrf_token() }}', stage: 'shortlisted'},
            success: function (response) { if (response.status === 'success') { PageSwap.reload(); } }
        });
    });

    // Email a sent offer with the link for the candidate to accept or decline it online
    $('body').on('click', '.rc-card .rc-email-offer', function () {
        $.easyAjax({
            url: '{{ route('job-offers.email', ':id') }}'.replace(':id', $(this).data('id')), type: 'POST', blockUI: true,
            data: {_token: '{{ csrf_token() }}'},
            success: function (response) { if (response.status === 'success') { PageSwap.reload(); } }
        });
    });
</script>
@endpush
