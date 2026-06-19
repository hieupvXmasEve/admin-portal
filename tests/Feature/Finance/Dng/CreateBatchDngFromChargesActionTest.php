<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeSimpleAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeBatchStudent(string $code, Campus $campus, Semester $semester): Student
{
    $program = Program::factory()->create();
    $cv = CurriculumVersion::factory()->forProgram($program)->withEffectiveSemester($semester)->create();

    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'curriculum_version_id' => $cv->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

function makeCharge(Student $student, Semester $semester, string $type, float $amount): FinanceCharge
{
    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $type,
        'amount' => $amount,
        'description' => "Charge {$type}",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}

/**
 * Build the action with mocked DNG service, real cancel + simple-charge actions.
 */
function makeBatchAction(?DngPaymentRequest $fakeCreatedRequest = null, bool $dngFails = false): array
{
    $dngPaymentServiceMock = Mockery::mock(DngPaymentService::class);

    if ($dngFails) {
        $dngPaymentServiceMock->shouldReceive('createAndPush')
            ->andThrow(new RuntimeException('DNG API failed'));
    } else {
        $dngPaymentServiceMock->shouldReceive('createAndPush')
            ->andReturnUsing(function (Student $student, array $data) use ($fakeCreatedRequest) {
                if ($fakeCreatedRequest) {
                    return $fakeCreatedRequest;
                }

                // Create a real DB record mimicking what the service would create
                return DngPaymentRequest::create([
                    'student_id' => $student->id,
                    'campus_code' => $data['campus_code'],
                    'student_code' => $data['student_code'],
                    'fee_type' => $data['fee_type'],
                    'item_id' => $data['item_id'],
                    'amount' => $data['amount'],
                    'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                    'description' => $data['description'] ?? null,
                    'semester_id' => $data['semester_id'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                ]);
            });
    }

    $campusResolverMock = Mockery::mock(DngCampusCodeResolver::class);
    $campusResolverMock->shouldReceive('requireForStudent')->andReturn('FAUHN');

    $cancelAction = app(CancelDngPaymentRequestAction::class);
    $simpleChargeAction = app(CreateRetakeCourseChargeSimpleAction::class);
    $examResitChargeAction = app(CreateExamResitChargeSimpleAction::class);

    $action = new CreateBatchDngFromChargesAction(
        $dngPaymentServiceMock,
        $campusResolverMock,
        $cancelAction,
        $simpleChargeAction,
        $examResitChargeAction,
    );

    return [$action, $dngPaymentServiceMock];
}

function batchPayload(array $studentIds, Semester $semester, string $feeType = 'HP'): array
{
    return [
        'student_ids' => $studentIds,
        'dng_fee_type' => $feeType,
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'Test DNG batch',
        'estimate_time' => '05/26',
        'amount_overrides' => null,
    ];
}

// ─── Tests ──────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->campus = Campus::factory()->create(['dng_code' => 'FAUHN']);
    $this->semester = Semester::factory()->active()->create();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates DNG and pivot rows for a single student', function () {
    $student = makeBatchStudent('BATCH001', $this->campus, $this->semester);
    $charge = makeCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);

    [$action] = makeBatchAction();

    $result = $action->handle(batchPayload([$student->id], $this->semester));

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $dng = DngPaymentRequest::where('student_id', $student->id)->first();
    expect($dng)->not->toBeNull()
        ->and((float) $dng->amount)->toBe(10_000_000.0);

    $pivots = DngPaymentRequestCharge::where('dng_payment_request_id', $dng->id)->get();
    expect($pivots)->toHaveCount(1)
        ->and($pivots->first()->finance_charge_id)->toBe($charge->id);
});

it('creates DNG for multiple students in a batch', function () {
    $s1 = makeBatchStudent('MULTI001', $this->campus, $this->semester);
    $s2 = makeBatchStudent('MULTI002', $this->campus, $this->semester);

    makeCharge($s1, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeCharge($s2, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 7_000_000);

    [$action] = makeBatchAction();

    $result = $action->handle(batchPayload([$s1->id, $s2->id], $this->semester));

    expect($result['created'])->toBe(2)
        ->and($result['failed'])->toBe(0);
    expect(DngPaymentRequest::whereIn('student_id', [$s1->id, $s2->id])->count())->toBe(2);
});

it('auto-cancels existing active DNG before creating new one', function () {
    $student = makeBatchStudent('CANCEL001', $this->campus, $this->semester);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);

    // Existing pending DNG (no DNG API needed — we can use STATUS_PENDING so cancel is local-only)
    $oldDng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'CANCEL001',
        'fee_type' => 'HP',
        'item_id' => 'OLD-ITEM',
        'amount' => 8_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    [$action] = makeBatchAction();

    $result = $action->handle(batchPayload([$student->id], $this->semester));

    expect($result['created'])->toBe(1)
        ->and($result['cancelled_old'])->toBe(1);

    expect(DngPaymentRequest::find($oldDng->id)->status)
        ->toBe(DngPaymentRequest::STATUS_CANCELLED);
});

