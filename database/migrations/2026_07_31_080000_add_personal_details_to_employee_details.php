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
        Schema::table('employee_details', function (Blueprint $table) {
            $table->string('aadhar_card_number')->nullable()->after('ifsc_code');
            $table->string('father_or_husband_name')->nullable()->after('aadhar_card_number');
            $table->string('qualification')->nullable()->after('father_or_husband_name');
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable()->after('qualification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropColumn(['aadhar_card_number', 'father_or_husband_name', 'qualification', 'blood_group']);
        });
    }
};
