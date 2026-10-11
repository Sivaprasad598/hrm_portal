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
        Schema::create('recurring_invoice_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('recurring_invoice_id')->index();
            $table->unsignedInteger('invoice_id')->nullable()->index();
            $table->enum('status', ['success', 'failed']);
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('response_code')->default(0);
            $table->timestamps();

            $table->foreign('recurring_invoice_id')->references('id')->on('invoice_recurring')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_logs');
        // Note: foreign keys are dropped automatically with the table
    }
};
