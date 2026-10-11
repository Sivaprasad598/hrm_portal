<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->decimal('total_experience', 5, 2)->nullable()->after('employment_type');
            $table->string('previous_company_name')->nullable()->after('total_experience');
            $table->string('company_location')->nullable()->after('previous_company_name');
            $table->string('last_held_designation')->nullable()->after('company_location');
            $table->date('experience_joining_date')->nullable()->after('last_held_designation');
            $table->date('experience_leaving_date')->nullable()->after('experience_joining_date');
            $table->string('pf_account_number')->nullable()->after('experience_leaving_date');
            $table->string('esi_number')->nullable()->after('pf_account_number');
            $table->string('personal_account_number')->nullable()->after('esi_number');
            $table->string('ifsc_code')->nullable()->after('personal_account_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropColumn([
                'total_experience',
                'previous_company_name',
                'company_location',
                'last_held_designation',
                'experience_joining_date',
                'experience_leaving_date',
                'pf_account_number',
                'esi_number',
                'personal_account_number',
                'ifsc_code'
            ]);
        });
    }
};
