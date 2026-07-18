<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('course_result_id')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->decimal('final_percentage', 5, 2);
            $table->string('final_letter_grade', 5);
            $table->decimal('credit_points', 6, 2);
            $table->decimal('credit_points_earned', 6, 2)->default(0);
            $table->decimal('quality_points', 8, 2);
            $table->boolean('is_passed');
            $table->boolean('excluded_from_gpa')->default(false);
            $table->boolean('affects_academic_standing')->default(true);
            $table->boolean('affects_graduation_requirement')->default(true);
            $table->boolean('satisfies_prerequisite')->default(true);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'semester_id', 'excluded_from_gpa'], 'transcript_student_gpa_idx');
            $table->index(['student_id', 'unit_id', 'attempt_number'], 'transcript_best_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_entries');
    }
};
