<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * The daily attendance routine:
     * - attendance_daily_runs: one row per date — pulled from the device, reviewed,
     *   notified — so a day whose pull failed is retried until it goes through.
     * - attendance_reviews: one row per employee per date — what that day was
     *   (present, late, short hours, missing exit, absent without leave, leave
     *   clash...) and whether HR has dealt with it.
     * - iapp_settings: the time of the daily pull (11:00) and whether employees
     *   are told about their own exceptions.
     */
    public function up(): void
    {
        if (!Schema::hasTable('attendance_daily_runs')) {
            Schema::create('attendance_daily_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->date('date');
                $table->string('status', 20)->default('pending'); // pending, pulled, reviewed
                $table->boolean('device_complete')->default(false);
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->string('summary', 500)->nullable();
                $table->timestamp('pulled_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'date']);
            });
        }

        if (!Schema::hasTable('attendance_reviews')) {
            Schema::create('attendance_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->date('date');
                $table->unsignedInteger('user_id');
                $table->string('status', 30);
                $table->json('flags')->nullable();
                $table->boolean('is_exception')->default(false);
                $table->string('source', 20)->nullable(); // device, web, none
                $table->timestamp('first_in')->nullable();
                $table->timestamp('last_out')->nullable();
                $table->unsignedInteger('presence_minutes')->default(0);
                $table->unsignedSmallInteger('punches')->default(0);
                $table->decimal('credit', 3, 1)->default(0);
                $table->string('note', 500)->nullable();
                $table->timestamp('employee_notified_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->timestamps();

                $table->unique(['date', 'user_id']);
                $table->index(['date', 'is_exception']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        Schema::table('iapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_settings', 'daily_pull_time')) {
                $table->string('daily_pull_time', 5)->default('11:00')->after('sync_interval_minutes');
            }

            if (!Schema::hasColumn('iapp_settings', 'notify_employees')) {
                $table->boolean('notify_employees')->default(true)->after('auto_generate_payslips');
            }
        });

        DB::table('iapp_settings')->update(['daily_pull_time' => '11:00', 'notify_employees' => 1]);
    }

    public function down(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_pull_time', 'notify_employees']);
        });

        Schema::dropIfExists('attendance_reviews');
        Schema::dropIfExists('attendance_daily_runs');
    }

};
