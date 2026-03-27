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
use App\Modules\Finance\Services\PaymentService;
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

it('stores the exact DNG insert payload on successful push', function () {
    $chargeData = [
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
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

    $service = new DngPaymentService($dngClientMock, $paymentServiceMock);

    $request = $service->createAndPush($this->student, $chargeData);

    expect($request->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($request->push_payload)
        ->toMatchArray($expectedPayload)
        ->and((float) $request->push_payload['Amount'])->toBe((float) $expectedPayload['Amount'])
        ->and($request->dng_transaction_id)->toBe('TXN001')
        ->and($request->dng_payment_id)->toBe('PAY001');
});

it('stores the exact DNG insert payload when push fails', function () {
    $chargeData = [
        'campus_code' => 'FAUHN',
        'student_code' => 'STU001',
        'fee_type' => 'HP',
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

    $service = new DngPaymentService($dngClientMock, $paymentServiceMock);

    expect(fn () => $service->createAndPush($this->student, $chargeData))
        ->toThrow(RuntimeException::class, 'DNG unavailable');

    $request = DngPaymentRequest::query()->sole();

    expect($request->status)->toBe(DngPaymentRequest::STATUS_FAILED)
        ->and($request->push_payload)
        ->toMatchArray($expectedPayload)
        ->and((float) $request->push_payload['Amount'])->toBe((float) $expectedPayload['Amount'])
        ->and($request->error_message)->toBe('DNG unavailable');
});
