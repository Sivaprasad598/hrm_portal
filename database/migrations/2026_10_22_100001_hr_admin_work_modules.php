<?php

use App\Services\Employee\RoleAccess;
use Illuminate\Database\Migrations\Migration;

/**
 * HR, Admin and CEO get the Work modules - Projects, Contracts and Tasks - at
 * "all": every permission except delete_*, and the Projects / Contracts menu
 * entries (Tasks was already on their menu, limited to their own tasks).
 * See RoleAccess::MODULES.
 */
return new class extends Migration {

    public function up(): void
    {
        RoleAccess::applyAll();
        \App\Models\User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        // Access only: narrow it again under Settings -> Roles & Permissions if needed.
    }

};
