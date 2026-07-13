<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\ScholarshipCarrierClassification;
use App\Modules\Finance\Support\ScholarshipCarrierClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Support/ledger_fixtures.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);
});

/**
 * @return array{0: StudentInvoice, 1: InvoiceLine, 2: FinanceCharge}
 */
function seedTuitionDebitForScholarship(Student $student, Semester $semester, float $amount = 10_000_000): array
{
    return seedDebitLedgerViaIntake(
        student: $student,
        semester: $semester,
        amount: $amount,
        description: 'Tuition',
    );
}

function seedLegacyScholarshipCredit(
    Student $student,
    Semester $semester,
    StudentInvoice $invoice,
    float $amount = 2_500_000,
    string $description = 'Legacy scholarship credit',
): FinanceCharge {
    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $creditCharge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'charge_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
            'amount' => -abs($amount),
            'description' => $description,
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -abs($amount),
        'description_snapshot' => $description,
        'status' => 'active',
    ]);

    return $creditCharge;
}

/**
 * @return array{0: InvoiceDiscount, 1: DiscountAllocation}
 */
function seedScholarshipDiscountCarrier(
    StudentInvoice $invoice,
    InvoiceLine $debitLine,
    float $amount,
    ?int $referenceId = null,
): array {
    $discount = new InvoiceDiscount([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'legacy_scholarship',
        'description' => 'Legacy scholarship discount',
        'amount' => $amount,
        'reference_id' => $referenceId,
        'status' => 'active',
    ]);
    $discount->saveQuietly();

    $allocation = DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $debitLine->id,
        'amount' => $amount,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    return [$discount, $allocation];
}

it('classifies fee-specific scholarships with discount carriers as discount with auditable evidence', function (): void {
    [$invoice, $debitLine] = seedTuitionDebitForScholarship($this->student, $this->semester);
    $creditCharge = seedLegacyScholarshipCredit($this->student, $this->semester, $invoice, 2_500_000);
    [$discount] = seedScholarshipDiscountCarrier($invoice, $debitLine, 2_500_000);

    $classification = app(ScholarshipCarrierClassifier::class)->classify($creditCharge);

    expect($classification->isDiscount())->toBeTrue()
        ->and($classification->carrier)->toBe(ScholarshipCarrierClassification::CARRIER_DISCOUNT)
        ->and($classification->reason)->toBe(ScholarshipCarrierClassification::REASON_DISCOUNT_ALLOCATION_CARRIER)
        ->and($classification->amount)->toBe(2_500_000.0)
        ->and($classification->carrierDiscountIds)->toBe([(int) $discount->id])
        ->and($classification->evidence)->toHaveKeys([
            'charge_id',
            'student_id',
            'invoice_ids',
            'carrier_invoice_discount_ids',
            'match_method',
            'target_entitlement',
        ])
        ->and($classification->evidence['match_method'])->toBe('amount_match')
        ->and($classification->evidence['target_entitlement'])->toBe('FinanceDiscountEntitlement')
        ->and($classification->toArray()['carrier'])->toBe('discount');
});

it('classifies grant-like scholarships without discount carriers as credit with auditable evidence', function (): void {
    [$invoice] = seedTuitionDebitForScholarship($this->student, $this->semester);
    $creditCharge = seedLegacyScholarshipCredit($this->student, $this->semester, $invoice, 1_500_000);

    $classification = app(ScholarshipCarrierClassifier::class)->classify($creditCharge);

    expect($classification->isCredit())->toBeTrue()
        ->and($classification->carrier)->toBe(ScholarshipCarrierClassification::CARRIER_CREDIT)
        ->and($classification->reason)->toBe(ScholarshipCarrierClassification::REASON_NO_DISCOUNT_ALLOCATION_CARRIER)
        ->and($classification->amount)->toBe(1_500_000.0)
        ->and($classification->carrierDiscountIds)->toBe([])
        ->and($classification->evidence['target_entitlement'])->toBe('FinanceCreditEntitlement')
        ->and($classification->evidence['carrier_invoice_discount_ids'])->toBe([])
        ->and($classification->evidence['match_method'])->toBeNull();
});

