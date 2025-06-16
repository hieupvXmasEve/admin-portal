<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop tables in correct order (child tables first)
        Schema::dropIfExists('student_unit_enrollments');
        Schema::dropIfExists('student_enrollments');
    }

    public function down(): void
    {
        // These tables are no longer needed, but keeping the structure for reference
        // in case we need to restore data during development

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('semester_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->foreignId('specialization_id')->nullable()->constrained()->onDelete('set null');

            $table->enum('enrollment_status', [
                'enrolled',
                'active',
                'withdrawn',
                'completed',
                'suspended',
                'deferred'
            ])->default('enrolled');

            $table->date('enrollment_date');
            $table->decimal('total_credit_hours', 5, 2)->default(0.00);
            $table->decimal('gpa_semester', 3, 2)->nullable();
            $table->decimal('gpa_cumulative', 3, 2)->nullable();

            $table->enum('academic_standing', [
                'good_standing',
                'probation',
                'suspension',
                'dismissal',
                'dean_list',
                'honor_roll'
            ])->default('good_standing');

            $table->boolean('is_full_time')->default(true);
            $table->boolean('is_probation')->default(false);
            $table->boolean('is_dean_list')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'semester_id']);
            $table->index(['semester_id', 'enrollment_status']);
            $table->index(['program_id', 'specialization_id']);
            $table->index(['academic_standing', 'is_probation']);

            // Unique constraint to prevent duplicate enrollments
            $table->unique(['user_id', 'semester_id'], 'unique_student_semester_enrollment');
        });

        Schema::create('student_unit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_offering_id')->constrained()->onDelete('cascade');

            $table->enum('enrollment_status', [
                'enrolled',
                'active',
                'dropped',
                'withdrawn',
                'completed',
                'waitlisted'
            ])->default('enrolled');

            $table->date('enrollment_date');
            $table->date('drop_date')->nullable();
            $table->date('withdrawal_date')->nullable();

            // Grades
            $table->string('midterm_grade', 3)->nullable();
            $table->string('final_grade', 3)->nullable();
            $table->enum('grade_status', [
                'in_progress',
                'midterm',
                'final',
                'incomplete',
                'audit'
            ])->default('in_progress');

            // Attendance
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            // Special flags
            $table->boolean('is_audit')->default(false);
            $table->enum('payment_status', [
                'paid',
                'pending',
                'overdue',
                'waived',
                'scholarship'
            ])->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes with shorter names
            $table->index(['student_enrollment_id', 'enrollment_status'], 'sue_student_enrollment_status_idx');
            $table->index(['course_offering_id', 'enrollment_status'], 'sue_offering_status_idx');
            $table->index(['final_grade', 'grade_status'], 'sue_grade_status_idx');
            $table->index(['payment_status'], 'sue_payment_status_idx');
            $table->index(['is_audit'], 'sue_audit_idx');

            // Unique constraint to prevent duplicate enrollments
            $table->unique([
                'student_enrollment_id',
                'course_offering_id'
            ], 'unique_student_unit_enrollment');
        });
    }
};
