<?php

namespace App\Services\Bank;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Checks an IFSC code against Razorpay's public IFSC directory
 * (https://ifsc.razorpay.com), which needs no key and returns the bank and
 * branch the code belongs to.
 *
 * Results are cached for 30 days (codes rarely change); a code the directory
 * does not know is cached for a day.
 */
class IfscLookup
{
    public const PATTERN = '/^[A-Z]{4}0[A-Z0-9]{6}$/';

    /**
     * @return array{status: string, message: string, bank?: string, branch?: string, address?: string,
     *               city?: string, state?: string}
     *         status: valid | invalid | format | unavailable
     */
    public function lookup(string $code): array
    {
        $code = strtoupper(trim($code));

        if (!preg_match(self::PATTERN, $code)) {
            return ['status' => 'format', 'message' => 'An IFSC is 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. SBIN0001234).'];
        }

        $cached = Cache::get('ifsc:' . $code);

        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get('https://ifsc.razorpay.com/' . $code);
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'message' => 'The IFSC directory could not be reached; the format is correct.'];
        }

        if ($response->status() === 404) {
            $result = ['status' => 'invalid', 'message' => $code . ' is not a known IFSC code. Check it against the cheque book or passbook.'];
            Cache::put('ifsc:' . $code, $result, now()->addDay());

            return $result;
        }

        if (!$response->successful() || !$response->json('BANK')) {
            return ['status' => 'unavailable', 'message' => 'The IFSC directory did not answer; the format is correct.'];
        }

        $result = [
            'status' => 'valid',
            'bank' => (string) $response->json('BANK'),
            'branch' => (string) $response->json('BRANCH'),
            'address' => (string) $response->json('ADDRESS'),
            'city' => (string) $response->json('CITY'),
            'state' => (string) $response->json('STATE'),
        ];
        $result['message'] = $result['bank'] . ', ' . $result['branch'] . ($result['city'] ? ' (' . $result['city'] . ')' : '');

        Cache::put('ifsc:' . $code, $result, now()->addDays(30));

        return $result;
    }

    /**
     * Checks on an account number that need no outside service: 9-18 digits,
     * not one digit repeated, not a run like 123456789.
     */
    public static function accountNumberProblem(?string $number): ?string
    {
        $number = (string) $number;

        if (!preg_match('/^[0-9]{9,18}$/', $number)) {
            return 'Bank account number must be 9 to 18 digits.';
        }

        if (preg_match('/^(\d)\1+$/', $number)) {
            return 'Bank account number cannot be one digit repeated.';
        }

        if (str_contains('0123456789012345678', $number) || str_contains('9876543210987654321', $number)) {
            return 'Bank account number looks like a placeholder (a run of digits).';
        }

        return null;
    }
}
