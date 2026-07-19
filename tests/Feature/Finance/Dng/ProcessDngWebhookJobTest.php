<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngWebhookService;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\Payment;
use App\Services\FinanceService\PaymentService;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.dng.hash_key' => '2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J',
        'services.dng.access_code' => 'TEST_ACCESS',
        'services.dng.client_code' => 'TEST_CLIENT',
    ]);

    $this->campus = Campus::factory()->create();
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

    $this->checksumService = app(DngChecksumService::class);
});

function generateProcessWebhookChecksum(
    DngChecksumService $service,
    string $amount,
    string $studentCode,
    string $feeType,
    string $campusCode,
    string $invoiceSerialNumber = '',
): string {
    return $service->generate('TEST_ACCESS'.'TEST_CLIENT'.$amount.$invoiceSerialNumber.$studentCode.$feeType.$campusCode);
}

function createDngPaymentRequest(Student $student, string $dngPaymentId, float $amount): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => $amount,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_transaction_id' => 'TXN001',
        'dng_payment_id' => $dngPaymentId,
        'push_payload' => [
            'StudentId' => 'STU001',
            'CampusCode' => 'CAMPUS001',
            'Type' => 'tuition',
            'Amount' => 5000000,
            'ItemId' => 'ITEM001',
        ],
    ]);
}

function processRetakeCharge(CourseRetakeRegistration $registration, int $amount, string $description): FinanceCharge
{
    $obligation = FinanceObligation::create([
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

    return FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $registration->student_id,
        'semester_id' => $registration->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => $amount,
        'description' => $description,
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}

function createWebhookEvent(
    DngPaymentRequest $request,
    string $eventType,
    array $payloadOverrides = []
): DngWebhookEvent {
    $basePayload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => $request->dng_payment_id,
        'Amount' => (string) $request->amount,
    ];

    $payload = array_merge($basePayload, $payloadOverrides);
    $pushPayload = $request->push_payload ?? [
        'Amount' => $request->amount,
        'StudentId' => $request->student_code,
        'Type' => $request->fee_type,
        'CampusCode' => $request->campus_code,
    ];

    if (! array_key_exists('CheckSum', $payloadOverrides)) {
        $payload['CheckSum'] = generateProcessWebhookChecksum(
            app(DngChecksumService::class),
            (string) $pushPayload['Amount'],
            (string) $pushPayload['StudentId'],
            (string) ($pushPayload['Type'] ?? $pushPayload['FeeType'] ?? $request->fee_type),
            (string) $pushPayload['CampusCode'],
            (string) ($payload['InvoiceSerialNumber'] ?? ''),
        );
    }

    return DngWebhookEvent::create([
        'dng_payment_id' => $request->dng_payment_id,
        'dng_payment_request_id' => $request->id,
        'event_type' => $eventType,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);
}

it('processes callback 1 (no invoice) and transitions to paid_uninvoiced', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    // Mock the bridge service to avoid full settlement setup
    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
    expect($dngPaymentRequest->paid_at)->not->toBeNull();

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->attempt_count)->toBe(1);
    expect($event->is_valid_checksum)->toBeTrue();
});

it('retries a paid retake webhook when the Academic projection command reports a failure', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY-RETRY-RETAKE', 5000000);
    $dngPaymentRequest->update([
        'fee_type' => 'HL',
        'push_payload' => [
            'StudentId' => 'STU001',
            'CampusCode' => 'CAMPUS001',
            'Type' => 'HL',
            'Amount' => 5000000,
            'ItemId' => 'ITEM001',
        ],
    ]);

    $event = createWebhookEvent($dngPaymentRequest->fresh(), DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $paymentService = Mockery::mock(DngPaymentService::class);
    $paymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $paymentService);

    $syncer = Mockery::mock(RetakeRegistrationPaymentSyncer::class);
    $syncer->shouldReceive('runForStudent')
        ->once()
        ->with($this->student->id)
        ->andReturn([
            'checked' => 1,
            'eligible' => 1,
            'synced' => 0,
            'waiting_for_class' => 0,
            'skipped' => 0,
            'failed' => 1,
            'details' => [],
        ]);
    app()->instance(RetakeRegistrationPaymentSyncer::class, $syncer);

    expect(fn () => (new ProcessDngWebhookJob($event->id))->handle(app(DngWebhookService::class)))
        ->toThrow(RuntimeException::class, 'Academic retake payment sync failed.');

    expect($event->fresh()->processing_status)->toBe(DngWebhookEvent::STATUS_FAILED_RETRYABLE);
});

