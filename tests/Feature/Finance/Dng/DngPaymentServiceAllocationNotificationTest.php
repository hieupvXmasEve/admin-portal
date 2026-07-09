<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.dng.hash_key' => 'test',
        'services.dng.access_code' => 'TEST',
        'services.dng.client_code' => 'TEST',
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

    $this->hqDept = Department::factory()->create(['code' => 'HQ']);
    $this->hqUser = User::factory()->create();
    DepartmentMembership::factory()->create([
        'department_id' => $this->hqDept->id,
        'user_id' => $this->hqUser->id,
        'is_active' => true,
    ]);
});

function makeUnbridgedRequest(Student $student): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU001',
        'fee_type' => 'tuition',
        'item_id' => 'ITEM001',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'PAY001',
        'paid_at' => now(),
    ]);
}

function makeAllocatedPayment(Student $student, float $amount): Payment
{
    return Payment::create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
}

function makeBridgingDngPaymentService(
    PaymentService $paymentServiceMock,
): DngPaymentService {
    $dngClientMock = Mockery::mock(DngClient::class);

    return new DngPaymentService(
        $dngClientMock,
        $paymentServiceMock,
        app(PublishDomainEventAction::class),
    );
}

it('publishes finance.dng_payment_allocated with success body when allocations exist', function () {
    $request = makeUnbridgedRequest($this->student);
    $payment = makeAllocatedPayment($this->student, 5000000);

    $fakeAllocation = new stdClass;
    $fakeAllocation->amount = 5000000;
    $allocations = collect([$fakeAllocation]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('recordPayment')->andReturn($payment);
    $paymentServiceMock->shouldReceive('autoAllocatePayment')->andReturn($allocations);

    $service = makeBridgingDngPaymentService($paymentServiceMock);
    $service->bridgeToPayment($request);

    $outbox = NotificationEventOutbox::where('event_name', 'finance.dng_payment_allocated')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['type_key'])->toBe('dng_payment_allocated')
        ->and($outbox->payload['channels'])->toBe(['realtime'])
        ->and($outbox->payload['recipient_targets'][0]['type'])->toBe('department')
        ->and($outbox->payload['recipient_targets'][0]['id'])->toBe($this->hqDept->id)
        ->and($outbox->payload['data']['title'])->toContain('thành công');
});

it('publishes finance.dng_payment_allocated with warning body when allocations are empty', function () {
    $request = makeUnbridgedRequest($this->student);
    $payment = makeAllocatedPayment($this->student, 5000000);

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('recordPayment')->andReturn($payment);
    $paymentServiceMock->shouldReceive('autoAllocatePayment')->andReturn(collect());

    $service = makeBridgingDngPaymentService($paymentServiceMock);
    $service->bridgeToPayment($request);

    $outbox = NotificationEventOutbox::where('event_name', 'finance.dng_payment_allocated')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['data']['title'])->toContain('Cảnh báo')
        ->and($outbox->payload['data']['body'])->toContain('không tìm thấy khoản phí');
});

it('does not throw when HQ department not found and logs warning', function () {
    $this->hqDept->update(['code' => 'OTHER']);

    $request = makeUnbridgedRequest($this->student);
    $payment = makeAllocatedPayment($this->student, 5000000);

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('recordPayment')->andReturn($payment);
    $paymentServiceMock->shouldReceive('autoAllocatePayment')->andReturn(collect());

    Log::shouldReceive('warning')
        ->atLeast()->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'HQ department not found'));
    Log::shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

    $service = makeBridgingDngPaymentService($paymentServiceMock);

    expect(fn () => $service->bridgeToPayment($request))->not->toThrow(Throwable::class);

    expect(NotificationEventOutbox::where('event_name', 'finance.dng_payment_allocated')->exists())->toBeFalse();
});
