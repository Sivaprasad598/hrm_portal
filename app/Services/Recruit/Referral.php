<?php

namespace App\Services\Recruit;

use App\Models\Candidate;
use App\Models\User;
use App\Notifications\RecruitReferralNotice;
use App\Services\Access\SystemAccounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Referrals: the employee who referred a candidate (Source = Referral).
 * Set from Add / Edit Candidate, kept on the timeline, and the referrer is
 * told twice only: when the referral is recorded and when the candidate joins.
 */
class Referral
{

    public function __construct(protected Pipeline $pipeline)
    {
    }

    /**
     * The employees one can pick: everyone active, plus the one already chosen
     * (kept, marked "(left)", when they are no longer active).
     */
    public static function employees(?int $keep = null): Collection
    {
        return User::join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('teams', 'teams.id', '=', 'employee_details.department_id')
            ->whereNotIn('users.id', SystemAccounts::hidden())
            ->where(fn ($q) => $q->where('users.status', 'active')->when($keep, fn ($k) => $k->orWhere('users.id', $keep)))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.status', 'employee_details.employee_id', 'teams.team_name as division']);
    }

    /** For the "Referred by" filter: id => "Name (EMP-0042)" of everyone who has referred a candidate. */
    public static function options(): Collection
    {
        $ids = Candidate::whereNotNull('referred_by')->distinct()->pluck('referred_by');

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::withoutGlobalScopes()->leftJoin('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('teams', 'teams.id', '=', 'employee_details.department_id')
            ->whereIn('users.id', $ids)->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.status', 'employee_details.employee_id', 'teams.team_name as division'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ($u->employee_id ? ' (' . $u->employee_id . ')' : '') . ($u->status !== 'active' ? ' (left)' : '')
                . ($u->division ? ' · ' . $u->division : '')]);
    }

    /** Whether $userId may be chosen as the referrer ($current: the one already set, which stays allowed). */
    public static function allowed(?int $userId, ?int $current = null): bool
    {
        return $userId && ($userId === $current || self::employees()->contains('id', $userId));
    }

    /** Record the referrer (null to clear it): timeline, and a notice to a newly named referrer. */
    public function set(Candidate $candidate, ?int $userId, ?int $applicationId = null): void
    {
        $before = $candidate->referred_by ? (int) $candidate->referred_by : null;
        $userId = $userId ?: null;

        if ($before === $userId) {
            return;
        }

        $oldLabel = $candidate->referrerLabel();
        $candidate->referred_by = $userId;
        $candidate->save();
        $candidate->unsetRelation('referrer');

        $this->pipeline->log($candidate->id, $applicationId, 'referral', $userId
            ? ($before ? 'Referred by: ' . $oldLabel . ' → ' . $candidate->referrerLabel() : 'Referred by ' . $candidate->referrerLabel())
            : 'Referrer removed (was ' . $oldLabel . ')', null, null, $candidate->company_id);

        if ($userId) {
            $position = $candidate->applications()->with('position')->first()?->position?->title;
            $this->notify($candidate, 'added', $position);
        }
    }

    /** The candidate has joined: tell the referrer. */
    public function joined(Candidate $candidate, ?string $position = null): void
    {
        if ($candidate->referred_by) {
            $this->notify($candidate, 'joined', $position);
        }
    }

    protected function notify(Candidate $candidate, string $type, ?string $position): void
    {
        // the HR person recording their own referral is not told about it
        if (!$candidate->referred_by || (int) $candidate->referred_by === (int) user()?->id) {
            return;
        }

        $referrer = User::withoutGlobalScopes()->where('id', $candidate->referred_by)->where('status', 'active')->first();

        if (!$referrer) {
            return;
        }

        try {
            Notification::send($referrer, new RecruitReferralNotice($candidate, $type, $position));
        } catch (\Throwable $e) {
            logger()->warning('Referral notice not sent', ['candidate' => $candidate->id, 'error' => $e->getMessage()]);
        }
    }

}
