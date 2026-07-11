<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\CompleteFinanceCancellationOperationAction;
use App\Modules\Academic\Actions\RecoverAcademicFinanceCancellationHandoffsAction;
use App\Modules\Academic\Jobs\DispatchAcademicFinanceCancellationHandoffJob;
use App\Modules\Academic\Models\AcademicFinanceCancellationHandoff;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeSimpleAction;
use App\Modules\Finance\Actions\ProcessFinanceCancellationOperationAction;
use App\Modules\Finance\Actions\RecoverFinanceCancellationWorkAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Jobs\DispatchFinanceCancellationCompletionJob;
use App\Modules\Finance\Jobs\ProcessFinanceCancellationOperationJob;
use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\DTO\FinanceCancellationCompletionData;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use App\Shared\Contracts\Finance\FinanceCancellationOperationRequestContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function cancellationOperationRetake(): CourseRetakeRegistration
{
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

    return CourseRetakeRegistration::query()->create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'approved_by_user_id' => User::factory()->create()->id,
        'approved_at' => now(),
        'cancellation_reason' => 'Student withdrew',
    ]);
}

function requestCancellationOperation(
    CourseRetakeRegistration $registration,
    array $payload = [],
): object {
    return app(FinanceCancellationOperationRequestContract::class)->request(
        AcademicFinanceObligationSource::SOURCE_SYSTEM,
        AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        AcademicFinanceObligationSource::RETAKE_FEE,
        'retake_course_cancelled',
        'retake_course_cancelled_paid_no_refund',
        null,
        array_merge(['reason' => 'Student withdrew'], $payload),
    );
}

function dispatchCancellationCompletion(int $operationId): void
{
    $outboxes = FinanceCancellationCompletionOutbox::query()
        ->where('finance_cancellation_operation_id', $operationId)
        ->where('status', FinanceCancellationCompletionOutbox::STATUS_PENDING)
        ->orderBy('event_version')
        ->get();

    expect($outboxes)->not->toBeEmpty();

    foreach ($outboxes as $outbox) {
        app(DispatchFinanceCancellationCompletionJob::class, ['outboxId' => $outbox->id])
            ->handle(app(FinanceCancellationCompletionContract::class));
    }
}

it('completes a no-request cancellation only through the durable completion outbox', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $operation = requestCancellationOperation($registration);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $storedOperation = FinanceCancellationOperation::query()->findOrFail($operation->operationId);

    expect($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION)
        ->and($storedOperation->status)->toBe(FinanceCancellationOperation::STATUS_COMPLETED)
        ->and($storedOperation->result_payload['fee_disposition'] ?? null)
        ->toBe(ProcessFinanceCancellationOperationAction::FEE_DISPOSITION_NO_CHARGE);

    $outbox = FinanceCancellationCompletionOutbox::query()->firstOrFail();
    $completion = new FinanceCancellationCompletionData(
        $storedOperation->source_system,
        $storedOperation->source_kind,
        $storedOperation->source_ref,
        $storedOperation->obligation_type,
        $storedOperation->result_payload ?? [],
    );
    app(CompleteFinanceCancellationOperationAction::class)->complete($completion);
    $outbox->update([
        'status' => FinanceCancellationCompletionOutbox::STATUS_DISPATCHED,
        'dispatched_at' => now(),
    ]);

    expect($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED)
        ->and($outbox->fresh()->status)->toBe(FinanceCancellationCompletionOutbox::STATUS_DISPATCHED);

    app(CompleteFinanceCancellationOperationAction::class)->complete($completion);

    expect($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('uses one source-keyed operation for a repeated cancellation request', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $request = app(FinanceCancellationOperationRequestContract::class);

    $first = $request->request(
        AcademicFinanceObligationSource::SOURCE_SYSTEM,
        AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        AcademicFinanceObligationSource::RETAKE_FEE,
        'retake_course_cancelled',
        'retake_course_cancelled_paid_no_refund',
        null,
        ['reason' => 'Student withdrew'],
    );
    $retry = $request->request(
        AcademicFinanceObligationSource::SOURCE_SYSTEM,
        AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        AcademicFinanceObligationSource::RETAKE_FEE,
        'retake_course_cancelled',
        'retake_course_cancelled_paid_no_refund',
        null,
        ['reason' => 'Duplicate retry'],
    );

    expect($retry->operationId)->toBe($first->operationId)
        ->and(FinanceCancellationOperation::query()->count())->toBe(1);
});

it('keeps the source pending when the provider cancellation outcome is unknown', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-unknown-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
        'push_payload' => [],
    ]);
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('buildInsertNewRecordPayload')->andReturn([]);
    $client->shouldReceive('cancelRecord')->once()->andThrow(new RuntimeException('timeout'));
    app()->instance(DngClient::class, $client);

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);
    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $stored = FinanceCancellationOperation::query()->findOrFail($operation->operationId);
    expect($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION)
        ->and($stored->status)
        ->toBe(FinanceCancellationOperation::STATUS_REQUIRES_REVIEW)
        ->and($stored->provider_attempts[(string) DngPaymentRequest::query()->firstOrFail()->id]['status'] ?? null)
        ->toBe('unknown')
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and(FinanceCancellationCompletionOutbox::query()->count())->toBe(0);
});

