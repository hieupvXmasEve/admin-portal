<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Queries\Student360\GetStudentFinancePaymentHistoryQuery;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function surplusDispositionUser(array $permissions): User
{
    $user = User::factory()->create();
    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->singleton(PermissionService::class, fn () => $mock);

    return $user;
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

it('requires external evidence and refund permission', function () {
    $user = surplusDispositionUser(['refund_finance_payment']);
    $key = (string) Str::uuid();
    $payload = ['idempotency_key' => $key, 'type' => 'refund', 'amount' => 50000, 'external_reference' => 'BANK-RF-42'];

    $this->actingAs($user)->post("/finance/payments/{$this->payment->id}/surplus-dispositions", $payload)->assertRedirect();
    $this->actingAs($user)->post("/finance/payments/{$this->payment->id}/surplus-dispositions", $payload)->assertRedirect();

    expect(PaymentSurplusDisposition::query()->count())->toBe(1)
        ->and(PaymentSurplusDisposition::query()->first()->audit_signature)->toHaveLength(64)
        ->and($this->payment->fresh()->status)->toBe(Payment::STATUS_COMPLETED);

    $history = app(GetStudentFinancePaymentHistoryQuery::class)->handle((int) $this->student->id);
    expect($history[0]['collected_amount'])->toBe(0.0)
        ->and($history[0]['surplus_amount'])->toBe(50000.0);
});

it('requires explicit policy reason and separate forfeit permission', function () {
    $user = surplusDispositionUser(['forfeit_finance_payment_surplus']);
    $this->actingAs($user)->post("/finance/payments/{$this->payment->id}/surplus-dispositions", [
        'idempotency_key' => (string) Str::uuid(), 'type' => 'retain_forfeit', 'amount' => 100000,
        'policy_code' => 'POL-SURPLUS-01', 'reason' => 'Approved retention decision',
    ])->assertRedirect();

    expect(PaymentSurplusDisposition::query()->first()->type)->toBe('retain_forfeit');
});

it('does not treat acknowledge no refund as a disposition instruction', function () {
    $user = surplusDispositionUser(['refund_finance_payment']);
    $this->actingAs($user)->post("/finance/payments/{$this->payment->id}/surplus-dispositions", [
        'idempotency_key' => (string) Str::uuid(), 'type' => 'refund', 'amount' => 100000,
        'acknowledge_no_refund' => true,
    ])->assertSessionHasErrors('external_reference');

    expect(PaymentSurplusDisposition::query()->count())->toBe(0);
});
