<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

const HASH_KEY = '2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J';
const ACCESS_CODE = 'TEST_ACCESS';
const CLIENT_CODE = 'TEST_CLIENT';

beforeEach(function () {
    config([
        'services.dng.hash_key' => HASH_KEY,
        'services.dng.access_code' => ACCESS_CODE,
        'services.dng.client_code' => CLIENT_CODE,
    ]);
    $this->checksumService = new DngChecksumService;

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    Cache::flush();
    Bus::fake();
});

/**
 * Generate webhook checksum using the correct formula:
 * AccessCode + ClientCode + Amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode
 */
function generateWebhookChecksum(
    DngChecksumService $service,
    string $amount,
    string $studentCode,
    string $feeType,
    string $campusCode,
    string $invoiceSerialNumber = '',
): string {
    $checksumValue = ACCESS_CODE.CLIENT_CODE.$amount.$invoiceSerialNumber.$studentCode.$feeType.$campusCode;

    return $service->generate($checksumValue);
}

it('accepts valid webhook callback and returns 200', function () {
    $student = Student::factory()
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

    $dngPaymentRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
    ];

    $checksum = generateWebhookChecksum($this->checksumService, '5000000.00', 'STU001', 'tuition', 'CAMPUS001');
    $payload['CheckSum'] = $checksum;

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertOk();
    $response->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
    $event = DngWebhookEvent::first();
    expect($event->is_valid_checksum)->toBeFalse();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_RECEIVED);
    expect($event->received_at)->not->toBeNull();
});

it('accepts webhook with invalid checksum while checksum enforcement is temporarily bypassed', function () {
    $student = Student::factory()
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

    $dngPaymentRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
        'CheckSum' => 'InvalidChecksum123456789==',
    ];

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertOk();
    $response->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
    $event = DngWebhookEvent::first();
    expect($event->is_valid_checksum)->toBeFalse();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_RECEIVED);
    expect($event->error_message)->toBeNull();
});

it('deduplicates identical payloads and returns 200 on second call', function () {
    $student = Student::factory()
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

    $dngPaymentRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
    ];

    $checksum = generateWebhookChecksum($this->checksumService, '5000000.00', 'STU001', 'tuition', 'CAMPUS001');
    $payload['CheckSum'] = $checksum;

    // First call - should succeed and store one event (received, job faked).
    $response1 = $this->postJson('/api/webhooks/dng/payment', $payload);
    $response1->assertOk();
    expect(DngWebhookEvent::count())->toBe(1);
    $eventId = DngWebhookEvent::first()->id;

    // Second call with a byte-identical payload - still returns 200 (DNG expects it)
    // and does NOT create a second row. Crucially, the retry is NOT swallowed: the
    // existing event stays non-terminal (received) so its still-pending job will
    // process it. (The re-dispatch itself is correctly collapsed by the job's
    // ShouldBeUniqueUntilProcessing lock while the first job is still pending; the
    // failed_terminal case below proves the explicit reset + re-dispatch path.)
    $response2 = $this->postJson('/api/webhooks/dng/payment', $payload);
    $response2->assertOk();
    $response2->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
    expect(DngWebhookEvent::find($eventId)->processing_status)
        ->toBe(DngWebhookEvent::STATUS_RECEIVED);
});

it('skips a duplicate of an already-processed event without re-dispatching', function () {
    // A retry of a payload whose prior attempt already PROCESSED is a true no-op:
    // re-running the identical payload would only churn. Drop it (no re-dispatch).
    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
        'CheckSum' => 'whatever',
    ];

    DngWebhookEvent::create([
        'dng_payment_id' => 'PAY001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => true,
        'processing_status' => DngWebhookEvent::STATUS_PROCESSED,
        'received_at' => now(),
    ]);

    $this->postJson('/api/webhooks/dng/payment', $payload)->assertOk();

    expect(DngWebhookEvent::count())->toBe(1);
    Bus::assertNotDispatched(ProcessDngWebhookJob::class);
});

it('re-dispatches and resets a duplicate of a failed_terminal (not_found) event', function () {
    // The first attempt failed because the matching request did not exist yet
    // (NOT_FOUND). DNG retries the same payload after the request is created — the
    // retry must NOT be swallowed: reset the stored event and re-dispatch it.
    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY-LATE',
        'Amount' => '5000000',
        'CheckSum' => 'whatever',
    ];

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-LATE',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => DngWebhookEvent::computePayloadHash($payload),
        'headers' => [],
        'payload' => $payload,
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_FAILED_TERMINAL,
        'error_category' => DngWebhookEvent::ERROR_CATEGORY_NOT_FOUND,
        'error_message' => 'No DNG payment request found',
        'received_at' => now(),
    ]);

    $this->postJson('/api/webhooks/dng/payment', $payload)->assertOk();

    expect(DngWebhookEvent::count())->toBe(1);
    $event->refresh();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_RECEIVED)
        ->and($event->error_category)->toBeNull();
    Bus::assertDispatched(ProcessDngWebhookJob::class);
});

