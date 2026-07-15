<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['view_finance_dng_payment_requests', 'create_finance_payments', 'resolve_finance_dng_receipt_exceptions']);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

function createDngPaymentRequestForStatus(object $context, string $status, ?Campus $campus = null): DngPaymentRequest
{
    $campus ??= $context->campus;

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($context->program)
        ->state([
            'student_id' => 'DNG001',
            'full_name' => 'DNG Student',
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    return DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Tuition payment',
        'item_id' => 'DNG-ITEM-001',
        'amount' => 2500000,
        'status' => $status,
    ]);
}

it('cancels a pending dng payment request', function () {
    $request = createDngPaymentRequestForStatus($this, DngPaymentRequest::STATUS_PENDING);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->from(route('finance.dng.payment-requests.index'))
        ->post(route('finance.dng.payment-requests.cancel', $request), ['_token' => 'test-token'])
        ->assertRedirect(route('finance.dng.payment-requests.index'))
        ->assertSessionHas('inertia.flash_data', ['success' => 'DNG payment request cancelled.']);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
});

it('does not cancel a paid dng payment request', function () {
    $request = createDngPaymentRequestForStatus($this, DngPaymentRequest::STATUS_PAID_UNINVOICED);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->from(route('finance.dng.payment-requests.index'))
        ->post(route('finance.dng.payment-requests.cancel', $request), ['_token' => 'test-token'])
        ->assertRedirect(route('finance.dng.payment-requests.index'))
        ->assertSessionHas('inertia.flash_data', ['error' => 'This DNG payment request cannot be cancelled.']);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_UNINVOICED);
});

it('does not cancel a dng payment request from another campus', function () {
    $otherCampus = Campus::factory()->create();
    $request = createDngPaymentRequestForStatus($this, DngPaymentRequest::STATUS_PENDING, $otherCampus);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.dng.payment-requests.cancel', $request), ['_token' => 'test-token'])
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PENDING);
});

it('lets authorized staff resolve a held DNG outcome through the review endpoint', function () {
    $request = createDngPaymentRequestForStatus($this, DngPaymentRequest::STATUS_NEEDS_REVIEW);
    $request->update([
        'billing_account_id' => BillingAccount::query()->firstOrCreate(['student_id' => $request->student_id])->id,
        'active_slot_key' => 'review-slot-'.$request->id,
    ]);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->from(route('finance.dng.payment-requests.show', $request))
        ->post(route('finance.dng.payment-requests.resolve-outcome', $request), [
            '_token' => 'test-token',
            'outcome' => 'failed',
            'reason' => 'Provider confirmed the request was not created.',
        ])
        ->assertRedirect(route('finance.dng.payment-requests.show', $request))
        ->assertSessionHas('inertia.flash_data', ['success' => 'DNG reservation resolved as failed.']);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_FAILED)
        ->and($request->fresh()->active_slot_key)->toBeNull();
});