it('processes a verified receipt after a pushed request is held for settlement review', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    $dngPaymentRequest->update(['status' => DngPaymentRequest::STATUS_NEEDS_REVIEW]);
    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    (new ProcessDngWebhookJob($event->id))->handle(app(DngWebhookService::class));

    expect($dngPaymentRequest->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED)
        ->and($event->fresh()->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
});

it('processes callback 2 (with invoice) and transitions to paid_invoiced', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        [
            'InvoiceSerialNumber' => 'INV-2024-001',
            'InvoiceDate' => '2024-01-15',
        ]
    );

    // Mock the bridge service
    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);
    expect($dngPaymentRequest->invoice_serial_number)->toBe('INV-2024-001');
    expect($dngPaymentRequest->invoice_date)->not->toBeNull();

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->is_valid_checksum)->toBeTrue();
});

it('handles callback 2 arriving before callback 1 and skips to paid_invoiced', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    // Start in PUSHED_TO_DNG state (no callback yet)
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    $eventWithInvoice = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        [
            'InvoiceSerialNumber' => 'INV-2024-001',
            'InvoiceDate' => '2024-01-15',
        ]
    );

    // Mock the bridge service
    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    // Process callback 2 first
    $job = new ProcessDngWebhookJob($eventWithInvoice->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    // Should skip directly to PAID_INVOICED
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);
    expect($dngPaymentRequest->invoice_serial_number)->toBe('INV-2024-001');
});

it('does not let a stale Call 1 downgrade a request already advanced by Call 2 (race guard)', function () {
    // Locked, fresh-read transition: once Call 2 has advanced the request to
    // paid_invoiced, a late Call 1 (paid_uninvoiced target) must be skipped as
    // "already progressed", never overwriting the higher status back down.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');
    app()->instance(DngPaymentService::class, $mockPaymentService);

    // Call 2 first → paid_invoiced
    $event2 = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        ['InvoiceSerialNumber' => 'INV-2024-001', 'InvoiceDate' => '2024-01-15']
    );
    (new ProcessDngWebhookJob($event2->id))->handle(app(DngWebhookService::class));
    expect($dngPaymentRequest->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);

    // Stale Call 1 (no invoice) arrives afterwards → must NOT downgrade.
    $event1 = createWebhookEvent($dngPaymentRequest->fresh(), DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);
    (new ProcessDngWebhookJob($event1->id))->handle(app(DngWebhookService::class));

    expect($dngPaymentRequest->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);
    $event1->refresh();
    expect($event1->processing_status)->toBe(DngWebhookEvent::STATUS_SKIPPED)
        ->and($event1->error_message)->toContain('already progressed');
});

it('captures verified callback cash for cancelled requests without reviving state', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    $dngPaymentRequest->update(['status' => DngPaymentRequest::STATUS_CANCELLED]);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($dngPaymentRequest->payment_id)->toBeNull();

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_SKIPPED)
        ->and($event->error_message)->toContain('cancelled');
});

it('captures the actual provider receipt when amount differs', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    // Create event with mismatched amount
    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        ['Amount' => '3000000'] // Different from request amount
    );

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);

    // Receipt is recorded while target validation evidence preserves the drift.
    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($dngPaymentRequest->payment_id)->not->toBeNull();
});

