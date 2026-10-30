<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Errors from the mobile app API in the same shape as its answers:
 * { success: false, message, errors }. Nothing internal is shown for an
 * unexpected error; it is still logged. Used by App\Http\Middleware\MobileApi.
 */
class MobileApiErrors
{
    public static function render(Throwable $e)
    {
        [$status, $message, $errors] = match (true) {
            $e instanceof ValidationException => [422, collect($e->errors())->flatten()->first() ?: 'Please check the details sent.', $e->errors()],
            $e instanceof AuthenticationException => [401, 'Please log in again.', []],
            $e instanceof AuthorizationException => [403, 'You are not allowed to do that.', []],
            $e instanceof ModelNotFoundException => [404, 'Not found.', []],
            $e instanceof ThrottleRequestsException => [429, 'Too many attempts. Please wait a minute and try again.', []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), self::httpMessage($e), []],
            default => [500, 'Something went wrong. Please try again.', []],
        };

        return response()->json(['success' => false, 'message' => $message, 'errors' => (object) $errors], $status);
    }

    private static function httpMessage(HttpExceptionInterface $e): string
    {
        return match ($e->getStatusCode()) {
            403 => 'You are not allowed to do that.',
            404 => 'Not found.',
            405 => 'That method is not allowed here.',
            default => $e->getMessage() ?: 'Request failed.',
        };
    }
}
