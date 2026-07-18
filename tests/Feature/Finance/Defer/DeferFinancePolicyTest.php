<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\DeferCase;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\Operations\ApplyDeferFinancePolicyAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-REV-020-01 (M1) — Defer Policy Settlement Action.
 *
 * ApplyDeferFinancePolicyAction settles a FULL-scope PRESERVE/FORFEIT defer
 * case using only existing ledger operations (void → release → re-consume).
 * Lifecycle-linked DNG requests are cancelled locally before charges are voided;
 * discounts remain a fail-closed review gate. Finance invariants must stay clean
 * before and after.
 */
beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->user = User::factory()->create();
});

/**
 * Create a FULL-scope defer case for a fresh student under the given policy.
 *
 * @return array{0: Student, 1: DeferCase}
 */
function deferPolicyCase(
    Semester $semester,
    Campus $campus,
    Program $program,
    User $user,
    string $code,
    string $feePolicy,
    string $scopeType = DeferCase::SCOPE_FULL,
): array {
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'status' => 'deferred',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Defer settlement test',
        'from_semester_id' => $semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $deferCase = DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'scope_type' => $scopeType,
        'fee_policy' => $feePolicy,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    return [$student, $deferCase];
}

/** Create an active positive tuition obligation for a student in a semester. */
function deferObligation(int $studentId, int $semesterId, float $amount = 45_000_000): FinanceCharge
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance_test',
        'source_kind' => 'defer_policy',
        'source_ref' => 'defer-policy:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'defer-policy:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'defer-policy:test'],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $studentId,
        'semester_id' => $semesterId,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition obligation',
    ]);
}

/** Pay an obligation in full so the charge has released-able cash. */
function payObligation(Student $student, FinanceCharge $charge, float $amount, User $user): Payment
{
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $line = InvoiceLine::where('charge_id', $charge->id)->firstOrFail();
    app(SettlementService::class)->createPaymentApplication($payment, $line, $amount, 'application', $user->id);

    return $payment;
}

/** Total offending rows across every finance invariant (0 = clean). */
function deferInvariantOffending(): int
{
    return (int) collect(app(FinanceIntegrityAuditor::class)->summarize(null))
        ->sum(fn (array $row): int => (int) ($row['count'] ?? 0));
}

it('PRESERVE paid voids the obligation and leaves the paid cash available with no adjustment or debt', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'PRES-PAID', DeferCase::POLICY_PRESERVE);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    $payment = payObligation($student, $charge, 45_000_000, $this->user);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['reason'])->toBe('preserve_settled')
        ->and($result['released'])->toBe(45_000_000.0)
        ->and($result['consumed'])->toBe(0.0)
        ->and($result['adjustment_charge_id'])->toBeNull()
        ->and($result['voided_charge_ids'])->toContain($charge->id);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        // Paid cash is returned to the student as available (unapplied), preserved.
        ->and($payment->fresh()->unapplied_amount)->toBe(45_000_000.0)
        // No adjustment charge, no new debt.
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0)
        ->and(FinanceCharge::where('student_id', $student->id)->active()->charges()->count())->toBe(0);
});

