<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standing instructions for extra pay: "everyone in Gis Department gets ₹1,000
 * a month", "the night shift group gets an allowance", "this one person gets
 * a fixed deduction".
 *
 * A rule does not pay anything by itself. Each pay period it produces one
 * ordinary line in `payslip_components` for every employee it matches, which is
 * what reaches the payslip. That keeps one explanation for every figure on a
 * slip: it is a line, and the line says where it came from.
 *
 * Two rules matching the same person both apply, each as its own line, so an
 * employee can see exactly which instruction paid them what.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('pay_element_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 100);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('pay_element_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('pay_element_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('element_type_id');

            // Who it reaches. scope_id names the department, designation, group
            // or employee; for 'everyone' it is not used.
            $table->enum('scope', ['everyone', 'department', 'designation', 'group', 'employee']);
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->decimal('amount', 12, 2);
            // Pay periods, by the date each one starts.
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('status', ['active', 'stopped'])->default('active');
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_on']);
            $table->index(['scope', 'scope_id']);
        });

        // One row per rule, employee and period, written the first time that
        // rule produces a line for that person. It is what stops a line coming
        // back after somebody deliberately removed it, and what stops a rule
        // paying twice when a screen is opened twice.
        Schema::create('pay_element_rule_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('user_id');
            $table->date('period_start');
            $table->timestamps();

            $table->unique(['rule_id', 'user_id', 'period_start'], 'rule_user_period_unique');
        });

        Schema::table('payslip_components', function (Blueprint $table) {
            // Which standing instruction produced this line, if any.
            $table->unsignedBigInteger('rule_id')->nullable()->after('arrangement_id');
            $table->index('rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('payslip_components', function (Blueprint $table) {
            $table->dropIndex(['rule_id']);
            $table->dropColumn('rule_id');
        });

        Schema::dropIfExists('pay_element_rule_applications');
        Schema::dropIfExists('pay_element_rules');
        Schema::dropIfExists('pay_element_group_members');
        Schema::dropIfExists('pay_element_groups');
    }

};