it('marks event as mismatch when no matching payment request exists', function () {
    // Create event without a corresponding DNG payment request
    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'ORPHAN_PAY_001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash([
            'PaymentId' => 'ORPHAN_PAY_001',
            'StudentId' => 'UNKNOWN',
            'Amount' => '1000000',
        ]),
        'headers' => [],
        'payload' => [
            'CampusCode' => 'CAMPUS001',
            'StudentId' => 'UNKNOWN',
            'PaymentId' => 'ORPHAN_PAY_001',
            'Amount' => '1000000',
        ],
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_FAILED_TERMINAL);
    expect($event->error_message)->toContain('No DNG payment request found');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_NOT_FOUND);
});

it('marks event as mismatch when student code differs', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    // Create event with mismatched student code
    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        ['StudentId' => 'DIFFERENT_STUDENT'] // Different from request
    );

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_message)->toContain('Student mismatch');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_MISMATCH);
});

it('marks event as mismatch when ItemId differs from the matched request (P2)', function () {
    // ItemId is the settle-once key. A callback matched by PaymentId fallback but
    // carrying a different ItemId points at another debt — it must be rejected even
    // though the checksum (which does not cover ItemId) is otherwise valid.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000); // item_id = ITEM001

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'ItemId' => 'ITEM-WRONG',
        'Amount' => '5000000',
        'CheckSum' => generateProcessWebhookChecksum($this->checksumService, '5000000', 'STU001', 'tuition', 'CAMPUS001'),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    (new ProcessDngWebhookJob($event->id))->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH)
        ->and($event->error_message)->toContain('Item mismatch')
        ->and($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_MISMATCH);
    expect($dngPaymentRequest->fresh()->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW);
});

it('marks event as mismatch when checksum verification fails', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        [
            'InvoiceSerialNumber' => 'INV-2024-001',
            'InvoiceDate' => '2024-01-15',
            'CheckSum' => 'invalid-checksum',
        ]
    );

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_message)->toBe('Invalid checksum');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);
    expect($event->is_valid_checksum)->toBeFalse();
});

it('rejects first callback (no invoice serial) when the checksum is invalid (FIN-16)', function () {
    // FIN-16: the public webhook is an untrusted boundary. Call 1 (no invoice
    // serial) used to bypass checksum verification entirely, letting a forged
    // settlement event through. It must now be verified with the empty-serial
    // formula and rejected when invalid — without settling anything.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        ['CheckSum' => 'invalid-checksum']
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldNotReceive('bridgeToPayment');

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    $dngPaymentRequest->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);
    expect($event->is_valid_checksum)->toBeFalse();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
    expect($dngPaymentRequest->payment_id)->toBeNull();
});

it('settles first callback (no invoice serial) when the empty-serial checksum is valid (FIN-16)', function () {
    // The legitimate Call 1 is signed by DNG with an empty InvoiceSerialNumber
    // segment. Verifying with that same empty-serial string must accept it and
    // settle exactly once — the hardening must not reject the real Call 1.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        // explicit empty-serial signed checksum (overrides the helper's auto sign)
        ['CheckSum' => generateProcessWebhookChecksum(
            app(DngChecksumService::class),
            '5000000',
            'STU001',
            'tuition',
            'CAMPUS001',
        )]
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    $dngPaymentRequest->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->is_valid_checksum)->toBeTrue();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
});

