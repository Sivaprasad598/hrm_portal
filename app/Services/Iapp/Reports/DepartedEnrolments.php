<?php

namespace App\Services\Iapp\Reports;

use App\Services\Employment\Separation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * People who have left but still hold an enrolment on the devices.
 *
 * Somebody who has been terminated, or whose notice has finished, keeps their
 * finger or face on the readers until an administrator removes it there. The
 * module cannot do that — every path to the devices is read-only, by
 * instruction — so this lists them for whoever can, and says what still needs
 * doing. Nothing here writes to a device.
 *
 * The case worth catching is the one at the bottom: punches recorded *after*
 * somebody's last working day. That is not a stale record, it is somebody
 * getting in.
 */
class DepartedEnrolments
{

    public function __construct(protected Separation $separation)
    {
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, counts: array<string, int>}
     */
    public function build(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?: Carbon::today();
        $rows = [];

        foreach ($this->separation->everyoneWhoHasLeft($asOf) as $person) {
            $enrolment = $this->enrolmentFor($person);

            // Somebody who never had a device code has nothing to remove.
            if ($enrolment === null) {
                continue;
            }

            $punchedAfter = $this->punchesAfter($enrolment->emp_code, $person['date']);

            $rows[] = [
                'user_id' => $person['id'],
                'name' => $person['name'],
                'email' => $person['email'],
                'employee_card_id' => $person['employee_card_id'],
                'emp_code' => $enrolment->emp_code,
                'card_id' => $enrolment->card_id,
                'reason' => $person['reason'],
                'reason_label' => $person['label'],
                'last_working_date' => $person['date'],
                'days_since' => Carbon::parse($person['date'])->diffInDays($asOf),
                'last_seen_at' => $enrolment->last_seen_at,
                'punch_count' => (int) $enrolment->punch_count,
                'device_active' => (bool) $enrolment->is_active,
                'punches_after_leaving' => $punchedAfter['count'],
                'last_punch_after_leaving' => $punchedAfter['last'],
                'erp_login_open' => $person['status'] === 'active' && $person['login'] === 'enable',
            ];
        }

        // Worst first: still punching, then still able to sign in, then the
        // longest standing.
        usort($rows, function ($a, $b) {
            return [$b['punches_after_leaving'] > 0, $b['erp_login_open'], $b['days_since']]
                <=> [$a['punches_after_leaving'] > 0, $a['erp_login_open'], $a['days_since']];
        });

        return [
            'rows' => $rows,
            'counts' => [
                'total' => count($rows),
                'still_punching' => count(array_filter($rows, fn ($r) => $r['punches_after_leaving'] > 0)),
                'login_open' => count(array_filter($rows, fn ($r) => $r['erp_login_open'])),
                'terminated' => count(array_filter($rows, fn ($r) => $r['reason'] === 'terminated')),
            ],
        ];
    }

    /**
     * How many need attention, for the badge on the tab.
     *
     * Drawn on every biometric page, and working it out asks a few questions
     * per employee — fine for a dozen, not for a thousand. Held for a couple of
     * minutes: the badge being that stale costs nothing, since it counts people
     * who left days ago.
     */
    public function outstanding(?Carbon $asOf = null): int
    {
        $key = 'iapp_departed_enrolments_' . ($asOf ?: Carbon::today())->toDateString();

        return (int) cache()->remember($key, now()->addMinutes(2),
            fn () => $this->build($asOf)['counts']['total']);
    }

    /**
     * The device enrolment behind a person, by the mapping first and by their
     * card ID second — a code can be on the devices before the mapping is made.
     */
    protected function enrolmentFor(array $person): ?object
    {
        $codes = array_values(array_filter([$person['iapp_emp_code'], $person['employee_card_id']]));

        return DB::table('iapp_employees')
            ->where(function ($query) use ($person, $codes) {
                $query->where('user_id', $person['id']);

                if ($codes !== []) {
                    $query->orWhereIn('emp_code', $codes);
                }
            })
            ->orderByDesc('punch_count')
            ->first(['emp_code', 'card_id', 'last_seen_at', 'punch_count', 'is_active']);
    }

    /**
     * Punches recorded after somebody's last working day.
     *
     * @return array{count: int, last: ?string}
     */
    protected function punchesAfter(string $empCode, string $lastWorkingDate): array
    {
        $after = DB::table('iapp_device_transactions')
            ->where('emp_code', $empCode)
            ->whereDate('punch_time', '>', $lastWorkingDate)
            ->selectRaw('COUNT(*) AS n, MAX(punch_time) AS last')
            ->first();

        return ['count' => (int) ($after->n ?? 0), 'last' => $after->last ?? null];
    }

}
