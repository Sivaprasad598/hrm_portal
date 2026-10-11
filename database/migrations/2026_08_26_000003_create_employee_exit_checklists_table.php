<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_exit_checklists')) {
            Schema::create('employee_exit_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('exit_id');
                $table->string('category', 50);
                $table->string('item_key', 80);
                $table->string('item_label', 200);
                $table->enum('status', ['pending', 'completed', 'not_applicable'])->default('pending');
                $table->unsignedInteger('completed_by')->nullable();
                $table->date('completed_date')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('exit_id')->references('id')->on('employee_exits')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_exit_checklists');
    }
};
