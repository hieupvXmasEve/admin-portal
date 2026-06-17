<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_resit_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_record_id')->constrained('academic_records')->cascadeOnDelete();
            $table->foreignId('original_course_offering_id')->nullable()->constrained('course_offerings')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('syllabus_template_id')->nullable()->constrained('syllabus_templates')->nullOnDelete();
            $table->foreignId('original_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('operation_semester_id')->constrained('semesters')->restrictOnDelete();
            $table->foreignId('charge_semester_id')->constrained('semesters')->restrictOnDelete();

            $table->string('request_origin', 20)->default('staff');
            $table->foreignId('requested_by_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('status', 40)->default('approved');
            $table->unsignedInteger('request_sequence')->default(1);
            $table->unsignedInteger('attempt_number')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('hq_fee_status', 40)->default('hq_fee_pending');
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->foreignId('finance_charge_id')->nullable()->constrained('finance_charges')->nullOnDelete();
            $table->timestamp('charge_created_at')->nullable();
            $table->foreignId('charge_created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamp('payment_overdue_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();

            $table->json('policy_snapshot')->nullable();
            $table->unsignedInteger('max_attempts_snapshot')->default(1);
            $table->decimal('exam_resit_fee_snapshot', 15, 2)->default(0);
            $table->unsignedInteger('late_payment_grace_days_snapshot')->default(14);
            $table->boolean('allow_unpaid_sitting_snapshot')->default(false);

            $table->text('unpaid_allowed_reason')->nullable();
            $table->foreignId('unpaid_allowed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unpaid_allowed_at')->nullable();

            $table->decimal('resit_score', 5, 2)->nullable();
            $table->string('resit_grade', 10)->nullable();
            $table->boolean('resit_passed')->nullable();
            $table->json('previous_result_snapshot')->nullable();
            $table->decimal('final_chosen_score', 5, 2)->nullable();
            $table->json('result_snapshot')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'unit_id', 'status'], 'exam_resit_student_unit_status_idx');
            $table->index(['academic_record_id', 'status'], 'exam_resit_record_status_idx');
            $table->index(['finance_charge_id']);
            $table->index(['operation_semester_id', 'campus_id', 'status'], 'exam_resit_ops_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_resit_attempts');
    }
};
