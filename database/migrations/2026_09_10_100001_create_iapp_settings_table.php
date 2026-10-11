<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('iapp_settings')) {
            return;
        }

        Schema::create('iapp_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();

            // Connection. client_secret is cast 'encrypted' on the model.
            $table->string('base_url')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('company_code', 10)->nullable();
            $table->string('location_code', 5)->nullable();

            $table->boolean('status')->default(0);

            // Which employee_details column iApp's empId maps to. The Excel
            // punch import matches on employee_card_id, so that is the default
            // here too — keeping both device paths on one identity column.
            $table->string('employee_match_field', 30)->default('employee_card_id');

            // Sync behaviour
            $table->boolean('sync_enabled')->default(0);
            $table->unsignedSmallInteger('sync_interval_minutes')->default(60);
            // Attendance for a past date changes after regularisation/leave
            // approval, so each run re-pulls this many days back.
            $table->unsignedSmallInteger('resync_days')->default(3);
            $table->boolean('auto_promote')->default(0);

            // Health
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->text('last_sync_message')->nullable();

            $table->timestamps();

            $table->unique('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_settings');
    }
};
