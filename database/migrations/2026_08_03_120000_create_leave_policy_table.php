<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('leave_policy')) {
            Schema::create('leave_policy', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->unsignedInteger('leave_type_id');
                $table->decimal('monthly_allocation', 3, 1)->default(1.0);
                $table->boolean('carry_forward')->default(false);
                $table->integer('half_day_conversion_rate')->default(2);
                $table->decimal('fixed_annual_days', 3, 1)->default(0);
                $table->boolean('is_active')->default(true);

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade')->onUpdate('cascade');
            });
        }

        $this->seedDefaultPolicies();
    }

    /**
     * Seed a default policy row for the Casual/Sick/Marriage leave types on
     * company 1. This schema's `leave_types` table has no `code` column, so
     * the type is matched by `type_name` instead (Casual/Sick/Marriage...).
     */
    private function seedDefaultPolicies(): void
    {
        $leaveTypes = DB::table('leave_types')->where('company_id', 1)->get(['id', 'type_name']);

        foreach ($leaveTypes as $leaveType) {
            $name = strtolower(trim((string) $leaveType->type_name));
            $policy = null;

            if (str_starts_with($name, 'casual')) {
                $policy = ['carry_forward' => true, 'fixed_annual_days' => 0];
            }
            elseif (str_starts_with($name, 'sick')) {
                $policy = ['carry_forward' => false, 'fixed_annual_days' => 0];
            }
            elseif (str_starts_with($name, 'marriage')) {
                $policy = ['carry_forward' => false, 'fixed_annual_days' => 7.0];
            }

            if ($policy === null) {
                continue;
            }

            $exists = DB::table('leave_policy')
                ->where('company_id', 1)
                ->where('leave_type_id', $leaveType->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('leave_policy')->insert([
                'company_id' => 1,
                'leave_type_id' => $leaveType->id,
                'monthly_allocation' => 1.0,
                'carry_forward' => $policy['carry_forward'],
                'half_day_conversion_rate' => 2,
                'fixed_annual_days' => $policy['fixed_annual_days'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policy');
    }
};
