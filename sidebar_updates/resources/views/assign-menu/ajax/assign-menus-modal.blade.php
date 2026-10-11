<div id="assignMenusForm" class="d-flex flex-column">
    @csrf
    <input type="hidden" name="role_id" value="{{ $role->id }}">

    {{-- ==================== ADMIN NOTICE ==================== --}}
    @if ($role->id == 1)
        <div class="alert alert-info d-flex align-items-center mb-3 flex-shrink-0">
            <i class="fa fa-info-circle fa-lg mr-2"></i>
            <div>
                <strong>Admin Role</strong> — This role automatically has access to every menu.
            </div>
        </div>
    @endif

    {{-- ==================== QUICK ACTIONS ==================== --}}
    @if ($role->id != 1)
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap flex-shrink-0">
            <small class="text-muted">
                <i class="fa fa-info-circle"></i>
                Check a main menu to auto-select its submenus and children.
            </small>
            <div>
                <button type="button" class="btn btn-outline-success btn-sm" id="selectAllBtn">
                    <i class="fa fa-check-square"></i> Select All
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="deselectAllBtn">
                    <i class="fa fa-square"></i> Deselect All
                </button>
            </div>
        </div>
    @endif

    {{-- ==================== MENU TREE (fills remaining space) ==================== --}}
    <div class="menu-access-tree flex-grow-1"
         style="overflow-y: auto; border: 1px solid #e9ecef; padding: 20px; border-radius: 6px; background: #fafafa;">

        @forelse ($menus as $mainMenu)
            <div class="main-menu-item mb-3 p-3 rounded" style="background: #fff; border: 1px solid #eee;">

                {{-- MAIN MENU (Level 0) --}}
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input main-checkbox"
                           id="menu_{{ $mainMenu->id }}" name="menu_ids[]" value="{{ $mainMenu->id }}"
                           {{ $role->id == 1 || in_array($mainMenu->id, $assignedMenus) ? 'checked' : '' }}
                           {{ $role->id == 1 ? 'disabled' : '' }}>
                    <label class="custom-control-label font-weight-bold text-dark"
                           for="menu_{{ $mainMenu->id }}" style="cursor: pointer; font-size: 15px;">
                        <i class="fa fa-folder{{ $mainMenu->children->count() > 0 ? '-open' : '' }} text-warning mr-1"></i>
                        {{ $mainMenu->name }}
                    </label>
                </div>

                {{-- SUBMENUS (Level 1) --}}
                @if ($mainMenu->children->count() > 0)
                    <div class="submenu-container ml-4 mt-2">
                        @foreach ($mainMenu->children as $subMenu)
                            <div class="sub-menu-item mb-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input sub-checkbox"
                                           id="menu_{{ $subMenu->id }}" name="menu_ids[]" value="{{ $subMenu->id }}"
                                           {{ $role->id == 1 || in_array($subMenu->id, $assignedMenus) ? 'checked' : '' }}
                                           {{ $role->id == 1 ? 'disabled' : '' }}>
                                    <label class="custom-control-label text-secondary"
                                           for="menu_{{ $subMenu->id }}" style="cursor: pointer;">
                                        <i class="fa fa-level-up-alt fa-rotate-90 text-info mr-1"></i>
                                        {{ $subMenu->name }}
                                    </label>
                                </div>

                                {{-- CHILD MENUS (Level 2) --}}
                                @if ($subMenu->children->count() > 0)
                                    <div class="childmenu-container ml-4 mt-1">
                                        @foreach ($subMenu->children as $childMenu)
                                            <div class="child-menu-item mb-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input child-checkbox"
                                                           id="menu_{{ $childMenu->id }}" name="menu_ids[]" value="{{ $childMenu->id }}"
                                                           {{ $role->id == 1 || in_array($childMenu->id, $assignedMenus) ? 'checked' : '' }}
                                                           {{ $role->id == 1 ? 'disabled' : '' }}>
                                                    <label class="custom-control-label text-muted"
                                                           for="menu_{{ $childMenu->id }}" style="cursor: pointer;">
                                                        <i class="fa fa-circle-notch text-muted mr-1" style="font-size: 10px;"></i>
                                                        {{ $childMenu->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        @empty
            <div class="text-center text-muted py-4">
                <i class="fa fa-inbox fa-2x mb-2"></i>
                <p>No menus found in the database.</p>
            </div>
        @endforelse

    </div>

    {{-- ==================== SELECTED COUNT ==================== --}}
    <div class="mt-2 text-right flex-shrink-0">
        <small class="text-muted">
            Selected: <span id="selectedCountBadge" class="badge badge-primary">0</span> menu(s)
        </small>
    </div>

    {{-- ==================== FOOTER ==================== --}}
    <div class="d-flex justify-content-end border-top pt-3 mt-3">
        <button type="button" class="btn btn-secondary mr-2" id="cancelAssignMenus">
            <i class="fa fa-times"></i> Cancel
        </button>
        @if ($role->id != 1)
            <button type="button" class="btn btn-primary" id="saveAssignMenusBtn">
                <i class="fa fa-save"></i> Save Access
            </button>
        @endif
    </div>
</div>

<script>
    $(document).ready(function () {

        // --------------------------------------------------
        // 1) Parent → children cascade
        // --------------------------------------------------
        $(document).off('change', '.main-checkbox').on('change', '.main-checkbox', function () {
            var isChecked = $(this).prop('checked');
            $(this).closest('.main-menu-item').find('.sub-checkbox, .child-checkbox').prop('checked', isChecked);
            updateSelectedCountBadge();
        });

        $(document).off('change', '.sub-checkbox').on('change', '.sub-checkbox', function () {
            var isChecked = $(this).prop('checked');
            $(this).closest('.sub-menu-item').find('.child-checkbox').prop('checked', isChecked);
            if (isChecked) {
                $(this).closest('.main-menu-item').find('.main-checkbox').prop('checked', true);
            }
            updateSelectedCountBadge();
        });

        $(document).off('change', '.child-checkbox').on('change', '.child-checkbox', function () {
            if ($(this).prop('checked')) {
                $(this).closest('.sub-menu-item').find('.sub-checkbox').prop('checked', true);
                $(this).closest('.main-menu-item').find('.main-checkbox').prop('checked', true);
            }
            updateSelectedCountBadge();
        });

        // --------------------------------------------------
        // 2) Select All / Deselect All
        // --------------------------------------------------
        $(document).off('click', '#selectAllBtn').on('click', '#selectAllBtn', function () {
            $('#assignMenusForm input[type="checkbox"]:not(:disabled)').prop('checked', true);
            updateSelectedCountBadge();
        });

        $(document).off('click', '#deselectAllBtn').on('click', '#deselectAllBtn', function () {
            $('#assignMenusForm input[type="checkbox"]:not(:disabled)').prop('checked', false);
            updateSelectedCountBadge();
        });

        $(document).off('click', '#cancelAssignMenus').on('click', '#cancelAssignMenus', function () {
            $('#assignMenusPanel').addClass('d-none');
            $('#assignMenusPanelBody').empty();
        });

        // --------------------------------------------------
        // 3) Live count
        // --------------------------------------------------
        function updateSelectedCountBadge() {
            var count = $('#assignMenusForm input[name="menu_ids[]"]:checked').length;
            $('#selectedCountBadge').text(count);
        }
        updateSelectedCountBadge();

        // --------------------------------------------------
        // 4) AJAX submit
        // --------------------------------------------------
        $(document).off('click', '#saveAssignMenusBtn').on('click', '#saveAssignMenusBtn', function () {
            var formData = $('#assignMenusForm').find(':input').serialize();
            var roleId   = $('input[name="role_id"]').val();
            var btn      = $('#saveAssignMenusBtn');
            var original = btn.html();

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            var url = "{{ route('assign-menus.update-access', ':roleId') }}".replace(':roleId', roleId);

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    $('#assignMenusPanel').addClass('d-none');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: response.message || 'Menu access updated.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function () { location.reload(); });
                    } else {
                        alert('Menu access updated.');
                        location.reload();
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html(original);
                    var msg = 'Something went wrong. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    } else {
                        alert(msg);
                    }
                }
            });
        });
    });
</script>
