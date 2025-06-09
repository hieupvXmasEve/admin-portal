<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->nullable()->constrained()->cascadeOnDelete();

            // Credit Requirements
            $table->decimal('total_credits_required', 5, 2);
            $table->decimal('core_credits_required', 5, 2)->default(0.00);
            $table->decimal('major_credits_required', 5, 2)->default(0.00);
            $table->decimal('elective_credits_required', 5, 2)->default(0.00);

            // GPA Requirements
            $table->decimal('minimum_gpa', 3, 2)->default(2.00);
            $table->decimal('minimum_major_gpa', 3, 2)->default(2.00);

            // Other Requirements
            $table->integer('maximum_study_years')->default(6);
            $table->boolean('required_internship')->default(false);
            $table->boolean('required_thesis')->default(false);
            $table->boolean('required_english_certification')->default(false);

            // Special Requirements (JSON)
            $table->json('special_requirements')->nullable();

            // Validity
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['program_id', 'specialization_id']);
            $table->index(['is_active', 'effective_from', 'effective_to'], 'grad_req_active_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_requirements');
    }
};
