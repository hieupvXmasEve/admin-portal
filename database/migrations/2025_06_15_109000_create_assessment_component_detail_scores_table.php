<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_component_detail_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_component_detail_id');
            $table->foreign('assessment_component_detail_id', 'detail_scores_detail_id_fk')->references('id')->on('assessment_component_details')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('course_offering_id')->constrained('course_offerings')->onDelete('cascade');
            $table->unsignedBigInteger('graded_by_lecture_id')->nullable();
            $table->foreign('graded_by_lecture_id', 'detail_scores_graded_by_fk')->references('id')->on('lectures')->nullOnDelete();

            // Score values
            $table->decimal('points_earned', 8, 2)->nullable(); // Actual points earned
            $table->decimal('percentage_score', 5, 2)->nullable(); // Percentage score (0-100)
            $table->string('letter_grade', 5)->nullable(); // A+, A, A-, B+, etc.
            $table->decimal('gpa_points', 3, 2)->nullable(); // 4.0 scale equivalent

            // Submission details
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->integer('submission_attempt')->default(1); // Which submission attempt this is
            $table->json('submission_files')->nullable(); // Paths to submitted files
            $table->longText('submission_text')->nullable(); // Text-based submissions
            $table->string('submission_url', 500)->nullable(); // External submission URLs

            // Late submission tracking
            $table->boolean('is_late')->default(false);
            $table->integer('minutes_late')->default(0);
            $table->decimal('late_penalty_applied', 5, 2)->default(0.00); // Percentage penalty applied
            $table->text('late_excuse')->nullable();
            $table->boolean('late_excuse_approved')->default(false);

            // Score status and workflow
            $table->enum('status', [
                'not_submitted',
                'submitted',
                'grading',
                'graded',
                'returned',
                'resubmit_required',
                'excused',
                'incomplete',
                'cancelled'
            ])->default('not_submitted');

            $table->enum('score_status', [
                'draft',
                'provisional',
                'final',
                'disputed',
                'under_review'
            ])->default('draft');

            // Feedback and comments
            $table->longText('instructor_feedback')->nullable();
            $table->longText('private_notes')->nullable(); // Internal notes, not visible to student
            $table->json('rubric_scores')->nullable(); // Detailed rubric scoring
            $table->decimal('bonus_points', 8, 2)->default(0.00);
            $table->text('bonus_reason')->nullable();

            // Academic integrity and plagiarism
            $table->boolean('plagiarism_suspected')->default(false);
            $table->decimal('plagiarism_score', 5, 2)->nullable(); // Similarity percentage from plagiarism checker
            $table->text('plagiarism_notes')->nullable();
            $table->enum('integrity_status', [
                'clear',
                'under_investigation',
                'violation_confirmed',
                'violation_minor',
                'violation_major'
            ])->default('clear');

            // Score history and auditing
            $table->json('score_history')->nullable(); // Track all score changes
            $table->timestamp('last_modified_at')->nullable();
            $table->unsignedBigInteger('last_modified_by_lecture_id')->nullable();
            $table->foreign('last_modified_by_lecture_id', 'detail_scores_modified_by_fk')->references('id')->on('lectures')->nullOnDelete();

            // Special circumstances
            $table->boolean('is_extra_credit')->default(false);
            $table->boolean('is_makeup')->default(false); // Makeup assignment/exam
            $table->text('special_circumstances')->nullable();
            $table->boolean('score_excluded')->default(false); // Exclude from final score calculation
            $table->text('exclusion_reason')->nullable();

            // Student feedback and appeals
            $table->text('student_comments')->nullable();
            $table->boolean('appeal_requested')->default(false);
            $table->timestamp('appeal_requested_at')->nullable();
            $table->text('appeal_reason')->nullable();
            $table->enum('appeal_status', [
                'none',
                'pending',
                'under_review',
                'approved',
                'denied'
            ])->default('none');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance optimization
            $table->index(['assessment_component_detail_id', 'student_id'], 'detail_student_idx');
            $table->index(['student_id', 'status'], 'student_status_idx');
            $table->index(['student_id', 'score_status'], 'student_score_status_idx');
            $table->index(['course_offering_id', 'status'], 'course_status_idx');
            $table->index(['graded_by_lecture_id', 'graded_at'], 'graded_by_date_idx');
            $table->index(['submitted_at', 'status'], 'submitted_status_idx');
            $table->index(['is_late', 'late_penalty_applied'], 'late_penalty_idx');
            $table->index(['plagiarism_suspected', 'integrity_status'], 'plagiarism_integrity_idx');
            $table->index(['appeal_requested', 'appeal_status'], 'appeal_status_idx');
            $table->index(['score_excluded', 'is_extra_credit'], 'excluded_extra_idx');

            // Unique constraint to prevent duplicate scores
            $table->unique(['assessment_component_detail_id', 'student_id', 'course_offering_id', 'submission_attempt'], 'unique_detail_student_course_attempt');

            // Indexes for GPA calculations and transcript generation
            $table->index(['student_id', 'score_status', 'score_excluded', 'gpa_points'], 'student_score_gpa_calc_idx');
            $table->index(['student_id', 'graded_at', 'score_status'], 'student_transcript_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_component_detail_scores');
    }
};
