<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('course_offering_id')->constrained('course_offerings')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();

            // Final grade information
            $table->decimal('final_percentage', 5, 2)->nullable(); // Final percentage score (0-100)
            $table->string('final_letter_grade', 5)->nullable(); // A+, A, A-, B+, etc.
            $table->decimal('grade_points', 3, 2)->nullable(); // 4.0 scale equivalent
            $table->decimal('quality_points', 6, 2)->nullable(); // grade_points * credit_hours
            $table->decimal('credit_hours', 4, 2); // Credit hours for this course
            $table->decimal('credit_hours_earned', 4, 2)->default(0.00); // Credit hours earned (0 if failed)

            // Grade status and verification
            $table->enum('grade_status', [
                'in_progress',
                'provisional',
                'final',
                'incomplete',
                'withdrawn',
                'failed',
                'pass_no_credit',
                'audit',
                'transfer_credit'
            ])->default('in_progress');

            $table->enum('completion_status', [
                'enrolled',
                'completed',
                'withdrawn',
                'failed',
                'incomplete',
                'in_progress'
            ])->default('enrolled');

            // Important dates
            $table->date('enrollment_date');
            $table->date('completion_date')->nullable();
            $table->date('grade_submission_date')->nullable();
            $table->date('grade_finalized_date')->nullable();

            // Academic performance indicators
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->integer('total_absences')->default(0);
            $table->integer('total_class_sessions')->nullable();
            $table->boolean('meets_attendance_requirement')->default(true);

            // Special circumstances and notes
            $table->boolean('is_repeat_course')->default(false); // Student repeating this course
            $table->integer('attempt_number')->default(1); // Which attempt this is (1st, 2nd, etc.)
            $table->foreignId('original_record_id')->nullable()->constrained('academic_records')->nullOnDelete();

            $table->boolean('is_transfer_credit')->default(false);
            $table->string('transfer_institution', 200)->nullable();
            $table->string('transfer_course_code', 50)->nullable();
            $table->string('transfer_course_title', 200)->nullable();

            $table->boolean('is_advanced_placement')->default(false);
            $table->boolean('is_challenge_exam')->default(false);
            $table->boolean('is_credit_by_exam')->default(false);

            // Grading and calculation details
            $table->json('grade_breakdown')->nullable(); // Detailed breakdown of component grades
            $table->decimal('raw_percentage', 5, 2)->nullable(); // Before any adjustments
            $table->decimal('curve_adjustment', 5, 2)->default(0.00); // Grade curve applied
            $table->text('grade_adjustment_reason')->nullable();

            $table->boolean('excluded_from_gpa')->default(false);
            $table->text('gpa_exclusion_reason')->nullable();

            // Administrative fields
            $table->text('instructor_comments')->nullable();
            $table->text('administrative_notes')->nullable();

            // Lecture-based references for instructor and grading activities
            $table->foreignId('instructor_id')->nullable()->constrained('lectures')->nullOnDelete();
            $table->foreignId('grade_submitted_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();
            $table->foreignId('grade_approved_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            // Academic standing impact
            $table->boolean('affects_academic_standing')->default(true);
            $table->boolean('affects_graduation_requirement')->default(true);
            $table->boolean('satisfies_prerequisite')->default(true);

            // Audit trail
            $table->json('grade_history')->nullable(); // Track all grade changes
            $table->timestamp('last_grade_change_at')->nullable();
            $table->foreignId('last_changed_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance optimization and fast queries
            $table->index(['student_id', 'semester_id'], 'student_semester_idx');
            $table->index(['student_id', 'grade_status', 'completion_status'], 'student_grade_completion_idx');
            $table->index(['student_id', 'excluded_from_gpa', 'grade_points'], 'student_gpa_calc_idx');
            $table->index(['unit_id', 'semester_id', 'grade_status'], 'unit_semester_grade_idx');
            $table->index(['program_id', 'semester_id', 'completion_status'], 'program_semester_completion_idx');
            $table->index(['campus_id', 'semester_id', 'grade_status'], 'campus_semester_grade_idx');
            $table->index(['instructor_id', 'semester_id', 'grade_status'], 'instructor_semester_grade_idx');
            $table->index(['is_repeat_course', 'attempt_number'], 'repeat_attempt_idx');
            $table->index(['is_transfer_credit', 'grade_status'], 'transfer_grade_idx');
            $table->index(['grade_finalized_date', 'grade_status'], 'finalized_date_idx');
            $table->index(['enrollment_date', 'completion_date'], 'enrollment_completion_dates_idx');

            // Unique constraint to prevent duplicate records
            $table->unique(['student_id', 'course_offering_id'], 'unique_student_course_offering');

            // Composite indexes for transcript and GPA calculations
            $table->index(['student_id', 'completion_status', 'grade_finalized_date', 'credit_hours'], 'transcript_calc_idx');
            $table->index(['student_id', 'excluded_from_gpa', 'quality_points', 'credit_hours'], 'gpa_calc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};