it('re-dispatches committed pending rows when their original enqueue was lost', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $handoff = AcademicFinanceCancellationHandoff::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:'.$registration->id,
        'obligation_type' => 'retake_fee',
        'unpaid_void_reason' => 'retake_course_cancelled',
        'paid_void_reason' => 'retake_course_cancelled_paid_no_refund',
        'payload' => ['reason' => 'Student withdrew'],
        'status' => AcademicFinanceCancellationHandoff::STATUS_PENDING,
    ]);
    $failedHandoff = AcademicFinanceCancellationHandoff::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'exam_resit_attempt',
        'source_ref' => 'exam-resit:failed-recovery:'.$registration->id,
        'obligation_type' => 'exam_resit_fee',
        'unpaid_void_reason' => 'exam_resit_cancelled',
        'paid_void_reason' => 'exam_resit_cancelled_paid_no_refund',
        'payload' => ['reason' => 'Retry failed handoff'],
        'status' => AcademicFinanceCancellationHandoff::STATUS_FAILED,
        'attempts' => 1,
        'last_error' => 'lost queue publish',
    ]);
    $operation = FinanceCancellationOperation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:recovery:'.$registration->id,
        'obligation_type' => 'retake_fee',
        'status' => FinanceCancellationOperation::STATUS_REQUESTED,
        'unpaid_void_reason' => 'retake_course_cancelled',
        'paid_void_reason' => 'retake_course_cancelled_paid_no_refund',
        'source_payload' => [],
    ]);
    $staleOperation = FinanceCancellationOperation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'exam_resit_attempt',
        'source_ref' => 'exam-resit:recovery:'.$registration->id,
        'obligation_type' => 'exam_resit_fee',
        'status' => FinanceCancellationOperation::STATUS_PROCESSING,
        'unpaid_void_reason' => 'exam_resit_cancelled',
        'paid_void_reason' => 'exam_resit_cancelled_paid_no_refund',
        'source_payload' => [],
        'processing_claimed_at' => now()->subMinutes(10),
    ]);
    $outbox = FinanceCancellationCompletionOutbox::query()->create([
        'finance_cancellation_operation_id' => $operation->id,
        'event_id' => 'recovery-completion-'.$operation->id,
        'event_kind' => FinanceCancellationCompletionOutbox::EVENT_KIND_COMPLETED,
        'event_version' => 1,
        'status' => FinanceCancellationCompletionOutbox::STATUS_PENDING,
    ]);

    RecoverAcademicFinanceCancellationHandoffsAction::run(['limit' => 100]);
    RecoverFinanceCancellationWorkAction::run(['limit' => 100]);

    Queue::assertPushed(DispatchAcademicFinanceCancellationHandoffJob::class, fn ($job) => $job->handoffId === $handoff->id);
    Queue::assertPushed(DispatchAcademicFinanceCancellationHandoffJob::class, fn ($job) => $job->handoffId === $failedHandoff->id);
    Queue::assertPushed(ProcessFinanceCancellationOperationJob::class, fn ($job) => $job->operationId === $operation->id);
    Queue::assertPushed(ProcessFinanceCancellationOperationJob::class, fn ($job) => $job->operationId === $staleOperation->id);
    Queue::assertPushed(DispatchFinanceCancellationCompletionJob::class, fn ($job) => $job->outboxId === $outbox->id);
});

