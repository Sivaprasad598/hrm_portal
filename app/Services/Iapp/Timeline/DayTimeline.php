<?php

namespace App\Services\Iapp\Timeline;

use App\Models\IappDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One employee's day, reconstructed from raw punches.
 *
 * The single place punches become arrival, departure and sessions, shared by
 * the derived attendance records (PunchAggregator) and Employee 360
 * (EmployeeAttendanceProfile) so the two can never disagree.
 *
 * Three behaviours of this site's devices shape it:
 *
 *  - Gate duplicates. A controller reports one swipe once per gate, a few
 *    seconds apart. Repeats of the same kind, direction and controller within
 *    COLLAPSE_SECONDS are one event.
 *  - Direction-less readers. The Face Attendance units are In-mode: every scan
 *    arrives as IN, including the scan people make on the way out — 191 of
 *    670 scans land within five minutes after a door exit. Their direction is
 *    ignored. A scan marks presence: it can be the day's arrival or departure,
 *    and it closes a session a door left open, but it never opens one.
 *  - Loose swipe discipline. People re-enter behind someone else without
 *    swiping in, so paired door time undercounts; presence (arrival to
 *    departure) is reported alongside it.
 *
 * All differences are taken from absolute timestamps. Carbon 3 returns signed
 * differences, and comparing later-minus-earlier against a positive window
 * once collapsed every later same-direction punch of the day — which is how a
 * 16:30 and an 18:31 exit disappeared behind a 12:30 one.
 */
class DayTimeline
{

    public const COLLAPSE_SECONDS = 90;

    /**
     * A scan this soon after arriving is part of arriving, not a departure:
     * people scan their face moments after walking through the door.
     */
    public const ARRIVAL_WINDOW_MINUTES = 30;

    public const KIND_DOOR = 'door';
    public const KIND_SCAN = 'scan';

    /** controller_code => true, for readers whose direction is meaningless */
    protected array $directionless;

    /**
     * @param  array<int, string>  $directionlessControllers
     */
    public function __construct(array $directionlessControllers = [])
    {
        $this->directionless = array_fill_keys(array_map('strval', $directionlessControllers), true);
    }

    /**
     * Controllers configured In-only or Out-only, whose punches carry no real
     * direction.
     *
     * @return array<int, string>
     */
    public static function directionlessControllers(?int $companyId = null): array
    {
        return DB::table('iapp_devices')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('device_mode', [IappDevice::MODE_IN, IappDevice::MODE_OUT])
            ->distinct()
            ->pluck('controller_code')
            ->map(fn ($code) => (string) $code)
            ->all();
    }

