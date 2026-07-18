<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('curriculum_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('intake_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('intake_major_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('enrollment_status', 64);
            $table->string('study_stage', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_active_student_id')
                ->storedAs("IF(is_primary = 1 AND enrollment_status = 'active', student_id, NULL)")
                ->unique();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->json('source_snapshot');
            $table->timestamp('materialized_at');
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['student_id', 'is_primary', 'enrollment_status'], 'program_enrollment_primary_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_enrollments');
    }
};
