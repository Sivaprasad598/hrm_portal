{{-- Same message and way back as before; the look is errors/epic --}}
@include('errors.epic', [
    'code' => 404,
    'title' => __('messages.pageNotFound'),
    'heading' => 'Oops.. this page could not be found',
    'message' => __('messages.pageNotFound'),
    'backUrl' => url('/'),
    'backText' => 'Back To Home',
])
