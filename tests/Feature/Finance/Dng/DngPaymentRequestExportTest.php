<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Http\Export\DngPaymentRequestExport;
use App\Modules\Finance\Models\Payment;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();
});

function makeDngExportStudent(object $context, string $studentCode): Student
{
    return Student::factory()
        ->forCampus($context->campus)
        ->forProgram($context->program)
        ->state([
            'student_id' => $studentCode,
            'full_name' => "Student {$studentCode}",
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

it('exports one row per DNG payment request with its bound ref, latest webhook ref, and bridged payment', function (): void {
    $student = makeDngExportStudent($this, 'DNGX001');

    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 2_500_000,
        'method' => Payment::METHOD_GATEWAY,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => '2026-07-01 10:00:00',
    ]);

    $paymentRequest = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $this->campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'DNGX-ITEM-001',
        'amount' => 2_500_000,
        'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        'semester_id' => $this->semester->id,
        'dng_payment_id' => 'DNGPAY-BOUND-001',
        'dng_transaction_id' => 'TXN-001',
        'payment_id' => $payment->id,
        'invoice_serial_number' => 'INV-2026-0099',
    ]);

    // First (earlier) webhook call for this request.
    DngWebhookEvent::query()->create([
        'dng_payment_id' => 'DNGPAY-BOUND-001',
        'dng_payment_request_id' => $paymentRequest->id,
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => hash('sha256', 'call-1'),
        'payload' => ['PaymentId' => 'DNGPAY-BOUND-001'],
        'is_valid_checksum' => true,
        'processing_status' => DngWebhookEvent::STATUS_PROCESSED,
        'created_at' => '2026-07-01 09:00:00',
    ]);
    // Later (latest) webhook call — this is the ref the export must surface.
    DngWebhookEvent::query()->create([
        'dng_payment_id' => 'DNGPAY-BOUND-001',
        'dng_payment_request_id' => $paymentRequest->id,
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        'payload_hash' => hash('sha256', 'call-2'),
        'payload' => ['PaymentId' => 'DNGPAY-BOUND-001'],
        'is_valid_checksum' => true,
        'processing_status' => DngWebhookEvent::STATUS_PROCESSED,
        'created_at' => '2026-07-01 10:05:00',
    ]);

    $export = new DngPaymentRequestExport([], app(StudentReferenceReader::class));
    $export->prepareRows([$paymentRequest->load('semester', 'payment')]);
    $row = $export->map($paymentRequest);

    expect($row[0])->toBe('DNGX001')
        ->and($row[1])->toBe('STUDENT DNGX001')
        ->and($row[2])->toBe($this->campus->code)
        ->and($row[3])->toBe($this->semester->name)
        ->and($row[4])->toBe('HP')
        ->and($row[5])->toBe('DNGX-ITEM-001')
        ->and($row[6])->toBe(2500000.0)
        ->and($row[7])->toBe(DngPaymentRequest::STATUS_PAID_INVOICED)
        ->and($row[8])->toBe('DNGPAY-BOUND-001')
        ->and($row[9])->toBe('TXN-001')
        ->and($row[10])->toBe('DNGPAY-BOUND-001')
        ->and($row[11])->toBe($payment->id)
        ->and($row[12])->toBe(Payment::METHOD_GATEWAY)
        ->and($row[13])->toBe(2500000.0)
        ->and($row[14])->toContain('2026-07-01T10:00:00')
        ->and($row[15])->toBe('INV-2026-0099')
        ->and($row[16])->toBeNull()
        ->and($row[17])->not->toBeNull();
});

it('exports a pending request with no webhook and no bridged payment as empty columns', function (): void {
    $student = makeDngExportStudent($this, 'DNGX002');

    $paymentRequest = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $this->campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'DNGX-ITEM-002',
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    $export = new DngPaymentRequestExport([], app(StudentReferenceReader::class));
    $export->prepareRows([$paymentRequest->load('semester', 'payment')]);
    $row = $export->map($paymentRequest);

    expect($row[8])->toBeNull() // DNG Payment Ref
        ->and($row[10])->toBeNull() // Latest Webhook Ref
        ->and($row[11])->toBeNull(); // Bridged Payment ID
});

it('surfaces the raw webhook ref for a request that never bound (checksum mismatch)', function (): void {
    $student = makeDngExportStudent($this, 'DNGX003');

    $paymentRequest = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $this->campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'DNGX-ITEM-003',
        'amount' => 1_500_000,
        'status' => DngPaymentRequest::STATUS_FAILED,
    ]);

    DngWebhookEvent::query()->create([
        'dng_payment_id' => 'DNGPAY-UNBOUND-003',
        'dng_payment_request_id' => $paymentRequest->id,
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => hash('sha256', 'mismatch-call'),
        'payload' => ['PaymentId' => 'DNGPAY-UNBOUND-003'],
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_MISMATCH,
    ]);

    $export = new DngPaymentRequestExport([], app(StudentReferenceReader::class));
    $export->prepareRows([$paymentRequest->load('semester', 'payment')]);
    $row = $export->map($paymentRequest);

    expect($row[8])->toBeNull() // never bound onto the request
        ->and($row[10])->toBe('DNGPAY-UNBOUND-003'); // still visible for reconciliation
});

it('scopes the export query to the requested semester only', function (): void {
    $otherSemester = Semester::factory()->create();

    $studentInTarget = makeDngExportStudent($this, 'DNGX004');
    $studentInOther = makeDngExportStudent($this, 'DNGX005');

    $targetRequest = DngPaymentRequest::query()->create([
        'student_id' => $studentInTarget->id,
        'campus_code' => $this->campus->code,
        'student_code' => $studentInTarget->student_id,
        'fee_type' => 'HP',
        'item_id' => 'DNGX-ITEM-004',
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'semester_id' => $this->semester->id,
    ]);
    DngPaymentRequest::query()->create([
        'student_id' => $studentInOther->id,
        'campus_code' => $this->campus->code,
        'student_code' => $studentInOther->student_id,
        'fee_type' => 'HP',
        'item_id' => 'DNGX-ITEM-005',
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'semester_id' => $otherSemester->id,
    ]);

    $export = new DngPaymentRequestExport(['semester_id' => $this->semester->id], app(StudentReferenceReader::class));
    $ids = $export->query()->pluck('id')->all();

    expect($ids)->toBe([$targetRequest->id]);
});
