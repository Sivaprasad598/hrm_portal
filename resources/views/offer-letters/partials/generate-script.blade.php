{{-- "Offer Letter" from the Employees list, the profile and the Offer Letters page: any element with
     class generate-offer-letter and data-user-id. The record is checked first (what is missing is
     listed, with a link to edit the employee); then the reference number is asked, the next one
     suggested; then the letter opens. --}}
<script>
    (function () {
        if (window.offerLetterBound) { return; }
        window.offerLetterBound = true;

        const checkUrl = "{{ route('offer-letters.check', ':id') }}";
        const issueUrl = "{{ route('offer-letters.issue', ':id') }}";
        const swalButtons = {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary', denyButton: 'btn btn-secondary mr-3'};
        const esc = function (v) { return $('<div>').text(v == null ? '' : String(v)).html(); };

        function askReference(userId, suggested) {
            Swal.fire({
                title: 'Reference number',
                html: '<div class="f-14 text-dark-grey mb-2">Suggested: the next after the last letter. Change it if needed.</div>',
                input: 'text',
                inputValue: suggested,
                showCancelButton: true,
                confirmButtonText: 'Generate Offer Letter',
                customClass: swalButtons,
                buttonsStyling: false,
                inputValidator: function (value) { if (!value || !value.trim()) { return 'Enter the reference number.'; } },
                showLoaderOnConfirm: true,
                preConfirm: function (reference) {
                    return $.ajax({
                        url: issueUrl.replace(':id', userId),
                        type: 'POST',
                        data: {_token: '{{ csrf_token() }}', reference: reference.trim()}
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

        $('body').on('click', '.generate-offer-letter', function () {
            const userId = $(this).data('user-id');

            $.easyAjax({
                url: checkUrl.replace(':id', userId),
                type: 'GET',
                blockUI: true,
                success: function (response) {
                    if (response.problems && response.problems.length) {
                        Swal.fire({
                            title: 'Update the employee first',
                            icon: 'warning',
                            html: '<div class="text-left f-14">The offer letter needs these on the employee:<ul class="mt-2 mb-0 pl-3">'
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
                            title: 'Offer letter already issued',
                            icon: 'info',
                            html: '<div class="f-14">' + esc(response.existing.reference) + ' on ' + esc(response.existing.date) + '.</div>',
                            showDenyButton: true,
                            showCancelButton: true,
                            confirmButtonText: 'Open it',
                            denyButtonText: 'New letter, new number',
                            customClass: swalButtons,
                            buttonsStyling: false
                        }).then(function (r) {
                            if (r.isConfirmed) { window.location.href = response.existing.url; }
                            else if (r.isDenied) { askReference(userId, response.suggested); }
                        });
                        return;
                    }

                    askReference(userId, response.suggested);
                }
            });
        });
    })();
</script>
