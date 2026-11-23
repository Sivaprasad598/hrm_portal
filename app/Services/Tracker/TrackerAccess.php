<?php

namespace App\Services\Tracker;

use App\Models\Project;

/**
 * Who may do what in the project tracker, on the Projects module's own permissions:
 *
 *   settings  manage_project_setting = all       - project types, stages, rates, working hours
 *   manage    admin, edit_projects = all, or the project's admin - a project's structure and plan
 *   view      anyone who can open the project (its own page decides that)
 */
class TrackerAccess
{

    public static function settings(): bool
    {
        return user() && (in_array('admin', user_roles()) || user()->permission('manage_project_setting') === 'all');
    }

    public static function manage(?Project $project = null): bool
    {
        if (!user()) {
            return false;
        }

        return in_array('admin', user_roles()) || user()->permission('edit_projects') === 'all'
            || ($project && (int) $project->project_admin === (int) user()->id);
    }

}
