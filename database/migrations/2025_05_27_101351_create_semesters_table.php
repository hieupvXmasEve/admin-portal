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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->onDelete('cascade');
            $table->string('name', 25);
            $table->enum('semester_type', ['fall', 'spring', 'summer', 'winter', 'intersession'])->default('fall');
            $table->string('academic_year', 9)->nullable(); // e.g., "2024-2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->date('enrollment_start_date')->nullable();
            $table->date('enrollment_end_date')->nullable();
            $table->date('add_drop_deadline')->nullable();
            $table->date('withdrawal_deadline')->nullable();
            $table->date('final_exam_start')->nullable();
            $table->date('final_exam_end')->nullable();
            $table->enum('locked_status', ['locked', 'unlocked'])->default('unlocked');
            $table->boolean('is_attendance_locked')->default(false);
            $table->boolean('is_certificate_locked')->default(false);
            $table->boolean('has_tuition_fee')->default(false);
            $table->boolean('has_gc_fee')->default(false);
            $table->boolean('is_current')->default(false);
            $table->boolean('is_registration_open')->default(false);
            $table->decimal('max_credit_load', 4, 2)->default(18.00);
            $table->decimal('min_credit_load', 4, 2)->default(12.00);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['campus_id', 'semester_type']);
            $table->index(['academic_year', 'semester_type']);
            $table->index(['is_current', 'is_registration_open']);
            $table->index(['enrollment_start_date', 'enrollment_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
