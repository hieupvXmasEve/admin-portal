<?php

declare(strict_types=1);

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
        Schema::create('lectures', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('employee_id', 20)->unique();
            $table->string('title', 10)->nullable(); // Dr., Prof., Mr., Ms., etc.
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('mobile_phone', 20)->nullable();

            // Authentication and OAuth fields
            $table->string('password')->nullable();
            $table->string('oauth_provider')->nullable(); // google, microsoft, etc.
            $table->string('oauth_provider_id')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Campus and Department
            $table->foreignId('campus_id')->constrained()->onDelete('restrict');
            $table->string('department', 100)->nullable();
            $table->string('faculty', 100)->nullable();

            // Academic Information
            $table->string('specialization', 255)->nullable();
            $table->json('expertise_areas')->nullable(); // Array of expertise areas
            $table->enum('academic_rank', [
                'lecturer',
                'senior_lecturer',
                'associate_professor',
                'professor',
                'emeritus_professor',
                'visiting_lecturer',
                'adjunct_professor',
            ])->default('lecturer');

            // Educational Background
            $table->string('highest_degree', 50)->nullable(); // PhD, Master's, etc.
            $table->string('degree_field', 255)->nullable();
            $table->string('alma_mater', 255)->nullable();
            $table->year('graduation_year')->nullable();

            // Employment Details
            $table->date('hire_date');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('employment_type', [
                'full_time',
                'part_time',
                'contract',
                'visiting',
                'emeritus',
            ])->default('full_time');
            $table->enum('employment_status', [
                'active',
                'on_leave',
                'sabbatical',
                'retired',
                'terminated',
                'suspended',
            ])->default('active');

            // Teaching Preferences and Constraints
            $table->json('preferred_teaching_days')->nullable(); // Array of preferred days
            $table->time('preferred_start_time')->nullable();
            $table->time('preferred_end_time')->nullable();
            $table->integer('max_teaching_hours_per_week')->default(40);
            $table->json('teaching_modalities')->nullable(); // ['in_person', 'online', 'hybrid']

            // Contact and Emergency Information
            $table->text('office_address')->nullable();
            $table->string('office_phone', 20)->nullable();
            $table->text('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();

            // Professional Information
            $table->text('biography')->nullable();
            $table->json('certifications')->nullable(); // Array of professional certifications
            $table->json('languages')->nullable(); // Array of languages spoken
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('salary', 12, 2)->nullable();

            // System Fields
            $table->boolean('is_active')->default(true);
            $table->boolean('can_teach_online')->default(true);
            $table->boolean('is_available_for_assignment')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['campus_id', 'is_active']);
            $table->index(['employment_status', 'is_active']);
            $table->index(['academic_rank', 'specialization']);
            $table->index(['hire_date']);
            $table->index(['email']);
            $table->index(['employee_id']);
            $table->index(['last_name', 'first_name']);
            $table->index(['department', 'faculty']);
            $table->index(['is_available_for_assignment', 'employment_status']);
            $table->index(['oauth_provider', 'oauth_provider_id']);
            $table->index(['last_login_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lectures');
    }
};