it('converts fee-specific scholarship rows to discount entitlements with no credit applications', function (): void {
    [$invoice, $debitLine] = seedTuitionDebitForScholarship($this->student, $this->semester, 10_000_000);
    $creditCharge = seedLegacyScholarshipCredit($this->student, $this->semester, $invoice, 2_500_000);
    [$discount] = seedScholarshipDiscountCarrier($invoice, $debitLine, 2_500_000);

    $settlement = app(SettlementService::class);
    $pre = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $pre['discount'])->toBe(2_500_000.0)
        ->and((float) $pre['credit'])->toBe(0.0)
        ->and((float) $pre['remaining'])->toBe(7_500_000.0);

    $this->artisan('finance:backfill-legacy-scholarship-entitlements')
        ->assertSuccessful();

    $entitlement = FinanceDiscountEntitlement::query()->firstOrFail();
    $creditCharge->refresh();
    $discount->refresh();
    $post = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect($creditCharge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
        ->and((float) $entitlement->amount)->toBe(2_500_000.0)
        ->and($discount->finance_discount_entitlement_id)->toBe($entitlement->id)
        ->and($entitlement->allocation_status)->toBe(FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED)
        ->and(CreditApplication::query()->count())->toBe(0)
        ->and(FinanceCreditEntitlement::query()->count())->toBe(0)
        ->and((float) $post['discount'])->toBe(2_500_000.0)
        ->and((float) $post['credit'])->toBe(0.0)
        ->and((float) $post['remaining'])->toBe((float) $pre['remaining'])
        ->and(
            InvoiceLine::query()
                ->where('charge_id', $creditCharge->id)
                ->where('status', 'active')
                ->count()
        )->toBe(0);

    // Idempotent re-run.
    $this->artisan('finance:backfill-legacy-scholarship-entitlements')
        ->assertSuccessful();

    expect(FinanceDiscountEntitlement::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(0);
});

it('converts grant-like scholarship rows to credit entitlements with credit applications', function (): void {
    [$invoice, $debitLine] = seedTuitionDebitForScholarship($this->student, $this->semester, 10_000_000);
    $creditCharge = seedLegacyScholarshipCredit($this->student, $this->semester, $invoice, 3_000_000);

    $settlement = app(SettlementService::class);
    $pre = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    // Legacy negative-line fallback nets as discount until conversion.
    expect((float) $pre['discount'])->toBe(3_000_000.0)
        ->and((float) $pre['remaining'])->toBe(7_000_000.0);

    $this->artisan('finance:backfill-legacy-scholarship-entitlements')
        ->assertSuccessful();

    $entitlement = FinanceCreditEntitlement::query()->firstOrFail();
    $creditCharge->refresh();
    $post = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect($creditCharge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
        ->and((float) $entitlement->amount)->toBe(3_000_000.0)
        ->and(CreditApplication::query()->where('finance_credit_entitlement_id', $entitlement->id)->count())->toBe(1)
        ->and((float) CreditApplication::query()->sum('amount'))->toBe(3_000_000.0)
        ->and(FinanceDiscountEntitlement::query()->count())->toBe(0)
        ->and((float) $post['credit'])->toBe(3_000_000.0)
        ->and((float) $post['remaining'])->toBe((float) $pre['remaining'])
        ->and((float) $post['remaining'])->toBe(7_000_000.0)
        // Negative-line discount fallback replaced by credit applications.
        ->and((float) $post['discount'])->toBe(0.0)
        ->and(
            InvoiceLine::query()
                ->where('charge_id', $creditCharge->id)
                ->where('status', 'active')
                ->count()
        )->toBe(0)
        // Credit apps land on the positive tuition line.
        ->and(
            CreditApplication::query()
                ->where('invoice_line_id', $debitLine->id)
                ->exists()
        )->toBeTrue();

    $this->artisan('finance:backfill-legacy-scholarship-entitlements')
        ->assertSuccessful();

    expect(FinanceCreditEntitlement::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(1);
});

it('proves a scholarship reduction cannot be counted in both discount allocations and credit applications', function (): void {
    // Fee-specific path: discount carrier is settlement truth; conversion must not
    // also write credit applications for the same reduction.
    [$feeInvoice, $feeDebitLine] = seedTuitionDebitForScholarship($this->student, $this->semester, 10_000_000);
    $feeCharge = seedLegacyScholarshipCredit(
        $this->student,
        $this->semester,
        $feeInvoice,
        2_000_000,
        'Fee-specific scholarship',
    );
    seedScholarshipDiscountCarrier($feeInvoice, $feeDebitLine, 2_000_000);

    // Grant-like path on a second invoice: credit applications only.
    $grantInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-SCHOLAR-GRANT-'.uniqid(),
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => 8_000_000,
        'discount_total' => 0,
        'total_amount' => 8_000_000,
        'paid_amount' => 0,
    ]);
    $grantDebit = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 8_000_000,
        'description' => 'Tuition grant case',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $grantDebitLine = InvoiceLine::create([
        'invoice_id' => $grantInvoice->id,
        'charge_id' => $grantDebit->id,
        'amount_snapshot' => 8_000_000,
        'description_snapshot' => 'Tuition grant case',
        'status' => 'active',
    ]);
    $grantCharge = seedLegacyScholarshipCredit(
        $this->student,
        $this->semester,
        $grantInvoice,
        1_000_000,
        'Grant-like scholarship',
    );

    $settlement = app(SettlementService::class);
    $feePre = $settlement->deriveInvoiceSnapshot($feeInvoice->fresh());
    $grantPre = $settlement->deriveInvoiceSnapshot($grantInvoice->fresh());

    $this->artisan('finance:backfill-legacy-scholarship-entitlements')
        ->assertSuccessful();

    $feePost = $settlement->deriveInvoiceSnapshot($feeInvoice->fresh());
    $grantPost = $settlement->deriveInvoiceSnapshot($grantInvoice->fresh());

    $feeLineIds = InvoiceLine::query()->where('invoice_id', $feeInvoice->id)->pluck('id');
    $grantLineIds = InvoiceLine::query()->where('invoice_id', $grantInvoice->id)->pluck('id');

    $feeDiscountAllocated = (float) DiscountAllocation::query()
        ->whereIn('invoice_line_id', $feeLineIds)
        ->sum('amount');
    $feeCreditApplied = (float) CreditApplication::query()
        ->whereIn('invoice_line_id', $feeLineIds)
        ->sum('amount');

    $grantDiscountAllocated = (float) DiscountAllocation::query()
        ->whereIn('invoice_line_id', $grantLineIds)
        ->sum('amount');
    $grantCreditApplied = (float) CreditApplication::query()
        ->whereIn('invoice_line_id', $grantLineIds)
        ->sum('amount');

    // Fee-specific reduction lives only on discount allocations.
    expect($feeDiscountAllocated)->toBe(2_000_000.0)
        ->and($feeCreditApplied)->toBe(0.0)
        ->and((float) $feePost['discount'])->toBe(2_000_000.0)
        ->and((float) $feePost['credit'])->toBe(0.0)
        ->and((float) $feePost['remaining'])->toBe((float) $feePre['remaining'])
        ->and(FinanceDiscountEntitlement::query()->count())->toBe(1)
        // Grant-like reduction lives only on credit applications.
        ->and($grantDiscountAllocated)->toBe(0.0)
        ->and($grantCreditApplied)->toBe(1_000_000.0)
        ->and((float) $grantPost['credit'])->toBe(1_000_000.0)
        ->and((float) $grantPost['discount'])->toBe(0.0)
        ->and((float) $grantPost['remaining'])->toBe((float) $grantPre['remaining'])
        ->and(FinanceCreditEntitlement::query()->count())->toBe(1)
        // No charge ends as both carriers for the same reduction.
        ->and($feeCharge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($grantCharge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        // Global invariant: each converted scholarship reduction uses one carrier only.
        ->and(
            FinanceDiscountEntitlement::query()
                ->where('entitlement_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
                ->pluck('source_ref')
                ->intersect(
                    FinanceCreditEntitlement::query()
                        ->where('entitlement_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
                        ->pluck('source_ref')
                )
                ->isEmpty()
        )->toBeTrue();
});
