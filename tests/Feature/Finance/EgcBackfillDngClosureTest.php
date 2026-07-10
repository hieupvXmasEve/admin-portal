<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use App\Modules\Finance\Actions\Egc\SubmitEgcLevelFeeDebitAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

function makeEgcBackfillStudent(Campus $campus, Semester $semester, string $code): Student
{
    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => 'intake_pre_uni_gc',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'full_name' => "Student {$code}",
        'email' => strtolower($code).'@example.com',
    ]);
}

function makeEgcLegacyLevelCharge(
    Student $student,
    Semester $semester,
    float $amount,
    string $status = FinanceCharge::STATUS_ACTIVE,
): FinanceCharge {
    return FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => $amount,
        'description' => 'Legacy EGC level fee',
        'effective_at' => now(),
        'status' => $status,
        'source_type' => 'EgcChargeBatch',
        'source_id' => null,
        'created_by_user_id' => test()->user->id,
        'voided_at' => $status === FinanceCharge::STATUS_VOID ? now() : null,
        'voided_by_user_id' => $status === FinanceCharge::STATUS_VOID ? test()->user->id : null,
        'void_reason' => $status === FinanceCharge::STATUS_VOID ? 'legacy void' : null,
    ]);
}

/**
 * @return array{0: StudentInvoice, 1: InvoiceLine}
 */
function attachEgcInvoiceLine(FinanceCharge $charge, float $amountSnapshot): array
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-EGC-'.uniqid(),
        'student_id' => $charge->student_id,
        'semester_id' => $charge->semester_id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => max(0, $amountSnapshot),
        'discount_total' => 0,
        'total_amount' => max(0, $amountSnapshot),
        'paid_amount' => 0,
    ]);

    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amountSnapshot,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);

    return [$invoice, $line];
}