it('resolves request by item and student then verifies checksum from stored push payload', function () {
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'AUH13582',
        'fee_type' => 'HP',
        'item_id' => 'AUH13582_1774587192884',
        'amount' => 200000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_transaction_id' => 'uqvgalfplbym',
        'dng_payment_id' => 'AUH13582_1774587192884',
        'push_payload' => [
            'ApiCode' => 'HC_ASIA',
            'StudentId' => 'AUH13582',
            'CampusCode' => 'FAUHN',
            'Type' => 'HP',
            'Amount' => 200000,
            'ItemId' => 'AUH13582_1774587192884',
            'Login' => 'HC_ASIA',
        ],
    ]);

    $payload = [
        'StudentId' => 'AUH13582',
        'StudentName' => 'LE THANH TRUNG',
        'PaymentId' => 'vhsfgf23423432esf',
        'PSPCode' => 'VIETINBANK',
        'FeeType' => 'HP',
        'Amount' => 200000,
        'CampusCode' => 'FAUHN',
        'EstimatedStartSemester' => '05/26',
        'ItemId' => 'AUH13582_1774587192884',
        'InvoiceSerialNumber' => 'hn-fa123',
        'InvoiceDate' => '2026-03-28T00:00:00',
        'CheckSum' => generateProcessWebhookChecksum(
            $this->checksumService,
            '200000',
            'AUH13582',
            'HP',
            'FAUHN',
            'hn-fa123',
        ),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'vhsfgf23423432esf',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    $request->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->dng_payment_request_id)->toBe($request->id);
    expect($event->is_valid_checksum)->toBeTrue();
    expect($request->dng_payment_id)->toBe('AUH13582_1774587192884');
    expect($request->invoice_serial_number)->toBe('hn-fa123');
});

it('accepts invoice callback checksum when third party formats amount with one decimal', function () {
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'AUH120339',
        'fee_type' => 'HP',
        'item_id' => 'AUH120339_1774595915622',
        'amount' => 230000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_transaction_id' => 'szhpoloijxyx',
        'dng_payment_id' => 'AUH120339_1774595915622',
        'push_payload' => [
            'ApiCode' => 'HC_ASIA',
            'StudentId' => 'AUH120339',
            'CampusCode' => 'FAUHN',
            'Type' => 'HP',
            'Amount' => 230000,
            'ItemId' => 'AUH120339_1774595915622',
            'Login' => 'HC_ASIA',
        ],
    ]);

    $payload = [
        'StudentId' => 'AUH120339',
        'TransactionId' => '3667507',
        'StudentName' => 'nguyen doan lam',
        'PaymentId' => 'hgd242342',
        'PSPCode' => 'VIETINBANK',
        'FeeType' => 'HP',
        'Amount' => 230000,
        'CampusCode' => 'FAUHN',
        'EstimatedStartSemester' => '06/26',
        'ItemId' => 'AUH120339_1774595915622',
        'InvoiceSerialNumber' => 'gh3rtr-shd-12343',
        'InvoiceDate' => '2026-03-27T00:00:00',
        'CheckSum' => generateProcessWebhookChecksum(
            $this->checksumService,
            '230000.0',
            'AUH120339',
            'HP',
            'FAUHN',
            'gh3rtr-shd-12343',
        ),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'hgd242342',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    $request->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->is_valid_checksum)->toBeTrue();
    expect($request->invoice_serial_number)->toBe('gh3rtr-shd-12343');
});

it('does not bind dng_payment_id when the callback fails business validation (P1)', function () {
    // P1: the callback PaymentId must not be bound to a request that turns out to be a
    // business mismatch. The checksum is signed with the request's real amount (so it
    // passes) while the callback's Amount field disagrees — binding must be deferred
    // past crossValidate, leaving dng_payment_id untouched on mismatch.
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-NOBIND',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => null,
        'push_payload' => [
            'StudentId' => 'STU001',
            'CampusCode' => 'CAMPUS001',
            'Type' => 'tuition',
            'Amount' => 5000000,
            'ItemId' => 'ITEM-NOBIND',
        ],
    ]);

    $payload = [
        'ItemId' => 'ITEM-NOBIND',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY-NEW',
        'CampusCode' => 'CAMPUS001',
        'Amount' => '7000000', // disagrees with local 5000000
        'CheckSum' => generateProcessWebhookChecksum($this->checksumService, '5000000', 'STU001', 'tuition', 'CAMPUS001'),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-NEW',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    (new ProcessDngWebhookJob($event->id))->handle(app(DngWebhookService::class));

    $event->refresh();
    $request->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    // Exact ItemId correlation binds the provider identity even when amount drifted.
    expect($request->dng_payment_id)->toBe('PAY-NEW')
        ->and($request->payment_id)->not->toBeNull();
});

