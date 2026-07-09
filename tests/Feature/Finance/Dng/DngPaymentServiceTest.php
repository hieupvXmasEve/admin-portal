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
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.dng.hash_key' => '2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J',
        'services.dng.access_code' => 'TEST_ACCESS',
        'services.dng.api_code' => 'TEST_API',
        'services.dng.client_code' => 'TEST_CLIENT',
        'services.dng.login' => 'TEST_LOGIN',
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
});

function makeDngPaymentService(DngClient $dngClientMock, PaymentService $paymentServiceMock): DngPaymentService
{
    $publishDomainEventActionMock = Mockery::mock(PublishDomainEventAction::class);
    $publishDomainEventActionMock->shouldIgnoreMissing();

    return new DngPaymentService($dngClientMock, $paymentServiceMock, $publishDomainEventActionMock);
}

it('stores the exact DNG insert payload on successful push', function () {
    $chargeData = [
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Settlement no-cash tuition request',
        'item_id' => 'ITEM001',
        'amount' => '11000.0',
        'type' => 'payment',
        'student_name' => 'Test Student',
        'email' => 'student@example.com',
        'estimate_time' => '2026-03-27 09:00:00',
        'student_address' => '123 Test Street',
        'cccd' => '012345678901',
    ];

    $dngClient = app(DngClient::class);
    $expectedPayload = $dngClient->buildInsertNewRecordPayload($chargeData);

    $dngClientMock = Mockery::mock(DngClient::class);
    $dngClientMock->shouldReceive('buildInsertNewRecordPayload')
        ->once()
        ->with($chargeData)
        ->andReturn($expectedPayload);
    $dngClientMock->shouldReceive('insertNewRecord')
        ->once()
        ->with($chargeData, $expectedPayload)
        ->andReturn([
            'data' => [
                'Id' => 'REC001',
                'TransactionID' => 'TXN001',
                'PaymentId' => 'PAY001',
            ],
        ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);

    $service = makeDngPaymentService($dngClientMock, $paymentServiceMock);

    $request = $service->createAndPush($this->student, $chargeData);

    expect($request->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($request->description)->toBe('Settlement no-cash tuition request')
        ->and($request->push_payload)
        ->toMatchArray($expectedPayload)
        ->and((float) $request->push_payload['Amount'])->toBe((float) $expectedPayload['Amount'])
        ->and($request->dng_transaction_id)->toBe('TXN001')
        ->and($request->dng_payment_id)->toBe('PAY001');
});

it('cancels previous unpaid requests of the same fee type after successful push', function () {
    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Old pending request',
        'item_id' => 'OLD-PENDING',
        'amount' => 9000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Old pushed request',
        'item_id' => 'OLD-PUSHED',
        'amount' => 10000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'OTHER',
        'description' => 'Different fee type',
        'item_id' => 'KEEP-OTHER',
        'amount' => 8000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $chargeData = [
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Replacement request',
        'item_id' => 'NEW-ITEM',
        'amount' => '11000.0',
        'type' => 'payment',
        'student_name' => 'Test Student',
        'email' => 'student@example.com',
        'estimate_time' => '2026-03-27 09:00:00',
        'student_address' => '123 Test Street',
        'cccd' => '012345678901',
    ];

    $dngClient = app(DngClient::class);
    $expectedPayload = $dngClient->buildInsertNewRecordPayload($chargeData);

    $dngClientMock = Mockery::mock(DngClient::class);
    $dngClientMock->shouldReceive('buildInsertNewRecordPayload')
        ->once()
        ->with($chargeData)
        ->andReturn($expectedPayload);
    $dngClientMock->shouldReceive('insertNewRecord')
        ->once()
        ->with($chargeData, $expectedPayload)
        ->andReturn([
            'data' => [
                'Id' => 'REC002',
                'TransactionID' => 'TXN002',
                'PaymentId' => 'PAY002',
            ],
        ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);

    $service = makeDngPaymentService($dngClientMock, $paymentServiceMock);

    $request = $service->createAndPush($this->student, $chargeData);

    expect($request->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and(DngPaymentRequest::query()->where('item_id', 'OLD-PENDING')->value('status'))->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and(DngPaymentRequest::query()->where('item_id', 'OLD-PUSHED')->value('status'))->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and(DngPaymentRequest::query()->where('item_id', 'KEEP-OTHER')->value('status'))->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('stores the exact DNG insert payload when push fails', function () {
    $chargeData = [
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Settlement no-cash tuition request',
        'item_id' => 'ITEM001',
        'amount' => '11000.0',
        'type' => 'payment',
        'student_name' => 'Test Student',
        'email' => 'student@example.com',
        'estimate_time' => '2026-03-27 09:00:00',
        'student_address' => '123 Test Street',
        'cccd' => null,
    ];

    $dngClient = app(DngClient::class);
    $expectedPayload = $dngClient->buildInsertNewRecordPayload($chargeData);

    $dngClientMock = Mockery::mock(DngClient::class);
    $dngClientMock->shouldReceive('buildInsertNewRecordPayload')
        ->once()
        ->with($chargeData)
        ->andReturn($expectedPayload);
    $dngClientMock->shouldReceive('insertNewRecord')
        ->once()
        ->with($chargeData, $expectedPayload)
        ->andThrow(new RuntimeException('DNG unavailable'));

    $paymentServiceMock = Mockery::mock(PaymentService::class);

    $service = makeDngPaymentService($dngClientMock, $paymentServiceMock);

    expect(fn () => $service->createAndPush($this->student, $chargeData))
        ->toThrow(RuntimeException::class, 'DNG unavailable');

    $request = DngPaymentRequest::query()->sole();

    expect($request->status)->toBe(DngPaymentRequest::STATUS_FAILED)
        ->and($request->description)->toBe('Settlement no-cash tuition request')
        ->and($request->push_payload)
        ->toMatchArray($expectedPayload)
        ->and((float) $request->push_payload['Amount'])->toBe((float) $expectedPayload['Amount'])
        ->and($request->error_message)->toBe('DNG unavailable');
});

it('keeps previous unpaid requests unchanged when replacement push fails', function () {
    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Old pending request',
        'item_id' => 'OLD-PENDING',
        'amount' => 9000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Old pushed request',
        'item_id' => 'OLD-PUSHED',
        'amount' => 10000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $chargeData = [
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Replacement request',
        'item_id' => 'NEW-ITEM',
        'amount' => '11000.0',
        'type' => 'payment',
        'student_name' => 'Test Student',
        'email' => 'student@example.com',
        'estimate_time' => '2026-03-27 09:00:00',
        'student_address' => '123 Test Street',
        'cccd' => null,
    ];

    $dngClient = app(DngClient::class);
    $expectedPayload = $dngClient->buildInsertNewRecordPayload($chargeData);

    $dngClientMock = Mockery::mock(DngClient::class);
    $dngClientMock->shouldReceive('buildInsertNewRecordPayload')
        ->once()
        ->with($chargeData)
        ->andReturn($expectedPayload);
    $dngClientMock->shouldReceive('insertNewRecord')
        ->once()
        ->with($chargeData, $expectedPayload)
        ->andThrow(new RuntimeException('DNG unavailable'));

    $paymentServiceMock = Mockery::mock(PaymentService::class);

    $service = makeDngPaymentService($dngClientMock, $paymentServiceMock);

    expect(fn () => $service->createAndPush($this->student, $chargeData))
        ->toThrow(RuntimeException::class, 'DNG unavailable');

    expect(DngPaymentRequest::query()->where('item_id', 'OLD-PENDING')->value('status'))->toBe(DngPaymentRequest::STATUS_PENDING)
        ->and(DngPaymentRequest::query()->where('item_id', 'OLD-PUSHED')->value('status'))->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and(DngPaymentRequest::query()->where('item_id', 'NEW-ITEM')->value('status'))->toBe(DngPaymentRequest::STATUS_FAILED);
});

it('returns qr access data without storing qr payload', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Tuition request',
        'item_id' => 'ITEM-QR-001',
        'amount' => 11000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $dngClientMock = Mockery::mock(DngClient::class);
    $dngClientMock->shouldReceive('createVirtualAccountByFeeType')
        ->once()
        ->with([
            'student_code' => 'STU001',
            'campus_code' => 'FAUHN',
            'fee_types' => ['HP'],
        ])
        ->andReturn([
            'Code' => 200,
            'data' => ['PaymentUrl' => 'https://example.test/qr'],
        ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);

    $service = makeDngPaymentService($dngClientMock, $paymentServiceMock);

    $response = $service->createQrAccess($request, ['HP']);

    expect($response['data']['PaymentUrl'])->toBe('https://example.test/qr')
        ->and($request->fresh()->qr_payload)->toBeNull()
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('allocates payment to specific charge when finance_charge_id is set on DNG request', function () {
    $charge = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'description' => 'Retake fee',
        'amount' => 2500000,
        'status' => FinanceCharge::STATUS_ACTIVE,
        'effective_at' => now(),
    ]);

    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-HL-001',
        'amount' => 2500000,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'PAY-HL-001',
        'paid_at' => now(),
        'finance_charge_id' => $charge->id,
    ]);

    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 2500000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('recordPayment')->andReturn($payment);
    // Must call allocatePayment with the specific charge, NOT autoAllocatePayment
    $paymentServiceMock->shouldReceive('allocatePayment')
        ->once()
        ->with($payment->id, [$charge->id => 2500000])
        ->andReturn(collect());
    $paymentServiceMock->shouldNotReceive('autoAllocatePayment');

    $dngClientMock = Mockery::mock(DngClient::class);
    $publishMock = Mockery::mock(PublishDomainEventAction::class);
    $publishMock->shouldIgnoreMissing();

    $service = new DngPaymentService($dngClientMock, $paymentServiceMock, $publishMock);
    $result = $service->bridgeToPayment($request);

    expect($result)->not->toBeNull()
        ->and($request->fresh()->payment_id)->toBe($payment->id);
});

it('falls back to auto-allocate when finance_charge_id is not set on DNG request', function () {
    $request = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-HP-001',
        'amount' => 11000000,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'PAY-HP-001',
        'paid_at' => now(),
        'finance_charge_id' => null,
    ]);

    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 11000000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('recordPayment')->andReturn($payment);
    // Must fall back to auto-allocate, NOT allocatePayment
    $paymentServiceMock->shouldReceive('autoAllocatePayment')
        ->once()
        ->with($payment->id)
        ->andReturn(collect());
    $paymentServiceMock->shouldNotReceive('allocatePayment');

    $dngClientMock = Mockery::mock(DngClient::class);
    $publishMock = Mockery::mock(PublishDomainEventAction::class);
    $publishMock->shouldIgnoreMissing();

    $service = new DngPaymentService($dngClientMock, $paymentServiceMock, $publishMock);
    $result = $service->bridgeToPayment($request);

    expect($result)->not->toBeNull()
        ->and($request->fresh()->payment_id)->toBe($payment->id);
});

it('returns installment access data without storing qr payload', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
        'description' => 'Installment request',
        'item_id' => 'ITEM-FOX-001',
        'amount' => 11000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $dngClientMock = Mockery::mock(DngClient::class);
    $dngClientMock->shouldReceive('createFoxpayPaymentByFeeType')
        ->once()
        ->with([
            'student_code' => 'STU001',
            'campus_code' => 'FAUHN',
            'fee_types' => ['HP'],
        ])
        ->andReturn([
            'code' => 200,
            'data' => ['PaymentUrl' => 'https://example.test/installment'],
        ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);

    $service = makeDngPaymentService($dngClientMock, $paymentServiceMock);

    $response = $service->createInstallmentAccess($request, ['HP']);

    expect($response['data']['PaymentUrl'])->toBe('https://example.test/installment')
        ->and($request->fresh()->qr_payload)->toBeNull()
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});
