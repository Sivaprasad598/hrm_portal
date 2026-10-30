@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="row">
        {{-- Full width; what types are for, in a line under the title. --}}
        <div class="col-12">
            <div class="bg-white rounded b-shadow-4">
                <div class="p-20 border-bottom-grey d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="f-18 font-weight-normal mb-1">Asset Types</h4>
                        <p class="f-13 text-dark-grey mb-0">
                            Every asset is filed under a type, so the Asset List and Assigned Assets can be filtered by it
                            (all laptops, all SIM cards...). A type that still has assets cannot be deleted: move those assets first.
                        </p>
                    </div>
                    <span class="f-13 text-dark-grey text-nowrap ml-3">{{ $types->count() }} types</span>
                </div>

                <form id="add-type-form" class="p-20 border-bottom-grey d-flex" onsubmit="return false">
                    @csrf
                    <input type="text" name="name" class="form-control f-14 mr-2" style="max-width: 420px" placeholder="New type, e.g. Tablet" maxlength="100">
                    <button type="button" id="add-type" class="btn btn-primary rounded f-14 text-nowrap"><i class="fa fa-plus mr-1"></i>Add Type</button>
                </form>

                <table class="table mb-0 f-14">
                    <thead class="bg-light"><tr><th>Name</th><th class="text-center">Assets</th><th class="text-right pr-4">Action</th></tr></thead>
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
                                <button type="button" class="btn btn-sm btn-outline-danger delete-type" @disabled($type->assets_count)
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
