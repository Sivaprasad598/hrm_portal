@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">
                        App Launcher Settings
                    </h2>
                    <p class="mb-0 px-4 pt-2 text-muted f-13">
                        Add, edit, delete, and reorder the launcher tiles shown in the header popup.
                    </p>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">

                {{-- ==================== TOP BAR ==================== --}}
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <div>
                        <span class="badge badge-primary p-2 mr-1">
                            <i class="fa fa-th"></i>
                            Total: {{ $launchers->count() }}
                        </span>
                        <span class="badge badge-success p-2">
                            <i class="fa fa-check-circle"></i>
                            Active: {{ $launchers->where('is_active', 1)->count() }}
                        </span>
                    </div>

                    <button type="button" class="btn btn-primary" id="addLauncherBtn">
                        <i class="fa fa-plus"></i> Add Launcher
                    </button>
                </div>

                {{-- ==================== LAUNCHER GRID ==================== --}}
                <div class="launcher-grid">
                    @forelse ($launchers as $l)
                        <div class="launcher-card {{ !$l->is_active ? 'inactive' : '' }}">
                            <span class="launcher-status-badge">
                                @if ($l->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </span>

                            <div class="launcher-icon" style="background: {{ $l->color }};">
                                <i class="{{ $l->icon }}"></i>
                            </div>

                            <div class="launcher-name">{{ $l->name }}</div>
                            <div class="launcher-order">Priority #{{ $l->sort_order }}</div>

                            <div class="launcher-actions">
                                <button type="button" class="btn btn-xs btn-outline-primary edit-launcher-btn"
                                        data-id="{{ $l->id }}" title="Edit">
                                    <i class="fa fa-pencil-alt"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-warning toggle-active-btn"
                                        data-id="{{ $l->id }}" title="Toggle Active">
                                    <i class="fa fa-power-off"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger delete-launcher-btn"
                                        data-id="{{ $l->id }}" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted w-100">
                            <i class="fa fa-th fa-3x mb-3"></i>
                            <p>No launchers found. Click <b>"+ Add Launcher"</b> to begin.</p>
                        </div>
                    @endforelse
                </div>

            </div>
        </x-setting-card>
    </div>
    <!-- SETTINGS END -->

    <!-- ==================== LAUNCHER MODAL ==================== -->
    <div class="modal fade" id="launcherModal" tabindex="-1" role="dialog"
         aria-labelledby="launcherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="launcherModalLabel">
                        <i class="fa fa-th mr-2"></i> App Launcher
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="launcherModalBody">
                    <div class="text-center py-5">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        #launcherModal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        #launcherModal .modal-header {
            padding: 20px 24px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }

        #launcherModal .modal-title {
            color: #253047;
            font-size: 16px;
            font-weight: 600;
        }

        #launcherModal .modal-header .close {
            color: #858b94;
            opacity: 1;
            text-shadow: none;
        }

        #launcherModal .modal-body {
            padding: 24px;
        }

        .launcher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .launcher-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px 12px;
            text-align: center;
            position: relative;
            transition: all 0.2s ease;
        }

        .launcher-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        .launcher-card.inactive {
            opacity: 0.55;
        }

        .launcher-status-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            font-size: 10px;
        }

        .launcher-status-badge .badge {
            font-size: 9px;
            padding: 3px 6px;
            border-radius: 4px;
        }

        .launcher-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            margin-bottom: 10px;
            transition: transform 0.2s ease;
        }

        .launcher-card:hover .launcher-icon {
            transform: scale(1.08);
        }

        .launcher-name {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }

        .launcher-order {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .launcher-actions {
            position: absolute;
            top: 6px;
            right: 6px;
            display: none;
            gap: 3px;
        }

        .launcher-card:hover .launcher-actions {
            display: flex;
        }

        .launcher-actions .btn-xs {
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 4px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function () {

            // ==========================================================
            // 1) ADD LAUNCHER
            // ==========================================================
            $(document).off('click', '#addLauncherBtn').on('click', '#addLauncherBtn', function (e) {
                e.preventDefault();
                openLauncherModal("{{ route('app-launcher-settings.create') }}", 'Add App Launcher');
            });

            // ==========================================================
            // 2) EDIT LAUNCHER
            // ==========================================================
            $(document).off('click', '.edit-launcher-btn').on('click', '.edit-launcher-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');
                var url = "{{ route('app-launcher-settings.edit', ':id') }}".replace(':id', id);
                openLauncherModal(url, 'Edit App Launcher');
            });

            // ==========================================================
            // 3) TOGGLE ACTIVE
            // ==========================================================
            $(document).off('click', '.toggle-active-btn').on('click', '.toggle-active-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');
                var url = "{{ route('app-launcher-settings.toggle-active', ':id') }}".replace(':id', id);

                $.easyAjax({
                    url: url,
                    type: 'POST',
                    blockUI: true,
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (response.status === 'success') {
                            location.reload();
                        }
                    }
                });
            });

            // ==========================================================
            // 4) DELETE LAUNCHER
            // ==========================================================
            $(document).off('click', '.delete-launcher-btn').on('click', '.delete-launcher-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Are you sure?',
                    html: 'This will delete the launcher <b>and remove it from the header popup</b>.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa fa-trash"></i> Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        var url = "{{ route('app-launcher-settings.destroy', ':id') }}".replace(':id', id);
                        $.easyAjax({
                            url: url,
                            type: 'POST',
                            blockUI: true,
                            data: {
                                _token: '{{ csrf_token() }}',
                                _method: 'DELETE'
                            },
                            success: function (response) {
                                if (response.status === 'success') {
                                    location.reload();
                                }
                            }
                        });
                    }
                });
            });

            // ==========================================================
            // 5) MODAL OPENER
            // ==========================================================
            function openLauncherModal(url, title) {
                $('#launcherModalLabel').html('<i class="fa fa-th mr-2"></i> ' + title);
                $('#launcherModalBody').html(
                    '<div class="text-center py-5">' +
                        '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>' +
                        '<p class="mt-2 text-muted">Loading...</p>' +
                    '</div>'
                );
                $('#launcherModal').modal('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success' && response.html) {
                            $('#launcherModalBody').html(response.html);
                            if (typeof $('.select-picker').selectpicker === 'function') {
                                $('.select-picker').selectpicker('refresh');
                            }
                        } else {
                            $('#launcherModalBody').html(
                                '<div class="alert alert-warning">No form data returned.</div>'
                            );
                        }
                    },
                    error: function () {
                        $('#launcherModalBody').html(
                            '<div class="alert alert-danger">' +
                                '<i class="fa fa-exclamation-triangle mr-1"></i>' +
                                ' Failed to load form.' +
                            '</div>'
                        );
                    }
                });
            }

        });
    </script>
@endpush
