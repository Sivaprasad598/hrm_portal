<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_quota_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index('elqe_user_id_foreign');
            $table->unsignedInteger('leave_type_id')->index('elqe_leave_type_id_foreign');
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->string('event_type', 64);
            $table->double('amount')->default(0);
            $table->double('carry_forward_total')->default(0);
            $table->double('carry_forward_used')->default(0);
            $table->date('leave_year_start');
            $table->date('expired_on')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->foreign(['leave_type_id'], 'elqe_leave_type_id_foreign')
                ->references(['id'])->on('leave_types')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign(['user_id'], 'elqe_user_id_foreign')
                ->references(['id'])->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');

            $table->unique(
                ['user_id', 'leave_type_id', 'event_type', 'leave_year_start'],
                'elqe_unique_carry_forward_expiry'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_quota_events');
    }
};