it('voids only after an unattempted collection is cancelled locally', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $request = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-pending-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'finance_charge_id' => $charge->id,
    ]);
    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and(FinanceCancellationOperation::query()->findOrFail($operation->operationId)->status)
        ->toBe(FinanceCancellationOperation::STATUS_COMPLETED)
        ->and($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION);
});

it('voids after a pushed request receives provider-confirmed cancellation', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $request = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-pushed-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
        'push_payload' => [
            'StudentName' => 'Test',
            'Email' => 'test@example.com',
            'EstimateTime' => now()->toDateString(),
            'StudentAddress' => 'Hanoi',
        ],
    ]);
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('buildInsertNewRecordPayload')->andReturn(['Amount' => -1]);
    $client->shouldReceive('cancelRecord')->once()->andReturn(['Status' => 'OK']);
    app()->instance(DngClient::class, $client);

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and(FinanceCancellationOperation::query()->findOrFail($operation->operationId)->status)
        ->toBe(FinanceCancellationOperation::STATUS_COMPLETED)
        ->and($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION);
});

it('bridges paid DNG evidence before voiding the payable charge', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $registration = cancellationOperationRetake();
    $registration->update([
        'status' => CourseRetakeRegistration::STATUS_APPROVED,
        'retake_fee' => 500000,
    ]);
    app(CreateRetakeCourseChargeSimpleAction::class)->handle([
        'registration_id' => $registration->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
    ]);
    $registration->refresh();
    $registration->update(['status' => CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION]);
    $charge = FinanceCharge::query()->findOrFail($registration->finance_charge_id);

    $paidDng = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-paid-'.$registration->id,
        'amount' => $charge->amount,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'finance_charge_id' => null,
        'dng_payment_id' => 'DNG-PAID-'.$registration->id,
        'paid_at' => now(),
    ]);
    DngPaymentRequestCharge::query()->create([
        'dng_payment_request_id' => $paidDng->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    expect($charge->is_fully_paid)->toBeFalse();

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $stored = FinanceCancellationOperation::query()->findOrFail($operation->operationId);
    $charge->refresh();
    $paidDng->refresh();

    expect($stored->status)->toBe(FinanceCancellationOperation::STATUS_COMPLETED)
        ->and($stored->result_payload['fee_disposition'] ?? null)
        ->toBe(ProcessFinanceCancellationOperationAction::FEE_DISPOSITION_KEPT_PAID_NO_REFUND)
        ->and($stored->result_payload['is_paid'] ?? null)->toBeTrue()
        ->and($charge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->void_reason)->toBe('retake_course_cancelled_paid_no_refund')
        ->and($paidDng->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED)
        ->and($paidDng->payment_id)->not->toBeNull()
        ->and($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION);

    $payment = $paidDng->payment()->firstOrFail();
    expect((float) PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe(0.0)
        ->and((float) $payment->unapplied_amount)->toBe(500000.0);
});

it('replaces only the remaining targets after cancelling an aggregate request', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $voided = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Void target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $remaining = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 300000,
        'description' => 'Remaining target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:aggregate-remaining:'.$registration->id,
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 300000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test:v1',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $remaining->update(['finance_obligation_id' => $obligation->id]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-CANCEL-AGG-'.$registration->id,
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
        'subtotal' => 300000,
        'discount_total' => 0,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'currency' => 'VND',
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $remaining->id,
        'amount_snapshot' => 300000,
        'description_snapshot' => 'Remaining target',
        'status' => 'active',
    ]);
    $aggregate = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'aggregate-cancel-'.$registration->id,
        'amount' => 800000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'finance_charge_id' => $voided->id,
    ]);
    foreach ([[$voided, 500000], [$remaining, 300000]] as [$charge, $amount]) {
        DngPaymentRequestCharge::query()->create([
            'dng_payment_request_id' => $aggregate->id,
            'finance_charge_id' => $charge->id,
            'amount' => $amount,
        ]);
    }
    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $voided->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $replacement = DngPaymentRequest::query()
        ->where('item_id', $aggregate->item_id.'-replacement-'.$operation->operationId)
        ->firstOrFail();

    expect($aggregate->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($voided->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($replacement->amount)->toBe('300000.00')
        ->and($replacement->chargeLinks()->pluck('finance_charge_id')->all())->toBe([$remaining->id])
        ->and($remaining->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('claims the operation so a concurrent worker does not call the provider again', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-claim-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
        'push_payload' => [
            'StudentName' => 'Test',
            'Email' => 'test@example.com',
            'EstimateTime' => now()->toDateString(),
            'StudentAddress' => 'Hanoi',
        ],
    ]);
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('buildInsertNewRecordPayload')->andReturn(['Amount' => -1]);
    $client->shouldReceive('cancelRecord')->never();
    app()->instance(DngClient::class, $client);

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    // Simulate another worker already claiming the operation (fresh claim, not stale).
    FinanceCancellationOperation::query()->whereKey($operation->operationId)->update([
        'status' => FinanceCancellationOperation::STATUS_PROCESSING,
        'processing_claimed_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($result->status)->toBe(FinanceCancellationOperation::STATUS_PROCESSING)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('only invokes the provider once when the processor is re-entered after completion', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $request = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-once-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
        'push_payload' => [
            'StudentName' => 'Test',
            'Email' => 'test@example.com',
            'EstimateTime' => now()->toDateString(),
            'StudentAddress' => 'Hanoi',
        ],
    ]);
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('buildInsertNewRecordPayload')->andReturn(['Amount' => -1]);
    $client->shouldReceive('cancelRecord')->once()->andReturn(['Status' => 'OK']);
    app()->instance(DngClient::class, $client);

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);
    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG)
        ->and(FinanceCancellationOperation::query()->findOrFail($operation->operationId)->status)
        ->toBe(FinanceCancellationOperation::STATUS_COMPLETED);
});

