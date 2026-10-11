<form id="menuForm" autocomplete="off">
    @csrf
    @method('PUT')
    <input type="hidden" name="id" value="{{ $menu->id }}">

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Menu Type <span class="text-danger">*</span></label>
                <select name="menu_type" id="menuType" class="form-control">
                    <option value="main"    {{ $menu->level == 0 ? 'selected' : '' }}>Main Menu</option>
                    <option value="submenu" {{ $menu->level == 1 ? 'selected' : '' }}>Sub Menu</option>
                    <option value="child"   {{ $menu->level == 2 ? 'selected' : '' }}>Child Menu</option>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Menu Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" id="menuNameInput" value="{{ $menu->name }}" required>
            </div>
        </div>
    </div>

    <div class="form-group menu-type-parent" id="parentWrap" style="display:none;">
        <label>Select Main Menu <span class="text-danger">*</span></label>
        <select name="parent_id" id="parentSelect" class="form-control">
            <option value="">— Select Main Menu —</option>
            @foreach ($mainMenus as $m)
                <option value="{{ $m->id }}" {{ $menu->level == 1 && $menu->parent_id == $m->id ? 'selected' : '' }}>
                    {{ $m->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="row menu-type-child" id="childWrap" style="display:none;">
        <div class="col-md-6">
            <div class="form-group">
                <label>Select Main Menu <span class="text-danger">*</span></label>
                <select name="main_id" id="mainSelect" class="form-control">
                    <option value="">— Select Main Menu —</option>
                    @foreach ($mainMenus as $m)
                        <option value="{{ $m->id }}" {{ $mainMenuId == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Select Sub Menu <span class="text-danger">*</span></label>
                <select name="submenu_id" id="submenuSelect" class="form-control">
                    <option value="">— Select Sub Menu —</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Route / URL</label>
                <input type="text" name="route" class="form-control" value="{{ $menu->route }}">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Icon (Font Awesome)</label>
                <input type="text" name="icon" class="form-control" value="{{ $menu->icon }}">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Priority / Order <span class="text-danger">*</span></label>
                <input type="number" name="sort_order" id="sortOrder" class="form-control"
                       value="{{ $menu->sort_order }}" min="1" required>
                <small class="text-muted" id="sortOrderHint">
                    <i class="fa fa-info-circle"></i>
                    Must be unique per level.
                </small>
            </div>
        </div>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="saveMenuBtn">
            <i class="fa fa-save"></i> Update Menu
        </button>
    </div>
</form>

<script>
    $(document).ready(function () {
        var menuNameInput = $('#menuNameInput');
        var emojiPattern = /[\u{1F000}-\u{1FAFF}\u{2300}-\u{23FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}\u{20E3}]/u;
        var emojiAlertShown = false;

        function validateMenuName(showAlert) {
            var hasEmoji = emojiPattern.test(menuNameInput.val());
            menuNameInput.toggleClass('is-invalid', hasEmoji);

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

        menuNameInput.on('input', function () {
            validateMenuName(true);
        });

        var initialMainId = {{ $mainMenuId ?? 'null' }};
        var initialParentId = {{ $menu->parent_id ?? 'null' }};
        var isChild = {{ $menu->level == 2 ? 'true' : 'false' }};

        function toggleTypeFields() {
            var type = $('#menuType').val();
            $('#parentWrap').hide();
            $('#childWrap').hide();

            if (type === 'submenu') {
                $('#parentWrap').show();
            } else if (type === 'child') {
                $('#childWrap').show();
            }
        }

        var initialLevel = {{ (int) $menu->level }};
        var initialSortOrder = {{ (int) $menu->sort_order }};
        var levels = { main: 0, submenu: 1, child: 2 };

        // Order numbers are per parent: when the menu moves under a different parent,
        // suggest the next free number there; when it moves back, restore its own number.
        function refreshNextPriority() {
            var menuType = $('#menuType').val();
            var parentId = '';

            if (menuType === 'submenu') {
                parentId = $('#parentSelect').val();
            } else if (menuType === 'child') {
                parentId = $('#submenuSelect').val();
            }

            var sortOrder = $('#sortOrder');
            var hint = $('#sortOrderHint');

            if (menuType !== 'main' && !parentId) {
                hint.html('<i class="fa fa-info-circle"></i> Select a parent menu to calculate the next priority.');
                return;
            }

            var sameList = levels[menuType] === initialLevel
                && String(parentId || '') === String(initialParentId || '');

            if (sameList) {
                sortOrder.val(initialSortOrder);
                hint.html('<i class="fa fa-info-circle"></i> Current position: <b>' + initialSortOrder + '</b>');
                return;
            }

            hint.html('<i class="fa fa-info-circle"></i> Loading next priority...');

            $.get("{{ route('menu-management.next-priority') }}", {
                menu_type: menuType,
                parent_id: parentId
            }, function (response) {
                sortOrder.val(response.next_order);
                hint.html('<i class="fa fa-info-circle"></i> Next available: <b>' + response.next_order + '</b>');
            });
        }

        $('#menuType').off('change').on('change', function () {
            toggleTypeFields();
            refreshNextPriority();
        });
        $('#parentSelect').off('change').on('change', refreshNextPriority);
        $('#submenuSelect').off('change').on('change', refreshNextPriority);
        toggleTypeFields();

        // Load submenus when main menu selected
        $('#mainSelect').off('change').on('change', function () {
            var mainId = $(this).val();
            var $sub = $('#submenuSelect');
            $sub.html('<option value="">Loading…</option>');

            if (!mainId) {
                $sub.html('<option value="">— Select Sub Menu —</option>');
                refreshNextPriority();
                return;
            }

            $.get("{{ route('menu-management.get-submenus') }}", { main_id: mainId }, function (res) {
                var html = '<option value="">— Select Sub Menu —</option>';
                (res.submenus || []).forEach(function (s) {
                    var sel = (initialParentId == s.id) ? 'selected' : '';
                    html += '<option value="' + s.id + '" ' + sel + '>' + s.name + '</option>';
                });
                $sub.html(html);
                refreshNextPriority();
            });
        });

        // Trigger for child: preselect main + load submenus
        if (isChild && initialMainId) {
            $('#mainSelect').val(initialMainId).trigger('change');
        }

        $('#menuForm').off('submit').on('submit', function (e) {
            e.preventDefault();

            if (!validateMenuName(true)) {
                return;
            }

            var btn = $('#saveMenuBtn');
            var original = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            var data = $(this).serialize();

            if ($('#menuType').val() === 'child') {
                data += '&parent_id=' + $('#submenuSelect').val();
            }

            var id = $('input[name="id"]').val();
            var url = "{{ route('menu-management.update', ':id') }}".replace(':id', id);

            $.easyAjax({
                url: url,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.status === 'success') {
                        $('#menuModal').modal('hide');
                        location.reload();
                    } else {
                        btn.prop('disabled', false).html(original);
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html(original);
                    var msg = 'Something went wrong.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    var nameErrors = xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.name;
                    if (nameErrors && nameErrors.indexOf('Emojis are not allowed.') !== -1) {
                        menuNameInput.addClass('is-invalid');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Emojis are not allowed' });
                        } else {
                            alert('Emojis are not allowed');
                        }
                        return;
                    }
                    if (nameErrors && nameErrors.length && nameErrors[0] === 'Menu name already exists') {
                        msg = nameErrors[0];
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: msg === 'Menu name already exists' ? msg : 'Error',
                            text: msg === 'Menu name already exists' ? 'Choose a different menu name.' : msg
                        });
                    } else {
                        alert(msg);
                    }
                }
            });
        });
    });
</script>
