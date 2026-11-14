@extends('layouts.app')

@section('content')
<div class="content-wrapper at">
    {{-- Count on the left; add a type on the right; then the list --}}
    <div class="at-bar">
        <span class="at-count"><b>{{ $types->count() }}</b> asset types</span>
        <form id="add-type-form" class="at-add" onsubmit="return false">
            @csrf
            <input type="text" name="name" class="form-control" placeholder="New type, e.g. Tablet" maxlength="100">
            <button type="button" id="add-type" class="btn-primary rounded f-14"><i class="fa fa-plus mr-1"></i>Add Type</button>
        </form>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="at-card">
                <table class="table mb-0 f-14">
                    <thead><tr><th>Name</th><th class="text-center">Assets</th><th class="text-right pr-4">Action</th></tr></thead>
                    <tbody>
                    @forelse ($types as $type)
                        <tr data-type-id="{{ $type->id }}">
                            <td>
                                <span class="type-name">{{ $type->name }}</span>
                                <input type="text" class="form-control form-control-sm f-14 type-input d-none" value="{{ $type->name }}" maxlength="100">
                            </td>
                            <td class="text-center">
                                <a href="{{ route('assets.index') }}" class="text-darkest-grey">{{ $type->assets_count }}</a>
                            </td>
                            <td class="text-right pr-4 text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary rename-type"><i class="fa fa-edit"></i> Rename</button>
                                <button type="button" class="btn btn-sm btn-primary save-type d-none"><i class="fa fa-check"></i> Save</button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-type at-icon" @disabled($type->assets_count)
                                    title="{{ $type->assets_count ? 'Assets are filed under this type' : 'Delete' }}"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-cards.no-record icon="list" message="No asset types yet." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .at-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .at-count { font-size: 14px; color: #667085; }
    .at-count b { color: #101828; font-size: 16px; margin-right: 3px; }
    .at-add { display: flex; gap: 10px; margin: 0; }
    .at-add .form-control { width: 280px; height: 38px; border-radius: 8px; border: 1px solid #e8e9e9; font-size: 14px; }
    .at-add .form-control:focus { border-color: var(--epic-blue, #cb6b4f); box-shadow: 0 0 0 3px color-mix(in srgb, var(--epic-blue, #cb6b4f) 15%, transparent); }
    body.epic .at-add .btn-primary { height: 38px; border-radius: 8px !important; padding: 0 14px !important; display: inline-flex; align-items: center; white-space: nowrap; }
    .at-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); overflow: hidden; }
    .at-card table th { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085; padding: 14px 20px !important;
        border-top: 0 !important; border-bottom: 1px solid #eaecf0 !important; background: #fff; }
    .at-card table td { padding: 12px 20px !important; vertical-align: middle !important; border-top: 1px solid #f2f4f7 !important; color: #101828; }
    .at-card table tbody tr:hover td { background: #fcfcfd; }
    body.epic .at-card .btn-sm { height: 32px; border-radius: 8px !important; display: inline-flex; align-items: center; gap: 4px; padding: 0 12px !important; }
    body.epic .at-card .at-icon { width: 32px; padding: 0 !important; justify-content: center; }
    .at-card .delete-type:disabled { opacity: .35; }
</style>
@endsection

@push('scripts')
<script>
    $(function () {
        const token = '{{ csrf_token() }}';
        const updateUrl = "{{ route('asset-types.update', ':id') }}";

        $('#add-type').on('click', function () {
            $.easyAjax({
                url: "{{ route('asset-types.store') }}",
                container: '#add-type-form',
                type: 'POST',
                blockUI: true,
                disableButton: true,
                buttonSelector: '#add-type',
                data: $('#add-type-form').serialize(),
                success: function (response) {
                    if (response.status === 'success') {
                        window.location.reload();
                    }
                }
            });
        });

        $('.rename-type').on('click', function () {
            const row = $(this).closest('tr');
            row.find('.type-name, .rename-type').addClass('d-none');
            row.find('.type-input, .save-type').removeClass('d-none');
            row.find('.type-input').focus();
        });

        $('.save-type').on('click', function () {
            const row = $(this).closest('tr');
            $.easyAjax({
                url: updateUrl.replace(':id', row.data('type-id')),
                type: 'POST',
                blockUI: true,
                data: {_token: token, _method: 'PUT', name: row.find('.type-input').val()},
                success: function (response) {
                    if (response.status === 'success') {
                        window.location.reload();
                    }
                }
            });
        });

        $('.delete-type').on('click', function () {
            const row = $(this).closest('tr');
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: 'Delete the type ' + row.find('.type-name').text() + '?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: "@lang('messages.confirmDelete')",
                cancelButtonText: "@lang('app.cancel')",
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    $.easyAjax({
                        url: updateUrl.replace(':id', row.data('type-id')),
                        type: 'POST',
                        blockUI: true,
                        data: {_token: token, _method: 'DELETE'},
                        success: function (response) {
                            if (response.status === 'success') {
                                row.remove();
                            }
                        }
                    });
                }
            });
        });
    });
</script>
@endpush
