<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('recorded_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            $table->enum('status', [
                'present',
                'absent',
                'late',
                'excused',
                'partial', // For sessions where student left early or arrived very late
                'medical_leave',
                'official_leave',
            ])->default('absent');

            // Timing details
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->integer('minutes_late')->default(0);
            $table->integer('minutes_present')->nullable(); // How long the student was actually present

            // Method of attendance recording
            $table->enum('recording_method', [
                'manual',
                'qr_code',
                'rfid',
                'biometric',
                'mobile_app',
                'online_participation',
                'auto_system',
            ])->default('manual');

            // Additional details
            $table->text('notes')->nullable(); // Reason for absence, late arrival explanation, etc.
            $table->text('excuse_reason')->nullable(); // Medical certificate, family emergency, etc.
            $table->string('excuse_document_path', 500)->nullable(); // Path to uploaded excuse document

            // Participation and engagement (optional)
            $table->enum('participation_level', [
                'excellent',
                'good',
                'average',
                'poor',
                'none',
            ])->nullable();

            $table->decimal('participation_score', 3, 1)->nullable(); // Out of 10
            $table->text('participation_notes')->nullable();

            // Administrative fields
            $table->boolean('is_verified')->default(false); // Has attendance been verified by instructor
            $table->boolean('affects_grade')->default(true); // Does this attendance count towards grade
            $table->boolean('is_makeup_allowed')->default(false); // Can student make up for this absence

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            // For bulk operations and auditing
            $table->string('batch_id', 50)->nullable(); // For grouping bulk attendance updates
            $table->json('device_info')->nullable(); // Information about device used for check-in
            $table->string('ip_address', 45)->nullable(); // IP address for digital check-ins
            $table->decimal('latitude', 10, 8)->nullable(); // GPS latitude for mobile check-ins
            $table->decimal('longitude', 11, 8)->nullable(); // GPS longitude for mobile check-ins

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance and fast querying
            $table->index(['class_session_id', 'status'], 'session_attendance_status_idx');
            $table->index(['student_id', 'status'], 'student_attendance_status_idx');
            $table->index(['class_session_id', 'student_id'], 'session_student_idx');
            $table->index(['recorded_by_lecture_id', 'created_at']);
            $table->index(['recording_method', 'created_at']);
            $table->index(['status', 'affects_grade']);
            $table->index(['is_verified', 'verified_at']);
            $table->index(['batch_id']);
            $table->index(['check_in_time', 'check_out_time']);

            // Unique constraint to prevent duplicate attendance records
            $table->unique(['class_session_id', 'student_id'], 'unique_session_student_attendance');

            // Index for GPA and transcript calculations
            $table->index(['student_id', 'status', 'affects_grade', 'created_at'], 'student_grade_attendance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
