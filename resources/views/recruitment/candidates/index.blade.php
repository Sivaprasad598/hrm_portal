@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php $fmt = company()->date_format; @endphp
<div class="content-wrapper">

    <form method="GET" action="{{ route('candidates.index') }}" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-lg-3 col-md-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Name, email, mobile, skill, company">
            </div>
            <div class="col-lg-2 col-md-3 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Status</label>
                <select name="status" class="form-control select-picker">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>In a hiring process</option>
                    <option value="pool" @selected(request('status') === 'pool')>In the pool only</option>
                    <option value="joined" @selected(request('status') === 'joined')>Joined</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Source</label>
                <select name="source" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach (\App\Models\Candidate::SOURCES as $key => [$label])
                        <option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 mb-2">
                <label class="f-13 text-dark-grey mb-1">Position applied</label>
                <select name="position_id" class="form-control select-picker" data-live-search="true">
                    <option value="">Any</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->title }} ({{ $position->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Exp. from</label>
                <input type="number" step="0.5" min="0" name="exp_min" value="{{ request('exp_min') }}" class="form-control f-14">
            </div>
            <div class="col-lg-1 col-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">to</label>
                <input type="number" step="0.5" min="0" name="exp_max" value="{{ request('exp_max') }}" class="form-control f-14">
            </div>
            <div class="col-lg-1 col-6 mb-2 text-right">
                <button type="submit" class="btn btn-primary rounded f-14 w-100"><i class="fa fa-filter"></i></button>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="f-15 font-weight-600 text-dark">{{ $candidates->total() }} candidate(s)
            @if (request()->hasAny(['search', 'status', 'source', 'position_id', 'exp_min', 'exp_max']))
                <a href="{{ route('candidates.index') }}" class="f-13 font-weight-normal ml-2">Clear filters</a>
            @endif
        </span>
        @if ($canManage)
            <a href="{{ route('candidates.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-user-plus mr-1"></i>Add Candidate</a>
        @endif
    </div>

    <div class="bg-white rounded b-shadow-4 table-responsive">
        <table class="table table-hover mb-0 f-14">
            <thead class="bg-light">
                <tr>
                    <th>Candidate</th>
                    <th>Experience</th>
                    <th>Skills</th>
                    <th>Applied for</th>
                    <th>Source</th>
                    <th class="text-right pr-4">Resume</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($candidates as $candidate)
                {{-- Click a row for the candidate's details in the side panel --}}
                <tr class="rc-row--quick" title="Click for details">
                    <td>
                        <a href="{{ route('candidates.quick', $candidate->id) }}" class="openRightModal rc-quick-link d-none" aria-hidden="true"></a>
                        <div class="d-flex align-items-center">
                            <span class="rc-avatar mr-2">{{ $candidate->initials() }}</span>
                            <div>
                                <a href="{{ route('candidates.show', $candidate->id) }}" class="text-darkest-grey font-weight-600 rc-row-name">{{ $candidate->name }}</a>
                                @if ($candidate->user_id) <span class="badge badge-dark">Employee</span> @endif
                                <div class="f-12 text-dark-grey">{{ $candidate->email }}{{ $candidate->mobile ? ' · ' . $candidate->mobile : '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="f-13">
                        {{ $candidate->experienceText() }}
                        @if ($candidate->current_designation || $candidate->current_company)
                            <div class="f-12 text-dark-grey">{{ trim($candidate->current_designation . ($candidate->current_company ? ' at ' . $candidate->current_company : '')) }}</div>
                        @endif
                        @if ($candidate->qualification)
                            <div class="f-12 text-dark-grey">{{ $candidate->qualification }}</div>
                        @endif
                    </td>
                    <td style="max-width: 260px">
                        @foreach (array_slice($candidate->skills ?? [], 0, 6) as $skill)
                            <span class="rc-chip">{{ $skill }}</span>
                        @endforeach
                    </td>
                    <td class="f-13">
                        @forelse ($candidate->applications->take(3) as $application)
                            <div>{{ $application->positionLabel() }} {!! $application->stageBadge() !!}</div>
                        @empty
                            <span class="text-dark-grey">--</span>
                        @endforelse
                    </td>
                    <td class="f-13">{{ $candidate->sourceLabel() }}<div class="f-12 text-dark-grey">{{ $candidate->created_at->format($fmt) }}</div></td>
                    <td class="text-right pr-4">
                        @if ($candidate->resume)
                            <a href="{{ $candidate->resume->url }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ $candidate->resume->filename }}"><i class="fa fa-file-alt"></i></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-cards.no-record icon="user" message="No candidates found." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Where you are in the list, how many per page, and the pages --}}
    @if ($candidates->total() > 0)
        <div class="rc-pager">
            <span class="text-dark-grey f-13">Showing <strong>{{ $candidates->firstItem() }}–{{ $candidates->lastItem() }}</strong> of <strong>{{ $candidates->total() }}</strong></span>
            <form method="GET" class="d-inline-flex align-items-center f-13 text-dark-grey mb-0">
                @foreach (request()->except(['per_page', 'page']) as $key => $value)
                    @if (!is_array($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <label for="per_page" class="mb-0 mr-2">Show</label>
                <select id="per_page" name="per_page" class="form-control form-control-sm" style="width: 72px" onchange="this.form.submit()">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected($candidates->perPage() === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </form>
            <div class="ml-md-auto">{{ $candidates->onEachSide(1)->links() }}</div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // A row opens the candidate's details in the side panel - unless the click
    // was on something of its own (the resume link, a button). The name too;
    // the full profile is a button inside the panel.
    $('body').on('click', '.rc-row--quick', function (e) {
        if ($(e.target).closest('a:not(.rc-row-name), button').length) { return; }
        e.preventDefault();
        $(this).find('.rc-quick-link').trigger('click');
    });
</script>
@endpush
