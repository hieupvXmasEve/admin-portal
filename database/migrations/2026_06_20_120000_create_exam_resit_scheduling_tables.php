<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam-resit scheduling foundation (ACAD-RET-001 slice 5).
 *
 * Exam resit (thi lại) is scheduled with a dedicated session model, NOT the
 * course `class_sessions` model, so it never looks like normal course delivery:
 *
 *   exam_room_slots          = one room + date/time block (one physical booking)
 *   exam_resit_sessions      = one unit/course exam held inside a room slot
 *   exam_room_slot_invigilators = invigilators attached to the shared room block
 *
 * Multiple unit-scoped sessions may share one room slot (Academic deliberately
 * groups small retake exams in the same room/time), but the room block must not
 * overlap normal class sessions or unrelated active room bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_room_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            // The optional backing room booking, so the exam block also shows up in
            // the canonical room-booking ledger when Academic reserves the room.
            $table->foreignId('room_booking_id')->nullable()->constrained('room_bookings')->nullOnDelete();

            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            // Total seat capacity for the shared block; defaults to the room capacity
            // at creation time. Sum of session expected candidates must not exceed it.
            $table->unsignedInteger('capacity')->default(0);

            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['room_id', 'exam_date', 'start_time', 'end_time'], 'exam_room_slot_conflict_idx');
            $table->index(['campus_id', 'exam_date', 'status'], 'exam_room_slot_scope_idx');
        });

        Schema::create('exam_resit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_room_slot_id')->constrained('exam_room_slots')->cascadeOnDelete();
            // One session belongs to exactly one unit/course.
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('syllabus_template_id')->nullable()->constrained('syllabus_templates')->nullOnDelete();

            $table->string('status', 20)->default('scheduled');
            $table->unsignedInteger('expected_candidates')->default(0);
            $table->unsignedInteger('actual_candidates')->nullable();
            $table->text('instructions')->nullable();
            $table->json('materials_allowed')->nullable();

            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('exam_room_slot_id');
            $table->index(['unit_id', 'semester_id', 'status'], 'exam_resit_session_unit_idx');
        });

        Schema::create('exam_room_slot_invigilators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_room_slot_id')->constrained('exam_room_slots')->cascadeOnDelete();
            // Invigilators are existing lecturers (the `lectures` table doubles as staff).
            $table->foreignId('lecture_id')->constrained('lectures')->cascadeOnDelete();
            $table->string('role', 20)->default('assistant');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['exam_room_slot_id', 'lecture_id'], 'exam_room_slot_invigilator_unique');
        });

        Schema::table('exam_resit_attempts', function (Blueprint $table) {
            $table->foreignId('exam_resit_session_id')->nullable()->after('status')
                ->constrained('exam_resit_sessions')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->after('approved_at');
            $table->foreignId('scheduled_by_user_id')->nullable()->after('scheduled_at')
                ->constrained('users')->nullOnDelete();

            $table->index('exam_resit_session_id', 'exam_resit_attempt_session_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_resit_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_resit_attempt_session_idx');
            $table->dropForeign(['exam_resit_session_id']);
            $table->dropForeign(['scheduled_by_user_id']);
            $table->dropColumn(['exam_resit_session_id', 'scheduled_at', 'scheduled_by_user_id']);
        });

        Schema::dropIfExists('exam_room_slot_invigilators');
        Schema::dropIfExists('exam_resit_sessions');
        Schema::dropIfExists('exam_room_slots');
    }
};
