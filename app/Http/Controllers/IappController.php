<?php

namespace App\Http\Controllers;

use App\Services\IappAttendanceSyncService;
use App\Services\IappService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IappController extends Controller
{

    protected IappService $iapp;

    public function __construct(IappService $iapp)
    {
        $this->iapp = $iapp;
    }

    /**
     * Connectivity check: authenticate with iApp and fetch today's
     * attendance. Hit /iapp/test in the browser to verify the integration
     * end to end.
     */
    public function test(): JsonResponse
    {
        $today = now()->format('d-m-Y');

        $response = $this->iapp->getAttendance([
            'filter' => "attendanceDate ge '{$today}' and attendanceDate le '{$today}'",
        ]);

        return response()->json([
            'status' => $response->status(),
            'data' => $response->json(),
        ], $response->status());
    }

    /**
     * Pull iApp attendance for a date range and save it into the local
     * `attendances` table. Hit /iapp/attendance/sync?from=DD-MM-YYYY&to=DD-MM-YYYY
     * (both default to today; location is optional).
     */
    public function syncAttendance(Request $request, IappAttendanceSyncService $syncService): JsonResponse
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->format('d-m-Y') : now()->format('d-m-Y');
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->format('d-m-Y') : now()->format('d-m-Y');

        $locationCode = $request->query('location_code') ?: config('services.iapp.location_code') ?: null;

        $summary = $syncService->sync($from, $to, $locationCode);

        return response()->json($summary);
    }

}
