<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            // Add more detailed fields for assessment configuration
            $table->text('description')->nullable()->after('name');
            $table->string('code', 20)->nullable()->after('name'); // e.g., "A1", "MID", "PROJ"

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
                'no_submission'
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
                'cancelled'
            ])->default('draft');

            // Ordering and organization
            $table->integer('sort_order')->default(0);
            $table->string('category', 50)->nullable(); // Group related components

            // Add unique constraint for component codes within syllabus
            $table->unique(['syllabus_id', 'code'], 'unique_syllabus_component_code');

            // Add indexes
            $table->index(['syllabus_id', 'type'], 'syllabus_type_idx');
            $table->index(['syllabus_id', 'sort_order'], 'syllabus_sort_order_idx');
            $table->index(['due_date', 'status']);
            $table->index(['is_published', 'status']);
            $table->index(['is_group_work', 'type']);
            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->dropUnique('unique_syllabus_component_code');
            $table->dropIndex('syllabus_type_idx');
            $table->dropIndex('syllabus_sort_order_idx');
            $table->dropIndex(['due_date', 'status']);
            $table->dropIndex(['is_published', 'status']);
            $table->dropIndex(['is_group_work', 'type']);
            $table->dropIndex(['category', 'sort_order']);

            $table->dropColumn([
                'description',
                'code',
                'due_date',
                'available_from',
                'late_submission_deadline',
                'late_penalty_percentage',
                'late_penalty_type',
                'submission_type',
                'allowed_file_types',
                'max_file_size_mb',
                'max_submissions',
                'allow_resubmission',
                'is_group_work',
                'min_group_size',
                'max_group_size',
                'students_form_groups',
                'assessment_criteria',
                'grading_instructions',
                'is_published',
                'scores_published',
                'is_extra_credit',
                'status',
                'sort_order',
                'category'
            ]);
        });
    }
};
