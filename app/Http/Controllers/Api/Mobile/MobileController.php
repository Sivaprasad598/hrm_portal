<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Base for the employee mobile app API (routes/api.php, /api/mobile/v1).
 *
 * Every answer has one shape: { success, message, data }. Everything here
 * works on the signed-in employee only - nothing takes a user id from the
 * request - so one employee cannot read another's records.
 *
 * Kept off App\Http\Controllers\Controller on purpose: that one prepares the
 * web pages (session, theme, menus) on every request.
 */
abstract class MobileController extends Controller
{
    protected function ok($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function fail(string $message, int $status = 422, array $errors = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => (object) $errors], $status);
    }

    protected function me(): User
    {
        return auth()->user();
    }

    protected function tz(): string
    {
        return company()?->timezone ?: 'Asia/Kolkata';
    }

    /** A stored time as the employee's local time, ISO 8601 ("2026-10-27T09:31:00+05:30"). */
    protected function time($value): ?string
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone($this->tz())->toIso8601String() : null;
    }

    /** A file on the public disk as a full URL. */
    protected function publicUrl(?string $path): ?string
    {
        return $path ? asset_url_local_s3($path) : null;
    }
}
