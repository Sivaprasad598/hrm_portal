<div class="row">
    <div class="col-sm-12">
        <x-cards.data :title="__('app.recurring') . ' ' . __('app.details')" class=" mt-4">
            <x-cards.data-row :label="__('modules.events.repeatEvery')" :value="$event->repeat_every . ' ' . __('app.'.$event->repeat_type)" />
            <x-cards.data-row :label="__('modules.events.cycles')" :value="$event->repeat_cycles" />
            <div class="col-12 px-0 pb-3 d-block d-lg-flex d-md-flex">
                <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                    @lang('modules.events.completedTotalEvent')</p>
                <p class="mb-0 text-dark-grey f-14 ">
                    {{$completedEventCount}}/{{$event->repeat_cycles}}
                </p>
            </div>
        </x-cards.data>
        <x-cards.data :title="__('app.menu.event') . ' ' . __('app.details')" class=" mt-4">
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-body">
                    <x-cards.data-row :label="__('modules.events.eventName')" :value="$event->event_name"
                        html="true" />

                    @if (!in_array('client', user_roles()))
                        <div class="col-12 px-0 pb-3 d-flex">
                            <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                            @lang('app.attendeesEmployee')</p>
                            <p class="mb-0 text-dark-grey f-14">
                                @foreach ($event->attendee as $item)
                                @if(in_array('employee', $item->user->roles->pluck('name')->toArray()))
                                    <div class="taskEmployeeImg rounded-circle mr-1">
                                        <img data-toggle="tooltip" data-original-title="{{ $item->user->name }}"
                                            src="{{ $item->user->image_url }}">
                                    </div>
                                @endif
                                @endforeach
                            </p>
                        </div>

                        @if (in_array('clients', user_modules()))
                            <div class="col-12 px-0 pb-3 d-flex">
                                <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                                    @lang('app.attendeesClients')</p>
                                <p class="mb-0 text-dark-grey f-14">
                                    @foreach ($event->attendee as $item)
                                    @if(in_array('client', $item->user->roles->pluck('name')->toArray()))
                                        <div class="taskEmployeeImg rounded-circle mr-1">
                                            <img data-toggle="tooltip" data-original-title="{{ $item->user->name }}"
                                                src="{{ $item->user->image_url }}">
                                        </div>
                                    @endif
                                    @endforeach
                                </p>
                            </div>
                        @endif
                    @endif

                    <div class="col-12 px-0 pb-3 d-flex">
                        <p class="mb-0 text-lightest f-14 w-30 d-inline-block ">
                            @lang('app.host')</p>
                        <p class="mb-0 text-dark-grey f-14">
                            @if ($event->user)
                            <div class="taskEmployeeImg rounded-circle mr-1">
                                <img data-toggle="tooltip"
                                data-original-title="{{ $event->user->name }}"
                                src="{{ $event->user->image_url }}">
                            </div>
                            @else
                            --
                            @endif
                        </p>
                    </div>

                    <x-cards.data-row :label="__('app.description')" :value="$event->description"
                        html="true" />
                    <x-cards.data-row :label="__('app.where')" :value="$event->where"
                        html="true" />
                    <x-cards.data-row :label="__('modules.events.startOn')"
                        :value="$event->start_date_time->translatedFormat(company()->date_format. ' - '.company()->time_format)"
                        html="true" />
                    <x-cards.data-row :label="__('modules.events.endOn')"
                        :value="$event->end_date_time->translatedFormat(company()->date_format. ' - '.company()->time_format)"
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
        </x-cards.data>
    </div>
</div>

