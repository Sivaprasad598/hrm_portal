<?php

namespace App\Services\Employment;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Has this person left the company?
 *
 * Four records can say so, and they are not kept in step with each other:
 *
 *   employee_details.last_date               set by the employee screens
 *   employee_details.notice_period_end_date  set when a resignation is approved
 *   resignations.last_working_date           the approved resignation itself
 *   terminations.last_working_date           the legacy termination page
 *
 * The nightly `inactive-employee` command reads only the first two, and the
 * termination page writes neither — it sets employment_status to 'off_boarded'
 * and nothing else. So a terminated employee was never deactivated at all, and
 * somebody whose notice ended kept their access until the command next ran,
 * which on an installation without a cron entry is never.
 *
 * Asking all four at the moment of sign-in does not depend on a scheduler
 * having run.
 */
class Separation
{

    /**
     * Why this person has left, or null if they have not.
     *
     * A date in the future means they are working their notice and still have
     * their access; today counts as their last day, so access ends after it.
     */
    public function reasonFor(int $userId, ?Carbon $asOf = null): ?array
    {
        $asOf = ($asOf ?: Carbon::today())->toDateString();

        $details = DB::table('employee_details')->where('user_id', $userId)
            ->first(['last_date', 'notice_period_end_date', 'employment_status']);

        // Each candidate: the reason to show, and the date it took effect.
        $candidates = [];

        if ($details?->last_date && substr((string) $details->last_date, 0, 10) < $asOf) {
            $candidates[] = ['reason' => 'exit', 'label' => 'Left the company',
                'date' => substr((string) $details->last_date, 0, 10)];
        }

        if ($details?->notice_period_end_date && substr((string) $details->notice_period_end_date, 0, 10) < $asOf) {
            $candidates[] = ['reason' => 'notice_period', 'label' => 'Notice period finished',
                'date' => substr((string) $details->notice_period_end_date, 0, 10)];
        }

        // A resignation from an employment that has since ended in a rejoin no
        // longer closes the account.
        $resignation = DB::table('resignations')->where('user_id', $userId)
            ->when($this->hasColumn('resignations', 'closed_by_rejoin_id'), fn ($q) => $q->whereNull('closed_by_rejoin_id'))
            ->where('status', 'approved')->whereNotNull('last_working_date')
            ->whereDate('last_working_date', '<', $asOf)
            ->orderByDesc('last_working_date')->value('last_working_date');

        if ($resignation) {
            $candidates[] = ['reason' => 'resigned', 'label' => 'Resigned, notice period finished',
                'date' => substr((string) $resignation, 0, 10)];
        }

        $termination = $this->terminationDate($userId, $asOf);

        if ($termination !== null) {
            $candidates[] = ['reason' => 'terminated', 'label' => 'Terminated',
                'date' => $termination];
        }

        // A termination outranks the rest: it is the reason that matters when
        // somebody is asked why the account is closed.
        usort($candidates, fn ($a, $b) => ($a['reason'] === 'terminated' ? -1 : 0) <=> ($b['reason'] === 'terminated' ? -1 : 0));

        return $candidates[0] ?? null;
    }

    public function hasLeft(int $userId, ?Carbon $asOf = null): bool
    {
        return $this->reasonFor($userId, $asOf) !== null;
    }

    /**
     * Close the account: no sign-in, and any session already open is dropped,
     * because the person is holding one when their last day ends.
     */
    public function closeAccount(int $userId): void
    {
        DB::table('users')->where('id', $userId)->update([
            'status' => 'deactive',
            'login' => 'disable',
            'inactive_date' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('sessions')->where('user_id', $userId)->delete();
        } catch (\Throwable) {
            // Sessions are not always kept in the database; the account is
            // closed either way.
        }
    }

    /**
     * The date a termination took effect, if it is in the past.
     *
     * The legacy page records `last_working_date` but may leave it empty, in
     * which case the termination date itself is the day it took effect. A row
     * with neither is a termination that has been entered but not dated, and is
     * not acted on.
     */
    protected function terminationDate(int $userId, string $asOf): ?string
    {
        if (!$this->tableExists('terminations')) {
            return null;
        }

        $row = DB::table('terminations')->where('user_id', $userId)
            ->when($this->hasColumn('terminations', 'closed_by_rejoin_id'), fn ($q) => $q->whereNull('closed_by_rejoin_id'))
            ->orderByDesc('id')
            ->first(['termination_date', 'last_working_date']);

        if (!$row) {
            return null;
        }

        $date = $row->last_working_date ?: $row->termination_date;

        if (!$date) {
            return null;
        }

        $date = substr((string) $date, 0, 10);

        return $date < $asOf ? $date : null;
    }

    protected function hasColumn(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Everyone who has left, for the screens that list them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function everyoneWhoHasLeft(?Carbon $asOf = null): array
    {
        $rows = [];

        foreach (DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'u.status', 'u.login', 'ed.employee_card_id',
                'ed.iapp_emp_code', 'ed.employment_status']) as $user) {

            $reason = $this->reasonFor((int) $user->id, $asOf);

            if ($reason === null) {
                continue;
            }

            $rows[] = (array) $user + $reason;
        }

        return $rows;
    }

}
