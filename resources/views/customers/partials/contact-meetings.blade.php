{{--
    Meetings tab of a lead's or client's page: meetings grouped by project, one collapsible section per project.
    $meetings, $from ('lead-contact' | 'client'), $contactParam ('lead_id' | 'client_id'), $contactId, $contactLabel ('Lead' | 'Client')
--}}
@php
    // Edit and Meet Notes open on the Meetings page (same form and notes pop-up); Back and saving return here
    $meetingsPage = fn ($hash) => route('customers.index', ['from' => $from, $contactParam => $contactId]) . '#' . $hash;

    $projectGroups = \App\Models\CustomerMeeting::groupByProject($meetings);
@endphp

@include('customers.partials.project-group-assets')

<!-- ROW START -->
<div class="row pb-5">
    <div class="col-lg-12 col-md-12 mb-4 mb-xl-0 mb-lg-4 meeting-carousel-groups">
        @forelse ($projectGroups as $projectId => $groupMeetings)
            @include('customers.partials.project-group-header', [
                'projectName' => $groupMeetings->first()->leadProject->name ?? 'Project',
                'count' => $groupMeetings->count(),
                'open' => $loop->first,
            ])
            {{-- This project's meetings: one card at a time, ‹ › to move between them --}}
            <div class="meeting-carousel">
                <button type="button" class="meeting-carousel-arrow prev" aria-label="Previous meetings"><i class="fa fa-chevron-left"></i></button>
                <div class="meeting-track">
                @foreach ($groupMeetings as $meeting)
                    <div class="meeting-slide">
                    <div class="card meeting-card lead-meeting-card h-100" data-meeting-id="{{ $meeting->id }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2" style="gap: 8px;">
                                <h3 class="meeting-card-title mb-0">{{ $meeting->title ?: ucfirst($meeting->meeting_type) . ' Meeting' }}</h3>
                                <i class="fa fa-thumbtack meeting-card-pin" aria-hidden="true"></i>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <div class="d-flex flex-wrap" style="gap: 6px;">
                                    <span class="meeting-chip meeting-chip-type">{{ ucfirst($meeting->meeting_type) }}</span>
                                    @if ($meeting->status)
                                        <span class="meeting-chip">{{ $meeting->status }}</span>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap meeting-card-actions" style="gap: 6px;">
                                    <a href="{{ $meetingsPage('edit-meeting-' . $meeting->id) }}" class="btn btn-outline-primary">Edit</a>
                                    @if ($meeting->meeting_type === 'online' && $meeting->provider === 'Google Meet' && $meeting->meeting_link)
                                        <a href="{{ $meetingsPage('meet-notes-' . $meeting->id) }}" class="btn btn-outline-info">
                                            Meet Notes @if ($meeting->meet_notes)<i class="fa fa-check-circle ml-1"></i>@endif
                                        </a>
                                    @endif
                                    <a href="{{ route('customers.meetings.result', ['id' => $meeting->id, 'from' => $from, $contactParam => $contactId]) }}" class="btn btn-outline-success">Meeting Result</a>
                                    <button type="button" class="btn btn-outline-danger delete-lead-meeting-btn"
                                            data-meeting-id="{{ $meeting->id }}">Delete</button>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="meeting-card-details">
                                <p><b>{{ $contactLabel }}:</b> {{ $meeting->contact->company_name ?? '-' }}</p>
                                @if ($meeting->leadProject)
                                    <p><b>Project:</b> {{ $meeting->leadProject->name }}</p>
                                @endif
                                @if ($meeting->title)
                                    <p><b>Title:</b> {{ $meeting->title }}</p>
                                @endif
                                <p class="small">
                                    <b>{{ $meeting->purpose ?: 'Meeting' }}</b> ·
                                    {{ $meeting->meeting_date ? $meeting->meeting_date->format('d M Y') : '-' }} ·
                                    {{ $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') : '' }}
                                    - {{ $meeting->end_time ? \Carbon\Carbon::parse($meeting->end_time)->format('h:i A') : '' }}
                                </p>
                                @if ($meeting->lead_owner)
                                    <p class="small"><b>Lead Owner:</b> {{ $meeting->lead_owner }}</p>
                                @endif
                                @if ($meeting->meeting_link)
                                    <p class="small meeting-card-link"><b>Link:</b> <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener">{{ $meeting->meeting_link }}</a></p>
                                @endif
                                @if ($meeting->location)
                                    <p class="small"><b>Location:</b> {{ $meeting->location }}</p>
                                @endif
                            </div>

                            @if ($meeting->result === 'followup')
                                <div class="meeting-result-pill meeting-result-followup">
                                    <b>Follow-up</b> {{ $meeting->followup_action }} on {{ $meeting->followup_date ? $meeting->followup_date->format('d M Y') : '-' }}
                                </div>
                            @elseif ($meeting->result === 'outcome')
                                <div class="meeting-result-pill">
                                    <b>Result</b> {{ $meeting->outcome }}@if ($meeting->followup_date) · Follow-up on {{ $meeting->followup_date->format('d M Y') }}@endif
                                </div>
                            @elseif ($meeting->result === 'ended')
                                <div class="meeting-result-pill meeting-result-ended">
                                    <b>Discussion Ended</b> {{ $meeting->end_reason }}
                                </div>
                            @endif
                        </div>
                    </div>
                    </div>
                @endforeach
                </div>
                <button type="button" class="meeting-carousel-arrow next" aria-label="Next meetings"><i class="fa fa-chevron-right"></i></button>
            </div>
            {{-- "1 / 3": which card is showing --}}
            <div class="meeting-carousel-count"></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body text-muted">No meetings with this {{ strtolower($contactLabel) }} yet.</div>
            </div>
        @endforelse
    </div>
</div>
<!-- ROW END -->

{{-- After the cards: its script binds to them --}}
@include('customers.partials.meeting-carousel-assets')

<script>
    $('body').off('click', '.delete-lead-meeting-btn').on('click', '.delete-lead-meeting-btn', function () {
        var id = $(this).data('meeting-id');

        if (!confirm('Delete this meeting? Its Google Calendar event (if any) will be cancelled.')) return;

        $.easyAjax({
            url: "{{ route('customers.meetings.destroy', ':id') }}".replace(':id', id),
            type: 'POST',
            blockUI: true,
            data: { _token: '{{ csrf_token() }}', _method: 'DELETE' },
            success: function (r) {
                if (r.status === 'success') {
                    var card = $('.lead-meeting-card[data-meeting-id="' + id + '"]');
                    var group = card.closest('.project-meetings-group');
                    var left = group.find('.lead-meeting-card').length - 1;

                    card.closest('.meeting-slide').remove();

                    // Last meeting of the project: drop its section; otherwise update its count
                    if (left === 0) {
                        group.remove();
                    } else {
                        group.find('.group-count').text(left);
                        if (window.updateMeetingCarousels) updateMeetingCarousels();
                    }
                }
            }
        });
    });
</script>
