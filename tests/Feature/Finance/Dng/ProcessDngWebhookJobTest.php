<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use App\Modules\Finance\Dng\Services\DngPaymentService;
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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
    expect($dngPaymentRequest->paid_at)->not->toBeNull();

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->attempt_count)->toBe(1);
    expect($event->is_valid_checksum)->toBeTrue();
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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $dngPaymentRequest->refresh();
    // Should skip directly to PAID_INVOICED
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);
    expect($dngPaymentRequest->invoice_serial_number)->toBe('INV-2024-001');
});

it('skips callback processing for cancelled requests', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);
    $dngPaymentRequest->update(['status' => DngPaymentRequest::STATUS_CANCELLED]);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldNotReceive('bridgeToPayment');

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($dngPaymentRequest->payment_id)->toBeNull();

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_SKIPPED)
        ->and($event->error_message)->toContain('cancelled');
});

it('marks event as mismatch when amount differs', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    // Create event with mismatched amount
    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        ['Amount' => '3000000'] // Different from request amount
    );

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_message)->toContain('Amount mismatch');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_MISMATCH);

    // Payment request status should not change
    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_message)->toContain('Student mismatch');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_MISMATCH);
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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_MISMATCH);
    expect($event->error_message)->toBe('Invalid checksum');
    expect($event->error_category)->toBe(DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);
    expect($event->is_valid_checksum)->toBeFalse();
});

it('skips checksum enforcement for first callback without invoice serial number', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent(
        $dngPaymentRequest,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        ['CheckSum' => 'invalid-checksum']
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();

    app()->instance(DngPaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $event->refresh();
    $request->refresh();

    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
    expect($event->is_valid_checksum)->toBeTrue();
    expect($request->invoice_serial_number)->toBe('gh3rtr-shd-12343');
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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $mockPaymentService = Mockery::mock(App\Services\FinanceService\PaymentService::class);
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

    app()->instance(App\Services\FinanceService\PaymentService::class, $mockPaymentService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $mockPaymentService->shouldReceive('bridgeToPayment')->once()->andReturnUsing(function ($request) use (&$callCount) {
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
    $job1->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job2->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $dngPaymentRequest->refresh();
    // Payment ID should not change
    expect($dngPaymentRequest->payment_id)->toBe($firstPaymentId);
});

it('skips processing if event already processed', function () {
    $dngPaymentRequest = createDngPaymentRequest($this->student, 'PAY001', 5000000);

    $event = createWebhookEvent($dngPaymentRequest, DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);
    $event->update(['processing_status' => DngWebhookEvent::STATUS_PROCESSED]);

    $mockWebhookService = Mockery::mock(App\Modules\Finance\Dng\Services\DngWebhookService::class);
    $mockWebhookService->shouldNotReceive('processEvent');

    app()->instance(App\Modules\Finance\Dng\Services\DngWebhookService::class, $mockWebhookService);

    $job = new ProcessDngWebhookJob($event->id);
    $job->handle($mockWebhookService);

    // Event status should remain PROCESSED
    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED);
});

it('logs error and marks failed when event not found', function () {
    // Non-existent event ID
    $job = new ProcessDngWebhookJob(99999);
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

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
    $job->handle(app(App\Modules\Finance\Dng\Services\DngWebhookService::class));

    $dngPaymentRequest->refresh();
    expect($dngPaymentRequest->last_callback_payload)->toBe($payload);
    expect($dngPaymentRequest->psp_code)->toBe('PSP123');
});
