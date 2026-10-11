<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_ctc')) {
                $table->decimal('employee_ctc', 16, 2)->nullable()->after('country_phonecode');
            }

            if (!Schema::hasColumn('users', 'employee_image')) {
                $table->string('employee_image')->nullable()->after('employee_ctc');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_image')) {
                $table->dropColumn('employee_image');
            }

            if (Schema::hasColumn('users', 'employee_ctc')) {
                $table->dropColumn('employee_ctc');
            }
        });
    }
};
