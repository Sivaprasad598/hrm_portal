<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emp_passport_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->string('passport_number')->nullable();
            $table->date('passport_date_of_issue')->nullable();
            $table->string('passport_place_of_issue')->nullable();
            $table->date('passport_valid_up_to')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_passport_details');
    }
};
