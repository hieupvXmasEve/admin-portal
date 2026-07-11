<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
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

it('captures the provider receipt when the paid amount does not match local amount', function () {
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
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:00:00');

    expect($summary)->toBe([
        'backfilled' => 1,
        'up_to_date' => 0,
        'orphans' => 0,
        'errors' => 0,
    ]);

    $request->refresh();
    expect($request->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED)
        ->and($request->paid_at)->not->toBeNull()
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

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:05:00');

    expect($summary)->toBe([
        'backfilled' => 0,
        'up_to_date' => 1,
        'orphans' => 0,
        'errors' => 0,
    ]);
});

it('captures a receipt for cancelled requests without reviving reconciliation state', function () {
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
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

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

it('reconciles by ItemId when the DNG PaymentId differs from the stored placeholder (P1)', function () {
    // ItemId + Student + Campus is the stable correlation key. A request stored with a
    // placeholder dng_payment_id that differs from what DNG reports must be reconciled
    // by ItemId, NOT treated as an orphan.
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-CORR',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PLACEHOLDER-OLD',
    ]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:35:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'REAL-PAY-NEW', // differs from the stored placeholder
                'ItemId' => 'ITEM-CORR',
                'StudentId' => 'STU001',
                'Amount' => '5000000',
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:35:00');

    expect($summary['orphans'])->toBe(0)
        ->and($summary['backfilled'])->toBe(1);

    $request->refresh();
    expect($request->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED)
        // The stored placeholder is kept (not overwritten by the differing PaymentId).
        ->and($request->dng_payment_id)->toBe('PLACEHOLDER-OLD');
});

it('does not bind dng_payment_id to a fallback-matched request that fails the mismatch check (P1)', function () {
    // P1: the fallback (item/student/campus) match must not bind the DNG PaymentId
    // before the amount/student/campus mismatch check — otherwise a non-matching DNG
    // row poisons the local request with a wrong dng_payment_id.
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-RECON',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => null,
    ]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:25:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY-RECON',
                'ItemId' => 'ITEM-RECON',
                'StudentId' => 'STU001',
                'Amount' => '7000000', // disagrees with local 5000000
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:25:00');

    expect($summary['errors'])->toBe(0);
    // Exact ItemId correlation remains safe; the actual receipt is captured.
    expect($request->fresh()->dng_payment_id)->toBe('PAY-RECON')
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
});

it('does not bridge or settle a failed request during reconciliation (P1 cannot_transition)', function () {
    // P1: an illegal transition (e.g. a failed request can only go back to pending) is
    // an error — reconciliation must not create a Payment or settle installments while
    // the DNG request stays failed.
    $request = createReconciliationRequest($this->student, 'PAY001', 5000000);
    $request->update(['status' => DngPaymentRequest::STATUS_FAILED]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:30:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY001',
                'StudentId' => 'STU001',
                'Amount' => '5000000',
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldNotReceive('bridgeToPayment');

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:30:00');

    expect($summary['errors'])->toBe(1)
        ->and($summary['backfilled'])->toBe(0);
    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_FAILED)
        ->and($request->fresh()->payment_id)->toBeNull();
});

it('does not downgrade a paid_invoiced request during reconciliation (race guard)', function () {
    // P1: reconciliation locks + re-reads the request before transitioning, so a
    // no-invoice reconciliation row (target paid_uninvoiced) cannot overwrite a
    // request a concurrent webhook Call 2 already advanced to paid_invoiced.
    $request = createReconciliationRequest($this->student, 'PAY001', 5000000);
    $request->update(['status' => DngPaymentRequest::STATUS_PAID_INVOICED]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:20:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY001',
                'StudentId' => 'STU001',
                'Amount' => '5000000',
                // no InvoiceSerialNumber -> target would be paid_uninvoiced (lower)
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:20:00');

    expect($summary['up_to_date'])->toBe(1)
        ->and($summary['backfilled'])->toBe(0);

    // Must NOT be downgraded.
    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);
});

it('captures receipts for cancel_pushed_to_dng requests without reviving reconciliation state (FIN-18)', function () {
    // FIN-18: cancel_pushed_to_dng is terminal but was missing from statusOrder()
    // (fell through to default 0), so a late reconciliation hit could revive a
    // cancelled-at-DNG request. Cash is captured, but the terminal state remains.
    $request = createReconciliationRequest($this->student, 'PAY001', 5000000);
    $request->update(['status' => DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG]);

    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('checkPaidOfDay')
        ->once()
        ->with('CAMPUS001', '2026-03-26 21:15:00')
        ->andReturn([
            'data' => [[
                'PaymentId' => 'PAY001',
                'StudentId' => 'STU001',
                'Amount' => '5000000',
            ]],
        ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    $service = new DngReconciliationService($mockClient, $mockPaymentService, app(SettleInstallmentFromDngAction::class));

    $summary = $service->reconcileDay('CAMPUS001', '2026-03-26 21:15:00');

    expect($summary)->toBe([
        'backfilled' => 0,
        'up_to_date' => 1,
        'orphans' => 0,
        'errors' => 0,
    ]);

    $request->refresh();
    expect($request->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG)
        ->and($request->payment_id)->toBeNull();
});