it('FORFEIT paid voids the obligation, creates an adjustment equal to paid and fully allocates it with no residual debt', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'FORF-PAID', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    $sourceInvoice = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail()->invoice()->firstOrFail();
    app(InvoiceGenerationService::class)->closeInvoice($sourceInvoice);

    $this->travelTo(now()->subDays(10));
    $payment = payObligation($student, $charge, 45_000_000, $this->user);
    $sourceInvoicePaidAt = $sourceInvoice->fresh()->cached_paid_at;
    $payment->forceFill(['paid_at' => now()->subDay()])->save();
    $signedApplicationsBefore = (float) $payment->applications()->sum('amount');
    $dngItemId = 'ITEM-FORF-PAID-'.uniqid();
    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'semester_id' => $this->semester->id,
        'item_id' => $dngItemId,
        'amount' => 45_000_000,
        'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        'payment_id' => $payment->id,
        'paid_at' => $payment->paid_at,
    ]);
    $this->travelBack();

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['reason'])->toBe('forfeit_settled')
        ->and($result['released'])->toBe(45_000_000.0)
        ->and($result['consumed'])->toBe(45_000_000.0)
        ->and($result['adjustment_charge_id'])->not->toBeNull()
        ->and($result['voided_charge_ids'])->toContain($charge->id);

    $adjustment = FinanceCharge::findOrFail($result['adjustment_charge_id']);
    $adjustmentInvoice = $adjustment->invoiceLines()->firstOrFail()->invoice()->firstOrFail();

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($adjustment->charge_type)->toBe(FinanceCharge::TYPE_ADJUSTMENT)
        ->and((float) $adjustment->amount)->toBe(45_000_000.0)
        ->and($adjustment->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($adjustment->source_type)->toBeNull()
        ->and($adjustment->finance_obligation_id)->not->toBeNull()
        // Consumed paid cash is re-allocated onto the adjustment — no residual debt.
        ->and(app(SettlementService::class)->getChargePaidAmount($adjustment->id))->toBe(45_000_000.0)
        ->and($adjustment->fresh()->balance)->toBe(0.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0)
        ->and((float) $payment->applications()->sum('amount'))->toBe($signedApplicationsBefore)
        ->and($adjustmentInvoice->id)->toBe($sourceInvoice->id)
        ->and($adjustmentInvoice->status)->toBe('paid')
        ->and($adjustmentInvoice->cached_paid_at?->equalTo($sourceInvoicePaidAt))->toBeTrue()
        ->and($adjustmentInvoice->cached_paid_at?->equalTo($payment->paid_at))->toBeFalse()
        ->and($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED)
        ->and($dng->fresh()->payment_id)->toBe($payment->id)
        ->and($dng->fresh()->item_id)->toBe($dngItemId);
});

it('FORFEIT unpaid voids the obligation and creates no adjustment and no debt', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'FORF-UNPAID', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['reason'])->toBe('forfeit_settled')
        ->and($result['released'])->toBe(0.0)
        ->and($result['consumed'])->toBe(0.0)
        ->and($result['adjustment_charge_id'])->toBeNull();

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0);
});

it('is safe to re-run: a second forfeit settlement is a noop and does not re-settle its own adjustment', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'FORF-RERUN', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    payObligation($student, $charge, 45_000_000, $this->user);

    $first = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);
    $second = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($first['status'])->toBe('applied')
        ->and($second['status'])->toBe('noop')
        ->and($second['reason'])->toBe('no_charge')
        // The first run's adjustment must survive untouched — exactly one adjustment, still active.
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(1)
        ->and(FinanceCharge::findOrFail($first['adjustment_charge_id'])->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and(deferInvariantOffending())->toBe(0);
});

it('returns noop when the student has no obligation in the defer semester and mutates nothing', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'NO-CHG', DeferCase::POLICY_FORFEIT);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('noop')
        ->and($result['reason'])->toBe('no_charge')
        ->and($result['voided_charge_ids'])->toBe([])
        ->and(FinanceCharge::count())->toBe(0);
});

it('cancels an installment-linked DNG locally before voiding a deferred obligation', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'DNG-LIVE', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'S1',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 45_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 45_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dng->id,
    ]);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['reason'])->toBe('forfeit_settled')
        ->and($result['cancelled_dng_request_ids'])->toBe([$dng->id])
        ->and($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0);
});

