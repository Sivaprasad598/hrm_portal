<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('probation_replies')) {
            Schema::create('probation_replies', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('employee_details_id');
                $table->foreign('employee_details_id')->references('id')->on('employee_details')->onDelete('cascade');
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->string('sender_type', 20)->default('admin');
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('probation_replies');
    }
};
