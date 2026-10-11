<form id="launcherForm" autocomplete="off">
    @csrf
    @method('PUT')
    <input type="hidden" name="id" value="{{ $launcher->id }}">

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                  <label class="f-14 text-dark-grey mb-1">Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" id="launcherNameInput" class="form-control height-35 f-14"
                      value="{{ $launcher->name }}" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                  <label class="f-14 text-dark-grey mb-1">Priority / Order <span class="text-danger">*</span></label>
                  <input type="number" name="sort_order" class="form-control height-35 f-14"
                      value="{{ $launcher->sort_order }}" min="1" required>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="f-14 text-dark-grey mb-1">Icon Class <span class="text-danger">*</span></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white">
                            <i id="iconPreview" class="{{ $launcher->icon ?: 'fa fa-th' }}"></i>
                        </span>
                    </div>
                    <input type="text" name="icon" class="form-control height-35 f-14"
                           value="{{ $launcher->icon }}"
                           oninput="document.getElementById('iconPreview').className = (this.value.trim().replace(/\bfa-solid\b/g, 'fas').replace(/\bfa-regular\b/g, 'far').replace(/\bfa-brands\b/g, 'fab') || 'fa fa-th')"
                           required>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                  <label class="f-14 text-dark-grey mb-1">Icon Color</label>
                  <input type="color" name="color" class="form-control height-35"
                      value="{{ $launcher->color }}" style="padding: 2px;">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                  <label class="f-14 text-dark-grey mb-1">Laravel Route Name</label>
                  <input type="text" name="route" class="form-control height-35 f-14"
                      value="{{ $launcher->route }}">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                  <label class="f-14 text-dark-grey mb-1">Or Custom URL</label>
                  <input type="text" name="url" class="form-control height-35 f-14"
                      value="{{ $launcher->url }}">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="f-14 text-dark-grey mb-1">Open In</label>
        <select name="target" class="form-control height-35 f-14">
            <option value="_self" {{ $launcher->target == '_self' ? 'selected' : '' }}>Same Tab</option>
            <option value="_blank" {{ $launcher->target == '_blank' ? 'selected' : '' }}>New Tab</option>
        </select>
    </div>

    <div class="w-100 border-top-grey d-flex justify-content-end mt-3 pt-3">
        <button type="button" class="btn-secondary rounded f-14 p-2 mr-3" data-dismiss="modal">
            <i class="fa fa-times mr-1"></i> Cancel
        </button>
        <button type="submit" class="btn-primary rounded f-14 p-2" id="saveLauncherBtn">
            <i class="fa fa-check mr-1"></i> Update
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

            var id = $('input[name="id"]').val();
            var url = "{{ route('app-launcher-settings.update', ':id') }}".replace(':id', id);

            $.easyAjax({
                url: url, type: 'POST',
                data: $(this).serialize(),
                success: function (r) {
                    if (r.status === 'success') {
                        $('#launcherModal').modal('hide');
                        location.reload();
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
                    }
                }
            });
        });
    });
</script>
