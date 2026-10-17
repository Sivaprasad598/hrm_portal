<?php

namespace App\Services\Iapp;

use Carbon\Carbon;

/**
 * deviceTrans/get — the raw punch feed.
 *
 * This is the authoritative device data for this deployment: attd/getDetail
 * returns 404 here, so day-wise summaries are not available and attendance is
 * reconstructed from punches instead.
 *
 * Two server behaviours shape this class:
 *
 *  - `pageno` is inert. Pages 1, 2 and 3 return byte-identical rows and every
 *    response is capped at 100. Paging is therefore done with a timestamp
 *    cursor: ask for punches after the last one already seen.
 *  - The only comparison operators accepted are gt/gteq/lt/lteq. `ge` and `le`
 *    return "Invalid Parameter Operator!", the opposite of what the published
 *    examples show.
 */
class DeviceTransEndpoint
{

    public const PATH = 'api/deviceTrans/get';

    /** Rows the server returns per call, regardless of any page-size hint. */
    public const PAGE_SIZE = 100;

    /** Seconds covered by the first window tried; adapts to punch density. */
    public const INITIAL_WINDOW_SECONDS = 600;

    public const MAX_WINDOW_SECONDS = 21600;

    public const MIN_WINDOW_SECONDS = 1;

    /**
     * Columns confirmed valid in `select` on this server.
     *
     * Names are case-sensitive and inconsistent: 'Name' returns the employee's
     * name, while 'name', 'empName', 'employeeName' and 'EmployeeName' are all
     * rejected with "Detail Column not found". Each entry here was verified by
     * reading values back rather than by trusting the response message — a
     * single-column select can report success while returning nothing useful.
     */
    public const FIELDS = [
        'empcode',
        'Name',
        'controllerCode',
        'controllerName',
        'cardid',
        'PunchDetail',
        'companyCode',
        'companyName',
        'locationCode',
        'locationName',
        'inOut',
    ];

    public function __construct(
        protected IappClient $client
    ) {
    }

    /**
     * One call: punches strictly after $after.
     */
    public function after(Carbon $after, array $fields = self::FIELDS): IappResponse
    {
        $cursor = $after->format(IappHelper::FILTER_DATETIME);

        return $this->client->get(self::PATH, [
            'select' => implode(',', $fields),
            'filter' => "PunchDetail gt '" . IappHelper::escapeFilterValue($cursor) . "'",
            'pageno' => 1,
        ]);
    }

    /**
     * One call: punches in (after, until], optionally for one employee code.
     *
     * The employee filter field is `empcode` (also accepted as `empCode` and
     * `EmpCode`); `EmployeeCode` is rejected with "Filter Parameter Not Found!".
     * Verified against the live server, read-only.
     */
    public function between(Carbon $after, Carbon $until, array $fields = self::FIELDS, ?string $empCode = null): IappResponse
    {
        $filter = sprintf(
            "PunchDetail gt '%s' and PunchDetail lteq '%s'",
            IappHelper::escapeFilterValue($after->format(IappHelper::FILTER_DATETIME)),
            IappHelper::escapeFilterValue($until->format(IappHelper::FILTER_DATETIME))
        );

        if ($empCode !== null && $empCode !== '') {
            $filter .= sprintf(" and empcode eq '%s'", IappHelper::escapeFilterValue($empCode));
        }

        return $this->client->get(self::PATH, [
            'select' => implode(',', $fields),
            'filter' => $filter,
            'pageno' => 1,
        ]);
    }

