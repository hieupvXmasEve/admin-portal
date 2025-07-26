<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->onDelete('cascade');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('room_booking_id')->nullable()->constrained('room_bookings')->nullOnDelete();
            $table->foreignId('lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            $table->string('session_title', 200)->nullable(); // e.g., "Introduction to Programming", "Midterm Exam"
            $table->text('session_description')->nullable();

            // Session timing
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->nullable(); // Calculated on the fly

            $table->enum('session_type', [
                'lecture',
                'tutorial',
                'practical',
                'laboratory',
                'seminar',
                'workshop',
                'exam',
                'assessment',
                'field_trip',
                'guest_lecture',
                'review',
                'other'
            ])->default('lecture');

            $table->enum('delivery_mode', [
                'in_person',
                'online',
                'hybrid',
                'blended'
            ])->default('in_person');

            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
                'postponed',
                'moved'
            ])->default('scheduled');

            // Online session details
            $table->string('online_meeting_url', 500)->nullable();
            $table->string('meeting_id', 100)->nullable();
            $table->string('meeting_password', 100)->nullable();

            // Session content and materials
            $table->json('learning_objectives')->nullable(); // ["Understand basic concepts", "Apply theory"]
            $table->json('required_materials')->nullable(); // ["textbook_chapter_5", "calculator", "laptop"]
            $table->json('topics_covered')->nullable(); // ["Variables", "Functions", "Loops"]

            // Attendance and participation
            $table->boolean('attendance_required')->default(true);
            $table->boolean('attendance_tracking_enabled')->default(true);
            $table->integer('expected_attendees')->nullable();
            $table->integer('actual_attendees')->nullable();
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            // Assessment details (if applicable)
            $table->boolean('is_assessment')->default(false);
            $table->decimal('assessment_weight', 5, 2)->nullable(); // Percentage of total grade
            $table->integer('assessment_duration_minutes')->nullable();
            $table->json('assessment_materials_allowed')->nullable(); // ["calculator", "notes", "open_book"]

            // Recurring session support
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('parent_session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
            $table->integer('sequence_number')->nullable(); // For ordering recurring sessions

            // Administrative fields
            $table->text('instructor_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('student_instructions')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Timestamps for tracking changes
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['course_offering_id', 'session_date', 'start_time'], 'co_session_schedule_idx');
            $table->index(['room_id', 'session_date', 'start_time', 'end_time'], 'room_session_conflict_idx');
            $table->index(['lecture_id', 'session_date', 'start_time'], 'instructor_schedule_idx');
            $table->index(['session_type', 'status']);
            $table->index(['delivery_mode', 'session_date']);
            $table->index(['is_assessment', 'session_date']);
            $table->index(['attendance_required', 'attendance_tracking_enabled'], 'attendance_config_idx');
            $table->index(['is_recurring', 'parent_session_id']);
            $table->index(['sequence_number']);

            // Unique constraint for session sequence in course offering
            $table->unique(['course_offering_id', 'sequence_number'], 'unique_co_sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