    /**
     * @param  iterable<object>  $punches  rows with punch_time, punch_direction, controller_code
     */
    public function build(string $date, iterable $punches): array
    {
        // Everything recorded, with gate repeats marked; then the subset the
        // day's figures are built from.
        $recorded = $this->collapse($this->normalise($punches));
        $events = self::distinct($recorded);

        if ($events === []) {
            return $this->emptyDay($date);
        }

        $sessions = [];
        $inside = 0;
        $outside = 0;
        $openIn = null;
        $pendingOut = null;
        $checkIns = 0;
        $checkOuts = 0;
        $scans = 0;

        foreach ($events as $event) {
            if ($event['kind'] === self::KIND_SCAN) {
                $scans++;

                continue;
            }

            if ($event['direction'] === 'in') {
                $checkIns++;

                // An exit followed by this entry is time spent away.
                if ($pendingOut) {
                    $outside += self::minutesBetween($pendingOut, $event['time']);
                    $pendingOut = null;
                }

                // Repeated entries without an exit keep the earliest: that is
                // the real arrival, the later one a re-entry after an
                // unrecorded exit.
                $openIn ??= $event['time'];

                continue;
            }

            if ($event['direction'] === 'out') {
                $checkOuts++;

                if ($openIn) {
                    $minutes = self::minutesBetween($openIn, $event['time']);
                    $inside += $minutes;
                    $sessions[] = self::session($openIn, $event['time'], $minutes, self::KIND_DOOR);
                    $openIn = null;
                }

                $pendingOut = $event['time'];
            }
        }

        $arrival = $this->arrival($events);
        $closedAt = null;

        // A door session still open at the end of the day is closed by a
        // trailing face scan — the scan made on the way out.
        if ($openIn) {
            $last = $events[count($events) - 1];

            if ($last['kind'] === self::KIND_SCAN && self::minutesBetween($openIn, $last['time']) > self::ARRIVAL_WINDOW_MINUTES) {
                $minutes = self::minutesBetween($openIn, $last['time']);
                $inside += $minutes;
                $sessions[] = self::session($openIn, $last['time'], $minutes, self::KIND_SCAN);
                $closedAt = $last['time'];
                $openIn = null;
            }
        }

        $endedInside = $openIn !== null;

        if ($endedInside) {
            $sessions[] = self::session($openIn, null, 0, null);
        }

        $departure = $endedInside ? null : $this->departure($events, $arrival, $closedAt);

        // Scan-only days have no door sessions; presence is the span from the
        // first scan to the last.
        if ($checkIns === 0 && $checkOuts === 0 && $arrival && $departure) {
            $inside = self::minutesBetween($arrival, $departure);
            $sessions = [self::session($arrival, $departure, $inside, self::KIND_SCAN)];
        }

        return [
            'date' => $date,
            'first_in' => $arrival?->format('H:i:s'),
            'last_out' => $departure?->format('H:i:s'),
            'first_in_at' => $arrival,
            'last_out_at' => $departure,
            'ended_inside' => $endedInside,
            'check_ins' => $checkIns,
            'check_outs' => $checkOuts,
            'scans' => $scans,
            'inside_minutes' => $inside,
            'outside_minutes' => $outside,
            'presence_minutes' => ($arrival && $departure) ? self::minutesBetween($arrival, $departure) : null,
            'sessions' => $sessions,
            'controllers' => array_values(array_unique(array_filter(
                array_column($events, 'controller'),
                fn ($code) => $code !== null
            ))),
            'controller_visits' => $this->visits($events),
            'events' => array_map(fn ($event) => [
                'time' => $event['time']->format('H:i:s'),
                'at' => $event['time'],
                'direction' => $event['direction'],
                'controller' => $event['controller'],
                'kind' => $event['kind'],
            ], $events),
            'total_events' => count($events),

            // Every punch the device reported, repeats included and flagged.
            // The day's figures come from `events`; this is what the punch log
            // shows, so the two screens can no longer disagree.
            'all_events' => array_map(fn ($event) => [
                'time' => $event['time']->format('H:i:s'),
                'at' => $event['time'],
                'direction' => $event['direction'],
                'controller' => $event['controller'],
                'kind' => $event['kind'],
                'repeat' => (bool) ($event['repeat'] ?? false),
                'repeat_of' => $event['repeat_of'] ?? null,
            ], $recorded),
            'recorded_events' => count($recorded),
            'repeat_events' => count($recorded) - count($events),
        ];
    }

    /**
     * Sort punches and mark which carry a real direction.
     */
    protected function normalise(iterable $punches): array
    {
        $rows = [];

        foreach ($punches as $punch) {
            $time = $punch->punch_time instanceof Carbon
                ? $punch->punch_time->copy()
                : Carbon::parse($punch->punch_time);

            $controller = $punch->controller_code !== null ? (string) $punch->controller_code : null;
            $isScan = $controller !== null && isset($this->directionless[$controller]);

            $rows[] = [
                'time' => $time,
                'direction' => $isScan ? null : $punch->punch_direction,
                'controller' => $controller,
                'kind' => $isScan ? self::KIND_SCAN : self::KIND_DOOR,
            ];
        }

        // Punches can share a timestamp to the second, and MySQL breaks ties
        // differently between queries. Without a deterministic tiebreak the
        // aggregator and Employee 360 read the same day two different ways.
        usort($rows, fn ($a, $b) => [$a['time']->getTimestamp(), $a['kind'], (string) $a['controller']]
            <=> [$b['time']->getTimestamp(), $b['kind'], (string) $b['controller']]);

        return $rows;
    }

