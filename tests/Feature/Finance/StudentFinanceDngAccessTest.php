<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\BillingAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->withDngMapping('FAUHN')->create();
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

    $this->billingAccount = BillingAccount::query()
        ->where('student_id', $this->student->id)
        ->firstOrFail();
});

it('returns qr access data for the authenticated student dng request', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'billing_account_id' => $this->billingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Tuition request',
        'item_id' => 'ITEM-QR-001',
        'amount' => 1500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'billing_account_id' => $this->billingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HL',
        'description' => 'Retake request',
        'item_id' => 'ITEM-QR-002',
        'amount' => 750000,
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
        'billing_account_id' => $this->billingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
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
        'billing_account_id' => $this->billingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
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

it('fails closed for pay-all when multiple active fee types have no provider mapping', function () {
    foreach (['HP', 'HL'] as $index => $feeType) {
        DngPaymentRequest::query()->create([
            'student_id' => $this->student->id,
            'billing_account_id' => $this->billingAccount->id,
            'campus_code' => 'FAUHN',
            'provider_rail' => 'dng',
            'student_code' => $this->student->student_id,
            'fee_type' => $feeType,
            'description' => "Fee {$feeType}",
            'item_id' => "ITEM-ALL-00{$index}",
            'amount' => 1000000,
            'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        ]);
    }

    $serviceMock = Mockery::mock(DngPaymentService::class);
    $serviceMock->shouldNotReceive('createQrAccess');
    app()->instance(DngPaymentService::class, $serviceMock);

    $this->postJson('/api/v1/student/finance/dng/qr')
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'DNG_PAYMENT_ACCESS_UNAVAILABLE');
});

it('marks pay-all unavailable when any active fee type is held for review', function () {
    foreach ([
        ['fee_type' => 'HP', 'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG],
        ['fee_type' => 'HL', 'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW],
    ] as $index => $item) {
        DngPaymentRequest::query()->create([
            'student_id' => $this->student->id,
            'billing_account_id' => $this->billingAccount->id,
            'campus_code' => 'FAUHN',
            'provider_rail' => 'dng',
            'student_code' => $this->student->student_id,
            'fee_type' => $item['fee_type'],
            'description' => "Fee {$item['fee_type']}",
            'item_id' => "ITEM-HELD-00{$index}",
            'amount' => 1000000,
            'status' => $item['status'],
        ]);
    }

    $this->getJson('/api/v1/student/finance/dng-requests/all')
        ->assertOk()
        ->assertJsonPath('data.pending.payment_access.status', 'unavailable');
});

it('fails closed when the selected fee type is held for review', function () {
    $request = DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'billing_account_id' => $this->billingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Held tuition request',
        'item_id' => 'ITEM-HELD-001',
        'amount' => 1500000,
        'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ]);

    $serviceMock = Mockery::mock(DngPaymentService::class);
    $serviceMock->shouldNotReceive('createQrAccess');
    app()->instance(DngPaymentService::class, $serviceMock);

    $this->postJson("/api/v1/student/finance/dng-requests/{$request->id}/qr")
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'DNG_PAYMENT_ACCESS_UNAVAILABLE');
});

it('does not authorize a dng request belonging to another student', function () {
    $otherStudent = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'STD002',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    $otherBillingAccount = BillingAccount::query()
        ->where('student_id', $otherStudent->id)
        ->firstOrFail();
    $request = DngPaymentRequest::query()->create([
        'student_id' => $otherStudent->id,
        'billing_account_id' => $otherBillingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => $otherStudent->student_id,
        'fee_type' => 'HP',
        'description' => 'Other student request',
        'item_id' => 'ITEM-OTHER-001',
        'amount' => 1500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $this->postJson("/api/v1/student/finance/dng-requests/{$request->id}/qr")
        ->assertNotFound();
});
