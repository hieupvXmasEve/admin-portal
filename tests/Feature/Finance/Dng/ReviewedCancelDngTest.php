<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantDngCancel')) {
    function grantDngCancel(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function makeReviewedCancelStudent(object $context): Student
{
    return Student::factory()
        ->forCampus($context->campus)
        ->forProgram($context->program)
        ->state([
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

function makePendingDng(Student $student, ?int $chargeId = null): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'C1',
        'student_code' => $student->student_id,
        'fee_type' => 'tuition',
        'amount' => 1000000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'item_id' => 'ITEM-'.$student->id,
        'due_date' => now()->addDays(10),
        'finance_charge_id' => $chargeId,
    ]);
}

it('returns cancel impact with blocking reasons', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $student = makeReviewedCancelStudent($this);
    $dng = makePendingDng($student);

    actingAs($user)->getJson(route('finance.dng.payment-requests.cancel-impact', $dng))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.dng_request_id', $dng->id)
        ->assertJsonStructure(['data' => ['dng_request_id', 'linked_charges', 'blocking_reasons', 'requires_void_permission']]);
});

it('requires a reason and acknowledgement to cancel', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $student = makeReviewedCancelStudent($this);
    $dng = makePendingDng($student);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.dng.payment-requests.cancel-reviewed', $dng), [
            '_token' => 'test-token',
            'reason' => '',
            'acknowledged' => false,
        ])
        ->assertSessionHasErrors(['reason', 'acknowledged']);
});

it('cancels a pending DNG with no linked charges', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $student = makeReviewedCancelStudent($this);
    $dng = makePendingDng($student);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.dng.payment-requests.cancel-reviewed', $dng), [
            '_token' => 'test-token',
            'reason' => 'Student dropped out',
            'acknowledged' => true,
        ])
        ->assertRedirect();

    expect($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
});

it('does not require void permission when the DNG has a linked charge', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $student = makeReviewedCancelStudent($this);
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $dng = makePendingDng($student, (int) $charge->id);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.dng.payment-requests.cancel-reviewed', $dng), [
            '_token' => 'test-token',
            'reason' => 'Voiding',
            'acknowledged' => true,
        ])
        ->assertRedirect();

    expect($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('blocks reviewed cancel for a non-cancellable status', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $student = makeReviewedCancelStudent($this);
    $dng = makePendingDng($student);
    $dng->update(['status' => DngPaymentRequest::STATUS_PAID_UNINVOICED]);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.dng.payment-requests.cancel-reviewed', $dng), [
            '_token' => 'test-token',
            'reason' => 'Should not apply',
            'acknowledged' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', ['error' => 'This DNG payment request cannot be cancelled.']);

    expect($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
});
