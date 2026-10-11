@php
$editPermission = user()->permission('edit_notice');
$deletePermission = user()->permission('delete_notice');
@endphp
<div id="notice-detail-section">
    <div class="row">
        <div class="col-sm-12">
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-header bg-white  border-bottom-grey  justify-content-between p-20">
                    <div class="row">
                        <div class="col-lg-10 col-10">
                            <h3 class="heading-h1 mb-3">@lang('app.noticeDetails')</h3>
                        </div>
                        <div class="col-lg-2 col-2 text-right">

                            @if (!in_array('client', user_roles()) && (($editPermission == 'all' || ($editPermission == 'added' && $notice->added_by == user()->id) || ($editPermission == 'owned' && in_array($notice->to, user_roles())) || ($editPermission == 'both' && (in_array($notice->to, user_roles()) || $notice->added_by == user()->id))) || ($deletePermission == 'all' || ($deletePermission == 'added' && $notice->added_by == user()->id) || ($deletePermission == 'owned' && in_array($notice->to, user_roles())) || ($deletePermission == 'both' && (in_array($notice->to, user_roles()) || $notice->added_by == user()->id)))))
                                <div class="dropdown">
                                    <button
                                        class="btn btn-lg f-14 px-2 py-1 text-dark-grey  rounded  dropdown-toggle"
                                        type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa fa-ellipsis-h"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0"
                                        aria-labelledby="dropdownMenuLink" tabindex="0">

                                        @if ($editPermission == 'all' || ($editPermission == 'added' && $notice->added_by == user()->id) || ($editPermission == 'owned' && in_array($notice->to, user_roles())) || ($editPermission == 'both' && (in_array($notice->to, user_roles()) || $notice->added_by == user()->id)))
                                            <a class="dropdown-item openRightModal"
                                                href="{{ route('notices.edit', $notice->id) }}">@lang('app.edit')</a>
                                        @endif
                                        @if ($deletePermission == 'all' || ($deletePermission == 'added' && $notice->added_by == user()->id) || ($deletePermission == 'owned' && in_array($notice->to, user_roles())) || ($deletePermission == 'both' && (in_array($notice->to, user_roles()) || $notice->added_by == user()->id)))
                                            <a class="dropdown-item delete-notice">@lang('app.delete')</a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <x-cards.data-row :label="__('modules.notices.noticeHeading')" :value="$notice->heading" />
                    <x-cards.data-row :label="__('app.date')"
                        :value="$notice->created_at->translatedFormat(company()->date_format)" />

                    <x-cards.data-row :label="__('app.to')" :value="__('app.'.$notice->to)" />

                    <div class="col-12 px-0 pb-2 d-lg-flex d-md-flex d-block">
                        <p class="mb-0 text-lightest f-13 w-30">
                        {{$notice->to == 'employee' ? __('app.employee') : __('app.client')}}</p>
                        <div class="mb-0 text-dark-grey f-13 w-70 text-wrap">
                            @if (count($noticeEmployees) > 0)
                                <div class="w-70">
                                    <div class="d-flex flex-wrap align-items-center">
                                        @foreach ($noticeEmployees->take(3) as $item)
                                            <div class="task-assignee-item d-flex align-items-center bg-light-grey rounded-pill px-2 py-1 mr-2 mb-1">
                                                <div class="taskEmployeeImg rounded-circle mr-2">
                                                    <a href="{{ route('employees.show', $item->id) }}">
                                                        <img data-toggle="tooltip" data-original-title="{{ $item->name }}"
                                                             src="{{ $item->image ? $item->masked_image_url : asset('img/avatar.png') }}"
                                                             class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                    </a>
                                                </div>
                                                <span class="f-12 text-darkest-grey">{{ $item->name }}</span>
                                                @if($item->status == 'deactive')
                                                    <span class="badge badge-pill badge-danger ml-1 f-10">Inactive</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (count($noticeEmployees) > 3)
                                        <div class="mt-2">
                                            <a href="javascript:;" class="text-primary f-11 amm-all" data-toggle="modal" data-target="#assigned-members-modal">
                                                <i class="fa fa-eye mr-1"></i>View All ({{ count($noticeEmployees) }} members)
                                            </a>
                                        </div>
                                    @else
                                        <div class="mt-2">
                                            <small class="text-lightest f-11">
                                                <i class="fa fa-users mr-1"></i>
                                                {{ count($noticeEmployees) }} {{ count($noticeEmployees) == 1 ? 'person' : 'people' }} assigned
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            @elseif (count($noticeClients) > 0)
                                <div class="w-70">
                                    <div class="d-flex flex-wrap align-items-center">
                                        @foreach ($noticeClients->take(3) as $item)
                                            <div class="task-assignee-item d-flex align-items-center bg-light-grey rounded-pill px-2 py-1 mr-2 mb-1">
                                                <div class="taskEmployeeImg rounded-circle mr-2">
                                                    <a href="{{ route('clients.show', $item->id) }}">
                                                        <img data-toggle="tooltip" data-original-title="{{ $item->name }}"
                                                             src="{{ $item->image ? $item->masked_image_url : asset('img/avatar.png') }}"
                                                             class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                    </a>
                                                </div>
                                                <span class="f-12 text-darkest-grey">{{ $item->name }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (count($noticeClients) > 3)
                                        <div class="mt-2">
                                            <a href="javascript:;" class="text-primary f-11 amm-all" data-toggle="modal" data-target="#assigned-members-modal">
                                                <i class="fa fa-eye mr-1"></i>View All ({{ count($noticeClients) }} members)
                                            </a>
                                        </div>
                                    @else
                                        <div class="mt-2">
                                            <small class="text-lightest f-11">
                                                <i class="fa fa-users mr-1"></i>
                                                {{ count($noticeClients) }} {{ count($noticeClients) == 1 ? 'person' : 'people' }} assigned
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            @else
                                --
                            @endif
                        </div>
                    </div>

                    <x-cards.data-row :label="__('app.description')" :value="!empty($notice->description) ? $notice->description : '--'" html="true" />

                    @if (!is_null($notice->attachment))
                        <x-cards.data-row :label="__('app.viewAttachment')"
                            value='<a target="_blank" href="{{ $notice->file_url }}" title="@lang('app.viewAttachment')">
                            <span class="btn btn-sm btn-info"> @lang('app.viewAttachment') </span>
                            </a>'/>
                    @endif

                    @if (in_array('admin', user_roles()))
                        <div class="col-12 px-0 pb-2 d-lg-flex d-md-flex d-block">
                            <p class="mb-0 text-lightest f-13 w-30" >@lang('app.readBy')</p>
                            <div class="mb-0 text-dark-grey f-13 w-70 text-wrap">
                            @if (count($readMembers) > 0)
                                <div class="w-70">
                                    <div class="d-flex flex-wrap align-items-center">
                                        @foreach ($readMembers->take(3) as $item)
                                            @if($notice->to == 'employee')
                                                <div class="task-assignee-item d-flex align-items-center bg-light-grey rounded-pill px-2 py-1 mr-2 mb-1">
                                                    <div class="taskEmployeeImg rounded-circle mr-2">
                                                        <a href="{{ route('employees.show', $item->user->id) }}">
                                                            <img data-toggle="tooltip" data-original-title="{{ $item->user->name }}"
                                                                 src="{{ $item->user->image_url }}"
                                                                 class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                        </a>
                                                    </div>
                                                    <span class="f-12 text-darkest-grey">{{ $item->user->name }}</span>
                                                </div>
                                            @else
                                                <div class="task-assignee-item d-flex align-items-center bg-light-grey rounded-pill px-2 py-1 mr-2 mb-1">
                                                    <div class="taskEmployeeImg rounded-circle mr-2">
                                                        <a href="{{ route('clients.show', $item->user->id) }}">
                                                            <img data-toggle="tooltip" data-original-title="{{ $item->user->name }}"
                                                                src="{{ $item->user->image_url }}"
                                                                class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                        </a>
                                                    </div>
                                                    <span class="f-12 text-darkest-grey">{{ $item->user->name }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if (count($readMembers) > 3)
                                        <div class="mt-2">
                                            <a href="javascript:;" class="text-primary f-11 amm-all" data-toggle="modal" data-target="#read-members-modal">
                                                <i class="fa fa-eye mr-1"></i>View All ({{ count($readMembers) }} members)
                                            </a>
                                        </div>
                                    @else
                                        <div class="mt-2">
                                            <small class="text-lightest f-11">
                                                <i class="fa fa-users mr-1"></i>
                                                {{ count($readMembers) }} {{ count($readMembers) == 1 ? 'person' : 'people' }} assigned
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            @else
                                --
                            @endif
                            </div>
                        </div>
                    @endif

                    <div class="col-12 px-0 mt-3 pb-3 d-flex">
                        <p class="mb-0 text-lightest f-14 w-30 d-inline-block text-capitalize">
                            @lang('app.file')</p>
                        <div class="d-flex flex-wrap" id="notice-file-list">
                            @php
                                $filesShowCount = 0;
                            @endphp
                            @forelse($notice->files as $file)
                                @php
                                    $filesShowCount++;
                                @endphp
                                <x-file-card :fileName="$file->filename" :dateAdded="$file->created_at->diffForHumans()">
                                    <x-file-view-thumbnail :file="$file"></x-file-view-thumbnail>
                                    <x-slot name="action">
                                        <div class="dropdown ml-auto file-action">
                                            <button class="btn btn-lg f-14 p-0 text-lightest text-capitalize rounded  dropdown-toggle"
                                                    type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fa fa-ellipsis-h"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0"
                                                aria-labelledby="dropdownMenuLink" tabindex="0">
                                                @if ($file->icon = 'images')
                                                    @if ($file->icon == 'images')
                                                        <a class="img-lightbox cursor-pointer d-block text-dark-grey f-13 pt-3 px-3" data-image-url="{{ $file->file_url }}" href="javascript:;">@lang('app.view')</a>
                                                    @else
                                                        <a class="cursor-pointer d-block text-dark-grey f-13 pt-3 px-3 " target="_blank" href="{{ $file->file_url }}">@lang('app.view')</a>
                                                    @endif
                                                @endif
                                                <a class="cursor-pointer d-block text-dark-grey f-13 py-3 px-3 "
                                                href="{{ route('notice_files.download', md5($file->id)) }}">@lang('app.download')</a>

                                                @if ($deletePermission == 'all'
                                                        || ($deletePermission == 'added' && $notice->added_by == user()->id)
                                                        || ($deletePermission == 'owned' && in_array($notice->to, user_roles()))
                                                        || ($deletePermission == 'both' && (in_array($notice->to, user_roles()) || $notice->added_by == user()->id)))
                                                    <a class="cursor-pointer d-block text-dark-grey f-13 pb-3 px-3 delete-file"
                                                    data-row-id="{{ $file->id }}" href="javascript:;">@lang('app.delete')</a>
                                                @endif
                                            </div>
                                        </div>
                                    </x-slot>
                                </x-file-card>
                            @empty
                                <x-cards.no-record :message="__('messages.noFileUploaded')" icon="file"/>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $('body').on('click', '.delete-notice', function() {
        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.recoverRecord')",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('messages.confirmDelete')",
            cancelButtonText: "@lang('app.cancel')",
            customClass: {
                confirmButton: 'btn btn-primary mr-3',
                cancelButton: 'btn btn-secondary'
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{ route('notices.destroy', $notice->id) }}";

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    data: {
                        '_token': token,
                        '_method': 'DELETE'
                    },
                    success: function(response) {
                        if (response.status == "success") {
                            window.location.href = response.redirectUrl;
                        }
                    }
                });
            }
        });
    });

    $('body').on('click', '.delete-file', function () {
        var id = $(this).data('row-id');
        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.recoverRecord')",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('messages.confirmDelete')",
            cancelButtonText: "@lang('app.cancel')",
            customClass: {
                confirmButton: 'btn btn-primary mr-3',
                cancelButton: 'btn btn-secondary'
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{ route('notice-files.destroy', ':id') }}";
                url = url.replace(':id', id);

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    data: {
                        '_token': token,
                        '_method': 'DELETE'
                    },
                    success: function (response) {
                        if (response.status == "success") {
                            $('#notice-file-list').html(response.view);
                        }
                    }
                });
            }
        });
    });

    $('body').on('click', '.amm-all', function () {
        setTimeout(function() {
            $('.modal-backdrop').hide();
        }, 150);
    });
