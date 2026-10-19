<?php

namespace App\Services\Iapp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP transport for the iApp API.
 *
 * Owns authentication, token caching and the 401 retry. Endpoint classes
 * build their own parameters and hand them here; nothing else in the app
 * talks to iApp directly.
 *
 * Every method on this class is a read. No endpoint that creates, edits or
 * deletes anything on the device is reachable through it — see GUARD below.
 */
class IappClient
{

    /**
     * Path fragments that indicate a write. A path containing any of these is
     * refused before a request is made, so a future endpoint class can never
     * accidentally mutate device state through this client.
     */
    private const WRITE_MARKERS = [
        'create', 'edit', 'delete', 'update', 'remove', 'block',
        'relieve', 'transfer', 'registration', 'assign', 'save',
    ];

    public function __construct(
        protected IappConfig $config
    ) {
    }

    public function config(): IappConfig
    {
        return $this->config;
    }

    /**
     * Obtain a bearer token, cached just under the server's token lifetime.
     */
    public function token(bool $forceRefresh = false): ?string
    {
        $key = $this->config->tokenCacheKey();

        if ($forceRefresh) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);

        if ($cached) {
            return $cached;
        }

        $token = $this->authenticate();

        if ($token) {
            Cache::put($key, $token, now()->addMinutes($this->config->tokenCacheMinutes()));
        }

        return $token;
    }

    /**
     * The one POST this client makes. It issues a token and writes nothing.
     */
    protected function authenticate(): ?string
    {
        if (!$this->config->isConfigured()) {
            return null;
        }

        if ($this->config->usesClientSecret()) {
            $path = 'api/authenticate/authenticateClient';
            $payload = ['ClientSecret' => $this->config->clientSecret()];
        } else {
            $path = 'api/authenticate/AuthenticateUser';
            $payload = [
                'username' => $this->config->username(),
                'password' => $this->config->password(),
            ];
        }

        $this->authProblem = null;

        try {
            $response = $this->http(30)->post($this->config->baseUrl() . '/' . $path, $payload);
        } catch (\Throwable $e) {
            Log::error('iApp authentication transport error', ['message' => $e->getMessage()]);
            // The server never answered: a network problem, not the login details.
            $this->authProblem = 'Could not reach the iApp server.';

            return null;
        }

        $token = IappResponse::fromResponse($response)->token();

        if (!$token) {
            $this->authProblem = 'The iApp server refused the login (HTTP ' . $response->status() . ') - check the username and password on Biometric Settings.';
            Log::error('iApp authentication failed', [
                'status' => $response->status(),
                'method' => $this->config->authMethod(),
            ]);
        }

        return $token;
    }

    /**
     * GET an iApp endpoint.
     *
     * @param  array  $query   URL query parameters
     * @param  array|null  $body  JSON body — deviceMaster/getdevices requires
     *                            one on a GET, which is unusual but is what the
     *                            server accepts (query params return HTTP 400).
     */
    /** Why the last login attempt produced no token: unreachable, or refused. */
    protected ?string $authProblem = null;

    public function get(string $path, array $query = [], ?array $body = null): IappResponse
    {
        $this->guard($path);

        $token = $this->token();

        if (!$token) {
            return IappResponse::fromError($this->authProblem ?? 'Could not authenticate with iApp.');
        }

        $response = $this->send($path, $query, $body, $token);

        // An expired token is the usual cause of a 401; refresh once and retry.
        if ($response && $response->status() === 401) {
            $token = $this->token(true);

            if (!$token) {
                return IappResponse::fromError($this->authProblem ?? 'Could not re-authenticate with iApp.');
            }

            $response = $this->send($path, $query, $body, $token);
        }

        if (!$response) {
            return IappResponse::fromError('Could not reach the iApp server.');
        }

        return IappResponse::fromResponse($response);
    }

    protected function send(string $path, array $query, ?array $body, string $token)
    {
        $url = $this->config->baseUrl() . '/' . ltrim($path, '/');

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        try {
            $request = $this->http(90)->withToken($token);

            // Laravel's get() cannot carry a body, so a GET-with-body goes
            // through send() explicitly.
            return $body !== null
                ? $request->withBody(json_encode($body), 'application/json')->send('GET', $url)
                : $request->get($url);
        } catch (\Throwable $e) {
            Log::error('iApp request failed', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * A configured HTTP client.
     *
     * connectTimeout is raised well above Laravel's 10s default: this server
     * is reached over a link whose TLS handshake has been measured anywhere
     * between 0.2s and 7s, and a 10s ceiling turns an ordinary slow connect
     * into a hard failure. retry() covers the occasional dropped connection
     * on the same link.
     */
    protected function http(int $timeout)
    {
        return Http::timeout($timeout)
            ->connectTimeout(45)
            ->retry(3, 1000, function ($exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->withOptions([
                'verify' => $this->config->verifySsl(),
                // The iApp server accepts TLS 1.2 and nothing else. Left to
                // negotiate, cURL offers a newer version first and the server
                // answers by resetting the connection, which surfaces as
                // "Recv failure: Connection was reset" and reads like the
                // device being unreachable. Pinned, the same request returns
                // 200. Measured against 106.51.5.128: TLS1.2 -> 200,
                // default -> reset.
                'curl' => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2],
            ])
            ->acceptJson();
    }

    /**
     * Refuses any path that looks like a write.
     */
    protected function guard(string $path): void
    {
        $needle = strtolower($path);

        foreach (self::WRITE_MARKERS as $marker) {
            if (str_contains($needle, $marker)) {
                throw new \RuntimeException(
                    "IappClient is read-only; refused path '{$path}'."
                );
            }
        }
    }

}
