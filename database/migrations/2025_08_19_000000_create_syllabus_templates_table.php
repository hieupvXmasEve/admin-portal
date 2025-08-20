<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('syllabus_templates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('unit_id');
            $table->string('title');
            $table->string('version', 20)->nullable();
            $table->text('description')->nullable();

            $table->integer('total_hours')->nullable();
            $table->integer('total_sessions')->nullable();

            // Options
            $table->json('learning_outcomes')->nullable();
            $table->json('grading_criteria')->nullable();
            $table->json('required_materials')->nullable();
            $table->text('assessment_policy')->nullable();

            $table->unsignedBigInteger('applicable_program_id')->nullable();
            $table->unsignedBigInteger('applicable_campus_id')->nullable();
            $table->enum('delivery_mode', ['in_person', 'online', 'hybrid', 'blended'])->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('source_template_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys with explicit names matching the provided schema
            $table->foreign('unit_id', 'fk_syllabus_templates_unit')
                ->references('id')->on('units');

            $table->foreign('applicable_program_id', 'fk_syllabus_templates_program')
                ->references('id')->on('programs');

            $table->foreign('applicable_campus_id', 'fk_syllabus_templates_campus')
                ->references('id')->on('campuses');

            $table->foreign('created_by', 'fk_syllabus_templates_creator')
                ->references('id')->on('users');

            $table->foreign('source_template_id', 'fk_syllabus_templates_source')
                ->references('id')->on('syllabus_templates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabus_templates');
    }
};
