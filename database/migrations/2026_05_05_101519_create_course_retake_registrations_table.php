<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_retake_registrations', function (Blueprint $table) {
            $table->id();

            // Core references
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('original_academic_record_id')->constrained('academic_records')->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();

            // State machine
            $table->enum('status', [
                'approved',
                'payment_pending',
                'paid',
                'enrolled',
                'cancelled',
            ])->default('approved');

            // Academic
            $table->unsignedInteger('attempt_number')->default(2);
            $table->decimal('retake_fee', 15, 2)->default(0);

            // Registration period
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();
            $table->date('payment_deadline')->nullable();

            // Approval audit
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Finance audit
            $table->unsignedBigInteger('finance_charge_id')->nullable();
            $table->foreignId('charge_created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('charge_created_at')->nullable();

            // Payment audit
            $table->timestamp('paid_at')->nullable();

            // Enrollment audit
            $table->unsignedBigInteger('course_registration_id')->nullable();
            $table->timestamp('enrolled_at')->nullable();

            // Cancellation audit
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign keys (non-constrained to avoid circular deps)
            $table->foreign('finance_charge_id')->references('id')->on('finance_charges')->nullOnDelete();
            $table->foreign('course_registration_id')->references('id')->on('course_registrations')->nullOnDelete();

            // Partial unique index: only 1 active (non-terminal) registration per student+unit+semester
            // MySQL doesn't support partial indexes, so we use a generated column approach
            // Instead, we enforce this in application logic (Action soft check)
            $table->index(['student_id', 'unit_id', 'semester_id', 'status'], 'ctr_student_unit_semester_status_idx');

            // Query indexes
            $table->index('status');
            $table->index('semester_id');
            $table->index('campus_id');
            $table->index('finance_charge_id');
            $table->index('course_registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_retake_registrations');
    }
};
