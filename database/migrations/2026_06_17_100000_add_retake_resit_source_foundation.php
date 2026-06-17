<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            $table->string('failure_reason', 40)->nullable()->after('is_passed');
            $table->json('failure_reason_snapshot')->nullable()->after('failure_reason');
            $table->index('failure_reason');
        });

        Schema::table('course_retake_registrations', function (Blueprint $table) {
            $table->foreignId('original_semester_id')->nullable()->after('campus_id')->constrained('semesters')->nullOnDelete();
            $table->foreignId('operation_semester_id')->nullable()->after('original_semester_id')->constrained('semesters')->nullOnDelete();
            $table->foreignId('charge_semester_id')->nullable()->after('operation_semester_id')->constrained('semesters')->nullOnDelete();
            $table->string('request_origin', 20)->default('staff')->after('status');
            $table->foreignId('requested_by_student_id')->nullable()->after('request_origin')->constrained('students')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->after('requested_by_student_id')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('requested_by_user_id');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->timestamp('rejected_at')->nullable()->after('reviewed_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->string('hq_fee_status', 40)->default('hq_fee_pending')->after('retake_fee');
            $table->json('policy_snapshot')->nullable()->after('hq_fee_status');
            $table->timestamp('first_class_session_at')->nullable()->after('payment_deadline');
            $table->timestamp('payment_overdue_at')->nullable()->after('first_class_session_at');
            $table->timestamp('last_reminded_at')->nullable()->after('payment_overdue_at');
        });

        DB::statement('ALTER TABLE course_retake_registrations MODIFY course_offering_id BIGINT UNSIGNED NULL');

        Schema::table('finance_charges', function (Blueprint $table) {
            $table->string('active_source_key', 255)->nullable()->after('source_id');
            $table->unique('active_source_key', 'finance_charges_active_source_key_unique');
        });

        Schema::table('syllabus_templates', function (Blueprint $table) {
            $table->unsignedInteger('exam_resit_max_attempts')->default(1)->after('min_grade_threshold');
            $table->decimal('exam_resit_fee', 15, 2)->nullable()->after('exam_resit_max_attempts');
            $table->unsignedInteger('exam_resit_registration_window_days')->nullable()->after('exam_resit_fee');
            $table->unsignedInteger('exam_resit_late_payment_grace_days')->default(14)->after('exam_resit_registration_window_days');
            $table->boolean('exam_resit_allow_unpaid_sitting')->default(false)->after('exam_resit_late_payment_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('syllabus_templates', function (Blueprint $table) {
            $table->dropColumn([
                'exam_resit_max_attempts',
                'exam_resit_fee',
                'exam_resit_registration_window_days',
                'exam_resit_late_payment_grace_days',
                'exam_resit_allow_unpaid_sitting',
            ]);
        });

        Schema::table('finance_charges', function (Blueprint $table) {
            $table->dropUnique('finance_charges_active_source_key_unique');
            $table->dropColumn('active_source_key');
        });

        DB::statement('ALTER TABLE course_retake_registrations MODIFY course_offering_id BIGINT UNSIGNED NOT NULL');

        Schema::table('course_retake_registrations', function (Blueprint $table) {
            $table->dropForeign(['original_semester_id']);
            $table->dropForeign(['operation_semester_id']);
            $table->dropForeign(['charge_semester_id']);
            $table->dropForeign(['requested_by_student_id']);
            $table->dropForeign(['requested_by_user_id']);
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropColumn([
                'original_semester_id',
                'operation_semester_id',
                'charge_semester_id',
                'request_origin',
                'requested_by_student_id',
                'requested_by_user_id',
                'requested_at',
                'reviewed_by_user_id',
                'reviewed_at',
                'rejected_at',
                'rejection_reason',
                'hq_fee_status',
                'policy_snapshot',
                'first_class_session_at',
                'payment_overdue_at',
                'last_reminded_at',
            ]);
        });

        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex(['failure_reason']);
            $table->dropColumn(['failure_reason', 'failure_reason_snapshot']);
        });
    }
};