it('upgrades a completed unpaid cancellation to paid disposition when late cash appears', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $stored = FinanceCancellationOperation::query()->findOrFail($operation->operationId);
    expect($stored->status)->toBe(FinanceCancellationOperation::STATUS_COMPLETED)
        ->and($stored->result_payload['fee_disposition'] ?? null)
        ->toBe(ProcessFinanceCancellationOperationAction::FEE_DISPOSITION_VOIDED_UNPAID_CHARGE)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);

    // Late verified cash after completion (payment/cancel race).
    $paidDng = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-late-paid-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'finance_charge_id' => null,
        'dng_payment_id' => 'DNG-LATE-'.$registration->id,
        'paid_at' => now(),
    ]);
    DngPaymentRequestCharge::query()->create([
        'dng_payment_request_id' => $paidDng->id,
        'finance_charge_id' => $charge->id,
        'amount' => 500000,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $upgraded = FinanceCancellationOperation::query()->findOrFail($operation->operationId);
    expect($upgraded->result_payload['fee_disposition'] ?? null)
        ->toBe(ProcessFinanceCancellationOperationAction::FEE_DISPOSITION_KEPT_PAID_NO_REFUND)
        ->and($upgraded->result_payload['is_paid'] ?? null)->toBeTrue()
        ->and($upgraded->result_payload['late_paid_disposition'] ?? null)->toBeTrue()
        ->and($paidDng->fresh()->payment_id)->not->toBeNull();

    dispatchCancellationCompletion($operation->operationId);

    expect($registration->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED)
        ->and($registration->fresh()->hq_fee_status)->toBe(CourseRetakeRegistration::HQ_FEE_PAID);
});

