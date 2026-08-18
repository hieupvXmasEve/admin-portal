<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\StudentRegistry\Actions\SoftDeleteStudentAction;
use App\Modules\StudentRegistry\Exceptions\StudentDeletionBlockedException;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();

    session(['current_campus_id' => $this->campus->id]);

    $this->grantPermission = function (array $permissions): void {
        $permissionService = Mockery::mock(CampusPermissionReader::class);
        $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
        app()->instance(CampusPermissionReader::class, $permissionService);
    };

    ($this->grantPermission)(['delete_student']);
});

function makeDeletableStudent(Campus $campus, Semester $semester, array $state = []): Student
{
    return Student::factory()->forCampus($campus)->state([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
        ...$state,
    ])->create();
}

function addSettlementLine(Student $student, Semester $semester, BillingAccount $billingAccount, string $amount): InvoiceLine
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-DELETE-GUARD-'.uniqid('', true),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'soft_delete_guard_test',
        'source_ref' => 'soft-delete-guard:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Soft delete guard tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Soft delete guard tuition',
        'status' => 'active',
    ]);
}

function applyPaymentToLine(Student $student, InvoiceLine $line, string $paymentAmount, ?string $appliedAmount = null): void
{
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => $paymentAmount,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => $appliedAmount ?? $paymentAmount,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);
}

it('denies deletion without the delete_student permission', function () {
    ($this->grantPermission)([]);
    $student = makeDeletableStudent($this->campus, $this->semester);
    $csrfToken = 'delete-student-csrf-token';

    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => $csrfToken])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->delete(route(StudentRoutes::DESTROY, $student), ['_token' => $csrfToken])
        ->assertForbidden();

    expect(Student::find($student->id))->not->toBeNull();
});

it('denies deletion across campuses with a 404', function () {
    $student = makeDeletableStudent($this->otherCampus, $this->semester);
    $csrfToken = 'delete-student-csrf-token';

    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => $csrfToken])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->delete(route(StudentRoutes::DESTROY, $student), ['_token' => $csrfToken])
        ->assertNotFound();

    expect(Student::find($student->id))->not->toBeNull();
});

it('soft-deletes a student with a clean finance position', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    $csrfToken = 'delete-student-csrf-token';

    $response = actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => $csrfToken])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->delete(route(StudentRoutes::DESTROY, $student), ['_token' => $csrfToken]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    expect(Student::find($student->id))->toBeNull()
        ->and(Student::withTrashed()->find($student->id))->not->toBeNull()
        ->and(Student::withTrashed()->find($student->id)->deleted_at)->not->toBeNull();
});

it('hides a deleted student from the index list', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    SoftDeleteStudentAction::run($student, (int) $this->campus->id);

    ($this->grantPermission)(['delete_student', 'view_student']);

    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Index')
            ->where('students.data', fn ($rows): bool => ! collect($rows)->contains('id', $student->id))
        );
});

it('blocks deletion when the student has no billing account', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    BillingAccount::query()->where('student_id', $student->id)->delete();

    expect(fn () => SoftDeleteStudentAction::run($student, (int) $this->campus->id))
        ->toThrow(StudentDeletionBlockedException::class);

    expect(Student::find($student->id))->not->toBeNull();
});

it('blocks deletion when the finance position is invalid', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-DELETE-GUARD-INVALID',
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5_000_000,
        'description' => 'Legacy tuition without obligation',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 5_000_000,
        'description_snapshot' => 'Legacy tuition without obligation',
        'status' => 'active',
    ]);

    expect(fn () => SoftDeleteStudentAction::run($student, (int) $this->campus->id))
        ->toThrow(StudentDeletionBlockedException::class);

    expect(Student::find($student->id))->not->toBeNull()
        ->and($billingAccount)->not->toBeNull();
});

