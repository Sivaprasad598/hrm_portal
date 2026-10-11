<form id="launcherForm" autocomplete="off">
    @csrf

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">
                    Name <span class="text-danger">*</span>
                </label>
                <input type="text" name="name" id="launcherNameInput" class="form-control height-35 f-14"
                       placeholder="Example: Dashboard" required>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">
                    Priority / Order <span class="text-danger">*</span>
                </label>
                <input type="number" name="sort_order" class="form-control height-35 f-14"
                       value="{{ $nextOrder ?? 1 }}" min="1" required>
                <small class="text-muted f-11">
                    Lower number = shown first. Next available: <b>{{ $nextOrder ?? 1 }}</b>
                </small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">
                    Icon Class <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white">
                            <i id="iconPreview" class="fa fa-th"></i>
                        </span>
                    </div>
                    <input type="text" name="icon" class="form-control height-35 f-14"
                           placeholder="fa fa-home"
                           oninput="document.getElementById('iconPreview').className = (this.value.trim().replace(/\bfa-solid\b/g, 'fas').replace(/\bfa-regular\b/g, 'far').replace(/\bfa-brands\b/g, 'fab') || 'fa fa-th')"
                           required>
                </div>
                <small class="text-muted f-11">
                    Font Awesome 5, e.g. <code>fa fa-home</code>, <code>fas fa-flask</code>
                </small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">Icon Color</label>
                <input type="color" name="color" class="form-control height-35"
                       value="#4f46e5" style="padding: 2px;">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">Laravel Route Name</label>
                <input type="text" name="route" class="form-control height-35 f-14"
                       placeholder="tasks.index">
                <small class="text-muted f-11">Optional — Laravel route name</small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">Or Custom URL</label>
                <input type="text" name="url" class="form-control height-35 f-14"
                       placeholder="https://example.com">
                <small class="text-muted f-11">Overrides Route if filled</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="f-14 text-dark-grey mb-1">Open In</label>
        <select name="target" class="form-control height-35 f-14">
            <option value="_self">Same Tab</option>
            <option value="_blank">New Tab</option>
        </select>
    </div>

    <div class="w-100 border-top-grey d-flex justify-content-end mt-3 pt-3">
        <button type="button" class="btn-secondary rounded f-14 p-2 mr-3" data-dismiss="modal">
            <i class="fa fa-times mr-1"></i> Cancel
        </button>
        <button type="submit" class="btn-primary rounded f-14 p-2" id="saveLauncherBtn">
            <i class="fa fa-check mr-1"></i> Save
        </button>
    </div>
</form>

<script>
    $(document).ready(function () {
        var launcherNameInput = $('#launcherNameInput');
        var emojiPattern = /[\u{1F000}-\u{1FAFF}\u{2300}-\u{23FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}\u{20E3}]/u;
        var emojiAlertShown = false;

        function validateLauncherName(showAlert) {
            var hasEmoji = emojiPattern.test(launcherNameInput.val());
            launcherNameInput.toggleClass('is-invalid', hasEmoji);

            if (hasEmoji && showAlert && !emojiAlertShown) {
                emojiAlertShown = true;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Emojis are not allowed' });
                } else {
                    alert('Emojis are not allowed');
                }
            } else if (!hasEmoji) {
                emojiAlertShown = false;
            }

            return !hasEmoji;
        }

        launcherNameInput.on('input', function () {
            validateLauncherName(true);
        });

        $('#launcherForm').off('submit').on('submit', function (e) {
            e.preventDefault();

            if (!validateLauncherName(true)) {
                return;
            }

            var btn = $('#saveLauncherBtn');
            var original = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.easyAjax({
                url: "{{ route('app-launcher-settings.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    if (response.status === 'success') {
                        $('#launcherModal').modal('hide');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message || 'Launcher added.',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(function () {
                                location.reload();
                            });
                        } else {
                            location.reload();
                        }
                    } else {
                        btn.prop('disabled', false).html(original);
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html(original);
                    var nameErrors = xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.name;
                    if (nameErrors && nameErrors.indexOf('Emojis are not allowed.') !== -1) {
                        launcherNameInput.addClass('is-invalid');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Emojis are not allowed' });
                        } else {
                            alert('Emojis are not allowed');
                        }
                        return;
                    }
                    var msg = 'Something went wrong.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    }
                }
            });
        });
    });
</script>
