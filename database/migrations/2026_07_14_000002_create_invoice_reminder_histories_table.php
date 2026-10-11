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
        if (!Schema::hasTable('invoice_reminder_histories')) {
            Schema::create('invoice_reminder_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('invoice_id')->index();
                $table->string('type', 20)->default('manual'); // before|after|manual
                $table->string('recipient_email')->nullable();
                $table->string('recipient_name')->nullable();
                $table->unsignedInteger('sent_by')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('invoice_id')
                    ->references('id')
                    ->on('invoices')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_reminder_histories');
    }
};
