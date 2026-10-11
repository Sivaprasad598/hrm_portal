<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('resignation_replies')) {
            Schema::create('resignation_replies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('resignation_id');
                $table->unsignedInteger('user_id');
                $table->enum('sender_type', ['employee', 'admin']);
                $table->text('message');
                $table->timestamps();

                $table->foreign('resignation_id')->references('id')->on('resignations')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resignation_replies');
    }
};
