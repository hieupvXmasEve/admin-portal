<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CaptureDngProviderReceiptAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class)->group('audit-baseline');

function auditBaselineRetakeWithDngRequest(float $amount = 5_000_000): array
{
    $user = User::factory()->create();
    $campus = Campus::factory()->create(['code' => 'CAMPUS-AUDIT-P01']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'student_id' => 'STU-AUDIT-P01',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'max_capacity' => 50,
        'current_enrollment' => 0,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
    $registration = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
        'attempt_number' => 2,
        'retake_fee' => $amount,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
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
        'amount' => $amount,
        'description' => 'Retake fee',
        'created_by_user_id' => $user->id,
    ]);
    $request = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'ITEM-AUDIT-P01',
        'amount' => $amount,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-AUDIT-P01',
    ]);
    DngPaymentRequestCharge::query()->create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $charge->id,
        'amount' => $amount,
    ]);

    return compact('registration', 'charge', 'request', 'student', 'campus');
}

it('marks Academic retake paid after DngReconciliationService settles without a webhook (P-01)', function () {
    [
        'registration' => $registration,
        'request' => $request,
        'campus' => $campus,
        'student' => $student,
    ] = auditBaselineRetakeWithDngRequest();

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with($campus->code, '2026-09-01 21:00:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY-AUDIT-P01',
                'StudentId' => $student->student_id,
                'Amount' => (string) $request->amount,
                'ItemId' => $request->item_id,
            ]],
        ]);

    $service = new DngReconciliationService(
        $mockClient,
        app(DngPaymentService::class),
        app(SettleInstallmentFromDngAction::class),
    );

    $summary = $service->reconcileDay($campus->code, '2026-09-01 21:00:00');

    expect($summary['backfilled'])->toBe(1)
        ->and($summary['errors'])->toBe(0);

    $registration->refresh();

    expect($registration->hq_fee_status)->toBe(CourseRetakeRegistration::HQ_FEE_PAID)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_PAID);
});

it('flips exam-resit hq_fee_status after CaptureDngProviderReceiptAction books a receipt (P-01)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $campus = Campus::factory()->create(['code' => 'CAMPUS-AUDIT-P01B']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'student_id' => 'STU-AUDIT-P01B',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $attempt = makeApprovedExamResitAttempt($student, $campus, $semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt = $attempt->fresh();
    $obligationId = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->where('obligation_type', AcademicFinanceObligationSource::EXAM_RESIT_FEE)
        ->value('id');
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligationId)->firstOrFail();

    $request = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'PTL',
        'item_id' => 'ITEM-AUDIT-P01B',
        'amount' => $charge->amount,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-AUDIT-P01B',
    ]);
    DngPaymentRequestCharge::query()->create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    CaptureDngProviderReceiptAction::run([
        'request' => $request,
        'receipt' => [
            'amount' => $charge->amount,
            'payload' => [
                'PaymentId' => 'PAY-AUDIT-P01B',
                'StudentId' => $student->student_id,
                'Amount' => (string) $charge->amount,
                'ItemId' => 'ITEM-AUDIT-P01B',
            ],
            'source' => 'daily_reconciliation',
            'authenticity' => ['status' => 'provider_authenticated_query'],
            'payer_correlation' => ['status' => 'matched'],
            'target_validation' => ['status' => 'matched', 'issues' => []],
        ],
    ]);

    $attempt->refresh();

    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull();
});

it('does not call Academic payment syncers when reconciling a non-retake fee (P-01 guard)', function () {
    $campus = Campus::factory()->create(['code' => 'CAMPUS-AUDIT-P01G']);
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'student_id' => 'STU-AUDIT-P01G',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'ITEM-AUDIT-P01G',
        'amount' => 5_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-AUDIT-P01G',
    ]);

    $retakeSyncer = Mockery::mock(RetakeRegistrationPaymentSyncer::class);
    $retakeSyncer->shouldNotReceive('runForStudent');
    app()->instance(RetakeRegistrationPaymentSyncer::class, $retakeSyncer);

    $resitSyncer = Mockery::mock(ExamResitAttemptPaymentSyncer::class);
    $resitSyncer->shouldNotReceive('runForStudent');
    app()->instance(ExamResitAttemptPaymentSyncer::class, $resitSyncer);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with($campus->code, '2026-09-01 21:00:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY-AUDIT-P01G',
                'StudentId' => $student->student_id,
                'Amount' => '5000000',
                'ItemId' => 'ITEM-AUDIT-P01G',
            ]],
        ]);

    $service = new DngReconciliationService(
        $mockClient,
        app(DngPaymentService::class),
        app(SettleInstallmentFromDngAction::class),
    );

    $summary = $service->reconcileDay($campus->code, '2026-09-01 21:00:00');

    expect($summary['backfilled'])->toBe(1)
        ->and($summary['errors'])->toBe(0);
});
