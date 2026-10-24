{{--
    Meet Notes pop-up for meeting cards (contact-meetings): the lead's Meetings tab, a client's Meetings tab and
    Lead Team Meetings open a meeting's notes here, on the same page, instead of going to the Meetings page.
    Lead Team meetings show the Lead Team Meeting Notes table; the transcript is underneath.
    Included once per page (@once) by customers.partials.contact-meetings.
--}}
@once
{{-- ==================== MEET NOTES POP-UP ====================
     Same notes as the Meetings page pop-up, opened here so the page stays on Lead Team Meetings.
     Lead Team meetings show the Lead Team Meeting Notes table; the transcript is underneath. --}}
<div class="modal fade" id="ltmNotesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Meet Notes</h5>
                    <div class="text-muted small" id="ltmNotesTitle"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="ltmNotesStatus" class="small mb-2"></div>
                <div id="ltmNotesMessage" class="alert alert-light border small mb-0" style="display: none;"></div>
                <div id="ltmNotesTable" style="display: none;"></div>
                <div id="ltmNotesText" class="border rounded p-3 bg-white" style="display: none; white-space: pre-wrap; font-size: 14px; line-height: 1.6;"></div>
                <details id="ltmTranscriptBox" class="mt-3" style="display: none;">
                    <summary class="small text-muted" style="cursor: pointer;">Show full transcript</summary>
                    <div id="ltmTranscriptText" class="border rounded p-3 mt-2 bg-light small" style="white-space: pre-wrap; max-height: 300px; overflow: auto;"></div>
                </details>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener" class="btn btn-outline-secondary" id="ltmNotesDocLink" style="display: none;">Open in Google Docs</a>
                <button type="button" class="btn btn-primary" id="ltmNotesRefreshBtn" style="display: none;">Check now</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        const NOTES_URL = "{{ route('customers.meetings.notes', ':id') }}";
        const REFRESH_URL = "{{ route('customers.meetings.notes.refresh', ':id') }}";
        let notesId = null;

        function render(r) {
            $('#ltmNotesTitle').text(r.title || '');
            $('#ltmNotesStatus').html(r.source === 'ai'
                ? '<span class="badge badge-primary">AI notes · CRM Notes extension</span>'
                : (r.auto_notes_enabled ? '<span class="badge badge-success">Automatic notes on</span>'
                    : '<span class="badge badge-secondary">Automatic notes off</span>'));
            if (r.fetched_at) $('#ltmNotesStatus').append($('<span class="text-muted ml-2">').text('Saved ' + r.fetched_at));

            $('#ltmNotesTable').html(r.lead_team_html || '').toggle(!!r.lead_team_html);
            $('#ltmNotesText').text(r.notes || '').toggle(!!r.notes && !r.lead_team_html);
            $('#ltmNotesMessage').text(r.message || '').toggle(!!r.message);
            $('#ltmNotesDocLink').attr('href', r.url || '#').toggle(!!r.url);
            $('#ltmNotesRefreshBtn').toggle(!r.notes);
            $('#ltmTranscriptText').text(r.transcript || '');
            $('#ltmTranscriptBox').toggle(!!r.transcript);
        }

        function fail(xhr) {
            $('#ltmNotesMessage').text(xhr.responseJSON?.message || 'Something went wrong. Please try again.').show();
        }

        $(document).off('click.ltmNotes').on('click.ltmNotes', '.ltm-notes-btn', function () {
            notesId = $(this).data('meeting-id');
            $('#ltmNotesTitle, #ltmNotesStatus').text('');
            $('#ltmNotesTable, #ltmNotesText, #ltmNotesDocLink, #ltmNotesRefreshBtn, #ltmTranscriptBox').hide();
            $('#ltmNotesMessage').html('<i class="fa fa-spinner fa-spin"></i> Loading...').show();
            $('#ltmNotesModal').modal('show');
            $.get(NOTES_URL.replace(':id', notesId)).done(render).fail(fail);
        });

        $('#ltmNotesRefreshBtn').on('click', function () {
            let btn = $(this).prop('disabled', true).text('Checking...');
            $.post(REFRESH_URL.replace(':id', notesId), {_token: '{{ csrf_token() }}'})
                .done(r => {
                    render(r);
                    if (r.notes) $('.ltm-notes-btn[data-meeting-id="' + notesId + '"]').html('Meet Notes <i class="fa fa-check-circle ml-1"></i>');
                })
                .fail(fail)
                .always(() => btn.prop('disabled', false).text('Check now'));
        });
    });
</script>
@endonce
