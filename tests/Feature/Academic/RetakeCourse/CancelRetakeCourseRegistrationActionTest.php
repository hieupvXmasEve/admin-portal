<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\CancelRetakeCourseRegistrationAction;
use App\Modules\Academic\Actions\CreateRetakeCourseRegistrationAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeSimpleAction;
use App\Modules\Finance\Actions\ProcessFinanceCancellationOperationAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Jobs\DispatchFinanceCancellationCompletionJob;
use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\PaymentApplication;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createCancelTestRegistration(string $status = 'approved', array $overrides = []): CourseRetakeRegistration
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
    $user = User::factory()->create();

    return CourseRetakeRegistration::create(array_merge([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => $status,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ], $overrides));
}

function createPaidRetakeDngForCharge(CourseRetakeRegistration $registration, FinanceCharge $charge): DngPaymentRequest
{
    $dng = DngPaymentRequest::create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'description' => 'Retake fee',
        'semester_id' => $charge->semester_id,
        'due_date' => now()->addDays(5),
        'item_id' => 'HL-'.$charge->id,
        'amount' => $charge->amount,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'DNG-HL-PAID-'.$charge->id,
        'paid_at' => now(),
        'finance_charge_id' => null,
    ]);

    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dng->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    return $dng;
}

function settleRetakeFinanceCancellation(CourseRetakeRegistration $registration): void
{
    $operation = FinanceCancellationOperation::query()
        ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
        ->where('source_ref', AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration))
        ->firstOrFail();

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->id);

    $outbox = FinanceCancellationCompletionOutbox::query()
        ->where('finance_cancellation_operation_id', $operation->id)
        ->first();

    if ($outbox !== null && $outbox->status !== FinanceCancellationCompletionOutbox::STATUS_DISPATCHED) {
        app(DispatchFinanceCancellationCompletionJob::class, ['outboxId' => $outbox->id])
            ->handle(app(FinanceCancellationCompletionContract::class));
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('requests finance cancellation and keeps the source pending until completion', function () {
    Queue::fake();
    $reg = createCancelTestRegistration('approved');

    $result = CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Student request',
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION)
        ->and($result->cancellation_reason)->toBe('Student request')
        ->and($result->cancelled_by_user_id)->toBe($this->user->id);

    settleRetakeFinanceCancellation($reg);

    expect($reg->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('cancels a target-path retake registration through the finance cancellation operation', function () {
    Queue::fake();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    $registration = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
    ]);
    $obligation = FinanceObligation::query()
        ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
        ->where('source_ref', AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration))
        ->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();

    $result = CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $registration->id,
        'reason' => 'Student request',
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION);

    settleRetakeFinanceCancellation($registration);
    $registration->refresh();

    expect($registration->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED)
        ->and($registration->hq_fee_status)->toBe(CourseRetakeRegistration::HQ_FEE_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->fresh()->void_reason)->toBe('retake_course_cancelled')
        ->and($obligation->fresh()->lifecycle_status)->toBe(FinanceObligation::STATUS_VOIDED);
});

it('cancels a payment_pending registration and voids charge after finance completion', function () {
    Queue::fake();
    $reg = createCancelTestRegistration('approved');
    $charge = FinanceCharge::create([
        'student_id' => $reg->student_id,
        'semester_id' => $reg->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class,
        'source_id' => $reg->id,
    ]);

    $reg->update([
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'finance_charge_id' => $charge->id,
    ]);

    $result = CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Fee issue',
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION);

    settleRetakeFinanceCancellation($reg);

    expect($reg->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('bridges linked paid dng evidence before cancelling a payment_pending registration', function () {
    Queue::fake();
    $reg = createCancelTestRegistration('approved');
    app(CreateRetakeCourseChargeSimpleAction::class)->handle([
        'registration_id' => $reg->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
    ]);
    $reg->refresh();
    $charge = FinanceCharge::findOrFail($reg->finance_charge_id);
    $paidDng = createPaidRetakeDngForCharge($reg, $charge);

    expect($charge->is_fully_paid)->toBeFalse();

    $result = CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Student request after DNG paid',
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION);

    settleRetakeFinanceCancellation($reg);

    $reg->refresh();
    $charge->refresh();
    $paidDng->refresh();

    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED)
        ->and($reg->hq_fee_status)->toBe(CourseRetakeRegistration::HQ_FEE_PAID)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->void_reason)->toBe('retake_course_cancelled_paid_no_refund')
        ->and($paidDng->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED)
        ->and($paidDng->payment_id)->not->toBeNull();

    $payment = $paidDng->payment()->firstOrFail();
    expect((float) PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe(0.0)
        ->and((float) $payment->unapplied_amount)->toBe((float) $charge->amount);
});

it('throws when cancelling a paid registration', function () {
    $reg = createCancelTestRegistration('paid');

    CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Should fail',
    ]);
})->throws(RuntimeException::class);

it('throws when cancelling an enrolled registration', function () {
    $reg = createCancelTestRegistration('enrolled');

    CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Should fail',
    ]);
})->throws(RuntimeException::class);