</script>



<!-- Assigned Members Modal -->
<div id="assigned-members-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('modules.tasks.assignTo') - {{ $notice->heading }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    @if ($notice->to == 'employee' && count($noticeEmployees) > 0)
                        @foreach ($noticeEmployees as $item)
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <div class="taskEmployeeImg rounded-circle mr-3">
                                        <a href="{{ route('employees.show', $item->id) }}">
                                            <img data-toggle="tooltip" data-original-title="{{ $item->name }}"
                                                src="{{ $item->image ? $item->masked_image_url : asset('img/avatar.png') }}"
                                                class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                        </a>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-darkest-grey">{{ $item->name }}</h6>
                                        @if($item->status == 'deactive')
                                            <span class="badge badge-pill badge-danger f-10">Inactive</span>
                                        @endif
                                        @if(isset($item->designation) && $item->designation)
                                            <small class="text-lightest">{{ $item->designation }}</small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @elseif($notice->to == 'client' && count($noticeClients) > 0)
                        @foreach ($noticeClients as $item)
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <div class="taskEmployeeImg rounded-circle mr-3">
                                        <a href="{{ route('clients.show', $item->id) }}">
                                            <img data-toggle="tooltip" data-original-title="{{ $item->name }}"
                                                src="{{ $item->image ? $item->masked_image_url : asset('img/avatar.png') }}"
                                                class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                        </a>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-darkest-grey">{{ $item->name }}</h6>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('app.close')</button>
            </div>
        </div>
    </div>
</div>



<!-- Assigned Members Modal -->
<div id="read-members-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('app.readBy') - {{ $notice->heading }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    @foreach ($readMembers as $item)
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <div class="taskEmployeeImg rounded-circle mr-3">
                                    @if($notice->to == 'employee')
                                        <a href="{{ route('employees.show', $item->user->id) }}">
                                            <img data-toggle="tooltip" data-original-title="{{ $item->user->name }}"
                                                src="{{ $item->user->image ? $item->user->masked_image_url : asset('img/avatar.png') }}"
                                                class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                        </a>
                                    @elseif($notice->to == 'client')
                                        <a href="{{ route('clients.show', $item->user->id) }}">
                                            <img data-toggle="tooltip" data-original-title="{{ $item->user->name }}"
                                                src="{{ $item->user->image ? $item->user->masked_image_url : asset('img/avatar.png') }}"
                                                class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                        </a>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 text-darkest-grey">{{ $item->user->name }}</h6>
                                </div>
                            </div>
                        </div>
                        @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('app.close')</button>
            </div>
        </div>
    </div>
</div>