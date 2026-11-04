<?php

namespace App\Services\Recruit;

/**
 * Who may do what in Recruitment (Settings -> Access):
 *
 *   view      view_recruitment             - see positions, candidates, interviews, offers
 *   manage    manage_recruitment           - add and edit them, move stages, schedule, offer
 *   settings  manage_recruitment_settings  - the careers mailbox, website API key, lists
 *
 * The Super Admin has them all. Interviewers need none of them to hand in the
 * scorecard for an interview they are on.
 */
class RecruitAccess
{

    public static function can(string $what): bool
    {
        if (!user()) {
            return false;
        }

        if (in_array('admin', user_roles())) {
            return true;
        }

        $permission = ['view' => 'view_recruitment', 'manage' => 'manage_recruitment', 'settings' => 'manage_recruitment_settings'][$what] ?? null;

        if (!$permission) {
            return false;
        }

        // Managing implies seeing.
        if ($what === 'view' && user()->permission('manage_recruitment') === 'all') {
            return true;
        }

        return user()->permission($permission) === 'all';
    }

}
