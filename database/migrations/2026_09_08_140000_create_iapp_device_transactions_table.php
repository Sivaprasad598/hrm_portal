<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw punch log pulled from iApp's deviceTrans/get — one row per punch,
     * as opposed to `attendances` which is one row per session (clock in +
     * clock out). Kept separate because the source fields (controller/card)
     * don't fit the attendances schema, and a punch stream doesn't have a
     * single clock-in/clock-out pair until it's aggregated.
     */
    public function up(): void
    {
        Schema::create('iapp_device_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('emp_code')->nullable();
            $table->string('controller_code')->nullable();
            $table->string('card_id')->nullable();
            $table->dateTime('punch_time')->nullable();
            // Raw record as returned by iApp — kept as a fallback since the
            // docs don't give a full field list/format for PunchDetail.
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->unique(['controller_code', 'card_id', 'punch_time'], 'iapp_device_transactions_natural_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_device_transactions');
    }
};
