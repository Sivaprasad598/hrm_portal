{{-- Same message and way back as before; the look is errors/epic --}}
@include('errors.epic', [
    'code' => $exception->getMessage() ? $exception->getStatusCode() : 403,
    'title' => __('messages.forbidden'),
    'heading' => 'You do not have access to this page',
    'message' => $exception->getMessage() ?: __('messages.permissionDenied'),
    'backUrl' => Route::is('settings.qr-login') ? null : (auth()->check() ? url()->previous() : url('/')),
    'backText' => __('app.backToHome'),
])