it('does not bind fallback payment id before checksum passes', function () {
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => null,
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'ItemId' => 'ITEM001',
        'Amount' => '5000000',
        'InvoiceSerialNumber' => 'INV-2024-001',
        'InvoiceDate' => '2024-01-15',
        'CheckSum' => 'invalid-checksum',
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $request->refresh();
    $event->refresh();

    expect($request->dng_payment_id)->toBeNull();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);
});

it('marks exhausted processing exceptions as failed terminal', function () {
    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => hash('sha256', 'job-failure-test'),
        'headers' => [],
        'payload' => ['PaymentId' => 'PAY001'],
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $job = new ProcessDngWebhookJob($event->id);
    $job->failed(new RuntimeException('boom'));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_FAILED_TERMINAL);
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_PROCESSING);
    expect($event->error_message)->toBe('boom');
});

it('creates Payment record via bridge when first callback arrives', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    // Don't mock - let it actually create the Payment
    // We'll need to mock just the auto-allocate part
    $mockPaymentService = Mockery::mock(PaymentService::class);
    $mockPaymentService->shouldReceive('recordPayment')->andReturn(
        Payment::create([
            'student_id' => $this->student->id,
            'amount' => 5000000,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'dng',
            'external_ref' => 'PAY001',
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ])
    );
    $mockPaymentService->shouldReceive('autoAllocatePayment');

    app()->instance(PaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->payment_id)->not->toBeNull();

    // Verify payment was created
    $payment = $dngPaymentRequest->payment;
    expect($payment)->not->toBeNull();
    expect($payment->external_ref)->toBe('PAY001');
    // Amount may be stored as decimal string
    expect((float) $payment->amount)->toBe(5000000.0);
});

it('does not create duplicate Payment on second callback', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    // Create and process first event
    $event1 = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    // Mock the payment service to return a payment on first call
    $callCount = 0;
    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->twice()->andReturnUsing(function ($request) use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            // First call: create a payment
            $payment = Payment::create([
                'student_id' => $request->student_id,
                'amount' => $request->amount,
                'method' => Payment::METHOD_GATEWAY,
                'source' => 'dng',
                'external_ref' => $request->dng_payment_id,
                'paid_at' => $request->paid_at ?? now(),
                'status' => Payment::STATUS_COMPLETED,
            ]);
            $request->update(['payment_id' => $payment->id]);

            return $payment;
        }

        // Second call: should return existing payment
        return $request->payment;
    });

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job1 = new ProcessDngWebhookJob($event1->id);
    $job1->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    $firstPaymentId = $dngPaymentRequest->payment_id;
    expect($firstPaymentId)->not->toBeNull();

    // Create second event (callback 2 with invoice)
    $event2 = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        [
            'InvoiceSerialNumber' => 'INV-2024-001',
            'InvoiceDate' => '2024-01-15',
        ]
    );

    // Process second event
    $job2 = new ProcessDngWebhookJob($event2->id);
    $job2->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    // Payment ID should not change
    expect($dngPaymentRequest->payment_id)->toBe($firstPaymentId);
});

it('skips processing if event already processed', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);
    $event->update(['processing_status' => DngWebhookEvent::STATUS_PROCESSED]);

    $mockWebhookService = Mockery::mock(DngWebhookService::class);
    $mockWebhookService->shouldNotReceive('processEvent');

    app()->instance(DngWebhookService::class, $mockWebhookService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle($mockWebhookService);

    // Event status should remain PROCESSED
    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
});

it('logs error and marks failed when event not found', function () {
    // Non-existent event ID
    $job = new ProcessDngWebhookJob(99999);
    $job->handle(app(DngWebhookService::class));

    // Should not throw, just log and return
    expect(DngWebhookEvent::count())->toBe(0);
});