it('cancels a pivot-linked DNG locally before voiding a deferred obligation', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'DNG-PIVOT', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'DNG-PIVOT',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 45_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dng->id,
        'finance_charge_id' => $charge->id,
        'amount' => 45_000_000,
    ]);

    $pivotExists = DngPaymentRequestCharge::query()
        ->where('finance_charge_id', $charge->id)
        ->whereHas('dngPaymentRequest', fn ($requests) => $requests->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG))
        ->exists();
    expect($pivotExists)->toBeTrue();

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['cancelled_dng_request_ids'])->toBe([$dng->id])
        ->and($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('does not treat the retired DNG header pointer as a supported charge link', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'DNG-HEADER', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'DNG-HEADER',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 45_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
    ]);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['cancelled_dng_request_ids'])->toBe([])
        ->and($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('cancels a reservation-target DNG locally before voiding a deferred obligation', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'DNG-TARGET', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'DNG-TARGET',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 45_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    DngPaymentRequestReservationTarget::create([
        'dng_payment_request_id' => $dng->id,
        'invoice_line_id' => $line->id,
        'captured_collectible' => 45_000_000,
        'target_identity' => 'line:'.$line->id,
    ]);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('applied')
        ->and($result['cancelled_dng_request_ids'])->toBe([$dng->id])
        ->and($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('skips with discount_present and mutates nothing when an obligation line carries a discount', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'DISC', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);

    $invoice = InvoiceLine::where('charge_id', $charge->id)->firstOrFail()->invoice()->firstOrFail();
    app(InvoiceGenerationService::class)->applyInvoiceDiscount(
        $invoice, 'voucher', 5_000_000, 'tests', 'Voucher', 1, $this->user->id,
    );

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('discount_present')
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0);
});

it('applies an explicitly reviewed FORFEIT disposition using released cash net of discount', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'DISC-REVIEWED', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    $invoice = InvoiceLine::where('charge_id', $charge->id)->firstOrFail()->invoice()->firstOrFail();
    app(InvoiceGenerationService::class)->applyInvoiceDiscount(
        $invoice, 'voucher', 9_000_000, 'tests', 'Reviewed voucher', 2, $this->user->id,
    );
    $payment = payObligation($student, $charge, 36_000_000, $this->user);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle(
        $case,
        $this->user->id,
        reviewedDiscountDispositionReason: 'Approved forfeit of 36M verified cash after 9M discount release',
    );

    $adjustment = FinanceCharge::findOrFail($result['adjustment_charge_id']);

    expect($result['status'])->toBe('applied')
        ->and($result['released'])->toBe(36_000_000.0)
        ->and($result['consumed'])->toBe(36_000_000.0)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->fresh()->void_reason)->toContain('Approved forfeit of 36M verified cash')
        ->and((float) $adjustment->amount)->toBe(36_000_000.0)
        ->and(app(SettlementService::class)->getChargePaidAmount($adjustment->id))->toBe(36_000_000.0)
        ->and($adjustment->invoiceLines()->firstOrFail()->invoice()->firstOrFail()->status)->toBe('paid')
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0)
        ->and(deferInvariantOffending())->toBe(0);
});

it('skips out_of_scope and mutates nothing for a non-FULL scope or non-PRESERVE/FORFEIT policy', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'OOS', DeferCase::POLICY_PARTIAL);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('out_of_scope')
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('skips a historical defer case when the student has returned to an active lifecycle', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'ACTIVE-AGAIN', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 15_000_000);
    payObligation($student, $charge, 15_000_000, $this->user);
    $student->update(['status' => 'intake_course']);

    $result = app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('student_lifecycle_active')
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0);
});

it('keeps finance invariants clean before and after a forfeit settlement', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'INV-FORF', DeferCase::POLICY_FORFEIT);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    payObligation($student, $charge, 45_000_000, $this->user);

    expect(deferInvariantOffending())->toBe(0);

    app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect(deferInvariantOffending())->toBe(0);
});

it('keeps finance invariants clean before and after a preserve settlement', function () {
    [$student, $case] = deferPolicyCase($this->semester, $this->campus, $this->program, $this->user, 'INV-PRES', DeferCase::POLICY_PRESERVE);
    $charge = deferObligation($student->id, $this->semester->id, 45_000_000);
    payObligation($student, $charge, 45_000_000, $this->user);

    expect(deferInvariantOffending())->toBe(0);

    app(ApplyDeferFinancePolicyAction::class)->handle($case, $this->user->id);

    expect(deferInvariantOffending())->toBe(0);
});
