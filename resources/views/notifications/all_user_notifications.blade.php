@extends('layouts.app')

@section('content')

<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12">
            <x-cards.data padding="false" title="Notifications">
                <div class="nt-page">
                    @forelse ($notificationList as $notification)

                        @if(view()->exists('notifications.'.$userType.'.'.\Illuminate\Support\Str::snake(class_basename($notification->type))))
                            @include('notifications.'.$userType.'.'.\Illuminate\Support\Str::snake(class_basename($notification->type)))
                        @endif

                        @foreach ($arowanaPlugins as $item)
                            @if(view()->exists(strtolower($item).'::notifications.'.\Illuminate\Support\Str::snake(class_basename($notification->type))))
                                @include(strtolower($item).'::notifications.'.\Illuminate\Support\Str::snake(class_basename($notification->type)))
                            @endif
                        @endforeach

                    @empty
                        <div class="nt-empty"><i class="fa fa-bell-slash"></i>@lang('messages.noNotification')</div>
                    @endforelse
                </div>
            </x-cards.data>
        </div>
    </div>
</div>

@endsection
