@php
$editPermission = user()->permission('edit_events');
$deletePermission = user()->permission('delete_events');
$attendeesIds = $event->attendee->pluck('user_id')->toArray();
$canEdit = $editPermission == 'all'
    || ($editPermission == 'added' && $event->added_by == user()->id)
    || ($editPermission == 'owned' && (in_array(user()->id, $attendeesIds) || $event->host == user()->id))
    || ($editPermission == 'both' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id || $event->host == user()->id));
$canDelete = $deletePermission == 'all'
    || ($deletePermission == 'added' && $event->added_by == user()->id)
    || ($deletePermission == 'owned' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id))
    || ($deletePermission == 'both' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id));
$canMark = $event->status == 'pending' && ($editPermission == 'all'
    || ($editPermission == 'added' && $event->added_by == user()->id)
    || ($editPermission == 'owned' && in_array(user()->id, $attendeesIds))
    || ($editPermission == 'both' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id))
    || $event->host == user()->id);
@endphp

<style>
    #event-status2 {
        border-radius: 0 5px 5px 0;
    }

    #event-status {
        border-radius: 5px 0 0 5px;
    }
    /* Fits its popup: no sideways scroll (the .row's negative margins stuck out),
       and the theme's standard card - thin border, soft shadow, rounded. */
    #task-detail-section { overflow-x: hidden; padding: 4px 6px 8px; }
    #task-detail-section > .row { margin-left: 0; margin-right: 0; }
    #task-detail-section > .row > [class*="col-"] { padding-left: 0; padding-right: 0; }
    body.epic #task-detail-section .card.b-shadow-4 { border: 1px solid var(--border-color, #e2e8f0) !important; border-radius: var(--card-radius, 12px) !important;
        box-shadow: var(--card-shadow, 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06)) !important; overflow: hidden; }
    #task-detail-section .heading-h1 { font-size: 20px; margin: 6px 0 14px !important; }
    .ev-people { display: flex; flex-wrap: wrap; gap: 8px 18px; }
    .ev-person { display: inline-flex; align-items: center; gap: 9px; }
    .ev-person img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex: 0 0 32px; }
    .ev-person > span { display: flex; flex-direction: column; line-height: 1.25; font-size: 13.5px; color: var(--epic-text, #212529); }
    .ev-person b { font-weight: 600; }
    .ev-person em { font-style: normal; color: var(--epic-muted, #99a5b5); font-size: 12px; }
    .ev-person small { color: var(--epic-muted, #99a5b5); font-size: 12px; }
</style>

<div id="task-detail-section">
    <h3 class="heading-h1 mb-3">{{ $event->event_name }}</h3>
    <div class="row">
        <div class="col-sm-12">
            <div class="card bg-white border-0 b-shadow-4">
                {{-- The action bar only when there is an action in it --}}
                @if ($canMark || $canEdit || $canDelete)
                <div class="card-header bg-white  border-bottom-grey justify-content-between p-20">
                    <div class="row">
                        <div class="col-lg-8 col-10">
                            @if ($event->status == 'pending')
                                @if ($editPermission == 'all'
                                || ($editPermission == 'added' && $event->added_by == user()->id)
                                || ($editPermission == 'owned' && in_array(user()->id, $attendeesIds))
                                || ($editPermission == 'both' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id))
                                || $event->host == user()->id
                                )
                                    <x-forms.button-primary icon="check" data-event-id="{{$event->id}}" id="event-status" value="completed"
                                                class="mr-2 mb-2 mb-lg-0 mb-md-0">
                                                @lang('app.markComplete')
                                    </x-forms.button-primary>

                                    <x-forms.button-secondary icon="times" data-event-id="{{$event->id}}" id="event-status2" value="cancelled"
                                        class="mr-3">
                                        @lang('app.markCancel')
                                    </x-forms.button-secondary>
                                @endif
                            @endif
                        </div>
                        <div class="col-lg-4 col-2 text-right">
                            {{-- Only when there is something in it --}}
                            @if ($canEdit || $canDelete)
                            <div class="dropdown">
                                <button
                                    class="btn btn-lg f-14 px-2 py-1 text-dark-grey  rounded  dropdown-toggle"
                                    type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-ellipsis-h"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0"
                                    aria-labelledby="dropdownMenuLink" tabindex="0">
                                    @if ($editPermission == 'all'
                                        || ($editPermission == 'added' && $event->added_by == user()->id)
                                        || ($editPermission == 'owned' && (in_array(user()->id, $attendeesIds) || $event->host == user()->id))
                                        || ($editPermission == 'both' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id || $event->host == user()->id))
                                    )
                                        <a class="dropdown-item openRightModal"
                                            href="{{ route('events.edit', $event->id) }}">@lang('app.edit')
                                        </a>
                                    @endif

                                    @if ($deletePermission == 'all'
                                        || ($deletePermission == 'added' && $event->added_by == user()->id)
                                        || ($deletePermission == 'owned' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id))
                                        || ($deletePermission == 'both' && (in_array(user()->id, $attendeesIds) || $event->added_by == user()->id))
                                    )
                                        <a class="dropdown-item delete-event">@lang('app.delete')</a>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
                <div class="card-body">
                    <x-cards.data-row :label="__('modules.events.eventName')" :value="$event->event_name"
                        html="true" />

                    @php
                        // People on the event, by name (a row of bare photos said nothing).
                        $isClient = fn ($u) => $u && $u->roles->pluck('name')->contains('client');
                        $staff = $event->attendee->pluck('user')->filter()->reject($isClient)->values();
                        $clients = $event->attendee->pluck('user')->filter()->filter($isClient)->values();
                        $scheduledBy = $event->added_by ? \App\Models\User::withoutGlobalScopes()->find($event->added_by) : null;
                        $person = function ($u) {
                            $role = optional(optional($u->employeeDetail)->designation)->name;
                            return '<span class="ev-person"><img src="' . e($u->image_url) . '" alt="">'
                                . '<span><b>' . e($u->name) . ($u->id == user()->id ? ' <em>(you)</em>' : '') . '</b>'
                                . ($role ? '<small>' . e($role) . '</small>' : '') . '</span></span>';
                        };
                    @endphp

                    @if (!in_array('client', user_roles()))
                        <div class="col-12 px-0 pb-3 d-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block">Attendees</p>
                            <div class="ev-people">
                                @forelse ($staff as $u){!! $person($u) !!}@empty<span class="text-dark-grey f-14">--</span>@endforelse
                            </div>
                        </div>

                        @if ($clients->isNotEmpty())
                            <div class="col-12 px-0 pb-3 d-flex">
                                <p class="mb-0 text-lightest f-14 w-30 d-inline-block">@lang('app.attendeesClients')</p>
                                <div class="ev-people">@foreach ($clients as $u){!! $person($u) !!}@endforeach</div>
                            </div>
                        @endif
                    @endif

                    <div class="col-12 px-0 pb-3 d-flex">
                        <p class="mb-0 text-lightest f-14 w-30 d-inline-block">@lang('app.host')</p>
                        <div class="ev-people">{!! $event->user ? $person($event->user) : '<span class="text-dark-grey f-14">--</span>' !!}</div>
                    </div>

                    @if ($scheduledBy)
                        <div class="col-12 px-0 pb-3 d-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block">Scheduled by</p>
                            <div class="ev-people">{!! $person($scheduledBy) !!}
                                <span class="text-lightest f-12 align-self-center">on {{ $event->created_at?->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}</span>
                            </div>
                        </div>
                    @endif

                    <x-cards.data-row :label="__('app.description')" :value="$event->description"
                        html="true" />
                    <x-cards.data-row :label="__('app.where')" :value="$event->where"
                        html="true" />
                    <x-cards.data-row :label="__('modules.events.startOn')"
                        :value="$event->start_date_time->copy()->timezone(company()->timezone)->translatedFormat(company()->date_format. ' - '.company()->time_format)"
                        html="true" />
                    <x-cards.data-row :label="__('modules.events.endOn')"
                        :value="$event->end_date_time->copy()->timezone(company()->timezone)->translatedFormat(company()->date_format. ' - '.company()->time_format)"
                        html="true" />
                        @php
                        $url = str_starts_with($event->event_link, 'http') ? $event->event_link : 'http://'.$event->event_link;
                            $link = "<a href=".$url." style='color:black; cursor: pointer;' target='_blank'>$event->event_link</a>";
                        @endphp

                    @if ($event->status)
                        <div class="col-12 px-0 pb-3 d-lg-flex d-md-flex d-block">
                            <p class="mb-0 text-lightest f-14 w-30 ">@lang('app.status')</p>
                            @if ($event->status == 'pending')
                                <p class="mb-0 text-dark-grey f-14 w-70 text-wrap"><i class="fa fa-circle mr-1 text-yellow f-10"></i>{{ ucfirst($event->status) }}</p>
                            @elseif ($event->status == 'completed')
                                <p class="mb-0 text-dark-grey f-14 w-70 text-wrap"><i class="fa fa-circle mr-1 text-dark-green f-10"></i>{{ ucfirst($event->status) }}</p>
                            @elseif ($event->status == 'cancelled')
                                <p class="mb-0 text-dark-grey f-14 w-70 text-wrap"><i class="fa fa-circle mr-1 text-red f-10"></i>{{ ucfirst($event->status) }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($event->note)
                    <x-cards.data-row :label="__('app.note')" :value="$event->note"
                        html="true" />
                    @endif
                    <x-cards.data-row :label="__('modules.events.eventLink')"
                    html="true" :value="$link"/>

                    @if (isset($fields) && count($fields) > 0)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5 class="mb-3"> @lang('modules.projects.otherInfo')</h5>
                                <x-forms.custom-field-show :fields="$fields" :model="$event"></x-forms.custom-field-show>
                            </div>
                        </div>
                    @endif

                    @if ($event->files->count() > 0)
                        <x-cards.data-row :label="__('app.file')"
                        html="true" :value="''"/>
                        <div div class="d-flex flex-wrap mt-3" id="event-file-list">
                            @forelse($event->files as $file)
                                <x-file-card :fileName="$file->filename" :dateAdded="$file->created_at->diffForHumans()">
                                    <x-file-view-thumbnail :file="$file"></x-file-view-thumbnail>

                                        <x-slot name="action">
                                            <div class="dropdown ml-auto file-action">
                                                <button class="btn btn-lg f-14 p-0 text-lightest  rounded  dropdown-toggle"
                                                    type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fa fa-ellipsis-h"></i>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0"
                                                    aria-labelledby="dropdownMenuLink" tabindex="0">
                                                        @if ($file->icon == 'images')
                                                            <a class="img-lightbox cursor-pointer d-block text-dark-grey f-13 pt-3 px-3" data-image-url="{{ $file->file_url }}" href="javascript:;">@lang('app.view')</a>
                                                        @else
                                                            <a class="cursor-pointer d-block text-dark-grey f-13 pt-3 px-3 " target="_blank" href="{{ $file->file_url }}">@lang('app.view')</a>
                                                        @endif
                                                        <a class="cursor-pointer d-block text-dark-grey f-13 py-3 px-3 "
                                                            href="{{ route('event-files.download', md5($file->id)) }}">@lang('app.download')</a>

                                                        <a class="cursor-pointer d-block text-dark-grey f-13 pb-3 px-3 delete-file"
                                                            data-row-id="{{ $file->id }}" href="javascript:;">@lang('app.delete')</a>
                                                </div>
                                            </div>
                                        </x-slot>

                                </x-file-card>
                            @empty
                            <x-cards.no-record :message="__('messages.noFileUploaded')" icon="file" />
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>

$('body').on('click', '.delete-event', function() {
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
                var url = "{{ route('events.destroy', $event->id) }}";

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    data: {
                        '_token': token,
                        '_method': 'DELETE',
                        @if ($event->parent_id)
                        'delete': result.value,
                        @endif
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


    $('body').on('click', '.delete-file', function() {
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
                var url = "{{ route('event-files.destroy', ':id') }}";
                url = url.replace(':id', id);

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
                            $('#event-file-list').html(response.view);
                        }
                    }
                });
            }
        });
    });

    $('#event-status2').click(function () {
        var id = $(this).data('event-id');
        var url = "{{ route('events.event_status_note', ':id') }}?status=cancelled";
        url = url.replace(':id', id);
        $(MODAL_DEFAULT + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_DEFAULT, url);
    });

    $('#event-status').click(function () {
        var id = $(this).data('event-id');
        var url = "{{ route('events.event_status_note', ':id') }}?status=completed";
        url = url.replace(':id', id);
        $(MODAL_DEFAULT + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_DEFAULT, url);
    });

</script>
