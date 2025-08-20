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
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_template_id')->constrained('syllabus_templates')->onDelete('cascade');
            $table->string('name', 100)->nullable();
            $table->string('code', 20)->nullable(); // e.g., "A1", "MID", "PROJ"
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->enum('type', ['quiz', 'assignment', 'project', 'exam', 'online_activity', 'other']);
            $table->boolean('is_required_to_sit_final_exam')->default(true);
            
            // Due dates and timing
            $table->datetime('due_date')->nullable();
            $table->datetime('available_from')->nullable(); // When students can start working on it
            $table->datetime('late_submission_deadline')->nullable();
            $table->decimal('late_penalty_percentage', 5, 2)->default(0.00); // Penalty per day/hour late
            $table->enum('late_penalty_type', ['per_day', 'per_hour', 'fixed', 'none'])->default('none');
            
            // Submission and assessment details
            $table->enum('submission_type', [
                'online',
                'in_person',
                'both',
                'no_submission',
            ])->default('online');
            
            $table->json('allowed_file_types')->nullable(); // ["pdf", "docx", "pptx"]
            $table->integer('max_file_size_mb')->nullable();
            $table->integer('max_submissions')->default(1); // How many times can student submit
            $table->boolean('allow_resubmission')->default(false);
            
            // Group work settings
            $table->boolean('is_group_work')->default(false);
            $table->integer('min_group_size')->nullable();
            $table->integer('max_group_size')->nullable();
            $table->boolean('students_form_groups')->default(true); // If false, instructor assigns groups
            
            // Assessment criteria and rubrics
            $table->json('assessment_criteria')->nullable(); // Detailed rubric or criteria
            $table->text('grading_instructions')->nullable(); // Instructions for graders
            
            // Administrative settings
            $table->boolean('is_published')->default(false); // Is visible to students
            $table->boolean('scores_published')->default(false); // Are scores visible to students
            $table->boolean('is_extra_credit')->default(false);
            
            $table->enum('status', [
                'draft',
                'published',
                'in_progress',
                'grading',
                'completed',
                'cancelled',
            ])->default('draft');
            
            // Ordering and organization
            $table->integer('sort_order')->default(0);
            $table->string('category', 50)->nullable(); // Group related components
            
            $table->timestamps();
            
            // Add unique constraint for component codes within syllabus_template
            $table->unique(['syllabus_template_id', 'code'], 'unique_syllabus_template_component_code');
            
            // Add indexes
            $table->index(['syllabus_template_id', 'type'], 'syllabus_template_type_idx');
            $table->index(['syllabus_template_id', 'sort_order'], 'syllabus_template_sort_order_idx');
            $table->index(['due_date', 'status']);
            $table->index(['is_published', 'status']);
            $table->index(['is_group_work', 'type']);
            $table->index(['category', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};
