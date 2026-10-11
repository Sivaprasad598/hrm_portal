<?php

namespace App\Services\Iapp\Shifts;

/**
 * Infers the shift someone actually works from when they punch.
 *
 * Uses the median first-in and last-out, which a single late morning or a
 * forgotten exit swipe does not drag around, then matches that window to the
 * closest ERP shift. Arrival identifies the shift; departure only rules a
 * shift out when it is far off, because staying late or leaving early is
 * overtime or an early exit on the same shift. A pattern whose arrival fits no
 * ERP shift is reported as unlisted — on this site that surfaces the
 * ~06:30–14:30 and ~14:30–22:30 patterns the ERP has no shift for.
 *
 * Detection only: it suggests, and never assigns a shift or writes a roster.
 */
class ShiftDetector
{

    public const CONFIDENCE_HIGH = 'high';
    public const CONFIDENCE_MEDIUM = 'medium';
    public const CONFIDENCE_LOW = 'low';
    public const CONFIDENCE_NONE = 'none';

    /** How far the median arrival may sit from a shift's start and still match. */
    public const TOLERANCE_MINUTES = 60;

    /**
     * How far the median departure may sit from a shift's end. Deliberately
     * wide: matching departure as tightly as arrival marked people who arrive
     * at 09:30 and stay until 20:00 as working some other shift.
     */
    public const END_TOLERANCE_MINUTES = 180;

    public const MATCH_MATCHES = 'matches';
    public const MATCH_DIFFERS = 'differs';
    public const MATCH_UNLISTED = 'unlisted';
    public const MATCH_UNASSIGNED = 'unassigned';
    public const MATCH_INSUFFICIENT = 'insufficient';

    public const MATCH_LABELS = [
        self::MATCH_MATCHES => 'Matches assigned shift',
        self::MATCH_DIFFERS => 'Differs from assigned shift',
        self::MATCH_UNLISTED => 'No matching ERP shift',
        self::MATCH_UNASSIGNED => 'No shift assigned',
        self::MATCH_INSUFFICIENT => 'Not enough data',
    ];

    /**
     * @param  array<int, array{first_in: ?string, last_out: ?string}>  $days  H:i:s clock times
     * @param  iterable<object>  $shifts  employee_shifts rows
     */
    public static function detect(array $days, iterable $shifts): array
    {
        $starts = [];
        $ends = [];

        foreach ($days as $day) {
            if (!empty($day['first_in'])) {
                $starts[] = self::toMinutes($day['first_in']);

                if (!empty($day['last_out'])) {
                    $ends[] = self::toMinutes($day['last_out']);
                }
            }
        }

        $count = count($starts);

        if ($count === 0) {
            return [
                'days' => 0, 'start' => null, 'end' => null, 'spread_minutes' => null,
                'confidence' => self::CONFIDENCE_NONE, 'shift' => null, 'score' => null,
                'window' => null, 'label' => 'Not enough data',
            ];
        }

        $start = self::median($starts);
        $end = $ends ? self::median($ends) : null;

        // Median distance from the median start: how consistent arrival is.
        $spread = (int) round(self::median(array_map(fn ($m) => abs($m - $start), $starts)));

        $confidence = match (true) {
            $count >= 5 && $spread <= 45 => self::CONFIDENCE_HIGH,
            $count >= 2 && $spread <= 90 => self::CONFIDENCE_MEDIUM,
            default => self::CONFIDENCE_LOW,
        };

        $best = null;
        $bestScore = null;

        foreach ($shifts as $shift) {
            if (
                ShiftCalendar::isDayOff($shift)
                || ($shift->shift_type ?? 'strict') !== 'strict'
                || empty($shift->office_start_time)
                || empty($shift->office_end_time)
            ) {
                continue;
            }

            $startDelta = abs($start - self::toMinutes($shift->office_start_time));
            $endDelta = $end === null ? 0 : abs($end - self::toMinutes($shift->office_end_time));

            if ($startDelta > self::TOLERANCE_MINUTES || $endDelta > self::END_TOLERANCE_MINUTES) {
                continue;
            }

            // Arrival weighs double, so between two plausible shifts the one
            // whose start fits wins.
            $score = ($startDelta * 2) + $endDelta;

            if ($bestScore === null || $score < $bestScore) {
                $best = $shift;
                $bestScore = $score;
            }
        }

        $window = self::format(self::roundToHalfHour($start)) . '–'
            . ($end !== null ? self::format(self::roundToHalfHour($end)) : '?');

        return [
            'days' => $count,
            'start' => self::format((int) round($start)),
            'end' => $end !== null ? self::format((int) round($end)) : null,
            'spread_minutes' => $spread,
            'confidence' => $confidence,
            'shift' => $best,
            'score' => $bestScore !== null ? (int) round($bestScore) : null,
            'window' => $window,
            'label' => $best ? (string) $best->shift_name : 'Unlisted ~' . $window,
        ];
    }

    /**
     * How the detected pattern relates to the shift the ERP has assigned.
     */
    public static function compare(array $detection, ?object $assigned): string
    {
        if ($detection['confidence'] === self::CONFIDENCE_NONE) {
            return self::MATCH_INSUFFICIENT;
        }

        if (!$detection['shift']) {
            return self::MATCH_UNLISTED;
        }

        if (!$assigned || ShiftCalendar::isDayOff($assigned)) {
            return self::MATCH_UNASSIGNED;
        }

        return (int) $detection['shift']->id === (int) $assigned->id
            ? self::MATCH_MATCHES
            : self::MATCH_DIFFERS;
    }

    protected static function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, 0);

        return ((int) $h * 60) + (int) $m;
    }

    protected static function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    protected static function roundToHalfHour(float $minutes): int
    {
        return (int) (round($minutes / 30) * 30);
    }

    protected static function format(int $minutes): string
    {
        $minutes = (($minutes % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

}
