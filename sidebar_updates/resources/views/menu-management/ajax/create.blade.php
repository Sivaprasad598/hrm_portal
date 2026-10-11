<form id="menuForm" autocomplete="off">
    @csrf

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Menu Type <span class="text-danger">*</span></label>
                <select name="menu_type" id="menuType" class="form-control">
                    <option value="main"    {{ old('menu_type', $menuType) == 'main'    ? 'selected' : '' }}>Main Menu</option>
                    <option value="submenu" {{ old('menu_type', $menuType) == 'submenu' ? 'selected' : '' }}>Sub Menu</option>
                    <option value="child"   {{ old('menu_type', $menuType) == 'child'   ? 'selected' : '' }}>Child Menu</option>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Menu Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" id="menuNameInput" required>
            </div>
        </div>
    </div>

    {{-- SUBMENU → Main Menu dropdown --}}
    <div class="form-group menu-type-parent" id="parentWrap" style="display:none;">
        <label>Select Main Menu <span class="text-danger">*</span></label>
        <select name="parent_id" id="parentSelect" class="form-control">
            <option value="">— Select Main Menu —</option>
            @foreach ($mainMenus as $m)
                <option value="{{ $m->id }}" {{ $parentId == $m->id ? 'selected' : '' }}>
                    {{ $m->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- CHILD → Main Menu + Sub Menu --}}
    <div class="row menu-type-child" id="childWrap" style="display:none;">
        <div class="col-md-6">
            <div class="form-group">
                <label>Select Main Menu <span class="text-danger">*</span></label>
                <select name="main_id" id="mainSelect" class="form-control">
                    <option value="">— Select Main Menu —</option>
                    @foreach ($mainMenus as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
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
                <input type="text" name="route" class="form-control" placeholder="tasks.index or /tasks">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Icon (Font Awesome)</label>
                <input type="text" name="icon" class="form-control" placeholder="fas fa-users">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>
                    Priority / Order <span class="text-danger">*</span>
                </label>
                  <input type="number" name="sort_order" id="sortOrder"
                      class="form-control" value="{{ $nextOrder ?? 1 }}" min="1" required>
                  <small class="text-muted" id="sortOrderHint">
                    <i class="fa fa-info-circle"></i>
                      Next available: <b>{{ $nextOrder ?? 1 }}</b>
                </small>
            </div>
        </div>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="saveMenuBtn">
            <i class="fa fa-save"></i> Save Menu
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
                sortOrder.val('').prop('disabled', true);
                hint.html('<i class="fa fa-info-circle"></i> Select a parent menu to calculate the next priority.');
                return;
            }

            sortOrder.prop('disabled', false);
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

        // Pre-select type based on parent_id
        @if ($parentId)
            // Parent given → submenu or child
            var parentLevel = {{ \App\Models\SidebarMenu::find($parentId)->level ?? 0 }};
            if (parentLevel === 0) {
                $('#menuType').val('submenu').trigger('change');
                $('#parentSelect').val('{{ $parentId }}');
            } else if (parentLevel === 1) {
                $('#menuType').val('child').trigger('change');
                $('#mainSelect').val(@json($preselectedMainId)).trigger('change');
            }
        @endif

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
                    var selected = s.id == @json($preselectedSubmenuId) ? ' selected' : '';
                    html += '<option value="' + s.id + '"' + selected + '>' + s.name + '</option>';
                });
                $sub.html(html);
                if ($sub.val()) {
                    refreshNextPriority();
                }
            });
        });

        if ($('#menuType').val() === 'child' && $('#mainSelect').val()) {
            $('#mainSelect').trigger('change');
        } else {
            refreshNextPriority();
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

            $.easyAjax({
                url: "{{ route('menu-management.store') }}",
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
