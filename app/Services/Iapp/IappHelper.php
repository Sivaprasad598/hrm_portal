<?php

namespace App\Services\Iapp;

use Carbon\Carbon;

/**
 * Small shared conversions for iApp payloads.
 *
 * Kept separate from the endpoint classes so date and duration handling is
 * defined once and behaves identically wherever a payload is parsed.
 */
class IappHelper
{

    /**
     * Date format the server actually parses in filter expressions.
     *
     * Verified against the live server: 'DD-MM-YYYY' is read as MM-DD-YYYY, so
     * "01-09-2026" returns January 9th, not 1 September. ISO is unambiguous
     * and accepted, so every filter this integration builds uses it.
     */
    public const FILTER_DATE = 'Y-m-d';

    public const FILTER_DATETIME = 'Y-m-d\TH:i:s';

    /**
     * Parse a timestamp from iApp.
     *
     * PunchDetail and LastActivityOn come back as ISO 8601 —
     * "2026-09-10T06:40:21", sometimes with fractional seconds. The documented
     * "DD-MM-YYYY HH:MM" shape is accepted too, for endpoints that use it.
     */
    public static function parseDateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d\TH:i:s.v', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'd-m-Y H:i:s', 'd-m-Y H:i'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                // Carbon throws when the value is shorter than the format
                // (an ISO timestamp with no fractional seconds, say) rather
                // than returning false. Just try the next format.
                continue;
            }

            // createFromFormat is lenient; only accept an exact round-trip.
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * "09:30" (9.5 hours) to minutes. Durations are HH:MM, not H.M decimals,
     * so they must be converted before any arithmetic.
     */
    public static function durationToMinutes(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (!preg_match('/^(\d{1,3}):([0-5]\d)$/', trim($value), $m)) {
            return null;
        }

        return ((int) $m[1] * 60) + (int) $m[2];
    }

    /**
     * Punch direction from the InOut column.
     *
     * Confirmed against a full day of live data: every one of 134 employees
     * had InOut=0 on their first punch of the day and none had InOut=1, so
     * 0 is an entry and 1 an exit.
     */
    public static function punchDirection($value): ?string
    {
        if (is_array($value) || $value === null) {
            return null;
        }

        return match (trim((string) $value)) {
            '0' => 'in',
            '1' => 'out',
            default => null,
        };
    }

    /**
     * Escapes a value for an OData-style filter expression.
     *
     * Values are wrapped in single quotes, so an embedded quote would end the
     * literal early and produce error 03. Stripping is safe here: no legitimate
     * company, location or employee code contains one.
     */
    public static function escapeFilterValue(string $value): string
    {
        return str_replace("'", '', $value);
    }

}
