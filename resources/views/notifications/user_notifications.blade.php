{{-- The bell panel's rows: the latest notifications, read and unread. --}}
@forelse ($notificationList as $notification)
    @if(view()->exists('notifications.'.$userType.'.'.\Illuminate\Support\Str::snake(class_basename($notification->type))))
        @include('notifications.'.$userType.'.'.\Illuminate\Support\Str::snake(class_basename($notification->type)))
    @endif

    @foreach ($arowanaPlugins as $item)
        @if(View::exists(strtolower($item).'::notifications.'.\Illuminate\Support\Str::snake(class_basename($notification->type))))
            @include(strtolower($item).'::notifications.'.\Illuminate\Support\Str::snake(class_basename($notification->type)))
        @endif
    @endforeach
@empty
    <div class="nt-empty"><i class="fa fa-bell-slash"></i>@lang('messages.noNotification')</div>
@endforelse
