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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->onDelete('cascade');
            $table->tinyInteger('semester_number')->unsigned();
            $table->enum('status', ['in_progress', 'completed', 'withdrawn'])->default('in_progress');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['student_id', 'semester_id']);
            $table->index(['semester_id', 'status']);
            $table->index(['curriculum_version_id', 'semester_number']);

            // Ensure a student can only be enrolled once per semester
            $table->unique(['student_id', 'semester_id'], 'unique_student_semester_enrollment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