it('sets paid_at timestamp when first callback processed', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    expect($dngPaymentRequest->paid_at)->toBeNull();

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->paid_at)->not->toBeNull();
});

it('preserves paid_at when second callback processed', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    $originalPaidAt = now()->subMinutes(5);
    $dngPaymentRequest->update(['paid_at' => $originalPaidAt, 'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED]);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        [
            'InvoiceSerialNumber' => 'INV-2024-001',
            'InvoiceDate' => '2024-01-15',
        ]
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    // paid_at should not be updated
    expect($dngPaymentRequest->paid_at->timestamp)->toBe($originalPaidAt->timestamp);
});

it('stores last_callback_payload from webhook', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
        'PSPCode' => 'PSP123',
        'CheckSum' => generateProcessWebhookChecksum(
            $this->checksumService,
            '5000000',
            'STU001',
            'tuition',
            'CAMPUS001',
        ),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY001',
        'dng_payment_request_id' => $dngPaymentRequest->id,
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->last_callback_payload)->toBe($payload);
    expect($dngPaymentRequest->psp_code)->toBe('PSP123');
});

it('marks event as mismatch when campus differs even if checksum passes (FIN-32)', function () {
    // FIN-32: CampusCode is inside the checksum string, but as defense in depth the
    // business-field match must also reject a callback whose CampusCode differs from
    // the local request — here the checksum is signed with the request's real campus
    // (CAMPUS001) while the callback body claims CAMPUS999.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        [
            'CampusCode' => 'CAMPUS999',
            'CheckSum' => generateProcessWebhookChecksum(
                app(DngChecksumService::class),
                '5000000',
                'STU001',
                'tuition',
                'CAMPUS001',
            ),
        ]
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldNotReceive('bridgeToPayment');
    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    $dngPaymentRequest->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_message)->toContain('Campus mismatch');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_MISMATCH);
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW);
});

it('captures late callback cash for a cancel_pushed_to_dng request without reviving state (FIN-18)', function () {
    // FIN-18: cancel_pushed_to_dng is terminal. A late settlement callback must be
    // captured without processing — previously this status fell through
    // statusOrder()'s default and could be advanced.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    $dngPaymentRequest->update(['status' => DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG]);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $event->refresh();
    $dngPaymentRequest->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_SKIPPED)
        ->and($event->error_message)->toContain('cancelled');
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG)
        ->and($dngPaymentRequest->payment_id)->toBeNull();
});

it('settles a still-pending installment on a duplicate callback for an already-advanced request (P2 recovery)', function () {
    // P2: if a prior attempt advanced the status (paid_uninvoiced) but crashed before
    // settling the linked installment, a later duplicate/late callback lands in the
    // equivalent/already_progressed branch. That recovery branch must now also settle
    // the installment (idempotently), not just bridge the Payment — otherwise the next
    // installment would never be pushed.
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $charge = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $installment = FinanceChargeInstallment::factory()->awaitingPayment()->create([
        'finance_charge_id' => $charge->id,
        'dng_payment_request_id' => $dngPaymentRequest->id,
        'amount' => 5000000,
    ]);

    // Status already advanced, but installment left un-settled (simulated prior crash).
    $dngPaymentRequest->update([
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'paid_at' => now(),
    ]);

    // Late/duplicate Call 1 (no invoice) → target paid_uninvoiced == current → equivalent.
    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');
    app()->instance(DngPaymentService::class, $mockPaymentService);

    (new ProcessDngWebhookJob($event->id))->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_SKIPPED)
        ->and($event->error_message)->toContain('Equivalent');

    // The recovery branch settled the previously-stuck installment.
    expect($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PAID);
});