it('uses amount override when provided', function () {
    $student = makeBatchStudent('OVERRIDE001', $this->campus, $this->semester);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);

    [$action] = makeBatchAction();

    $payload = batchPayload([$student->id], $this->semester);
    $payload['amount_overrides'] = [$student->id => 6_000_000.0];

    $result = $action->handle($payload);

    expect($result['created'])->toBe(1);

    $dng = DngPaymentRequest::where('student_id', $student->id)->first();
    expect((float) $dng->amount)->toBe(6_000_000.0);
});

it('fails gracefully per student without aborting the batch', function () {
    $sGood = makeBatchStudent('GRACE001', $this->campus, $this->semester);
    $sBad = makeBatchStudent('GRACE002', $this->campus, $this->semester);

    makeCharge($sGood, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    // sBad has no charges → action will throw "no charges found"

    [$action] = makeBatchAction();

    $result = $action->handle(batchPayload([$sGood->id, $sBad->id], $this->semester));

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1);
});

it('creates proportional pivot amounts when charge amount is overridden', function () {
    $student = makeBatchStudent('PROP001', $this->campus, $this->semester);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 6_000_000);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, 4_000_000);

    [$action] = makeBatchAction();

    $payload = batchPayload([$student->id], $this->semester);
    $payload['amount_overrides'] = [$student->id => 8_000_000.0]; // override from 10M to 8M

    $action->handle($payload);

    $dng = DngPaymentRequest::where('student_id', $student->id)->first();
    $pivots = DngPaymentRequestCharge::where('dng_payment_request_id', $dng->id)->get();

    expect($pivots)->toHaveCount(2);
    expect($pivots->sum('amount'))->toBe(8_000_000.0);
});

it('keeps pivot sum exactly equal to the request amount despite rounding (FIN-10)', function () {
    // Three equal balances + an override that does not divide evenly forces a
    // rounding remainder; the pivot sum must still equal the request amount.
    $student = makeBatchStudent('ROUND01', $this->campus, $this->semester);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 1_000_000);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 1_000_000);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 1_000_000);

    [$action] = makeBatchAction();

    $payload = batchPayload([$student->id], $this->semester, 'HL');
    // 10,000,000 / 3 = 3,333,333.33… → rounding must not drift the total.
    $payload['amount_overrides'] = [$student->id => 10_000_000.0];

    $action->handle($payload);

    $dng = DngPaymentRequest::where('student_id', $student->id)->first();
    $pivots = DngPaymentRequestCharge::where('dng_payment_request_id', $dng->id)->get();

    expect($pivots)->toHaveCount(3)
        ->and($pivots->sum('amount'))->toBe(10_000_000.0)
        ->and((float) $dng->amount)->toBe(10_000_000.0);
});

it('fails if DNG service throws, and records error', function () {
    $student = makeBatchStudent('DNGFAIL', $this->campus, $this->semester);
    makeCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    [$action] = makeBatchAction(dngFails: true);

    $result = $action->handle(batchPayload([$student->id], $this->semester));

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('DNG API failed');
});

it('HL fee_type: auto-creates charge for approved retake registration before DNG push', function () {
    $student = makeBatchStudent('HL001', $this->campus, $this->semester);

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    // Approved registration WITHOUT finance_charge_id
    $reg = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_academic_record_id' => $academicRecord->id,
        'status' => CourseRetakeRegistration::STATUS_APPROVED,
        'retake_fee' => 2_500_000,
        'finance_charge_id' => null,
    ]);

    [$action] = makeBatchAction();

    $payload = batchPayload([$student->id], $this->semester, 'HL');
    $action->handle($payload);

    // Registration should now have a finance_charge_id (transitioned to payment_pending)
    $refreshed = $reg->fresh();
    expect($refreshed->finance_charge_id)->not->toBeNull()
        ->and($refreshed->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('HL fee_type: skips registration that already has a charge', function () {
    $student = makeBatchStudent('HL002', $this->campus, $this->semester);

    // Pre-existing charge (registration already transitioned)
    $charge = makeCharge($student, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 2_500_000);

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_academic_record_id' => $academicRecord->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'retake_fee' => 2_500_000,
        'finance_charge_id' => $charge->id, // already linked
    ]);

    [$action] = makeBatchAction();

    $result = $action->handle(batchPayload([$student->id], $this->semester, 'HL'));

    // Should succeed (charge exists and has balance)
    expect($result['created'])->toBe(1);

    // Only 1 charge should exist (no duplicate created)
    expect(FinanceCharge::where('student_id', $student->id)->count())->toBe(1);
});

it('builds distinct item ids for same-second pushes of the same student and fee type (FIN-33)', function () {
    // FIN-33: a bare YmdHis suffix collided when two pushes for the same student +
    // fee type landed in the same second, breaking webhook resolution (item_id is a
    // reconciliation key). The random suffix must keep same-second item_ids distinct.
    $action = app(CreateBatchDngFromChargesAction::class);
    $method = new ReflectionMethod($action, 'buildItemId');
    $method->setAccessible(true);

    [$first, $second] = $this->travelTo('2026-06-14 10:00:00', function () use ($action, $method) {
        return [
            $method->invoke($action, 'STU001', 'HL'),
            $method->invoke($action, 'STU001', 'HL'),
        ];
    });

    expect($first)->not->toBe($second)
        ->and($first)->toStartWith('STU001_hl_20260614100000')
        ->and($second)->toStartWith('STU001_hl_20260614100000')
        ->and(strlen($first))->toBeLessThanOrEqual(100);
});
