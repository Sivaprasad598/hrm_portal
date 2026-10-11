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
        if (Schema::hasTable('emp_joining_checklists')) {
            return;
        }

        Schema::create('emp_joining_checklists', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->tinyInteger('educational_testimonials')->default(0);
            $table->tinyInteger('experience_letter')->default(0);
            $table->tinyInteger('relieving_letter')->default(0);
            $table->tinyInteger('two_references')->default(0);
            $table->tinyInteger('salary_certificate')->default(0);
            $table->tinyInteger('bank_statement')->default(0);
            $table->tinyInteger('passport_copy')->default(0);
            $table->tinyInteger('form_16')->default(0);
            $table->tinyInteger('passport_photographs')->default(0);
            $table->tinyInteger('offer_letter')->default(0);
            $table->tinyInteger('resume_copy')->default(0);
            $table->tinyInteger('nda')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_joining_checklists');
    }
};
