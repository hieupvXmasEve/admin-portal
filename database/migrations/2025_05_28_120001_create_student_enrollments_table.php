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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
