<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IappService
{

    /**
     * Endpoint fragments that would change something on the biometric server.
     * Kept identical to IappClient::WRITE_MARKERS so both doors are the same
     * size.
     */
    private const WRITE_MARKERS = [
        'create', 'edit', 'delete', 'update', 'remove', 'block',
        'relieve', 'transfer', 'registration', 'assign', 'save',
    ];

    protected string $baseUrl;

    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.iapp.base_url'), '/');
        $this->clientSecret = (string) config('services.iapp.client_secret');
    }

    /**
     * Authenticate with the client secret and return a JWT token, caching it
     * so we don't re-authenticate on every call. iApp doesn't document the
     * token lifetime, so we cache conservatively and refresh on a 401.
     */
    public function getToken(bool $forceRefresh = false): ?string
    {
        if ($forceRefresh) {
            Cache::forget('iapp_api_token');
        }

        return Cache::remember('iapp_api_token', now()->addMinutes(50), function () {
            return $this->authenticateClient();
        });
    }

    protected function authenticateClient(): ?string
    {
        $response = Http->withOptions(['curl' => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2]])->post("{$this->baseUrl}/api/authenticate/authenticateClient", [
            'ClientSecret' => $this->clientSecret,
        ]);

        $token = $response->json('token');

        if ($response->failed() || !$token) {
            Log::error('iApp authenticateClient failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $token;
    }

    /**
     * Call an iApp endpoint with the cached bearer token, retrying once with
     * a fresh token if the current one has expired (401).
     */
    public function request(string $method, string $endpoint, array $data = [])
    {
        // Read-only, enforced rather than assumed. Both call sites below pass
        // 'get', but this method takes the verb as an argument, so nothing
        // stopped a future caller from issuing a write against the biometric
        // server. The integration is only ever allowed to fetch.
        if (strtolower($method) !== 'get') {
            throw new \RuntimeException(
                "IappService is read-only; refused a {$method} request to '{$endpoint}'."
            );
        }

        foreach (self::WRITE_MARKERS as $marker) {
            if (str_contains(strtolower($endpoint), $marker)) {
                throw new \RuntimeException(
                    "IappService is read-only; refused endpoint '{$endpoint}'."
                );
            }
        }

        $token = $this->getToken();

        $response = Http->withOptions(['curl' => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2]])->withToken($token)->{$method}("{$this->baseUrl}/{$endpoint}", $data);

        if ($response->status() === 401) {
            $token = $this->getToken(true);
            $response = Http->withOptions(['curl' => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2]])->withToken($token)->{$method}("{$this->baseUrl}/{$endpoint}", $data);
        }

        return $response;
    }

    /**
     * Default set of getDetail fields — trimmed to what the sync actually
     * uses. Pass 'select' in $params to override.
     */
    public const ATTENDANCE_FIELDS = [
        'empId', 'employeeName', 'attendanceDate', 'firstIn', 'lastOut',
        'workHour', 'overTimeHours', 'lateHours', 'leaveCode', 'leaveQty',
        'weekOff', 'holiday', 'absent', 'present', 'shiftCode',
    ];

    /**
     * One page of the date-wise attendance detail report.
     * GET {domain}/api/attd/getDetail?filter=...&select=...&page=...&pageSize=...
     */
    public function getAttendance(array $params = [])
    {
        $params += [
            'select' => implode(',', self::ATTENDANCE_FIELDS),
            'page' => 1,
            'pageSize' => 1000,
        ];

        return $this->request('get', 'api/attd/getDetail', $params);
    }

    /**
     * All attendance records for a date range (inclusive), paginating
     * through iApp's getDetail report until every page is fetched.
     *
     * @param string $fromDate 'DD-MM-YYYY'
     * @param string $toDate 'DD-MM-YYYY'
     */
    public function getAllAttendance(string $fromDate, string $toDate, ?string $locationCode = null): array
    {
        $filter = "attendanceDate ge '{$fromDate}' and attendanceDate le '{$toDate}'";

        if ($locationCode) {
            $filter = "locationCode eq '{$locationCode}' and {$filter}";
        }

        $records = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = $this->getAttendance(['filter' => $filter, 'page' => $page]);
            $body = $response->json() ?? [];

            if ($response->failed() || !filter_var($body['isSuccess'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                Log::error('iApp getDetail failed', [
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                break;
            }

            $records = array_merge($records, $body['data'] ?? []);
            $totalPages = (int) ($body['totalPages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $records;
    }

    /**
     * One page of raw device transactions (individual punches, per
     * controller/gate — as opposed to getDetail's per-day summary).
     * GET {domain}/api/deviceTrans/get?filter=...&select=...&pageno=...
     *
     * filter and select are both required by this endpoint (no defaults
     * applied here, unlike getAttendance) — caller must supply both.
     */
    public function getDeviceTrans(array $params = [])
    {
        $params += ['pageno' => 1];

        return $this->request('get', 'api/deviceTrans/get', $params);
    }

    /**
     * Page through getDeviceTrans until a page comes back short of 1000
     * rows (the documented default page size). The docs don't show a
     * response shape with a total/page count like getDetail's, so this
     * assumes a plain { data: [...] } body — confirm against a real
     * response and adjust if it turns out to carry pagination metadata.
     */
    public function getAllDeviceTrans(string $filter, array $select): array
    {
        $records = [];
        $page = 1;

        do {
            $response = $this->getDeviceTrans([
                'filter' => $filter,
                'select' => implode(',', $select),
                'pageno' => $page,
            ]);
            $body = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('iApp deviceTrans/get failed', [
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                break;
            }

            $batch = $body['data'] ?? $body;
            $records = array_merge($records, $batch);
            $page++;
        } while (count($batch) >= 1000);

        return $records;
    }

}
