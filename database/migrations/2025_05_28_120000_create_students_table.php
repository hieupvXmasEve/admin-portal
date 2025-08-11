<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the table if it exists and recreate with new schema
        Schema::dropIfExists('students');

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20)->unique();
            $table->string('full_name', 100);
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password', 255)->nullable();

            // OAuth Integration (simplified)
            $table->enum('oauth_provider', ['google', 'microsoft', 'manual'])->default('google');
            $table->string('oauth_provider_id', 255)->nullable();
            $table->string('avatar_url', 500)->nullable();

            // Basic Information
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('nationality', 100)->default('Vietnamese');
            $table->string('national_id', 20)->unique()->nullable();
            $table->text('address')->nullable();

            // Academic Assignment
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('specialization_id')->nullable()->constrained('specializations')->nullOnDelete();
            $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->restrictOnDelete();
            $table->date('admission_date');
            $table->date('expected_graduation_date')->nullable();

            // Emergency Contact Information
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relationship', 100)->nullable();

            // Academic Background
            $table->string('high_school_name', 255)->nullable();
            $table->year('high_school_graduation_year')->nullable();
            $table->decimal('entrance_exam_score', 5, 2)->nullable();
            $table->text('admission_notes')->nullable();

            // System Fields
            $table->enum('status', ['active', 'inactive', 'suspended', 'graduated'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('student_id');
            $table->index('email');
            $table->index(['oauth_provider', 'oauth_provider_id']);
            $table->index('campus_id');
            $table->index(['program_id', 'specialization_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
