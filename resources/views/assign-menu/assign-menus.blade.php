@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header assign-menus-page-header" id="tabs">
                    <div class="assign-menus-title-row">
                        <h2 class="mb-0 p-20 f-21 font-weight-normal">
                            Assign Menus
                        </h2>
                        <div class="assign-menus-tabs" role="tablist" aria-label="Menu management">
                            <button type="button" class="assign-menus-tab active" data-menu-tab="assign" role="tab" aria-selected="true">
                                Assign Menu
                            </button>
                            <button type="button" class="assign-menus-tab" data-menu-tab="modules" role="tab" aria-selected="false">
                                Modules
                            </button>
                            <a href="{{ route('menu-management.index') }}" class="assign-menus-tab-link">
                                <i class="fa fa-plus mr-1"></i> Add Menu
                            </a>
                        </div>
                    </div>
                    <p class="mb-0 px-4 pt-2 text-muted f-13">
                        Control which main menus, submenus and child menus each role can access.
                    </p>
                </div>
            </x-slot>

            <div id="assignMenuTabContent" class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">

                @if ($roles->count() > 0)

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Role Name</th>
                                    <th>Display Name</th>
                                    <th>Description</th>
                                    <th style="width: 260px;">Assigned Menus</th>
                                    <th class="text-center" style="width: 180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roles as $role)
                                    <tr>
                                        <td class="font-weight-bold text-muted">{{ $role->id }}</td>

                                        <td>
                                            <span class="badge badge-secondary text-capitalize px-3 py-1">
                                                {{ $role->name }}
                                            </span>
                                        </td>

                                        <td class="font-weight-bold">
                                            {{ $role->display_name ?? $role->name }}
                                        </td>

                                        <td class="text-muted small">
                                            {{ $role->description ?? '—' }}
                                        </td>

                                        <td>
                                            @if ($role->id == 1)
                                                <span class="badge badge-success mb-1">
                                                    <i class="fa fa-check-circle mr-1"></i> All Menus
                                                </span>
                                            @endif
                                            <details class="assigned-menu-details">
                                                <summary>
                                                    {{ $role->menus_count ?? 0 }} assigned
                                                </summary>
                                                @if ($role->menus->isNotEmpty())
                                                    <ul class="assigned-menu-list mb-0 mt-2">
                                                        @foreach ($role->menus as $assignedMenu)
                                                            <li>{{ $assignedMenu->menu_name ?? $assignedMenu->name }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <p class="text-muted small mb-0 mt-2">No menu records assigned.</p>
                                                @endif
                                            </details>
                                        </td>

                                        <td class="text-center">
                                            @if ($role->id == 1)
                                                <button type="button" class="btn btn-secondary btn-sm" disabled
                                                        title="Admin role always has full access">
                                                    <i class="fa fa-lock"></i> Full Access
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-primary btn-sm assign-menus-btn"
                                                        data-role-id="{{ $role->id }}"
                                                        data-role-name="{{ $role->display_name ?? $role->name }}">
                                                    <i class="fa fa-key"></i> Give Access
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                @else

                    <div class="text-center py-5 text-muted">
                        <i class="fa fa-inbox fa-3x mb-3"></i>
                        <p class="f-14">No roles found.</p>
                    </div>

                @endif

            </div>

            {{-- Modules: one still being built is marked Coming soon. Its menu stays (tagged "Soon");
                 its pages show a Coming soon page to everyone but the Super Admin (ComingSoon). --}}
            <div id="modulesTabContent" class="col-lg-12 col-md-12 w-100 p-4 d-none">
                @if (!$comingSoonReady)
                    <div class="alert alert-warning mb-0">Run the database migrations to use this (sidebar_menus.coming_soon is missing).</div>
                @else
                    <p class="text-muted f-13 mb-3">
                        Mark a module that is still being built as <b>Coming soon</b>. People keep seeing it in the sidebar with a
                        <span class="cs-soon-demo">Soon</span> tag, and its pages show a Coming soon page instead of the work in progress.
                        The Super Admin still sees the real pages, to build and check them.
                    </p>
                    <div class="cs-modules">
                        @foreach ($menuParents as $module)
                            @php $pages = $module->children->where('is_active', true)->count(); @endphp
                            <div class="cs-module {{ $module->coming_soon ? 'is-soon' : '' }}">
                                <i class="cs-module-icon {{ $module->icon ? $module->display_icon : 'fa fa-folder' }}"></i>
                                <div class="cs-module-name">
                                    <b>{{ $module->name }}</b>
                                    <small>{{ $pages ? $pages . ' ' . \Illuminate\Support\Str::plural('page', $pages) : 'one page' }}</small>
                                </div>
                                @if ($module->slug === 'settings')
                                    <span class="cs-module-state text-muted" title="This page lives in Settings">Always open</span>
                                @else
                                    <span class="cs-module-state">{{ $module->coming_soon ? 'Coming soon' : 'Open' }}</span>
                                    <label class="cs-switch" title="Coming soon">
                                        <input type="checkbox" class="cs-toggle" data-url="{{ route('assign-menus.coming-soon', $module->id) }}" @checked($module->coming_soon)>
                                        <span></span>
                                    </label>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <section id="assignMenusPanel" class="assign-menus-panel d-none" aria-live="polite">
                <div class="assign-menus-panel-header">
                    <div>
                        <h3 id="assignMenusPanelTitle" class="mb-1">Manage Menu Access</h3>
                        <p class="mb-0 text-muted">Choose the menus available to this role.</p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeAssignMenusPanel">
                        <i class="fa fa-times mr-1"></i> Close
                    </button>
                </div>
                <div id="assignMenusPanelBody">
                    <div class="text-center py-5">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Loading menus...</p>
                    </div>
                </div>
            </section>
        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

@endsection

@push('styles')
    <style>
        .assign-menus-title-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: .5rem;
            padding-right: 1.25rem;
            border-bottom: 1px solid #e5e9ef;
        }

        .assign-menus-tabs {
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .assign-menus-tab,
        .assign-menus-tab-link {
            padding: .55rem .85rem;
            border: 1px solid transparent;
            border-radius: 4px 4px 0 0;
            background: transparent;
            color: #65758b;
            cursor: pointer;
        }

        .assign-menus-tab-link:hover {
            color: #2563eb;
            text-decoration: none;
        }

        .assign-menus-tab.active {
            border-color: #dfe5ec #dfe5ec #fff;
            background: #fff;
            color: #2563eb;
            font-weight: 600;
        }

        .add-menu-content {
            width: 100%;
            padding: 1.5rem;
        }

        .add-menu-form-wrap {
            max-width: 900px;
            padding: 1.5rem;
            border: 1px solid #dfe5ec;
            border-radius: 6px;
            background: #fff;
        }

        .add-menu-form-wrap h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .assign-menus-panel {
            width: 100%;
            margin: 0 1rem 1.5rem;
            padding: 1rem;
            border: 1px solid #dfe5ec;
            border-radius: 6px;
            background: #fff;
        }

        .assign-menus-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e9edf2;
        }

        .assign-menus-panel-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .assigned-menu-details summary {
            color: #526174;
            cursor: pointer;
            font-size: 12px;
        }

        .assigned-menu-list {
            max-height: 150px;
            overflow-y: auto;
            padding-left: 1.2rem;
            color: #526174;
            font-size: 12px;
        }

        .assign-menus-panel .menu-access-tree {
            max-height: 55vh;
            overflow-y: auto;
        }

        .cs-soon-demo { font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 1px 7px; border-radius: 10px;
            color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); }
        .cs-modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; }
        .cs-module { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #eaecf0; border-radius: 10px; background: #fff; }
        .cs-module.is-soon { border-color: color-mix(in srgb, var(--epic-blue, #cb6b4f) 45%, #fff); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 5%, #fff); }
        .cs-module-icon { width: 34px; height: 34px; flex: none; border-radius: 8px; display: grid; place-items: center; background: #f2f4f7; color: #475467; font-size: 15px; }
        .cs-module-name { flex: 1 1 auto; min-width: 0; }
        .cs-module-name b { display: block; font-size: 14px; color: #101828; font-weight: 600; }
        .cs-module-name small { font-size: 12px; color: #98a2b3; }
        .cs-module-state { font-size: 12px; color: #667085; white-space: nowrap; }
        .cs-module.is-soon .cs-module-state { color: var(--epic-blue, #cb6b4f); font-weight: 600; }
        .cs-switch { position: relative; width: 40px; height: 22px; margin: 0; flex: none; cursor: pointer; }
        .cs-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .cs-switch span { position: absolute; inset: 0; border-radius: 22px; background: #d0d5dd; transition: background .15s; }
        .cs-switch span::before { content: ''; position: absolute; left: 3px; top: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; box-shadow: 0 1px 2px rgba(16, 24, 40, .2); }
        .cs-switch input:checked + span { background: var(--epic-blue, #cb6b4f); }
        .cs-switch input:checked + span::before { transform: translateX(18px); }
        .cs-switch input:disabled + span { opacity: .6; }

        @media (max-width: 767.98px) {
            .assign-menus-panel {
                margin: 0 0 .75rem;
                padding: .75rem;
            }

            .assign-menus-panel-header {
                align-items: flex-start;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function () {

            $(document).on('click', '.assign-menus-tab', function () {
                var tab = $(this).data('menu-tab');
                $('.assign-menus-tab').removeClass('active').attr('aria-selected', 'false');
                $(this).addClass('active').attr('aria-selected', 'true');
                $('#assignMenuTabContent').toggleClass('d-none', tab !== 'assign');
                $('#modulesTabContent').toggleClass('d-none', tab !== 'modules');
                $('#assignMenusPanel').addClass('d-none');
            });

            // Modules: Coming soon on / off, saved at once
            $(document).off('change', '.cs-toggle').on('change', '.cs-toggle', function () {
                var box = $(this), on = box.is(':checked'), row = box.closest('.cs-module');
                box.prop('disabled', true);
                $.easyAjax({
                    url: box.data('url'),
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', coming_soon: on ? 1 : 0 },
                    success: function (r) {
                        if (r.status === 'success') {
                            row.toggleClass('is-soon', on).find('.cs-module-state').text(on ? 'Coming soon' : 'Open');
                        } else {
                            box.prop('checked', !on);
                        }
                    },
                    error: function () { box.prop('checked', !on); },
                    complete: function () { box.prop('disabled', false); }
                });
            });


            $(document).off('click', '.assign-menus-btn').on('click', '.assign-menus-btn', function (e) {

                e.preventDefault();
                e.stopPropagation();

                var roleId   = $(this).data('role-id');
                var roleName = $(this).data('role-name');

                $('#assignMenusPanelTitle').text('Manage Access: ' + roleName);

                $('#assignMenusPanelBody').html(
                    '<div class="text-center py-5">' +
                        '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>' +
                        '<p class="mt-2 text-muted">Loading menus...</p>' +
                    '</div>'
                );

                $('#assignMenusPanel').removeClass('d-none');
                $('#assignMenusPanel')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });

                var url = "{{ route('assign-menus.access', ':roleId') }}".replace(':roleId', roleId);

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success' && response.html) {
                            $('#assignMenusPanelBody').html(response.html);
                        } else {
                            $('#assignMenusPanelBody').html(
                                '<div class="alert alert-warning">No menu data returned.</div>'
                            );
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        $('#assignMenusPanelBody').html(
                            '<div class="alert alert-danger">' +
                                '<i class="fa fa-exclamation-triangle mr-1"></i> ' +
                                'Failed to load menus. Please try again.' +
                            '</div>'
                        );
                    }
                });

                return false;
            });

            $(document).off('click', '#closeAssignMenusPanel').on('click', '#closeAssignMenusPanel', function () {
                $('#assignMenusPanel').addClass('d-none');
                $('#assignMenusPanelBody').empty();
            });

        });
    </script>
@endpush
