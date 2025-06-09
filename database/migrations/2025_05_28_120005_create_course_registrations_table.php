<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            
            // Registration Details
            $table->enum('registration_status', [
                'registered', 'confirmed', 'dropped', 'withdrawn', 'completed'
            ])->default('confirmed');
            $table->timestamp('registration_date');
            $table->enum('registration_method', [
                'online', 'advisor', 'admin_override'
            ])->default('online');
            
            // Academic Record
            $table->decimal('credit_hours', 4, 2);
            $table->string('final_grade', 3)->nullable();
            $table->decimal('grade_points', 3, 2)->nullable();
            $table->integer('attempt_number')->default(1);
            $table->boolean('is_retake')->default(false);
            
            // Dates
            $table->timestamp('drop_date')->nullable();
            $table->timestamp('withdrawal_date')->nullable();
            $table->timestamp('completion_date')->nullable();
            
            // Financial
            $table->decimal('tuition_amount', 10, 2)->default(0.00);
            $table->decimal('fees_amount', 10, 2)->default(0.00);
            $table->enum('payment_status', [
                'pending', 'paid', 'overdue', 'waived'
            ])->default('pending');
            
            // System
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Constraints
            $table->unique(['student_id', 'course_offering_id', 'semester_id'], 'unique_student_course_semester');
            
            // Indexes
            $table->index('student_id');
            $table->index('course_offering_id');
            $table->index('semester_id');
            $table->index('registration_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_registrations');
    }
};
