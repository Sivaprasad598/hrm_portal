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
    <div class="col-lg-12 col-md-12 mb-4 mb-xl-0 mb-lg-4">
        @forelse ($projectGroups as $projectId => $groupMeetings)
            @include('customers.partials.project-group-header', [
                'projectName' => $groupMeetings->first()->leadProject->name ?? 'Project',
                'count' => $groupMeetings->count(),
                'open' => $loop->first,
            ])
                        @foreach ($groupMeetings as $meeting)
                            <div class="card mb-2 lead-meeting-card" data-meeting-id="{{ $meeting->id }}">
                                <div class="card-body d-flex justify-content-between align-items-start flex-wrap" style="gap: 16px;">
                                    <div>
                                        <h3 class="h6 mb-1 font-weight-bold">
                                            {{ $meeting->title ?: ucfirst($meeting->meeting_type) . ' Meeting' }}
                                            <span class="badge badge-light border ml-1">{{ ucfirst($meeting->meeting_type) }}</span>
                                            @if ($meeting->status)
                                                <span class="badge badge-light border text-primary ml-1">{{ $meeting->status }}</span>
                                            @endif
                                        </h3>
                                        <p class="mb-1"><b>{{ $contactLabel }}:</b> {{ $meeting->contact->company_name ?? '-' }}</p>
                                        @if ($meeting->leadProject)
                                            <p class="mb-1"><b>Project:</b> {{ $meeting->leadProject->name }}</p>
                                        @endif
                                        @if ($meeting->title)
                                            <p class="mb-1"><b>Title:</b> {{ $meeting->title }}</p>
                                        @endif
                                        <p class="mb-1 text-muted">
                                            {{ $meeting->purpose }} ·
                                            {{ $meeting->meeting_date ? $meeting->meeting_date->format('d M Y') : '-' }} ·
                                            {{ $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') : '' }}
                                            - {{ $meeting->end_time ? \Carbon\Carbon::parse($meeting->end_time)->format('h:i A') : '' }}
                                        </p>
                                        @if ($meeting->lead_owner)
                                            <p class="mb-1 text-muted">Lead Owner: {{ $meeting->lead_owner }}</p>
                                        @endif
                                        @if ($meeting->meeting_link)
                                            <p class="mb-1 text-muted">Link: <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener">{{ $meeting->meeting_link }}</a></p>
                                        @endif
                                        @if ($meeting->location)
                                            <p class="mb-0 text-muted">Location: {{ $meeting->location }}</p>
                                        @endif
                                        @if ($meeting->result === 'outcome')
                                            <p class="mb-0 mt-1 small"><span class="badge badge-success">Result</span>
                                                {{ $meeting->outcome }}@if ($meeting->followup_date) · Follow-up on {{ $meeting->followup_date->format('d M Y') }}@endif</p>
                                        @elseif ($meeting->result === 'ended')
                                            <p class="mb-0 mt-1 small"><span class="badge badge-danger">Discussion Ended</span>
                                                {{ $meeting->end_reason }}</p>
                                        @endif
                                    </div>
                                    <div class="d-flex" style="gap: 8px;">
                                        <a href="{{ $meetingsPage('edit-meeting-' . $meeting->id) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                        @if ($meeting->meeting_type === 'online' && $meeting->provider === 'Google Meet' && $meeting->meeting_link)
                                            <a href="{{ $meetingsPage('meet-notes-' . $meeting->id) }}" class="btn btn-outline-info btn-sm">
                                                Meet Notes @if ($meeting->meet_notes)<i class="fa fa-check-circle ml-1"></i>@endif
                                            </a>
                                        @endif
                                        <a href="{{ route('customers.meetings.result', $meeting->id) }}" class="btn btn-outline-success btn-sm">Meeting Result</a>
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-lead-meeting-btn"
                                                data-meeting-id="{{ $meeting->id }}">Delete</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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

                    card.remove();

                    // Last meeting of the project: drop its section; otherwise update its count
                    if (left === 0) {
                        group.remove();
                    } else {
                        group.find('.group-count').text(left);
                    }
                }
            }
        });
    });
</script>
