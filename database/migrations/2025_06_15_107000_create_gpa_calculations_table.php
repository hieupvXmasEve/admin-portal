<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gpa_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();

            // GPA calculation type
            $table->enum('calculation_type', [
                'semester', // GPA for a specific semester
                'cumulative', // Overall cumulative GPA
                'major', // GPA for major courses only
                'program', // GPA for specific program
                'year', // GPA for academic year
                'transfer', // Transfer credit GPA
                'institutional' // Institutional GPA (excluding transfer)
            ])->default('semester');

            // GPA values
            $table->decimal('gpa', 4, 3)->nullable(); // GPA on 4.0 scale (e.g., 3.750)
            $table->decimal('quality_points', 8, 3)->default(0.000); // Total quality points earned
            $table->decimal('credit_hours_attempted', 6, 2)->default(0.00); // Total credit hours attempted
            $table->decimal('credit_hours_earned', 6, 2)->default(0.00); // Total credit hours successfully completed
            $table->decimal('credit_hours_gpa', 6, 2)->default(0.00); // Credit hours used in GPA calculation

            // Course counts and statistics
            $table->integer('total_courses')->default(0); // Total number of courses
            $table->integer('completed_courses')->default(0); // Successfully completed courses
            $table->integer('failed_courses')->default(0); // Failed courses
            $table->integer('withdrawn_courses')->default(0); // Withdrawn courses
            $table->integer('incomplete_courses')->default(0); // Incomplete courses

            // Grade distribution
            $table->integer('a_grades')->default(0); // A+ and A grades
            $table->integer('b_grades')->default(0); // B+ and B grades
            $table->integer('c_grades')->default(0); // C+ and C grades
            $table->integer('d_grades')->default(0); // D+ and D grades
            $table->integer('f_grades')->default(0); // F grades

            // Academic standing indicators
            $table->enum('academic_standing', [
                'excellent', // Dean's List level
                'good', // Good standing
                'satisfactory', // Satisfactory standing
                'probation', // Academic probation
                'suspension', // Academic suspension
                'dismissal', // Academic dismissal
                'warning' // Academic warning
            ])->nullable();

            $table->decimal('required_gpa', 3, 2)->nullable(); // Required GPA for program/level
            $table->boolean('meets_gpa_requirement')->default(true);
            $table->decimal('gpa_deficit', 4, 3)->nullable(); // How far below required GPA

            // Semester/Year specific data
            $table->string('academic_year', 10)->nullable(); // e.g., "2024-2025"
            $table->integer('year_level')->nullable(); // 1, 2, 3, 4 for undergrad
            $table->enum('semester_type', ['fall', 'spring', 'summer', 'winter'])->nullable();

            // Ranking and percentile information
            $table->integer('class_rank')->nullable(); // Rank within graduating class
            $table->integer('class_size')->nullable(); // Total students in class
            $table->decimal('percentile', 5, 2)->nullable(); // Percentile ranking
            $table->integer('program_rank')->nullable(); // Rank within program
            $table->integer('program_class_size')->nullable(); // Total students in program

            // Graduation and progression tracking
            $table->decimal('credits_needed_to_graduate', 6, 2)->nullable();
            $table->decimal('completion_percentage', 5, 2)->nullable(); // % of degree completed
            $table->date('projected_graduation_date')->nullable();
            $table->boolean('on_track_to_graduate')->default(true);

            // Special circumstances
            $table->boolean('includes_transfer_credits')->default(false);
            $table->boolean('includes_repeated_courses')->default(false);
            $table->boolean('dean_list_eligible')->default(false);
            $table->boolean('honors_eligible')->default(false);
            $table->boolean('graduation_honors_eligible')->default(false);

            // Calculation metadata
            $table->timestamp('calculated_at');
            $table->foreignId('calculated_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();
            $table->json('calculation_parameters')->nullable(); // Parameters used in calculation
            $table->text('calculation_notes')->nullable();

            // Verification and approval
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            // Historical tracking
            $table->boolean('is_current')->default(true); // Is this the current calculation
            $table->decimal('previous_gpa', 4, 3)->nullable(); // Previous GPA for comparison
            $table->decimal('gpa_change', 4, 3)->nullable(); // Change from previous calculation
            $table->enum('gpa_trend', ['improving', 'declining', 'stable'])->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance optimization
            $table->index(['student_id', 'calculation_type', 'is_current'], 'student_calc_type_current_idx');
            $table->index(['student_id', 'semester_id', 'calculation_type'], 'student_semester_calc_idx');
            $table->index(['student_id', 'academic_year', 'calculation_type'], 'student_year_calc_idx');
            $table->index(['program_id', 'semester_id', 'gpa'], 'program_semester_gpa_idx');
            $table->index(['academic_standing', 'gpa'], 'standing_gpa_idx');
            $table->index(['dean_list_eligible', 'gpa'], 'dean_list_gpa_idx');
            $table->index(['class_rank', 'class_size'], 'class_ranking_idx');
            $table->index(['program_rank', 'program_class_size'], 'program_ranking_idx');
            $table->index(['calculated_at', 'is_current'], 'calc_date_current_idx');
            $table->index(['is_verified', 'verified_at'], 'verification_idx');
            $table->index(['completion_percentage', 'on_track_to_graduate'], 'completion_track_idx');

            // Composite indexes for common queries
            $table->index(['student_id', 'calculation_type', 'semester_id', 'is_current'], 'student_calc_semester_current_idx');
            $table->index(['student_id', 'gpa', 'credit_hours_earned', 'is_current'], 'student_progress_idx');

            // Unique constraints
            $table->unique(['student_id', 'semester_id', 'calculation_type'], 'unique_student_semester_calc_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpa_calculations');
    }
};