it('blocks deletion when the student owes an outstanding balance', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $line = addSettlementLine($student, $this->semester, $billingAccount, '1000000.00');
    applyPaymentToLine($student, $line, '400000.00');

    expect(fn () => SoftDeleteStudentAction::run($student, (int) $this->campus->id))
        ->toThrow(StudentDeletionBlockedException::class);

    expect(Student::find($student->id))->not->toBeNull();
});

it('blocks deletion when the student has an unapplied cash surplus owed as a refund', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $line = addSettlementLine($student, $this->semester, $billingAccount, '1000000.00');
    // Payment exceeds the line, but only the line's own amount is applied to
    // it (this repo never spills a surplus onto other fees) — the surplus
    // sits unapplied, which is the real "owed a refund" shape, distinct from
    // the invalid CASH_EXCEEDS_NET_DUE case where the excess is over-applied.
    applyPaymentToLine($student, $line, '1500000.00', '1000000.00');

    expect(fn () => SoftDeleteStudentAction::run($student, (int) $this->campus->id))
        ->toThrow(StudentDeletionBlockedException::class);

    expect(Student::find($student->id))->not->toBeNull();
});

it('blocks deletion when the finance position is invalid due to over-application', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $line = addSettlementLine($student, $this->semester, $billingAccount, '1000000.00');
    // Applying more cash to a line than its own amount is a data-integrity
    // violation (CASH_EXCEEDS_NET_DUE) — the position becomes invalid, which
    // the guard already blocks via its `valid` check.
    applyPaymentToLine($student, $line, '1500000.00');

    expect(fn () => SoftDeleteStudentAction::run($student, (int) $this->campus->id))
        ->toThrow(StudentDeletionBlockedException::class);

    expect(Student::find($student->id))->not->toBeNull();
});

it('revokes the student Sanctum tokens and the linked users account access', function () {
    $accountUser = User::factory()->create();
    $role = Role::factory()->create();
    CampusUserRole::query()->create([
        'user_id' => $accountUser->id,
        'campus_id' => $this->campus->id,
        'role_id' => $role->id,
    ]);
    $student = makeDeletableStudent($this->campus, $this->semester, ['user_id' => $accountUser->id]);
    $student->createToken('student-portal-token');

    expect($student->tokens()->exists())->toBeTrue();

    SoftDeleteStudentAction::run($student, (int) $this->campus->id);

    expect($student->tokens()->exists())->toBeFalse()
        ->and(User::find($accountUser->id))->toBeNull()
        ->and(CampusUserRole::query()->where('user_id', $accountUser->id)->exists())->toBeFalse();
});

it('tombstones identity fields so the original email can be reused', function () {
    $student = makeDeletableStudent($this->campus, $this->semester, [
        'email' => 'reusable@example.com',
        'student_id' => 'REUSE-001',
    ]);
    $originalEmail = $student->email;
    $originalStudentId = $student->student_id;

    SoftDeleteStudentAction::run($student, (int) $this->campus->id);

    $trashed = Student::withTrashed()->find($student->id);
    expect($trashed->email)->not->toBe($originalEmail)
        ->and($trashed->student_id)->not->toBe($originalStudentId)
        ->and(str_contains($trashed->email, $originalEmail))->toBeTrue();

    $newStudent = makeDeletableStudent($this->campus, $this->semester, [
        'email' => $originalEmail,
        'student_id' => $originalStudentId,
    ]);

    expect($newStudent->id)->not->toBe($student->id);
});

it('rolls back the transaction when the guard blocks deletion', function () {
    $student = makeDeletableStudent($this->campus, $this->semester);
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $line = addSettlementLine($student, $this->semester, $billingAccount, '1000000.00');
    applyPaymentToLine($student, $line, '400000.00');
    $originalEmail = $student->email;

    try {
        SoftDeleteStudentAction::run($student, (int) $this->campus->id);
    } catch (StudentDeletionBlockedException) {
        // expected
    }

    $fresh = Student::find($student->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->email)->toBe($originalEmail)
        ->and($fresh->deleted_at)->toBeNull();
});
