<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create(['code' => 'CAMPUS001']);
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'STU001',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
});

function createReconciliationRequest(Student $student, string $dngPaymentId, float $amount): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => $amount,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => $dngPaymentId,
    ]);
}

it('skips reconciliation updates when the paid amount does not match local amount', function () {
    $request = createReconciliationRequest($this->student, 'PAY001', 5000000);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:00:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY001',
                'StudentId' => 'STU001',
                'Amount' => '7000000',
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldNotReceive('bridgeToPayment');

    $service = new DngReconciliationService($mockClient, $mockPaymentService);

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:00:00');

    expect($summary)->toBe([
        'backfilled' => 0,
        'up_to_date' => 0,
        'orphans' => 0,
        'errors' => 1,
    ]);

    $request->refresh();
    expect($request->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($request->paid_at)->toBeNull()
        ->and($request->payment_id)->toBeNull();
});

it('bridges paid requests during reconciliation when status is already up to date', function () {
    $request = createReconciliationRequest($this->student, 'PAY001', 5000000);
    $request->update(['status' => DngPaymentRequest::STATUS_PAID_UNINVOICED]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:05:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY001',
                'StudentId' => 'STU001',
                'Amount' => '5000000',
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once()->withArgs(function (DngPaymentRequest $resolvedRequest): bool {
        return $resolvedRequest->is($resolvedRequest->fresh()) || $resolvedRequest->id > 0;
    });

    $service = new DngReconciliationService($mockClient, $mockPaymentService);

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:05:00');

    expect($summary)->toBe([
        'backfilled' => 0,
        'up_to_date' => 1,
        'orphans' => 0,
        'errors' => 0,
    ]);
});

it('skips bridging cancelled requests during reconciliation', function () {
    $request = createReconciliationRequest($this->student, 'PAY001', 5000000);
    $request->update(['status' => DngPaymentRequest::STATUS_CANCELLED]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:10:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY001',
                'StudentId' => 'STU001',
                'Amount' => '5000000',
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldNotReceive('bridgeToPayment');

    $service = new DngReconciliationService($mockClient, $mockPaymentService);

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:10:00');

    expect($summary)->toBe([
        'backfilled' => 0,
        'up_to_date' => 1,
        'orphans' => 0,
        'errors' => 0,
    ]);

    $request->refresh();
    expect($request->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($request->payment_id)->toBeNull();
});
