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
        Schema::create('student_unit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained()->onDelete('cascade');
            $table->foreignId('semester_unit_offering_id')->constrained()->onDelete('cascade');

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
            $table->index(['semester_unit_offering_id', 'enrollment_status'], 'sue_offering_status_idx');
            $table->index(['final_grade', 'grade_status'], 'sue_grade_status_idx');
            $table->index(['payment_status'], 'sue_payment_status_idx');
            $table->index(['is_audit'], 'sue_audit_idx');

            // Unique constraint to prevent duplicate enrollments
            $table->unique([
                'student_enrollment_id',
                'semester_unit_offering_id'
            ], 'unique_student_unit_enrollment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_unit_enrollments');
    }
};
