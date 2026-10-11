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
        Schema::create('project_label_list', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->string('label_name');
            $table->string('color')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('added_by')->nullable()->index('project_labels_added_by_foreign');
            $table->foreign(['added_by'])->references(['id'])->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->timestamps();
        });

        Schema::create('project_labels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('label_id')->index('project_labels_label_id_foreign');
            $table->unsignedInteger('project_id')->index('project_tags_project_id_foreign');
            $table->foreign(['label_id'])->references(['id'])->on('project_label_list')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign(['project_id'], 'project_tags_project_id_foreign')->references(['id'])->on('projects')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_label_list');
        Schema::dropIfExists('project_labels');
    }
};
