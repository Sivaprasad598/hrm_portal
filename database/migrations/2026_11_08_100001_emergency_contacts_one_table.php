<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency contacts were kept in two tables: Add / Edit Employee wrote
 * employee_relations, while the profile, Profile Settings, the add/edit
 * modal and the mobile app read emergency_contacts - so contacts entered on
 * the employee form never showed anywhere. emergency_contacts is now the
 * only one; this copies what the form saved into it (once: a contact already
 * there for the same person, name and number is left alone).
 * employee_relations is kept, unused, so nothing is lost.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('employee_relations') || !Schema::hasTable('emergency_contacts')) {
            return;
        }

        $now = now();

        DB::table('employee_relations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->orderBy('r.user_id')->orderBy('r.serial_no')->orderBy('r.id')
            ->select(['r.user_id', 'r.name', 'r.relation', 'r.contact_number', 'r.created_at', 'u.company_id'])
            ->chunk(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $name = trim((string) $row->name);

                    if ($name === '' && trim((string) $row->contact_number) === '') {
                        continue;
                    }

                    $exists = DB::table('emergency_contacts')
                        ->where('user_id', $row->user_id)
                        ->where('name', $name)
                        ->where('mobile', (string) $row->contact_number)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('emergency_contacts')->insert([
                        'company_id' => $row->company_id,
                        'user_id' => $row->user_id,
                        'name' => $name,
                        'mobile' => (string) $row->contact_number,
                        'relation' => $row->relation,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The copies cannot be told apart from contacts added since; nothing to undo.
    }

};
