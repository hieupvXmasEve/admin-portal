<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngWebhookService;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Shared\Contracts\Finance\DngPaymentNotificationContextReader;
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

function makeWebhookRequest(Student $student, string $dngPaymentId, float $amount): DngPaymentRequest
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
            'Amount' => $amount,
            'ItemId' => 'ITEM001',
        ],
    ]);
}

function makeWebhookEventForNotification(
    DngPaymentRequest $request,
    string $eventType,
    DngChecksumService $checksumService,
    array $overrides = []
): DngWebhookEvent {
    $payload = array_merge([
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => $request->dng_payment_id,
        'Amount' => (string) $request->amount,
    ], $overrides);

    if (! isset($overrides['CheckSum'])) {
        $pushPayload = $request->push_payload;
        $payload['CheckSum'] = $checksumService->generate(
            'TEST_ACCESS'.'TEST_CLIENT'.
            (string) $pushPayload['Amount'].
            (string) ($payload['InvoiceSerialNumber'] ?? '').
            (string) $pushPayload['StudentId'].
            (string) $pushPayload['Type'].
            (string) $pushPayload['CampusCode']
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

it('publishes finance.dng_payment_received outbox event on first PAID transition', function () {
    $request = makeWebhookRequest($this->student, 'PAY001', 5000000);
    $request->update(['semester_id' => $this->semester->id]);

    $event = makeWebhookEventForNotification(
        $request,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        $this->checksumService
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    app(DngWebhookService::class)->processEvent($event);

    expect(NotificationEventOutbox::where('event_name', 'finance.dng_payment_received')->exists())->toBeTrue();

    $outbox = NotificationEventOutbox::where('event_name', 'finance.dng_payment_received')->first();
    expect($outbox->payload['type_key'])->toBe('dng_payment_received')
        ->and($outbox->payload['channels'])->toContain('realtime', 'email')
        ->and($outbox->payload['recipient_targets'][0]['type'])->toBe('student')
        ->and($outbox->payload['recipient_targets'][0]['id'])->toBe($this->student->id)
        ->and($outbox->payload['data']['semester_code'])->toBe($this->semester->code);
});

it('builds DNG notification context through owner contracts', function () {
    $request = makeWebhookRequest($this->student, 'PAY-CONTEXT', 5000000);
    $request->update([
        'semester_id' => $this->semester->id,
        'due_date' => '2026-08-31',
    ]);

    $context = app(DngPaymentNotificationContextReader::class)->find((int) $request->id);

    expect($context)->not->toBeNull()
        ->and($context->studentName)->toBe($this->student->full_name)
        ->and($context->studentCode)->toBe('STU001')
        ->and($context->semesterCode)->toBe($this->semester->code)
        ->and($context->programName)->toBe($this->program->name)
        ->and($context->invoiceCode)->toBe('ITEM001')
        ->and($context->amountFormatted)->toBe('5.000.000 VNĐ')
        ->and($context->dueDate)->toBe('31/08/2026');
});

it('publishes the payment-received notification exactly once across Call 1 then Call 2 (P2)', function () {
    // Call 2 (paid_uninvoiced -> paid_invoiced) only attaches invoice metadata; it must
    // not re-run Call 1's "payment received" side effects (a second notification).
    $request = makeWebhookRequest($this->student, 'PAY001', 5000000);

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment'); // idempotent; any number of calls
    app()->instance(DngPaymentService::class, $mockPaymentService);

    // Call 1 — first settlement → notifies.
    $call1 = makeWebhookEventForNotification(
        $request,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        $this->checksumService,
    );
    app(DngWebhookService::class)->processEvent($call1);

    // Call 2 — invoice attach only → must NOT notify again.
    $request->refresh();
    $call2 = makeWebhookEventForNotification(
        $request,
        DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        $this->checksumService,
        ['InvoiceSerialNumber' => 'INV-2026-001', 'InvoiceDate' => '2026-01-15'],
    );
    app(DngWebhookService::class)->processEvent($call2);

    expect(NotificationEventOutbox::where('event_name', 'finance.dng_payment_received')->count())->toBe(1);

    $call2->refresh();
    $request->refresh();
    expect($call2->processing_status)->toBe(DngWebhookEvent::STATUS_PROCESSED)
        ->and($request->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED)
        ->and($request->invoice_serial_number)->toBe('INV-2026-001');
});

it('does not publish notification when webhook is a duplicate (markSkipped)', function () {
    $request = makeWebhookRequest($this->student, 'PAY001', 5000000);
    $request->update(['status' => DngPaymentRequest::STATUS_PAID_UNINVOICED]);

    $event = makeWebhookEventForNotification(
        $request,
        DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        $this->checksumService
    );

    $mockPaymentService = Mockery::mock(DngPaymentService::class);
    $mockPaymentService->shouldReceive('bridgeToPayment')->once();
    app()->instance(DngPaymentService::class, $mockPaymentService);

    app(DngWebhookService::class)->processEvent($event);

    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_SKIPPED);
    expect(NotificationEventOutbox::where('event_name', 'finance.dng_payment_received')->exists())->toBeFalse();
});
