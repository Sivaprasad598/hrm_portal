<?php

use App\Services\Employee\RoleAccess;
use Illuminate\Database\Migrations\Migration;

/**
 * HR runs Recruitment: view and manage it, and its settings (careers mailbox,
 * website API key, lists), with the Recruitment menu (Job Dashboard,
 * Positions, Applicants, Resumes, Interviews) and Recruitment Settings.
 * See RoleAccess::ONLY_FOR / MENU_ONLY_FOR; given to the HR role and everyone
 * holding it.
 */
return new class extends Migration {

    public function up(): void
    {
        RoleAccess::applyAll();
    }

    public function down(): void
    {
        // Access only: take it back under Settings -> Roles & Permissions if needed.
    }

};