    /**
     * Walk the feed from $from to $until, yielding each batch.
     *
     * Generator rather than one big array so a long window stays flat in
     * memory and the caller can persist as it goes.
     *
     * **Bounded windows, not a cursor.** The obvious approach — ask for
     * everything after the newest punch seen so far — silently loses data here,
     * because the feed is ordered by controller upload batch rather than by
     * punch time. A single page was observed running 09:23:42, 09:23:04,
     * 09:23:09: once the cursor jumped to a page's newest row, every older
     * punch that happened to arrive in a later batch was skipped for good. A
     * day walked that way missed 148 punches that bounded queries prove exist.
     *
     * So each call asks for a closed time window instead. A window that comes
     * back full is hiding rows behind the 100-row cap, so it is halved and
     * retried; a quiet one is widened, which keeps nights and weekends cheap.
     * Completeness then depends only on the window shrinking far enough, and
     * the only unreachable case is a single second holding more than PAGE_SIZE
     * punches.
     *
     * @return \Generator<int, array<int, array>>
     */
    public function stream(Carbon $from, ?Carbon $until = null, int $maxCalls = 20000, ?string $empCode = null): \Generator
    {
        $until = ($until ?? Carbon::now())->copy();
        // `gt` is exclusive, so step back to keep a punch exactly at $from.
        $cursor = $from->copy()->subSecond();
        $window = self::INITIAL_WINDOW_SECONDS;
        $calls = 0;

        while ($cursor->lt($until) && $calls < $maxCalls) {
            $end = $cursor->copy()->addSeconds($window);

            if ($end->gt($until)) {
                $end = $until->copy();
            }

            $calls++;
            $response = $this->between($cursor, $end, self::FIELDS, $empCode);

            if (!$response->successful()) {
                // "not found" means this window is simply empty.
                if (!$response->isEmptyResult()) {
                    throw new \RuntimeException(
                        'iApp deviceTrans/get failed: ' . ($response->message() ?: 'unknown error')
                    );
                }

                $cursor = $end;

                continue;
            }

            $rows = $response->rows();

            // A full window is truncated, so it cannot be trusted: halve it and
            // ask again from the same point. One second is the floor.
            if (count($rows) >= self::PAGE_SIZE && $window > self::MIN_WINDOW_SECONDS) {
                $window = max(self::MIN_WINDOW_SECONDS, intdiv($window, 2));

                continue;
            }

            if ($rows !== []) {
                yield $rows;
            }

            $cursor = $end;

            // Well under the cap: widen, so quiet stretches cost few calls.
            if (count($rows) < self::PAGE_SIZE / 4) {
                $window = min(self::MAX_WINDOW_SECONDS, $window * 2);
            }
        }
    }

    /**
     * Normalise a punch row into iapp_device_transactions column names.
     */
    public static function normalise(array $row): ?array
    {
        $punchTime = IappHelper::parseDateTime(
            IappResponse::scalar($row, 'PunchDetail', 'punchDetail')
        );

        if (!$punchTime) {
            return null;
        }

        $direction = IappHelper::punchDirection($row['InOut'] ?? $row['inOut'] ?? null);

        $normalised = [
            'emp_code' => IappResponse::scalar($row, 'empCode', 'empcode'),
            // Returned under the lower-case key even though the select must
            // ask for 'Name'.
            'employee_name' => IappResponse::scalar($row, 'name', 'Name'),
            'location_code' => IappResponse::scalar($row, 'locationCode'),
            'location_name' => IappResponse::scalar($row, 'locationName'),
            'controller_code' => IappResponse::scalar($row, 'controllerCode', 'ControllerCode'),
            'card_id' => IappResponse::scalar($row, 'CardID', 'cardid', 'cardId'),
            'punch_time' => $punchTime,
            'punch_direction' => $direction,
            'direction_source' => $direction ? 'device_inout' : null,
            'raw_payload' => $row,
        ];

        $normalised['punch_hash'] = self::dedupeKey($normalised);

        return $normalised;
    }

    /**
     * Natural key for de-duplication.
     *
     * One controller reports each gate separately, so a single swipe arrives
     * three or four times with an identical timestamp — roughly 4,000 raw rows
     * for 1,134 real punches in a day here. Gate number is not exposed by this
     * endpoint, so those repeats are indistinguishable and collapse to one.
     *
     * Hashed, and with empty strings for missing parts, because this is stored
     * in a NOT NULL unique column: a nullable key column would make MySQL treat
     * every NULL as distinct and defeat the uniqueness entirely.
     */
    public static function dedupeKey(array $normalised): string
    {
        return md5(implode('|', [
            $normalised['emp_code'] ?? '',
            $normalised['controller_code'] ?? '',
            $normalised['punch_time']->format('Y-m-d H:i:s'),
        ]));
    }

}
