<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function surplusDispositionUser(array $permissions): User
{
    $user = User::factory()->create();
    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
    app()->singleton(CampusPermissionReader::class, fn () => $mock);

    return $user;
}

function surplusLeftSchoolEnrollment(Student $student): void
{
    ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => 'graduated',
        'study_stage' => null,
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
}

beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);
    $campus = Campus::factory()->create();
    app()->singleton('campus', fn () => $campus);
    session(['current_campus_id' => $campus->id]);
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($campus)->forProgram($program)
        ->state(['intake' => 1, 'intake_semester_id' => $semester->id])->create();
    $this->payment = Payment::query()->create([
        'student_id' => $this->student->id, 'amount' => 100000, 'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng', 'paid_at' => now(), 'status' => Payment::STATUS_COMPLETED,
    ]);
});

it('rejects refund with the same policy message for every caller', function (array $permissions) {
    $user = surplusDispositionUser($permissions);
    $payload = [
        'idempotency_key' => (string) Str::uuid(),
        'type' => 'refund',
        'amount' => 50000,
        'external_reference' => 'BANK-RF-42',
    ];

    $this->actingAs($user)
        ->from("/finance/students/{$this->student->id}")
        ->post("/finance/payments/{$this->payment->id}/surplus-dispositions", $payload)
        ->assertRedirect("/finance/students/{$this->student->id}")
        ->assertSessionHasErrors(['type' => PaymentSurplusDisposition::REFUND_BLOCKED_MESSAGE]);

    expect(PaymentSurplusDisposition::query()->count())->toBe(0)
        ->and($this->payment->fresh()->status)->toBe(Payment::STATUS_COMPLETED);
})->with([
    'refund permission' => [['refund_finance_payment']],
    'no refund permission' => [['allocate_finance_payment']],
    'forfeit permission' => [['forfeit_finance_payment_surplus']],
]);

it('does not treat acknowledge no refund as a disposition instruction', function () {
    $user = surplusDispositionUser(['refund_finance_payment']);
    $this->actingAs($user)
        ->from("/finance/students/{$this->student->id}")
        ->post("/finance/payments/{$this->payment->id}/surplus-dispositions", [
            'idempotency_key' => (string) Str::uuid(),
            'type' => 'refund',
            'amount' => 100000,
            'acknowledge_no_refund' => true,
        ])
        ->assertSessionHasErrors(['type' => PaymentSurplusDisposition::REFUND_BLOCKED_MESSAGE]);

    expect(PaymentSurplusDisposition::query()->count())->toBe(0);
});

it('refuses retain_forfeit when the operator is also the approver', function () {
    surplusLeftSchoolEnrollment($this->student);
    $user = surplusDispositionUser(['forfeit_finance_payment_surplus']);

    $this->actingAs($user)
        ->from("/finance/students/{$this->student->id}")
        ->post("/finance/payments/{$this->payment->id}/surplus-dispositions", [
            'idempotency_key' => (string) Str::uuid(),
            'type' => 'retain_forfeit',
            'amount' => 100000,
            'policy_code' => 'POL-SURPLUS-01',
            'reason' => 'Approved retention decision',
            'approved_by' => $user->id,
        ])
        ->assertSessionHasErrors('approved_by');

    expect(PaymentSurplusDisposition::query()->count())->toBe(0);
});

it('refuses retain_forfeit while the student is still enrolled', function () {
    $user = surplusDispositionUser(['forfeit_finance_payment_surplus']);
    $approver = User::factory()->create();

    $this->actingAs($user)
        ->from("/finance/students/{$this->student->id}")
        ->post("/finance/payments/{$this->payment->id}/surplus-dispositions", [
            'idempotency_key' => (string) Str::uuid(),
            'type' => 'retain_forfeit',
            'amount' => 100000,
            'policy_code' => 'POL-SURPLUS-01',
            'reason' => 'Approved retention decision',
            'approved_by' => $approver->id,
        ])
        ->assertSessionHasErrors('type');

    expect(PaymentSurplusDisposition::query()->count())->toBe(0);
});

it('records retain_forfeit when a second person approves a leaver surplus', function () {
    surplusLeftSchoolEnrollment($this->student);
    $user = surplusDispositionUser(['forfeit_finance_payment_surplus']);
    $approver = User::factory()->create();
    $key = (string) Str::uuid();
    $payload = [
        'idempotency_key' => $key,
        'type' => 'retain_forfeit',
        'amount' => 100000,
        'policy_code' => 'POL-SURPLUS-01',
        'reason' => 'Approved retention decision',
        'approved_by' => $approver->id,
    ];

    $this->actingAs($user)->post("/finance/payments/{$this->payment->id}/surplus-dispositions", $payload)->assertRedirect();
    $this->actingAs($user)->post("/finance/payments/{$this->payment->id}/surplus-dispositions", $payload)->assertRedirect();

    $row = PaymentSurplusDisposition::query()->first();
    expect(PaymentSurplusDisposition::query()->count())->toBe(1)
        ->and($row->type)->toBe('retain_forfeit')
        ->and((int) $row->approved_by)->toBe((int) $approver->id)
        ->and($row->evidence['created_by_user_id'])->toBe($user->id)
        ->and($row->audit_signature)->toHaveLength(64);
});
