<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
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
            'student_id' => 'STD001',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    Sanctum::actingAs($this->student);
});

it('returns qr access data for the authenticated student dng request', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Tuition request',
        'item_id' => 'ITEM-QR-001',
        'amount' => 1500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $serviceMock = Mockery::mock(DngPaymentService::class);
    $serviceMock->shouldReceive('createQrAccess')
        ->once()
        ->withArgs(fn (DngPaymentRequest $dngRequest, array $feeTypes) => $dngRequest->is($request) && $feeTypes === ['HP'])
        ->andReturn([
            'code' => 200,
            'type' => 'success',
            'message' => 'OK',
            'data' => [
                'LinkQRCode' => 'https://example.test/qr-link',
            ],
        ]);
    app()->instance(DngPaymentService::class, $serviceMock);

    $this->postJson("/api/v1/student/finance/dng-requests/{$request->id}/qr")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment_method', 'qr')
        ->assertJsonPath('data.payment_url', 'https://example.test/qr-link');
});

it('returns installment access data for the authenticated student dng request', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Installment request',
        'item_id' => 'ITEM-INS-001',
        'amount' => 2500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $serviceMock = Mockery::mock(DngPaymentService::class);
    $serviceMock->shouldReceive('createInstallmentAccess')
        ->once()
        ->withArgs(fn (DngPaymentRequest $dngRequest, array $feeTypes) => $dngRequest->is($request) && $feeTypes === ['HP'])
        ->andReturn([
            'code' => 200,
            'type' => 'success',
            'message' => 'Thành công!',
            'data' => [
                'PaymentUrl' => 'https://example.test/installment-link',
            ],
        ]);
    app()->instance(DngPaymentService::class, $serviceMock);

    $this->postJson("/api/v1/student/finance/dng-requests/{$request->id}/installment")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment_method', 'installment')
        ->assertJsonPath('data.payment_url', 'https://example.test/installment-link');
});

it('does not expose qr payload on student dng request detail', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Tuition request',
        'item_id' => 'ITEM-DETAIL-001',
        'amount' => 1000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'qr_payload' => ['legacy' => true],
    ]);

    $this->getJson("/api/v1/student/finance/dng-requests/{$request->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.qr_payload');
});
