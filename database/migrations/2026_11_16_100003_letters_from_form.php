<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Letters made from the Letter Generator form, not only from an employee:
 *  - offer_letters / relieving_letters: the employee is optional (a candidate,
 *    or someone who left before the system), and every letter keeps what it
 *    printed (details) so it reopens the same;
 *  - offer_letters: the person's name, designation and joining date as columns,
 *    for the dashboard's Upcoming Joinings and the joining-day reminder
 *    (joining_notified_at), and cancelled_at for an offer that is withdrawn.
 * Each step checks first, so running it again changes nothing.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('offer_letters')) {
            DB::statement('ALTER TABLE offer_letters MODIFY user_id INT UNSIGNED NULL');

            foreach ([
                'source' => "VARCHAR(20) NOT NULL DEFAULT 'employee' AFTER user_id",
                'person_name' => 'VARCHAR(191) NULL AFTER source',
                'designation' => 'VARCHAR(191) NULL AFTER person_name',
                'location' => 'VARCHAR(191) NULL AFTER designation',
                'joining_date' => 'DATE NULL AFTER location',
                'annual_ctc' => 'DECIMAL(15,2) NULL AFTER joining_date',
                'details' => 'LONGTEXT NULL AFTER annual_ctc',
                'joining_notified_at' => 'TIMESTAMP NULL AFTER generated_by',
                'cancelled_at' => 'TIMESTAMP NULL AFTER joining_notified_at',
            ] as $column => $definition) {
                if (!Schema::hasColumn('offer_letters', $column)) {
                    DB::statement("ALTER TABLE offer_letters ADD COLUMN `$column` $definition");
                }
            }

            // Letters issued before: their employee's name, designation, branch and joining date.
            DB::statement("UPDATE offer_letters o
                JOIN users u ON u.id = o.user_id
                JOIN employee_details ed ON ed.user_id = u.id
                LEFT JOIN designations d ON d.id = ed.designation_id
                LEFT JOIN branches b ON b.id = ed.branch_id
                SET o.person_name = COALESCE(o.person_name, u.name),
                    o.designation = COALESCE(o.designation, d.name),
                    o.location = COALESCE(o.location, b.branch_name),
                    o.joining_date = COALESCE(o.joining_date, DATE(ed.joining_date)),
                    o.annual_ctc = COALESCE(o.annual_ctc, u.employee_ctc)");

            if (!collect(DB::select('SHOW INDEX FROM offer_letters'))->contains('Key_name', 'offer_letters_joining_date_index')) {
                DB::statement('CREATE INDEX offer_letters_joining_date_index ON offer_letters (joining_date)');
            }
        }

        if (Schema::hasTable('relieving_letters')) {
            DB::statement('ALTER TABLE relieving_letters MODIFY user_id INT UNSIGNED NULL');

            foreach ([
                'source' => "VARCHAR(20) NOT NULL DEFAULT 'employee' AFTER user_id",
                'person_name' => 'VARCHAR(191) NULL AFTER source',
                'details' => 'LONGTEXT NULL AFTER person_name',
            ] as $column => $definition) {
                if (!Schema::hasColumn('relieving_letters', $column)) {
                    DB::statement("ALTER TABLE relieving_letters ADD COLUMN `$column` $definition");
                }
            }

            DB::statement('UPDATE relieving_letters r JOIN users u ON u.id = r.user_id SET r.person_name = COALESCE(r.person_name, u.name)');
        }
    }

    public function down(): void
    {
        foreach (['offer_letters' => ['source', 'person_name', 'designation', 'location', 'joining_date', 'annual_ctc', 'details', 'joining_notified_at', 'cancelled_at'],
            'relieving_letters' => ['source', 'person_name', 'details']] as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE `$table` DROP COLUMN `$column`");
                }
            }
        }
    }

};
