<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets: servicing, repairs and renewals.
 *
 *   asset_services   one row per service / repair / inspection job: vendor,
 *                    sent and completed dates, cost, the bill
 *   asset_renewals   AMC, insurance, extended warranty, vehicle RC / PUC,
 *                    software licences: provider, reference, start, expiry,
 *                    cost, document; renewing adds the next period and keeps
 *                    the old one as history (previous_id)
 *   asset_reminders  which bell reminders went out, so each goes once
 *
 * On assets: how often it is serviced, when the next service is due, and the
 * earliest of everything due (next_due_on / next_due_what) for the list.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'service_interval_months')) {
                $table->unsignedSmallInteger('service_interval_months')->nullable()->after('warranty_until');
                $table->date('next_service_on')->nullable()->after('service_interval_months');
                $table->date('next_due_on')->nullable()->index()->after('next_service_on');
                $table->string('next_due_what', 100)->nullable()->after('next_due_on');
            }
        });

        if (!Schema::hasTable('asset_services')) {
            Schema::create('asset_services', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('kind', 20)->default('service');
                $table->string('vendor')->nullable();
                $table->string('reference')->nullable();
                $table->date('sent_on');
                $table->date('completed_on')->nullable()->index();
                $table->decimal('cost', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->string('bill')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asset_renewals')) {
            Schema::create('asset_renewals', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->unsignedBigInteger('previous_id')->nullable()->index();
                $table->string('kind', 20);
                $table->string('provider')->nullable();
                $table->string('reference_no')->nullable();
                $table->date('starts_on')->nullable();
                $table->date('expires_on')->index();
                $table->decimal('cost', 12, 2)->nullable();
                $table->string('document')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asset_reminders')) {
            Schema::create('asset_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('subject', 40);
                $table->date('due_on');
                $table->string('stage', 10);
                $table->timestamps();
                $table->unique(['asset_id', 'subject', 'due_on', 'stage'], 'asset_reminders_once');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_reminders');
        Schema::dropIfExists('asset_renewals');
        Schema::dropIfExists('asset_services');

        Schema::table('assets', function (Blueprint $table) {
            foreach (['next_due_what', 'next_due_on', 'next_service_on', 'service_interval_months'] as $column) {
                if (Schema::hasColumn('assets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

};
