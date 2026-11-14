@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $query = request()->except(['view', 'page']);
@endphp
<div class="content-wrapper">

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
            <div class="col-md-2 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Stage</label>
                <select name="stage" class="form-control select-picker">
                    <option value="active" @selected($stage === 'active')>Still moving</option>
                    @unless ($board)<option value="all" @selected($stage === 'all')>All</option>@endunless
                    @foreach (\App\Models\JobApplication::STAGES as $key => [$label])
                        <option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>
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
                    @forelse ($cards as $application)
                        {{-- Click a card for the applicant's details and actions in a side panel --}}
                        <div class="rc-card rc-card--quick" title="Click for details, or drag to another stage"
                             @if ($canManage) draggable="true" @endif data-id="{{ $application->id }}" data-stage="{{ $application->stage }}"
                             data-choices='@json(\App\Services\Recruit\Pipeline::choices($application))'>
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
                                            @if ($application->stage === 'selected')
                                                <a class="dropdown-item openRightModal" href="{{ route('job-offers.create', ['application' => $application->id]) }}">Make offer</a>
                                            @endif
                                            @if ($application->stage === 'offer_accepted')
                                                <a class="dropdown-item" href="{{ route('candidates.convert', $application->candidate_id) }}">Convert to employee</a>
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
                            <div class="rc-meta d-flex justify-content-between mt-1">
                                <span><i class="fa {{ \App\Models\Candidate::SOURCES[$application->source][1] ?? 'fa-circle' }} mr-1"></i>{{ ($application->stage_changed_at ?? $application->created_at)->diffForHumans(null, true) }}</span>
                                @if ($application->candidate->resume)
                                    <a href="{{ $application->candidate->resume->url }}" target="_blank" title="Resume"><i class="fa fa-file-alt"></i></a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="f-12 text-dark-grey text-center my-3">None</p>
                    @endforelse
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
                        <td class="f-13">{{ \App\Models\Candidate::SOURCES[$application->source][0] ?? $application->source }}</td>
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
        var moveForm = "{{ route('job-applications.move-form', [':id', 'to' => ':to']) }}";
        var moveUrl = "{{ route('job-applications.move', ':id') }}";
        var offerUrl = "{{ route('job-offers.create', ['application' => ':id']) }}";
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
                if (from === 'selected') { openPanel(offerUrl.replace(':id', id)); }
                else { refuse('Only a Selected applicant can be offered. Move them to Selected first.'); }
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
                openPanel(moveForm.replace(':id', id).replace(':to', to));
                return;
            }
            $.easyAjax({
                url: moveUrl.replace(':id', id), type: 'POST', blockUI: true,
                data: { _token: '{{ csrf_token() }}', stage: to },
                success: function (r) { if (r.status === 'success') { window.location.reload(); } }
            });
        });
    });
</script>
<style>
    .rc-card[draggable="true"] { cursor: grab; }
    .rc-card.rc-dragging { opacity: .45; }
    .rc-col.rc-drop { outline: 2px dashed var(--epic-blue, #cb6b4f); outline-offset: -4px; background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 6%, #f4f6f9); }
</style>
@endpush
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'active' => request()->anyFilled(['stage', 'source', 'search'])])
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
            success: function (response) { if (response.status === 'success') { window.location.reload(); } }
        });
    });
</script>
@endpush
