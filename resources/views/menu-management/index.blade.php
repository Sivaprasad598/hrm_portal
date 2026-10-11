@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">
                        Menu Management
                    </h2>
                    <p class="mb-0 px-4 pt-2 text-muted f-13">
                        Add, edit, delete, and reorder Main Menu → Sub Menu → Child Menu.
                    </p>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">

                {{-- ==================== TOP BAR ==================== --}}
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <div>
                        <span class="badge badge-primary p-2 mr-1">
                            <i class="fa fa-list"></i>
                            Total: {{ \App\Models\SidebarMenu::count() }}
                        </span>
                        <span class="badge badge-success p-2 mr-1">
                            <i class="fa fa-check-circle"></i>
                            Active: {{ \App\Models\SidebarMenu::where('is_active', 1)->count() }}
                        </span>
                        <span class="badge badge-secondary p-2">
                            <i class="fa fa-sitemap"></i>
                            Main:
                            {{ \App\Models\SidebarMenu::whereNull('parent_id')->count() }}
                        </span>
                    </div>

                    <button type="button" class="btn btn-primary" id="addMenuItemBtn">
                        <i class="fa fa-plus"></i> Add Menu Item
                    </button>
                </div>

                {{-- ==================== SEARCH + EXPAND ==================== --}}
                <div class="d-flex align-items-center mb-3 flex-wrap">
                    <div class="input-group mr-2" style="max-width: 320px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                        </div>
                        <input type="text" id="searchMenu" class="form-control"
                               placeholder="Search menu name, route...">
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mr-2" id="expandAllBtn">
                        <i class="fa fa-expand-arrows-alt"></i> Expand All
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllBtn">
                        <i class="fa fa-compress-arrows-alt"></i> Collapse All
                    </button>
                </div>

                {{-- ==================== HIERARCHICAL TREE ==================== --}}
                <div class="menu-tree-wrapper">

                    @forelse ($menus as $mainMenu)
                        @include('menu-management.partials.tree-node', ['menu' => $mainMenu])
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="fa fa-folder-open fa-3x mb-3"></i>
                            <p class="mb-0">No menus found. Click "+ Add Menu Item" to start.</p>
                        </div>
                    @endforelse

                </div>

            </div>
        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

    <!-- ==================== MENU MODAL ==================== -->
    <div class="modal fade" id="menuModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="menuModalLabel">Add Menu Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="menuModalBody">
                    <div class="text-center py-5">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        #menuModal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        #menuModal .modal-header {
            padding: 20px 24px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }

        #menuModal .modal-title {
            color: #253047;
            font-size: 16px;
            font-weight: 600;
        }

        #menuModal .modal-header .close {
            color: #858b94;
            opacity: 1;
            text-shadow: none;
        }

        #menuModal .modal-body {
            padding: 24px;
        }

        /* ============ TREE WRAPPER ============ */
        .menu-tree-wrapper {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            background: #fafafa;
        }

        /* ============ MAIN MENU NODE ============ */
        .menu-node {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .menu-node:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .menu-node-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            cursor: pointer;
            user-select: none;
            background: #f8f9fa;
            border-bottom: 1px solid transparent;
            transition: background 0.15s;
        }

        .menu-node-header:hover {
            background: #eef2ff;
        }

        .menu-node.open > .menu-node-header {
            background: #eef2ff;
            border-bottom-color: #e5e7eb;
        }

        .menu-node-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-grow: 1;
            min-width: 0;
        }

        .menu-toggle-icon {
            width: 22px;
            color: #6b7280;
            transition: transform 0.2s;
            font-size: 12px;
            text-align: center;
        }

        .menu-node.open > .menu-node-header .menu-toggle-icon {
            transform: rotate(90deg);
            color: #4f46e5;
        }

        .menu-icon-box {
            width: 34px;
            height: 34px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #4f46e5;
            flex-shrink: 0;
            font-size: 14px;
        }

        .menu-icon-box.level-1 {
            width: 28px;
            height: 28px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
        }

        .menu-icon-box.level-2 {
            width: 24px;
            height: 24px;
            background: transparent;
            color: #9ca3af;
            font-size: 10px;
        }

        .menu-info {
            min-width: 0;
        }

        .menu-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .menu-name.child {
            font-size: 13px;
            font-weight: 500;
            color: #4b5563;
        }

        .menu-meta {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .menu-meta code {
            background: #f3f4f6;
            padding: 1px 5px;
            border-radius: 3px;
            color: #4f46e5;
            font-size: 10px;
        }

        .menu-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
        }

        .menu-badge.badge-main  { background: #eef2ff; color: #4338ca; }
        .menu-badge.badge-sub   { background: #e0f2fe; color: #0369a1; }
        .menu-badge.badge-child { background: #f3f4f6; color: #4b5563; }

        /* ============ ACTION BUTTONS ============ */
        .menu-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }

        .menu-actions .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
            cursor: pointer;
        }

        .menu-actions .btn-icon:hover {
            background: #f9fafb;
        }

        .menu-actions .btn-icon.btn-add    { color: #10b981; border-color: #d1fae5; }
        .menu-actions .btn-icon.btn-add:hover { background: #d1fae5; }
        .menu-actions .btn-icon.btn-edit   { color: #3b82f6; border-color: #dbeafe; }
        .menu-actions .btn-icon.btn-edit:hover { background: #dbeafe; }
        .menu-actions .btn-icon.btn-delete { color: #ef4444; border-color: #fee2e2; }
        .menu-actions .btn-icon.btn-delete:hover { background: #fee2e2; }

        /* ============ CHILDREN CONTAINER ============ */
        .menu-children {
            padding: 8px 12px 12px 45px;
            background: #fff;
            display: none;
        }

        .menu-node.open > .menu-children {
            display: block;
        }

        /* ============ SUB NODE ============ */
        .sub-node {
            border-top: 1px solid #f3f4f6;
            padding: 8px 0;
        }

        .sub-node:first-child {
            border-top: none;
        }

        .sub-node-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .sub-node-header:hover {
            background: #f9fafb;
        }

        /* ============ CHILD CONTAINER ============ */
        .child-children {
            padding: 4px 0 6px 40px;
            display: none;
        }

        .sub-node.open > .child-children {
            display: block;
        }

        /* ============ CHILD NODE ============ */
        .child-node {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 8px;
            border-radius: 6px;
            transition: background 0.15s;
        }

        .child-node:hover {
            background: #f9fafb;
        }

        /* ============ SEARCH HIDDEN ============ */
        .search-hidden {
            display: none !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function () {

            // ==========================================================
            // 1) "+ ADD MENU ITEM"
            // ==========================================================
            $(document).off('click', '#addMenuItemBtn').on('click', '#addMenuItemBtn', function (e) {
                e.preventDefault();
                openMenuModal("{{ route('menu-management.create') }}", 'Add Menu Item');
            });

            // ==========================================================
            // 2) TOGGLE NODE (main menu expands submenus, submenu expands children)
            // ==========================================================
            $(document).off('click', '.menu-node-header').on('click', '.menu-node-header', function (e) {
                if ($(e.target).closest('.menu-actions, .btn-icon').length) return;
                $(this).closest('.menu-node').toggleClass('open');
            });

            $(document).off('click', '.sub-node-header').on('click', '.sub-node-header', function (e) {
                if ($(e.target).closest('.menu-actions, .btn-icon').length) return;
                $(this).closest('.sub-node').toggleClass('open');
            });

            // ==========================================================
            // 3) ADD CHILD (quick action per row)
            // ==========================================================
            $(document).off('click', '.add-child-btn').on('click', '.add-child-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var parentId = $(this).data('parent-id');
                var url = "{{ route('menu-management.create') }}?parent_id=" + parentId;
                openMenuModal(url, 'Add Child Menu');
            });

            // ==========================================================
            // 4) EDIT
            // ==========================================================
            $(document).off('click', '.edit-menu-btn').on('click', '.edit-menu-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');
                var url = "{{ route('menu-management.edit', ':id') }}".replace(':id', id);
                openMenuModal(url, 'Edit Menu Item');
            });

            // ==========================================================
            // 5) DELETE
            // ==========================================================
            $(document).off('click', '.delete-menu-btn').on('click', '.delete-menu-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Are you sure?',
                        html: 'This will delete the menu <b>and all of its children</b>.<br>This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fa fa-trash"></i> Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d'
                    }).then(function (result) {
                        if (result.isConfirmed) doDelete(id);
                    });
                } else if (confirm('Delete this menu and all children?')) {
                    doDelete(id);
                }
            });

            function doDelete(id) {
                var url = "{{ route('menu-management.destroy', ':id') }}".replace(':id', id);
                $.easyAjax({
                    url: url,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', _method: 'DELETE' },
                    success: function (response) {
                        if (response.status === 'success') location.reload();
                    }
                });
            }

            // ==========================================================
            // 6) SEARCH
            // ==========================================================
            $(document).off('input', '#searchMenu').on('input', '#searchMenu', function () {
                var term = $(this).val().toLowerCase().trim();

                if (!term) {
                    $('.menu-node, .sub-node, .child-node').removeClass('search-hidden');
                    return;
                }

                // Show matching + all their parents expanded
                $('.menu-node').each(function () {
                    var $node = $(this);
                    var $subNodes = $node.find('.sub-node');
                    var mainMatch = $node.find('> .menu-node-header').text().toLowerCase().indexOf(term) !== -1;
                    var anySubMatch = false;

                    $subNodes.each(function () {
                        var $sub = $(this);
                        var $childNodes = $sub.find('.child-node');
                        var subMatch = $sub.find('> .sub-node-header').text().toLowerCase().indexOf(term) !== -1;
                        var anyChildMatch = false;

                        $childNodes.each(function () {
                            var childMatch = $(this).text().toLowerCase().indexOf(term) !== -1;
                            if (childMatch) anyChildMatch = true;
                            $(this).toggleClass('search-hidden', !childMatch && !subMatch && !mainMatch);
                        });

                        if (subMatch || anyChildMatch) anySubMatch = true;
                        $sub.toggleClass('search-hidden', !subMatch && !anyChildMatch && !mainMatch);
                    });

                    if (mainMatch || anySubMatch) {
                        $node.removeClass('search-hidden');
                        if (term) $node.addClass('open');
                    } else {
                        $node.addClass('search-hidden');
                    }
                });
            });

            // ==========================================================
            // 7) EXPAND / COLLAPSE ALL
            // ==========================================================
            $(document).off('click', '#expandAllBtn').on('click', '#expandAllBtn', function () {
                $('.menu-node, .sub-node').addClass('open');
            });

            $(document).off('click', '#collapseAllBtn').on('click', '#collapseAllBtn', function () {
                $('.menu-node, .sub-node').removeClass('open');
            });

            // ==========================================================
            // 8) MODAL OPENER
            // ==========================================================
            function openMenuModal(url, title) {
                $('#menuModalLabel').text(title);
                $('#menuModalBody').html(
                    '<div class="text-center py-5">' +
                        '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>' +
                        '<p class="mt-2 text-muted">Loading...</p>' +
                    '</div>'
                );
                $('#menuModal').modal('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success' && response.html) {
                            $('#menuModalBody').html(response.html);
                            if (typeof $('.select-picker').selectpicker === 'function') {
                                $('.select-picker').selectpicker('refresh');
                            }
                        } else {
                            $('#menuModalBody').html('<div class="alert alert-warning">No form data returned.</div>');
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        $('#menuModalBody').html(
                            '<div class="alert alert-danger">' +
                                '<i class="fa fa-exclamation-triangle mr-1"></i>' +
                                'Failed to load form.' +
                            '</div>'
                        );
                    }
                });
            }

        });
    </script>
@endpush
