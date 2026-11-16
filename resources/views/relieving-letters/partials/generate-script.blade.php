{{-- "Relieving Letter" on the Exited Employees list (class generate-relieving-letter, data-user-id):
     checked first - left, assets returned, final settlement paid, record complete - then the letter
     date is confirmed (today by default), then the letter opens. --}}
<script>
    (function () {
        if (window.relievingLetterBound) { return; }
        window.relievingLetterBound = true;

        const checkUrl = "{{ route('relieving-letters.check', ':id') }}";
        const issueUrl = "{{ route('relieving-letters.issue', ':id') }}";
        const swalButtons = {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary', denyButton: 'btn btn-secondary mr-3'};
        const esc = function (v) { return $('<div>').text(v == null ? '' : String(v)).html(); };

        function askDate(userId, today) {
            Swal.fire({
                title: 'Letter date',
                html: '<div class="f-14 text-dark-grey mb-2">The date printed on the letter.</div>',
                input: 'date',
                inputValue: today,
                showCancelButton: true,
                confirmButtonText: 'Generate Relieving Letter',
                customClass: swalButtons,
                buttonsStyling: false,
                showLoaderOnConfirm: true,
                preConfirm: function (date) {
                    return $.ajax({
                        url: issueUrl.replace(':id', userId),
                        type: 'POST',
                        data: {_token: '{{ csrf_token() }}', letter_date: date}
                    }).then(function (response) {
                        if (response.status !== 'success') {
                            Swal.showValidationMessage(response.message);
                            return false;
                        }
                        return response;
                    }, function (xhr) {
                        Swal.showValidationMessage((xhr.responseJSON && xhr.responseJSON.message) || 'Could not generate the letter.');
                        return false;
                    });
                },
                allowOutsideClick: function () { return !Swal.isLoading(); }
            }).then(function (result) {
                if (result.isConfirmed && result.value && result.value.redirect_url) {
                    window.location.href = result.value.redirect_url;
                }
            });
        }

        $('body').on('click', '.generate-relieving-letter', function () {
            const userId = $(this).data('user-id');

            $.easyAjax({
                url: checkUrl.replace(':id', userId),
                type: 'GET',
                blockUI: true,
                success: function (response) {
                    if (response.problems && response.problems.length) {
                        Swal.fire({
                            title: 'Not ready for the relieving letter',
                            icon: 'warning',
                            html: '<div class="text-left f-14">Put these right first:<ul class="mt-2 mb-0 pl-3">'
                                + response.problems.map(function (p) { return '<li class="mb-1">' + esc(p) + '</li>'; }).join('')
                                + '</ul></div>',
                            showCancelButton: true,
                            confirmButtonText: 'Edit Employee',
                            cancelButtonText: 'Close',
                            customClass: swalButtons,
                            buttonsStyling: false
                        }).then(function (r) {
                            if (r.isConfirmed) { window.location.href = response.edit_url; }
                        });
                        return;
                    }

                    if (response.existing) {
                        Swal.fire({
                            title: 'Relieving letter already issued',
                            icon: 'info',
                            html: '<div class="f-14">Dated ' + esc(response.existing.date) + '.</div>',
                            showDenyButton: true,
                            showCancelButton: true,
                            confirmButtonText: 'Open it',
                            denyButtonText: 'New letter',
                            customClass: swalButtons,
                            buttonsStyling: false
                        }).then(function (r) {
                            if (r.isConfirmed) { window.location.href = response.existing.url; }
                            else if (r.isDenied) { askDate(userId, response.today); }
                        });
                        return;
                    }

                    askDate(userId, response.today);
                }
            });
        });
    })();
</script>