function makeEgcBatchDngAction(bool $dngFails = false): CreateBatchDngFromChargesAction
{
    $dngPaymentServiceMock = Mockery::mock(DngPaymentService::class);

    if ($dngFails) {
        $dngPaymentServiceMock->shouldReceive('createAndPush')
            ->andThrow(new RuntimeException('DNG API failed'));
    } else {
        $dngPaymentServiceMock->shouldReceive('createAndPush')
            ->andReturnUsing(function (Student $student, array $data) {
                return DngPaymentRequest::create([
                    'student_id' => $student->id,
                    'campus_code' => $data['campus_code'],
                    'student_code' => $data['student_code'],
                    'fee_type' => $data['fee_type'],
                    'item_id' => $data['item_id'],
                    'amount' => $data['amount'],
                    'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                    'description' => $data['description'] ?? null,
                    'semester_id' => $data['semester_id'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                ]);
            });
    }

    $campusResolverMock = Mockery::mock(DngCampusCodeResolver::class);
    $campusResolverMock->shouldReceive('requireForStudent')->andReturn('FAUHN');

    return new CreateBatchDngFromChargesAction(
        $dngPaymentServiceMock,
        $campusResolverMock,
        app(CancelDngPaymentRequestAction::class),
    );
}

// ─── Debit backfill ─────────────────────────────────────────────────────────

it('backfills active and voided legacy egc_level_fee charges with legacy provenance and is idempotent', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC2001');
    $active = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000, FinanceCharge::STATUS_ACTIVE);
    attachEgcInvoiceLine($active, 15_000_000);
    $voided = makeEgcLegacyLevelCharge($student, $this->semester, 12_000_000, FinanceCharge::STATUS_VOID);

    $tuition = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45_000_000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $this->artisan('finance:backfill-legacy-egc-level-fee-obligations')
        ->expectsOutputToContain('created 2')
        ->assertExitCode(0);

    $activeObligation = FinanceObligation::query()
        ->where('source_ref', FinanceOwnedObligationSource::legacyEgcLevelFeeChargeRef($active->id))
        ->firstOrFail();
    $voidedObligation = FinanceObligation::query()
        ->where('source_ref', FinanceOwnedObligationSource::legacyEgcLevelFeeChargeRef($voided->id))
        ->firstOrFail();

    expect(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(2)
        ->and($activeObligation->source_system)->toBe(FinanceOwnedObligationSource::SOURCE_SYSTEM)
        ->and($activeObligation->source_kind)->toBe(SubmitEgcLevelFeeDebitAction::SOURCE_KIND_LEGACY_EGC)
        ->and($activeObligation->obligation_type)->toBe(FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->and($activeObligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $activeObligation->amount)->toBe(15_000_000.0)
        ->and($activeObligation->pricing_rule_version)->toBe('egc_level_fee:legacy_backfill')
        ->and($activeObligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and($activeObligation->pricing_snapshot['legacy_charge_id'])->toBe($active->id)
        ->and($activeObligation->billing_account_id)->not->toBeNull()
        ->and($voidedObligation->lifecycle_status)->toBe(FinanceObligation::STATUS_VOIDED)
        ->and((float) $voidedObligation->amount)->toBe(12_000_000.0)
        ->and($voidedObligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and($active->fresh()->finance_obligation_id)->toBe($activeObligation->id)
        ->and($voided->fresh()->finance_obligation_id)->toBe($voidedObligation->id)
        ->and($tuition->fresh()->finance_obligation_id)->toBeNull();

    $this->artisan('finance:backfill-legacy-egc-level-fee-obligations')
        ->expectsOutputToContain('already linked 2')
        ->assertExitCode(0);

    expect(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(2)
        ->and($active->fresh()->finance_obligation_id)->toBe($activeObligation->id)
        ->and($voided->fresh()->finance_obligation_id)->toBe($voidedObligation->id);
});

it('hard-gates when derived settlement outstanding drifts from old computed balance', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC2002');
    $charge = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    attachEgcInvoiceLine($charge, 12_000_000);

    $this->artisan('finance:backfill-legacy-egc-level-fee-obligations')
        ->expectsOutputToContain('mismatch(es) 1')
        ->expectsOutputToContain('Reconciliation hard-gate failed')
        ->assertExitCode(1);

    expect($charge->fresh()->finance_obligation_id)->not->toBeNull()
        ->and(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(1);
});

it('passes hard-gate when settlement outstanding matches old balance', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC2003');
    $charge = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    attachEgcInvoiceLine($charge, 15_000_000);

    $this->artisan('finance:backfill-legacy-egc-level-fee-obligations')
        ->expectsOutputToContain('mismatch(es) 0')
        ->assertExitCode(0);
});

it('does not invent billing accounts for dry-run debit backfill', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC2004');
    $charge = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    attachEgcInvoiceLine($charge, 15_000_000);

    $this->artisan('finance:backfill-legacy-egc-level-fee-obligations --dry-run')
        ->expectsOutputToContain('[dry-run] Checked 1')
        ->assertExitCode(0);

    expect($charge->fresh()->finance_obligation_id)->toBeNull()
        ->and(FinanceObligation::query()->count())->toBe(0);
});

// ─── Exempt credit conversion ───────────────────────────────────────────────

it('converts legacy egc_exempt_credit negative rows with unchanged remaining settlement', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC3001');
    $student->update(['status' => 'intake_course']);

    $debit = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    [$invoice] = attachEgcInvoiceLine($debit, 15_000_000);

    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $creditCharge = FinanceCharge::query()->create([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_EGC_EXEMPT_CREDIT,
            'amount' => -15_000_000,
            'description' => 'Legacy EGC exempt credit',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
            'created_by_user_id' => $this->user->id,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -15_000_000,
        'description_snapshot' => 'Legacy EGC exempt credit',
        'status' => 'active',
    ]);

    $settlement = app(SettlementService::class);
    $pre = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $pre['discount'])->toBe(15_000_000.0)
        ->and((float) $pre['remaining'])->toBe(0.0);

    $this->artisan('finance:backfill-legacy-egc-exempt-credit-entitlements')
        ->assertSuccessful();

    $entitlement = FinanceCreditEntitlement::query()->firstOrFail();
    $creditCharge->refresh();
    $post = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect($creditCharge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_EGC_EXEMPT_CREDIT)
        ->and($entitlement->source_kind)->toBe('legacy_egc_exempt_credit')
        ->and((float) $entitlement->amount)->toBe(15_000_000.0)
        ->and(CreditApplication::query()->where('finance_credit_entitlement_id', $entitlement->id)->count())->toBe(1)
        ->and((float) CreditApplication::query()->sum('amount'))->toBe(15_000_000.0)
        ->and((float) $post['credit'])->toBe(15_000_000.0)
        ->and((float) $post['remaining'])->toBe((float) $pre['remaining'])
        ->and((float) $post['remaining'])->toBe(0.0)
        ->and(
            InvoiceLine::query()
                ->where('charge_id', $creditCharge->id)
                ->where('status', 'active')
                ->count()
        )->toBe(0)
        ->and((float) $post['discount'])->toBe(0.0);

    $this->artisan('finance:backfill-legacy-egc-exempt-credit-entitlements')
        ->assertSuccessful();

    expect(FinanceCreditEntitlement::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(1);
});

// ─── Retake discount conversion ─────────────────────────────────────────────

it('links legacy egc_retake discounts to FinanceDiscountEntitlement without changing settlement', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC4001');
    $debit = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    [$invoice, $line] = attachEgcInvoiceLine($debit, 15_000_000);

    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => ApplyEgcRetakeDiscountAction::DISCOUNT_TYPE,
        'discount_source' => 'EgcBlock',
        'description' => 'Legacy EGC retake discount',
        'amount' => 7_500_000,
        'status' => 'active',
        'reference_id' => 1,
        'finance_discount_entitlement_id' => null,
    ]);

    DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 7_500_000,
        'allocation_rule' => 'egc_retake_target',
    ]);

    $settlement = app(SettlementService::class);
    $pre = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $pre['discount'])->toBe(7_500_000.0)
        ->and((float) $pre['remaining'])->toBe(7_500_000.0);

    $this->artisan('finance:backfill-legacy-egc-retake-discount-entitlements')
        ->assertSuccessful();

    $discount->refresh();
    $entitlement = FinanceDiscountEntitlement::query()->firstOrFail();
    $post = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect($discount->finance_discount_entitlement_id)->toBe($entitlement->id)
        ->and($entitlement->entitlement_type)->toBe(ObligationTypeRegistry::TYPE_EGC_RETAKE)
        ->and($entitlement->source_kind)->toBe('legacy_egc_retake')
        ->and((float) $entitlement->amount)->toBe(7_500_000.0)
        ->and((float) $post['remaining'])->toBe((float) $pre['remaining'])
        ->and((float) $post['discount'])->toBe(7_500_000.0)
        ->and(CreditApplication::query()->count())->toBe(0);

    $this->artisan('finance:backfill-legacy-egc-retake-discount-entitlements')
        ->assertSuccessful();

    expect(FinanceDiscountEntitlement::query()->count())->toBe(1)
        ->and($discount->fresh()->finance_discount_entitlement_id)->toBe($entitlement->id);
});

