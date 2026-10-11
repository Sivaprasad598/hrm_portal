<?php

namespace App\Services\Iapp;

use Illuminate\Http\Client\Response;

/**
 * Normalises an iApp response envelope.
 *
 * Wraps three behaviours confirmed against the live server that a plain
 * json() call gets wrong:
 *
 *  - isSuccess is the string "true"/"False", not a boolean, and its casing
 *    varies between endpoints.
 *  - `data` is sometimes a JSON-encoded *string* that needs a second decode
 *    (getdevices, deviceTrans) and sometimes a real array.
 *  - Empty values arrive as an empty JSON array [] rather than null or "".
 */
class IappResponse
{

    protected array $body;

    public function __construct(
        protected ?Response $response,
        protected ?string $transportError = null
    ) {
        $this->body = $this->response?->json() ?? [];

        if (!is_array($this->body)) {
            $this->body = [];
        }
    }

    public static function fromResponse(Response $response): self
    {
        return new self($response);
    }

    public static function fromError(string $message): self
    {
        return new self(null, $message);
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }

    public function transportError(): ?string
    {
        return $this->transportError;
    }

    public function successful(): bool
    {
        if ($this->transportError || !$this->response) {
            return false;
        }

        return strtolower(trim((string) ($this->body['isSuccess'] ?? ''))) === 'true';
    }

    public function message(): string
    {
        return (string) ($this->body['message'] ?? $this->transportError ?? '');
    }

    /**
     * Leading numeric code on attendance-style errors, e.g. "02-No [Select]…".
     */
    public function errorCode(): ?string
    {
        return preg_match('/^(\d{2,3})\s*-/', $this->message(), $m) ? $m[1] : null;
    }

    /**
     * "No data found" is a valid empty result, not a failure.
     */
    public function isEmptyResult(): bool
    {
        if ($this->errorCode() === '101') {
            return true;
        }

        return (bool) preg_match('/not found|no data/i', $this->message());
    }

    /**
     * Rows from `data`, decoding the JSON-in-a-string case.
     */
    public function rows(): array
    {
        $data = $this->body['data'] ?? null;

        if (is_string($data)) {
            if (trim($data) === '') {
                return [];
            }

            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($data)) {
            return [];
        }

        // A single object rather than a list of them.
        return array_is_list($data) ? $data : [$data];
    }

    public function raw(): array
    {
        return $this->body;
    }

    /**
     * The token, under whichever key this server uses.
     *
     * The live server returns "AccessToken"; the published docs say "token".
     */
    public function token(): ?string
    {
        foreach (['AccessToken', 'accessToken', 'token', 'Token'] as $key) {
            if (!empty($this->body[$key]) && is_string($this->body[$key])) {
                return $this->body[$key];
            }
        }

        return null;
    }

    /**
     * Scalar field from a row, collapsing iApp's empty-array-for-null to null.
     */
    public static function scalar(array $row, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if (is_array($value) || $value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

}
