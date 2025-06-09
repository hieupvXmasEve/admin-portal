<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            
            // Course Details
            $table->string('course_code', 20);
            $table->string('section_code', 10)->default('01');
            $table->string('course_title', 255);
            $table->decimal('credit_hours', 4, 2);
            
            // Capacity and Enrollment
            $table->integer('max_enrollment')->default(30);
            $table->integer('current_enrollment')->default(0);
            $table->integer('waitlist_capacity')->default(10);
            $table->integer('current_waitlist')->default(0);
            
            // Delivery and Schedule
            $table->enum('delivery_mode', ['in_person', 'online', 'hybrid'])->default('in_person');
            $table->json('schedule')->nullable(); // Store schedule information
            $table->string('location', 255)->nullable();
            
            // Instructor and Prerequisites
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('prerequisites')->nullable(); // Store prerequisite requirements
            
            // Status and Dates
            $table->enum('status', ['active', 'cancelled', 'full', 'closed'])->default('active');
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();
            $table->date('drop_deadline')->nullable();
            $table->date('withdrawal_deadline')->nullable();
            
            // Financial
            $table->decimal('tuition_per_credit', 8, 2)->default(0.00);
            $table->decimal('additional_fees', 8, 2)->default(0.00);
            
            // System
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Constraints
            $table->unique(['semester_id', 'course_code', 'section_code'], 'unique_semester_course_section');
            
            // Indexes
            $table->index('semester_id');
            $table->index('unit_id');
            $table->index('campus_id');
            $table->index('course_code');
            $table->index('status');
            $table->index('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