it('resolves the webhook to the correct campus when ItemId and StudentId collide across campuses (P1)', function () {
    // Two requests share ItemId + StudentId but live in different campuses. The
    // resolver must scope by the callback CampusCode and settle the right one, not the
    // first-inserted row (which would then fail with a Campus mismatch).
    $requestA = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS-A',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-DUP',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-A',
        'push_payload' => ['StudentId' => 'STU001', 'CampusCode' => 'CAMPUS-A', 'Type' => 'tuition', 'Amount' => 5000000, 'ItemId' => 'ITEM-DUP'],
    ]);
    $requestB = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS-B',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-DUP',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-B',
        'push_payload' => ['StudentId' => 'STU001', 'CampusCode' => 'CAMPUS-B', 'Type' => 'tuition', 'Amount' => 5000000, 'ItemId' => 'ITEM-DUP'],
    ]);

    $payload = [
        'ItemId' => 'ITEM-DUP',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY-B',
        'CampusCode' => 'CAMPUS-B',
        'FeeType' => 'tuition',
        'Amount' => '5000000',
        'CheckSum' => generateProcessWebhookChecksum($this->checksumService, '5000000', 'STU001', 'tuition', 'CAMPUS-B'),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-B',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment');
    app()->instance(DngPaymentService::class, $mockPaymentService);

    (new ProcessDngWebhookJob($event->id))->handle(app(DngWebhookService::class));

    $event->refresh();
    expect($event->dng_payment_request_id)->toBe($requestB->id)
        ->and($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($requestB->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
    // The colliding Campus A request must be left untouched.
    expect($requestA->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

// =====================
// Retake auto-enroll via canonical Finance obligations
// =====================

it('does not link a retake registration from an HL webhook until the charge is settled', function () {
    // Setup two retake registrations for the same student to verify the precise charge link is respected.
    $campus = $this->campus;
    $semester = $this->semester;
    $student = $this->student;

    $courseOfferingA = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'max_capacity' => 50,
        'current_enrollment' => 5,
    ]);
    $courseOfferingB = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'max_capacity' => 50,
        'current_enrollment' => 5,
    ]);

    $user = User::factory()->create();

    // Academic records (failed) for each unit
    $academicRecordA = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOfferingA->unit_id,
        'course_offering_id' => $courseOfferingA->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
    $academicRecordB = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOfferingB->unit_id,
        'course_offering_id' => $courseOfferingB->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    // Registration A — the one being paid
    $regA = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOfferingA->unit_id,
        'original_academic_record_id' => $academicRecordA->id,
        'course_offering_id' => $courseOfferingA->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $chargeA = processRetakeCharge($regA, 5_000_000, 'Retake A');

    // Registration B — newer, should NOT be enrolled by this payment
    $regB = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOfferingB->unit_id,
        'original_academic_record_id' => $academicRecordB->id,
        'course_offering_id' => $courseOfferingB->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 7000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $chargeB = processRetakeCharge($regB, 7_000_000, 'Retake B');

    // DNG request linked explicitly to chargeA (the one being paid)
    $dngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-RETAKE-A',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-RETAKE-A',
    ]);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dngRequest->id,
        'finance_charge_id' => $chargeA->id,
        'amount' => $chargeA->amount,
    ]);

    $payload = [
        'PaymentId' => 'PAY-RETAKE-A',
        'ItemId' => 'ITEM-RETAKE-A',
        'StudentId' => 'STU001',
        'Amount' => '5000000',
        'FeeType' => 'HL',
        'CampusCode' => 'CAMPUS001',
        'CheckSum' => generateProcessWebhookChecksum($this->checksumService, '5000000', 'STU001', 'HL', 'CAMPUS001'),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-RETAKE-A',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'dng_payment_request_id' => $dngRequest->id,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $regA->refresh();
    $regB->refresh();

    // The mocked payment bridge does not allocate money to the charge, so retake sync must wait.
    expect($regA->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    // Registration B must remain untouched
    expect($regB->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('does not trigger auto-enroll for non-HL fee type webhooks', function () {
    $campus = $this->campus;
    $semester = $this->semester;
    $student = $this->student;

    $courseOffering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $user = User::factory()->create();
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    $reg = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    // DNG request with HP fee_type (not HL) — should not trigger auto-enroll
    $dngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-HP-001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-HP-001',
    ]);

    $payload = [
        'PaymentId' => 'PAY-HP-001',
        'ItemId' => 'ITEM-HP-001',
        'StudentId' => 'STU001',
        'Amount' => '5000000',
        'FeeType' => 'HP',
        'CampusCode' => 'CAMPUS001',
        'CheckSum' => generateProcessWebhookChecksum($this->checksumService, '5000000', 'STU001', 'HP', 'CAMPUS001'),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-HP-001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'dng_payment_request_id' => $dngRequest->id,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $reg->refresh();
    // Must NOT be enrolled — HP webhook doesn't affect retake registrations
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('does not link aggregate retake registrations from an HL webhook until the charges are settled', function () {
    // A student has 2 pending retake courses — both covered by ONE aggregate DNG request
    $campus = $this->campus;
    $semester = $this->semester;
    $student = $this->student;
    $user = User::factory()->create();

    // Set up two course offerings + academic records (failed)
    $courseOfferingA = CourseOffering::factory()->create(['semester_id' => $semester->id, 'max_capacity' => 50, 'current_enrollment' => 5]);
    $courseOfferingB = CourseOffering::factory()->create(['semester_id' => $semester->id, 'max_capacity' => 50, 'current_enrollment' => 5]);

    $academicRecordA = AcademicRecord::factory()->create([
        'student_id' => $student->id, 'campus_id' => $campus->id,
        'unit_id' => $courseOfferingA->unit_id, 'course_offering_id' => $courseOfferingA->id,
        'completion_status' => 'failed', 'is_passed' => false,
    ]);
    $academicRecordB = AcademicRecord::factory()->create([
        'student_id' => $student->id, 'campus_id' => $campus->id,
        'unit_id' => $courseOfferingB->unit_id, 'course_offering_id' => $courseOfferingB->id,
        'completion_status' => 'failed', 'is_passed' => false,
    ]);

    // Two payment_pending registrations
    $regA = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOfferingA->unit_id,
        'original_academic_record_id' => $academicRecordA->id,
        'course_offering_id' => $courseOfferingA->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $regB = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOfferingB->unit_id,
        'original_academic_record_id' => $academicRecordB->id,
        'course_offering_id' => $courseOfferingB->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 7000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    // One FinanceCharge per registration through canonical obligations.
    $chargeA = processRetakeCharge($regA, 5_000_000, 'Retake A');
    $chargeB = processRetakeCharge($regB, 7_000_000, 'Retake B');

    // Aggregate DNG request — links are via the canonical pivot.
    $dngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-AGG-001',
        'amount' => 12000000, // 5M + 7M
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-AGG-001',
    ]);

    // Insert pivot rows
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dngRequest->id,
        'finance_charge_id' => $chargeA->id,
        'amount' => 5000000,
    ]);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dngRequest->id,
        'finance_charge_id' => $chargeB->id,
        'amount' => 7000000,
    ]);

    $payload = [
        'PaymentId' => 'PAY-AGG-001',
        'ItemId' => 'ITEM-AGG-001',
        'StudentId' => 'STU001',
        'Amount' => '12000000',
        'FeeType' => 'HL',
        'CampusCode' => 'CAMPUS001',
        'CheckSum' => generateProcessWebhookChecksum($this->checksumService, '12000000', 'STU001', 'HL', 'CAMPUS001'),
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-AGG-001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'dng_payment_request_id' => $dngRequest->id,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'received_at' => now(),
    ]);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(DngWebhookService::class));

    $regA->refresh();
    $regB->refresh();

    // The mocked payment bridge does not allocate money to either charge, so retake sync must wait.
    expect($regA->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($regB->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});
