<?php

namespace App\Http\Controllers;

use App\Services\Attendance\ClockInCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Clock-in / clock-out pictures, served by the application through a signed,
 * expiring link (ClockInCapture::url) instead of /storage/...: that needs the
 * public/storage link on the server (not in git, often not allowed on shared
 * hosting) and left the faces open to anyone with the address.
 */
class AttendancePhotoController extends Controller
{
    public function show(Request $request)
    {
        $path = (string) $request->query('path', '');

        // only the attendance pictures folder, no climbing out of it
        abort_if($path === '' || str_contains($path, '..') || !str_starts_with($path, ClockInCapture::FOLDER . '/'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'private, max-age=43200',
        ]);
    }
}