    /**
     * Collapse gate repeats into distinct events.
     *
     * A repeat is judged against the previous event of the *same kind*, not
     * simply the previous event: a face scan often lands in the same second as
     * a door swipe, and letting it sit between two gate reports of one swipe
     * would preserve both as separate entries.
     */
    protected function collapse(array $rows): array
    {
        $events = [];
        $lastOfKind = [];

        foreach ($rows as $row) {
            $last = $lastOfKind[$row['kind']] ?? null;

            $isRepeat = $last
                && $last['direction'] === $row['direction']
                && $last['controller'] === $row['controller']
                && abs($row['time']->getTimestamp() - $last['time']->getTimestamp()) <= self::COLLAPSE_SECONDS;

            // Repeats are marked, not dropped. They used to be discarded here,
            // which left the punch log showing punches that the day view had no
            // trace of — six of them on one tested day. The figures below still
            // ignore them; the screens can now show them and say why they were
            // treated as one.
            $row['repeat'] = (bool) $isRepeat;
            $row['repeat_of'] = $isRepeat ? $last['time']->format('H:i:s') : null;

            $events[] = $row;

            // Anchored on the last event kept, not the last one seen, so a run
            // of punches spaced just under the window cannot collapse forever.
            if (!$isRepeat) {
                $lastOfKind[$row['kind']] = $row;
            }
        }

        return $events;
    }

    /**
     * The events that count, out of everything recorded.
     *
     * @param  array<int, array>  $rows  the output of collapse()
     */
    protected static function distinct(array $rows): array
    {
        return array_values(array_filter($rows, fn ($row) => !($row['repeat'] ?? false)));
    }

    /**
     * The earliest entry or scan. A day that records only exits started with
     * the person already inside, so its first punch is the earliest evidence.
     */
    protected function arrival(array $events): ?Carbon
    {
        foreach ($events as $event) {
            if ($event['kind'] === self::KIND_SCAN || $event['direction'] === 'in') {
                return $event['time'];
            }
        }

        return $events[0]['time'] ?? null;
    }

    /**
     * When the person left, for a day that did not end inside.
     */
    protected function departure(array $events, ?Carbon $arrival, ?Carbon $closedAt): ?Carbon
    {
        if ($closedAt) {
            return $closedAt;
        }

        $lastDoor = null;
        $lastScan = null;

        foreach ($events as $event) {
            if ($event['kind'] === self::KIND_DOOR) {
                $lastDoor = $event;
            } else {
                $lastScan = $event;
            }
        }

        $departure = ($lastDoor && $lastDoor['direction'] === 'out') ? $lastDoor['time'] : null;

        // A later exit scan beats the door exit; a scan made while arriving
        // does not count as leaving.
        if (
            $lastScan
            && $arrival
            && self::minutesBetween($arrival, $lastScan['time']) > self::ARRIVAL_WINDOW_MINUTES
            && (!$departure || $lastScan['time']->getTimestamp() > $departure->getTimestamp())
        ) {
            $departure = $lastScan['time'];
        }

        if ($departure && $arrival && $departure->getTimestamp() <= $arrival->getTimestamp()) {
            return null;
        }

        return $departure;
    }

    /**
     * Arrivals per controller. Door visits count a change of door controller;
     * each scan counts once, so a face scan doesn't split one door visit in two.
     */
    protected function visits(array $events): array
    {
        $visits = [];
        $previousDoor = null;

        foreach ($events as $event) {
            $controller = $event['controller'];

            if ($controller === null) {
                continue;
            }

            if ($event['kind'] === self::KIND_SCAN) {
                $visits[$controller] = ($visits[$controller] ?? 0) + 1;

                continue;
            }

            if ($controller !== $previousDoor) {
                $visits[$controller] = ($visits[$controller] ?? 0) + 1;
            }

            $previousDoor = $controller;
        }

        return $visits;
    }

    protected function emptyDay(string $date): array
    {
        return [
            'date' => $date, 'first_in' => null, 'last_out' => null, 'first_in_at' => null,
            'last_out_at' => null, 'ended_inside' => false, 'check_ins' => 0, 'check_outs' => 0,
            'scans' => 0, 'inside_minutes' => 0, 'outside_minutes' => 0, 'presence_minutes' => null,
            'sessions' => [], 'controllers' => [], 'controller_visits' => [], 'events' => [],
            'total_events' => 0, 'all_events' => [], 'recorded_events' => 0, 'repeat_events' => 0,
        ];
    }

    protected static function session(Carbon $in, ?Carbon $out, int $minutes, ?string $closedBy): array
    {
        return [
            'in' => $in->format('H:i:s'),
            'out' => $out?->format('H:i:s'),
            'minutes' => $minutes,
            'closed_by' => $closedBy,
        ];
    }

    public static function minutesBetween(Carbon $a, Carbon $b): int
    {
        return intdiv(abs($b->getTimestamp() - $a->getTimestamp()), 60);
    }

}
