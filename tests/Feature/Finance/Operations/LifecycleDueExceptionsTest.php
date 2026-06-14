<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\AcknowledgeLifecycleDueExceptionAction;
use App\Modules\Finance\Actions\Operations\ResolveLifecycleDueExceptionAction;
use App\Modules\Finance\Actions\Operations\SendDueItemParentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lifecycleExceptionStudent(Campus $campus, Program $program, Semester $semester, string $code): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'status' => 'deferred',
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();
}

function lifecycleExceptionDng(Student $student, Semester $semester, array $overrides = []): DngPaymentRequest
{
    return DngPaymentRequest::create(array_merge([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'ITEM-'.$student->student_id,
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->subDay(),
        'amount' => 2000000,
        'status' => 'pushed_to_dng',
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('skips lifecycle exceptions in student and parent reminder actions', function () {
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'REM001');
    $request = lifecycleExceptionDng($student, $this->semester);

    $studentResult = SendDueItemRemindersAction::run([
        'item_ids' => ['dng_request:'.$request->id],
    ]);

    $parentResult = SendDueItemParentRemindersAction::run([
        'item_ids' => ['dng_request:'.$request->id],
    ]);

    expect($studentResult['sent_count'])->toBe(0)
        ->and($studentResult['skipped_lifecycle_exception_count'])->toBe(1)
        ->and($parentResult['sent_count'])->toBe(0)
        ->and($parentResult['skipped_lifecycle_exception_count'])->toBe(1);
});

it('acknowledges lifecycle exceptions without mutating DNG or charges', function () {
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'ACK001');
    $request = lifecycleExceptionDng($student, $this->semester, [
        'finance_charge_id' => FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => 2000000,
            'description' => 'Tuition',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ])->id,
    ]);

    $result = app(AcknowledgeLifecycleDueExceptionAction::class)->run(
        $request,
        'Reviewed defer policy',
        $this->user->id,
    );

    $request->refresh();
    $charge = FinanceCharge::find($request->finance_charge_id);

    expect($result['review']->status)->toBe(LifecycleDueExceptionReviewStatus::Acknowledged)
        ->and($result['review']->resolution_reason)->toBe('Reviewed defer policy')
        ->and($result['review']->resolved_by_user_id)->toBe($this->user->id)
        ->and($request->status)->toBe('pushed_to_dng')
        ->and($charge?->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('refuses cancel_dng without create_finance_payments permission', function () {
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'AUTH001');
    $request = lifecycleExceptionDng($student, $this->semester);

    expect(fn () => app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::CancelDng,
        'Should be denied',
        $this->user->id,
        canCancelDng: false,
        canVoidCharges: false,
    ))->toThrow(AuthorizationException::class);
});

it('refuses cancel_dng_and_void_linked_charge without void_finance_charges permission', function () {
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'VOID001');
    $request = lifecycleExceptionDng($student, $this->semester);

    expect(fn () => app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::CancelDngAndVoidLinkedCharge,
        'Need void permission',
        $this->user->id,
        canCancelDng: true,
        canVoidCharges: false,
    ))->toThrow(AuthorizationException::class);
});

it('refuses cancel_dng for bridged payment requests', function () {
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'BRIDGE001');
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 2000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    $request = lifecycleExceptionDng($student, $this->semester, ['payment_id' => $payment->id]);

    expect(fn () => app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::CancelDng,
        'Should fail',
        $this->user->id,
        canCancelDng: true,
        canVoidCharges: true,
    ))->toThrow(RuntimeException::class);
});

it('stores review metadata for keep_as_debt resolution', function () {
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'DEBT001');
    $request = lifecycleExceptionDng($student, $this->semester);

    $result = app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::KeepAsDebt,
        'Debt remains collectible',
        $this->user->id,
        canCancelDng: false,
        canVoidCharges: false,
    );

    $stored = FinanceLifecycleDueExceptionReview::query()->where('dng_payment_request_id', $request->id)->first();

    expect($result['review']->status)->toBe(LifecycleDueExceptionReviewStatus::KeptAsDebt)
        ->and($stored?->resolution_reason)->toBe('Debt remains collectible')
        ->and($request->fresh()->status)->toBe('pushed_to_dng');
});

it('does not leave a completed cancel state when the DNG cancel rolls back (FIN-34)', function () {
    // FIN-34: the CancelRequested attempt event used to be written before the
    // transaction. If the DNG cancel API then failed and the transaction rolled
    // back, a committed "CancelRequested" transition survived that the review never
    // durably reached. The attempt event now commits/rolls back atomically with the
    // state change, so a failure leaves NO orphan completed-looking transition —
    // only the paired CancelFailed outcome — and the review never reads as resolved.
    $student = lifecycleExceptionStudent($this->campus, $this->program, $this->semester, 'ROLL001');
    $request = lifecycleExceptionDng($student, $this->semester, [
        'push_payload' => ['StudentName' => 'Student ROLL001', 'Email' => 'roll001@example.com'],
    ]);

    // DNG cancel API fails — the cancel for a pushed_to_dng request calls the gateway.
    $mockClient = Mockery::mock(DngClient::class);
    $mockClient->shouldReceive('buildInsertNewRecordPayload')->andReturn(['Amount' => -1]);
    $mockClient->shouldReceive('cancelRecord')->andThrow(new RuntimeException('DNG gateway unavailable'));
    app()->instance(DngClient::class, $mockClient);

    expect(fn () => app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::CancelDng,
        'Cancel attempt that fails at the gateway',
        $this->user->id,
        canCancelDng: true,
        canVoidCharges: false,
    ))->toThrow(RuntimeException::class);

    // Review state did not advance to a resolved/cancel-requested durable status.
    $review = FinanceLifecycleDueExceptionReview::query()
        ->where('dng_payment_request_id', $request->id)
        ->first();
    expect($review)->toBeNull();

    // Audit: no orphan completed-looking CancelRequested transition survived; the
    // paired CancelFailed outcome WAS recorded.
    $eventsByType = FinanceLifecycleDueExceptionReviewEvent::query()
        ->where('dng_payment_request_id', $request->id)
        ->pluck('event_type');

    expect($eventsByType->contains(LifecycleDueExceptionReviewEventType::CancelRequested))->toBeFalse()
        ->and($eventsByType->contains(LifecycleDueExceptionReviewEventType::CancelFailed))->toBeTrue();

    // The DNG request itself stays pushed_to_dng with no committed cancel payload.
    expect($request->fresh()->status)->toBe('pushed_to_dng')
        ->and($request->fresh()->cancel_push_payload)->toBeNull();
});
