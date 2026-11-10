{{-- One notification row (bell panel and the All notifications page). Unread
     rows carry a dot; styles live in css/epic/theme.css under .nt-row. --}}
<div class="card border-0 nt-row {{ is_null($notification->read_at) ? 'is-unread' : '' }}">
    <a class="view-notification nt-link" href="{{ $link }}" data-notification-id="{{ $notification->id }}">
        @if($type == 'image')
            <span class="nt-img"><img src="{{ $image }}" alt=""></span>
        @else
            <span class="nt-img nt-icon notification-icon">{!! $image !!}</span>
        @endif
        <span class="nt-body">
            <span class="nt-title">{{ $title ?? '' }}</span>
            @if (!empty($text))<span class="nt-text">{{ $text }}</span>@endif
            <span class="nt-time">{{ $time->diffForHumans() }}</span>
        </span>
        <span class="nt-dot" aria-hidden="true"></span>
    </a>
</div>