// ─── DNG guard ──────────────────────────────────────────────────────────────

it('HP fee_type: blocks push when egc_level_fee charge has no finance obligation', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC5001');
    $orphan = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    attachEgcInvoiceLine($orphan, 15_000_000);

    $action = makeEgcBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'EGC DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('missing_finance_obligation')
        ->and($result['errors'][0])->toContain('backfill-legacy-egc-level-fee-obligations')
        ->and($orphan->fresh()->finance_obligation_id)->toBeNull()
        ->and(DngPaymentRequest::where('student_id', $student->id)->count())->toBe(0)
        ->and(FinanceCharge::where('student_id', $student->id)->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(1);
});

it('HP fee_type: pushes DNG only from existing obligation-linked EGC payables', function (): void {
    $student = makeEgcBackfillStudent($this->campus, $this->semester, 'EGC5002');
    $charge = makeEgcLegacyLevelCharge($student, $this->semester, 15_000_000);
    attachEgcInvoiceLine($charge, 15_000_000);

    $this->artisan('finance:backfill-legacy-egc-level-fee-obligations')->assertExitCode(0);
    expect($charge->fresh()->finance_obligation_id)->not->toBeNull();

    $action = makeEgcBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'EGC DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and(DngPaymentRequest::where('student_id', $student->id)->where('fee_type', 'HP')->count())->toBe(1);
});
