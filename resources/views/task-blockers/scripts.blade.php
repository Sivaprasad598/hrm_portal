{{-- Shared roadblock / task meeting actions, available on every page --}}
<script>
    function openTaskModal(url) {
        $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_LG, url);
    }

    // Raise a roadblock (puts the task On Hold)
    window.openRoadblockModal = function (taskId) {
        openTaskModal("{{ route('task-blockers.create') }}?task_id=" + taskId);
    };

    // Choosing "On Hold" in any task list status dropdown opens the roadblock form instead
    // of changing the status directly. Capture phase, so it runs before each page's own handler.
    document.addEventListener('change', function (e) {
        let select = e.target;

        if (!select.matches || !select.matches('#allTasks-table select.change-status') || select.value !== 'on_hold') {
            return;
        }

        e.stopImmediatePropagation();
        openRoadblockModal($(select).data('task-id'));

        // Cancelled: put the dropdown back to the real status
        $(MODAL_LG).one('hidden.bs.modal', function () {
            if (window.LaravelDataTables && window.LaravelDataTables['allTasks-table']) {
                window.LaravelDataTables['allTasks-table'].draw(false);
            }
        });
    }, true);

    $('body').on('click', '.open-roadblock-modal', function () {
        openRoadblockModal($(this).data('task-id'));
    });

    // Resolve / cancel a roadblock
    $('body').on('click', '.close-roadblock', function () {
        let url = "{{ route('task-blockers.edit', ':id') }}".replace(':id', $(this).data('blocker-id'));
        openTaskModal(url + '?action=' + $(this).data('action'));
    });

    // Task meetings: request / follow-up, reschedule, minutes
    $('body').on('click', '.open-meeting-modal', function () {
        let url = "{{ route('task-meetings.create') }}?task_id=" + $(this).data('task-id');

        if ($(this).data('follow-up-of')) {
            url += '&follow_up_of=' + $(this).data('follow-up-of');
        }

        openTaskModal(url);
    });

    $('body').on('click', '.edit-meeting', function () {
        openTaskModal("{{ route('task-meetings.edit', ':id') }}".replace(':id', $(this).data('meeting-id')));
    });

    $('body').on('click', '.meeting-minutes', function () {
        openTaskModal("{{ route('task-meetings.minutes', ':id') }}".replace(':id', $(this).data('meeting-id')));
    });

    $('body').on('click', '.meeting-respond:not(.disabled)', function () {
        $.easyAjax({
            url: "{{ route('task-meetings.respond', ':id') }}".replace(':id', $(this).data('meeting-id')),
            type: 'POST',
            blockUI: true,
            data: {response: $(this).data('response'), _token: '{{ csrf_token() }}'},
            success: function (response) {
                if (response.status == 'success') {
                    $(document).trigger('meeting:changed');
                }
            }
        });
    });

    $('body').on('click', '.meeting-cancel', function () {
        let url = "{{ route('task-meetings.cancel', ':id') }}".replace(':id', $(this).data('meeting-id'));

        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('modules.tasks.meetingCancel')?",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('app.yes')",
            cancelButtonText: "@lang('app.no')",
            customClass: {
                confirmButton: 'btn btn-primary mr-3',
                cancelButton: 'btn btn-secondary'
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.easyAjax({
                    url: url,
                    type: 'POST',
                    blockUI: true,
                    data: {_token: '{{ csrf_token() }}'},
                    success: function (response) {
                        if (response.status == 'success') {
                            $(document).trigger('meeting:changed');
                        }
                    }
                });
            }
        });
    });

    // Refresh whatever is showing the task after a roadblock / meeting change
    $(document).on('roadblock:changed meeting:changed', function () {
        let taskDetailOpen = $('#task-detail-section').length > 0;

        if (!taskDetailOpen && window.LaravelDataTables && window.LaravelDataTables['allTasks-table']) {
            window.LaravelDataTables['allTasks-table'].draw(false);
            return;
        }

        if (!taskDetailOpen && $('#taskboard-columns').length && typeof loadData === 'function') {
            loadData();
            return;
        }

        window.location.reload();
    });
</script>
