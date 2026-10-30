@extends('layouts.app')

@push('styles')
    <style>
        .acc-roles { display: flex; flex-wrap: wrap; gap: 8px; }
        .acc-roles a { padding: 7px 14px; border: 1px solid #d0d5dd; border-radius: 18px; font-size: 13px; color: #344054; background: #fff; }
        .acc-roles a small { color: #98a2b3; margin-left: 4px; }
        .acc-roles a:hover { border-color: #1d82f5; color: #1d82f5; text-decoration: none; }
        .acc-roles a.active { background: #1d82f5; border-color: #1d82f5; color: #fff; }
        .acc-roles a.active small { color: #dbeafe; }
        .acc-area { border: 1px solid #eaecf0; border-radius: 10px; margin-bottom: 16px; background: #fff; }
        .acc-area > .acc-area-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eaecf0; background: #f9fafb; border-radius: 10px 10px 0 0; }
        .acc-area-head h5 { margin: 0; font-size: 15px; font-weight: 600; color: #101828; }
        .acc-area-head span { font-size: 12px; color: #667085; }
        .acc-row { display: grid; grid-template-columns: minmax(170px, 230px) 64px 1fr; gap: 12px; align-items: center; padding: 10px 16px; border-bottom: 1px solid #f2f4f7; }
        .acc-row:last-child { border-bottom: 0; }
        .acc-row.off .acc-name { color: #98a2b3; }
        .acc-name { font-size: 14px; color: #101828; font-weight: 500; }
        .acc-actions { display: flex; flex-wrap: wrap; gap: 6px 14px; }
        .acc-action { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: #475467; }
        .acc-action select { height: 28px; padding: 0 6px; font-size: 12.5px; border: 1px solid #d0d5dd; border-radius: 6px; background: #fff; color: #344054; }
        .acc-action.is-on select, .acc-action.is-on .acc-word { color: #067647; }
        .acc-none { font-size: 12px; color: #98a2b3; }
        .acc-other summary { cursor: pointer; font-size: 14px; font-weight: 600; color: #475467; padding: 10px 0; }
        .acc-history td { font-size: 12.5px; }
        @media (max-width: 767.98px) {
            .acc-row { grid-template-columns: 1fr auto; }
            .acc-actions { grid-column: 1 / -1; }
        }
    </style>
@endpush

@section('content')
    <div class="w-100 d-flex">
        <x-setting-sidebar :activeMenu="$activeSettingMenu" />

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center p-20 border-bottom-grey">
                        <div>
                            <h2 class="mb-1 f-21 font-weight-normal">Access</h2>
                            <p class="mb-0 f-13 text-dark-grey">Pick a role, then switch its pages on or off and choose what it may do on each. Changes save as you make them.</p>
                        </div>
                        {{-- The older screens: creating and deleting roles, per-person permissions, other modules' detail. --}}
                        <div class="f-13 mt-2 mt-md-0">
                            <span class="text-lightest mr-1">Advanced:</span>
                            <a href="{{ route('role-permissions.index') }}" class="mr-2">Roles &amp; Permissions</a>
                            <a href="{{ route('assign-menus.index') }}">Assign Menus</a>
                        </div>
                    </div>
                </div>
            </x-slot>

            <div class="col-12 p-20 w-100">
                <div class="acc-roles mb-4">
                    @foreach ($roles as $r)
                        <a href="{{ route('access-settings.index', ['role' => $r->id]) }}" class="{{ $r->id === $role->id ? 'active' : '' }}">
                            {{ $r->name === 'admin' ? 'Super Admin' : $r->display_name }}<small>{{ $r->users_count }}</small>
                        </a>
                    @endforeach
                </div>

                <div id="access-panel">
                    @include('access-settings.panel')
                </div>
            </div>
        </x-setting-card>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            function save(url, data) {
                $.easyAjax({
                    url: url,
                    type: 'POST',
                    blockUI: true,
                    container: '#access-panel',
                    data: Object.assign({ _token: '{{ csrf_token() }}' }, data),
                    success: function (response) {
                        if (response.html) {
                            $('#access-panel').html(response.html);
                        }
                    }
                });
            }

            // A page on or off for the role.
            $('#access-panel').on('change', '.acc-page', function () {
                save("{{ route('access-settings.page') }}", { role_id: $(this).data('role'), menu_id: $(this).data('menu'), on: this.checked ? 1 : 0 });
            });

            // An action: a switch (yes / no) or a level.
            $('#access-panel').on('change', '.acc-perm', function () {
                var level = this.type === 'checkbox' ? (this.checked ? $(this).data('on') : $(this).data('off')) : this.value;
                save("{{ route('access-settings.permission') }}", { role_id: $(this).data('role'), permission_id: $(this).data('permission'), level: level });
            });
        })();
    </script>
@endpush