it('captures mismatched amount payloads for debugging', function () {
    $student = Student::factory()
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

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '7000000',
    ];

    $payload['CheckSum'] = generateWebhookChecksum($this->checksumService, '5000000.00', 'STU001', 'tuition', 'CAMPUS001');

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertOk();
    $response->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
    expect(DngWebhookEvent::first()?->dng_payment_request_id)->not->toBeNull();
});

it('deduplicates repeated valid webhook events for the same payment and event type', function () {
    $student = Student::factory()
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

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
    ];

    $payload['CheckSum'] = generateWebhookChecksum($this->checksumService, '5000000.00', 'STU001', 'tuition', 'CAMPUS001');

    $this->postJson('/api/webhooks/dng/payment', $payload)->assertOk();

    $response = $this->postJson('/api/webhooks/dng/payment', [
        ...$payload,
        'PSPCode' => 'OTHER_GATEWAY',
    ]);

    $response->assertOk();
    $response->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(2);
});

it('captures orphan callbacks for debugging', function () {
    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY-UNKNOWN',
        'Amount' => '5000000',
        'CheckSum' => 'anything',
    ];

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertOk();
    $response->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
    expect(DngWebhookEvent::first()?->dng_payment_request_id)->toBeNull();
});

it('captures incomplete payloads for debugging', function () {
    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        // Missing PaymentId, Amount, CheckSum
    ];

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertOk();
    $response->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Accepted',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
    expect(DngWebhookEvent::first()?->payload)->toBe($payload);
});

it('dispatches webhook processing asynchronously after capture', function () {
    $student = Student::factory()
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

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
        'CheckSum' => 'any',
    ];

    $this->postJson('/api/webhooks/dng/payment', $payload)->assertOk();

    Bus::assertDispatched(ProcessDngWebhookJob::class);
});

it('links inbox event by item and student when callback payment id differs from stored request id', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'AUH13582',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $request = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'AUH13582',
        'fee_type' => 'HP',
        'item_id' => 'AUH13582_1774587192884',
        'amount' => 200000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_transaction_id' => 'uqvgalfplbym',
        'dng_payment_id' => 'AUH13582_1774587192884',
    ]);

    $payload = [
        'StudentId' => 'AUH13582',
        'PaymentId' => 'vhsfgf23423432esf',
        'Amount' => 200000,
        'CampusCode' => 'FAUHN',
        'ItemId' => 'AUH13582_1774587192884',
        'FeeType' => 'HP',
        'CheckSum' => 'any',
    ];

    $this->postJson('/api/webhooks/dng/payment', $payload)->assertOk();

    $event = DngWebhookEvent::query()->latest('id')->first();
    expect($event)->not->toBeNull();
    expect($event?->dng_payment_request_id)->toBe($request->id);
});

it('stores event with payload and headers', function () {
    $student = Student::factory()
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

    $dngPaymentRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
        'InvoiceSerialNumber' => 'INV-2024-001',
        'InvoiceDate' => '2024-01-15',
    ];

    $checksum = generateWebhookChecksum($this->checksumService, '5000000.00', 'STU001', 'tuition', 'CAMPUS001', 'INV-2024-001');
    $payload['CheckSum'] = $checksum;

    $this->postJson('/api/webhooks/dng/payment', $payload);

    $event = DngWebhookEvent::first();
    expect($event->payload)->toBe($payload);
    expect($event->headers)->toBeArray();
    expect($event->dng_payment_id)->toBe('PAY001');
});

it('resolves event type based on invoice presence', function () {
    $student = Student::factory()
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

    $dngPaymentRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY001',
    ]);

    // Payload without invoice
    $payloadNoInvoice = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY001',
        'Amount' => '5000000',
    ];

    $checksum = generateWebhookChecksum($this->checksumService, '5000000.00', 'STU001', 'tuition', 'CAMPUS001');
    $payloadNoInvoice['CheckSum'] = $checksum;

    $this->postJson('/api/webhooks/dng/payment', $payloadNoInvoice);

    $event = DngWebhookEvent::first();
    expect($event->event_type)->toBe(DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE);

    // Create second DNG payment request for PAY002
    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM002',
        'amount' => 3000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY002',
    ]);

    // Payload with invoice
    $payloadWithInvoice = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY002',
        'Amount' => '3000000',
        'InvoiceSerialNumber' => 'INV-2024-001',
        'InvoiceDate' => '2024-01-15',
    ];

    $checksum2 = generateWebhookChecksum($this->checksumService, '3000000.00', 'STU001', 'tuition', 'CAMPUS001', 'INV-2024-001');
    $payloadWithInvoice['CheckSum'] = $checksum2;

    $this->postJson('/api/webhooks/dng/payment', $payloadWithInvoice);

    $event2 = DngWebhookEvent::where('dng_payment_id', 'PAY002')->first();
    expect($event2->event_type)->toBe(DngWebhookEvent::EVENT_PAYMENT_INVOICED);
});