it('uses Settlement Position remaining when building aggregate replacement amounts', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $voided = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Void target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $obligation = FinanceObligation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:cancel-remaining:'.$registration->id,
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 300000,
        'currency' => 'VND',
        'pricing_rule_version' => 'retake_fee:test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $remaining = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 300000,
        'description' => 'Remaining target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'finance_obligation_id' => $obligation->id,
    ]);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-CANCEL-'.$registration->id,
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
        'subtotal' => 300000,
        'discount_total' => 0,
        'total_amount' => 300000,
        'paid_amount' => 100000,
        'currency' => 'VND',
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $remaining->id,
        'amount_snapshot' => 300000,
        'description_snapshot' => 'Remaining',
        'status' => 'active',
    ]);
    $payment = Payment::query()->create([
        'student_id' => $registration->student_id,
        'amount' => 100000,
        'method' => Payment::METHOD_CASH,
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'source' => 'cancellation-canonical-test',
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 100000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $aggregate = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'aggregate-cancel-canonical-'.$registration->id,
        'amount' => 800000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'finance_charge_id' => $voided->id,
    ]);
    foreach ([[$voided, 500000], [$remaining, 300000]] as [$charge, $amount]) {
        DngPaymentRequestCharge::query()->create([
            'dng_payment_request_id' => $aggregate->id,
            'finance_charge_id' => $charge->id,
            'amount' => $amount,
        ]);
    }

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $voided->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $replacement = DngPaymentRequest::query()
        ->where('item_id', $aggregate->item_id.'-replacement-'.$operation->operationId)
        ->firstOrFail();

    // Pivot still had 300000; canonical remaining after 100000 cash is 200000.
    expect($replacement->amount)->toBe('200000.00')
        ->and((string) $replacement->chargeLinks()->firstOrFail()->amount)->toBe('200000.00');
});

it('requires review when Settlement Position is invalid for aggregate remaining targets', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $voided = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Void target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    // Remaining has invoice lines but no obligation currency → SP invalid.
    $remaining = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 300000,
        'description' => 'Remaining target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-CANCEL-INVALID-'.$registration->id,
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
        'subtotal' => 300000,
        'discount_total' => 0,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'currency' => 'VND',
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $remaining->id,
        'amount_snapshot' => 300000,
        'description_snapshot' => 'Remaining',
        'status' => 'active',
    ]);
    $aggregate = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'aggregate-cancel-invalid-sp-'.$registration->id,
        'amount' => 800000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'finance_charge_id' => $voided->id,
    ]);
    foreach ([[$voided, 500000], [$remaining, 300000]] as [$charge, $amount]) {
        DngPaymentRequestCharge::query()->create([
            'dng_payment_request_id' => $aggregate->id,
            'finance_charge_id' => $charge->id,
            'amount' => $amount,
        ]);
    }

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $voided->id,
    ]);

    $result = app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($result->status)->toBe(FinanceCancellationOperation::STATUS_REQUIRES_REVIEW)
        ->and($voided->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and(DngPaymentRequest::query()
            ->where('item_id', $aggregate->item_id.'-replacement-'.$operation->operationId)
            ->exists())->toBeFalse();
});

it('requires review when an aggregate remaining target has no canonical payable line', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $voided = FinanceCharge::query()->create([
        'student_id' => $registration->student_id, 'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE, 'amount' => 500000,
        'description' => 'Void target', 'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $remaining = FinanceCharge::query()->create([
        'student_id' => $registration->student_id, 'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE, 'amount' => 300000,
        'description' => 'No canonical line', 'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $aggregate = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id, 'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id, 'fee_type' => 'HP',
        'item_id' => 'aggregate-cancel-no-line-'.$registration->id, 'amount' => 800000,
        'status' => DngPaymentRequest::STATUS_PENDING, 'finance_charge_id' => $voided->id,
    ]);
    foreach ([[$voided, 500000], [$remaining, 300000]] as [$charge, $amount]) {
        DngPaymentRequestCharge::query()->create([
            'dng_payment_request_id' => $aggregate->id,
            'finance_charge_id' => $charge->id,
            'amount' => $amount,
        ]);
    }
    $operation = requestCancellationOperation($registration, ['legacy_finance_charge_id' => $voided->id]);

    $result = app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($result->status)->toBe(FinanceCancellationOperation::STATUS_REQUIRES_REVIEW)
        ->and($voided->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and(DngPaymentRequest::query()
            ->where('item_id', $aggregate->item_id.'-replacement-'.$operation->operationId)
            ->exists())->toBeFalse();
});

