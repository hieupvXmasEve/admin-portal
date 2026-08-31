<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CancelExamResitAttemptAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\ProcessFinanceCancellationOperationAction;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Shared\Contracts\Finance\Enums\FinanceCancellationFeeDisposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('audit-baseline');

it('uses typed fee_outcome over mismatched paid_void_reason text (P-04)', function () {
    Queue::fake();

    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
    ]);
    $registration = CourseRetakeRegistration::query()->create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
        'cancellation_reason' => 'Student withdrew',
    ]);

    $obligation = FinanceObligation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 500000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['test' => true],
        'accepted_at' => now(),
    ]);
    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'created_by_user_id' => $user->id,
    ]);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->where('status', 'active')->firstOrFail();
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 500000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 500000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $operation = FinanceCancellationOperation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'status' => FinanceCancellationOperation::STATUS_REQUESTED,
        'unpaid_void_reason' => 'retake_course_cancelled',
        'paid_void_reason' => CancelExamResitAttemptAction::PAID_VOID_REASON_FORFEIT,
        'actor_user_id' => $user->id,
        'source_payload' => [
            'reason' => 'Student withdrew',
            'fee_outcome' => CancelExamResitAttemptAction::FEE_OUTCOME_KEEP_FOR_LATER,
        ],
    ]);

    $processed = app(ProcessFinanceCancellationOperationAction::class)->handle($operation->id);

    expect($processed->result_payload['fee_disposition'] ?? null)
        ->toBe(FinanceCancellationFeeDisposition::PaidReleaseToBalance->value);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('falls back to paid_void_reason when typed fee_outcome is missing (legacy handoff)', function () {
    Queue::fake();

    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
    ]);
    $registration = CourseRetakeRegistration::query()->create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
        'cancellation_reason' => 'Student withdrew',
    ]);

    $obligation = FinanceObligation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 500000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['test' => true],
        'accepted_at' => now(),
    ]);
    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'created_by_user_id' => $user->id,
    ]);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->where('status', 'active')->firstOrFail();
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 500000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 500000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $operation = FinanceCancellationOperation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'status' => FinanceCancellationOperation::STATUS_REQUESTED,
        'unpaid_void_reason' => 'retake_course_cancelled',
        'paid_void_reason' => CancelExamResitAttemptAction::PAID_VOID_REASON_KEEP_FOR_LATER,
        'actor_user_id' => $user->id,
        'source_payload' => [
            'reason' => 'Student withdrew',
        ],
    ]);

    $processed = app(ProcessFinanceCancellationOperationAction::class)->handle($operation->id);

    expect($processed->result_payload['fee_disposition'] ?? null)
        ->toBe(FinanceCancellationFeeDisposition::PaidReleaseToBalance->value);
});
