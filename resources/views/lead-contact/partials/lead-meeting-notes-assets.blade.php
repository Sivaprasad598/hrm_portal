{{--
    Styles and Analyse / Edit / Save for the Lead Team Meeting Notes table (lead-meeting-notes partial).
    Included once on each page that shows the table: the Lead Team tab and the Meetings page (Meet Notes pop-up).
--}}
<style>
    .lmn-table th { width: 38%; background: #f8fafc; font-size: 12px; font-weight: 600; color: #475569; vertical-align: top; }
    .lmn-table td { font-size: 12px; color: #1e293b; vertical-align: top; }
    .lmn-fit { display: inline-flex; align-items: center; padding: 2px 8px; border: 1px solid; border-radius: 12px; font-size: 12px; font-weight: 600; }
</style>
<script>
    $(function () {
        const ANALYSE_URL = "{{ route('lead-meetings.analyse', ':id') }}";
        const NOTES_URL = "{{ route('lead-meetings.update', ':id') }}";

        function lmnToast(text, icon = 'success') {
            Swal.fire({icon: icon, text: text, toast: true, position: 'top-end', timer: 3000,
                timerProgressBar: true, showConfirmButton: false});
        }

        // The table is replaced by the saved version; every copy of it on the page (card and pop-up) is updated
        function replaceNotes(box, r) {
            if (r.notes && r.notes.html) {
                $('.lead-meeting-notes[data-meeting-id="' + box.data('meeting-id') + '"]').replaceWith(r.notes.html);
            }
        }

        $(document).off('.lmn').on('click.lmn', '.lmn-analyse', function () {
            let box = $(this).closest('.lead-meeting-notes');
            let btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Analysing…');
            $.ajax({url: ANALYSE_URL.replace(':id', box.data('meeting-id')), type: 'POST', data: {_token: '{{ csrf_token() }}'}})
                .done(r => { replaceNotes(box, r); lmnToast('Lead meeting notes updated from the transcript.'); })
                .fail(xhr => { btn.prop('disabled', false).html('<i class="fa fa-magic mr-1"></i>Analyse'); lmnToast(xhr.responseJSON?.message || 'Could not analyse the meeting.', 'error'); });
        }).on('click.lmn', '.lmn-edit', function () {
            let box = $(this).closest('.lead-meeting-notes');
            box.find('.lmn-view').hide();
            box.find('.lmn-form').show();
        }).on('click.lmn', '.lmn-cancel', function () {
            let box = $(this).closest('.lead-meeting-notes');
            box.find('.lmn-form').hide();
            box.find('.lmn-view').show();
        }).on('submit.lmn', '.lmn-form', function (e) {
            e.preventDefault();
            let box = $(this).closest('.lead-meeting-notes');
            $.ajax({url: NOTES_URL.replace(':id', box.data('meeting-id')), type: 'POST',
                    data: $(this).serialize() + '&_method=PUT&_token={{ csrf_token() }}'})
                .done(r => { replaceNotes(box, r); lmnToast('Lead meeting notes saved.'); })
                .fail(xhr => lmnToast(xhr.responseJSON?.message || 'Could not save the notes.', 'error'));
        });
    });
</script>