it('refuses stale reclaim mid-flight without a second provider call', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $request = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-inflight-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
        'push_payload' => [
            'StudentName' => 'Test',
            'Email' => 'test@example.com',
            'EstimateTime' => now()->toDateString(),
            'StudentAddress' => 'Hanoi',
        ],
    ]);
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('buildInsertNewRecordPayload')->andReturn(['Amount' => -1]);
    $client->shouldReceive('cancelRecord')->never();
    app()->instance(DngClient::class, $client);

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);
    FinanceCancellationOperation::query()->whereKey($operation->operationId)->update([
        'status' => FinanceCancellationOperation::STATUS_PROCESSING,
        'processing_claimed_at' => now()->subMinutes(10),
        'provider_attempts' => [
            (string) $request->id => ['status' => 'in_flight', 'at' => now()->subMinutes(9)->toIso8601String()],
        ],
    ]);

    $result = app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    expect($result->status)->toBe(FinanceCancellationOperation::STATUS_REQUIRES_REVIEW)
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('appends a second completion outbox event on late paid disposition upgrade', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());
    $registration = cancellationOperationRetake();
    $charge = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $charge->id,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);
    expect(FinanceCancellationCompletionOutbox::query()->count())->toBe(1);

    $paidDng = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'cancel-operation-late-paid-outbox-'.$registration->id,
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'finance_charge_id' => null,
        'dng_payment_id' => 'DNG-LATE-OUTBOX-'.$registration->id,
        'paid_at' => now(),
    ]);
    DngPaymentRequestCharge::query()->create([
        'dng_payment_request_id' => $paidDng->id,
        'finance_charge_id' => $charge->id,
        'amount' => 500000,
    ]);

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId);

    $events = FinanceCancellationCompletionOutbox::query()
        ->where('finance_cancellation_operation_id', $operation->operationId)
        ->orderBy('event_version')
        ->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->event_kind)->toBe(FinanceCancellationCompletionOutbox::EVENT_KIND_COMPLETED)
        ->and($events[1]->event_kind)->toBe(FinanceCancellationCompletionOutbox::EVENT_KIND_PAID_DISPOSITION_UPGRADE)
        ->and($events[0]->event_id)->not->toBe($events[1]->event_id);
});

it('does not create a replacement when void fails after collection cancel', function () {
    Queue::fake();
    $registration = cancellationOperationRetake();
    $voided = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Void target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $remaining = FinanceCharge::query()->create([
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 300000,
        'description' => 'Remaining target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:void-failure-remaining:'.$registration->id,
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 300000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test:v1',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $remaining->update(['finance_obligation_id' => $obligation->id]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-CANCEL-VOID-FAIL-'.$registration->id,
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
        'subtotal' => 300000,
        'discount_total' => 0,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'currency' => 'VND',
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $remaining->id,
        'amount_snapshot' => 300000,
        'description_snapshot' => 'Remaining target',
        'status' => 'active',
    ]);
    $aggregate = DngPaymentRequest::query()->create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'aggregate-cancel-atomic-'.$registration->id,
        'amount' => 800000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'finance_charge_id' => $voided->id,
    ]);
    foreach ([[$voided, 500000], [$remaining, 300000]] as [$charge, $amount]) {
        DngPaymentRequestCharge::query()->create([
            'dng_payment_request_id' => $aggregate->id,
            'finance_charge_id' => $charge->id,
            'amount' => $amount,
        ]);
    }

    $void = Mockery::mock(VoidFinanceChargeAction::class);
    $void->shouldReceive('handle')->once()->andThrow(new RuntimeException('void failed'));
    app()->instance(VoidFinanceChargeAction::class, $void);

    $operation = requestCancellationOperation($registration, [
        'legacy_finance_charge_id' => $voided->id,
    ]);

    expect(fn () => app(ProcessFinanceCancellationOperationAction::class)->handle($operation->operationId))
        ->toThrow(RuntimeException::class, 'void failed');

    expect(DngPaymentRequest::query()
        ->where('item_id', $aggregate->item_id.'-replacement-'.$operation->operationId)
        ->exists())->toBeFalse()
        ->and($aggregate->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
});
