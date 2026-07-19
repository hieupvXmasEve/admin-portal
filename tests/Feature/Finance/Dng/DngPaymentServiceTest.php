<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Services\PaymentService;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function guardedDngService(DngClient $client): DngPaymentService
{
    return new DngPaymentService(
        $client,
        Mockery::mock(PaymentService::class),
        Mockery::mock(DomainEventPublisher::class),
    );
}

function guardedDngRequest(Student $student): DngPaymentRequest
{
    return DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
}

it('only sends an already reserved DNG payload to the provider', function (): void {
    $payload = ['student_code' => 'STU001', 'campus_code' => 'FAUHN', 'type' => 'HP'];
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('buildInsertNewRecordPayload')->once()->with($payload)->andReturn(['Record' => 'guarded']);
    $client->shouldReceive('insertNewRecord')->once()->with($payload, ['Record' => 'guarded'])->andReturn(['Code' => 1, 'data' => []]);

    expect(guardedDngService($client)->pushReserved($payload))->toBe(['Code' => 1, 'data' => []]);
});

it('keeps QR and installment access as read-only provider access operations', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus(Campus::factory()->create())->create([
        'student_id' => 'STU001',
        'intake' => 2024,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $request = guardedDngRequest($student);
    $client = Mockery::mock(DngClient::class);
    $client->shouldReceive('createVirtualAccountByFeeType')->once()->andReturn(['data' => ['PaymentUrl' => 'https://example.test/qr']]);
    $client->shouldReceive('createFoxpayPaymentByFeeType')->once()->andReturn(['data' => ['PaymentUrl' => 'https://example.test/installment']]);
    $service = guardedDngService($client);

    expect($service->createQrAccess($request, ['HP'])['data']['PaymentUrl'])->toBe('https://example.test/qr')
        ->and($service->createInstallmentAccess($request, ['HP'])['data']['PaymentUrl'])->toBe('https://example.test/installment')
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});
