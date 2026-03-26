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
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    expect($event->is_valid_checksum)->toBeTrue();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PENDING);
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
    expect($event->is_valid_checksum)->toBeTrue();
    expect($event->processing_status)->toBe(DngWebhookEvent::STATUS_PENDING);
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

    // First call - should succeed
    $response1 = $this->postJson('/api/webhooks/dng/payment', $payload);
    $response1->assertOk();
    expect(DngWebhookEvent::count())->toBe(1);

    // Second call with identical payload - should still return 200
    $response2 = $this->postJson('/api/webhooks/dng/payment', $payload);
    $response2->assertOk();
    $response2->assertJson([
        'Code' => 200,
        'Type' => 'Success',
        'Message' => 'Already received',
    ]);

    // Should not create duplicate event
    expect(DngWebhookEvent::count())->toBe(1);
});

it('rejects mismatched amount without storing a webhook event', function () {
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

    $response->assertUnprocessable();
    $response->assertJson([
        'Code' => 422,
        'Type' => 'Error',
        'Message' => 'Payload mismatch',
    ]);

    expect(DngWebhookEvent::count())->toBe(0);
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
        'Message' => 'Already received',
    ]);

    expect(DngWebhookEvent::count())->toBe(1);
});

it('rejects orphan callbacks without storing a webhook event', function () {
    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        'PaymentId' => 'PAY-UNKNOWN',
        'Amount' => '5000000',
        'CheckSum' => 'anything',
    ];

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertUnprocessable();
    $response->assertJson([
        'Code' => 422,
        'Type' => 'Error',
        'Message' => 'Payment request not found',
    ]);

    expect(DngWebhookEvent::count())->toBe(0);
});

it('validates required fields and returns 422', function () {
    $payload = [
        'CampusCode' => 'CAMPUS001',
        'StudentId' => 'STU001',
        // Missing PaymentId, Amount, CheckSum
    ];

    $response = $this->postJson('/api/webhooks/dng/payment', $payload);

    $response->assertUnprocessable();
    // The API returns errors in a specific format with "field" key
    $errors = $response->json('errors');
    $errorFields = array_map(fn ($error) => $error['field'], $errors);
    expect($errorFields)->toContain('PaymentId');
    expect($errorFields)->toContain('Amount');
    expect($errorFields)->toContain('CheckSum');
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
