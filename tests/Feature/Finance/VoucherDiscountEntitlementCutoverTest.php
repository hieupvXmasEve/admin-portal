<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
function seedTuitionDebitForVoucher(Student $student, Semester $semester, float $amount = 10_000_000): array
{
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-VOUCHER-DISC-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => $amount,
        'discount_total' => 0,
        'total_amount' => $amount,
        'paid_amount' => 0,
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    return [$invoice, $line, $charge];
}

it('creates a voucher discount entitlement and allocations without a negative charge', function (): void {
    [$invoice, $line] = seedTuitionDebitForVoucher($this->student, $this->semester, 10_000_000);

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'voucher_application',
        source_ref: 'voucher-app:1',
        financial_effect: FinancialEffect::Discount,
        obligation_type: FinanceCharge::TYPE_VOUCHER_CREDIT,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'amount' => 2_000_000,
            'invoice_id' => $invoice->id,
            'description' => 'Voucher Applied (TEST50)',
            'voucher_application_id' => 42,
        ],
    ));

    $entitlement = FinanceDiscountEntitlement::query()->firstOrFail();
    $discount = InvoiceDiscount::query()
        ->where('finance_discount_entitlement_id', $entitlement->id)
        ->firstOrFail();
    $allocations = DiscountAllocation::query()
        ->where('invoice_discount_id', $discount->id)
        ->get();

    expect($result->finance_discount_entitlement_id)->toBe($entitlement->id)
        ->and($result->finance_charge_id)->toBeNull()
        ->and($result->finance_obligation_id)->toBeNull()
        ->and($result->finance_credit_entitlement_id)->toBeNull()
        ->and($result->credit_application_ids)->toBe([])
        ->and($result->invoice_discount_ids)->toBe([(int) $discount->id])
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_VOUCHER_CREDIT)
        ->and($entitlement->lifecycle_status)->toBe(FinanceDiscountEntitlement::STATUS_APPROVED)
        ->and($entitlement->allocation_status)->toBe(FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED)
        ->and((float) $entitlement->amount)->toBe(2_000_000.0)
        ->and($discount->discount_type)->toBe('voucher')
        ->and((float) $discount->amount)->toBe(2_000_000.0)
        ->and($allocations)->toHaveCount(1)
        ->and((float) $allocations->first()->amount)->toBe(2_000_000.0)
        ->and($allocations->first()->invoice_line_id)->toBe($line->id)
        ->and(CreditApplication::query()->count())->toBe(0)
        ->and(
            FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_VOUCHER_CREDIT)
                ->count()
        )->toBe(0)
        ->and(
            FinanceCharge::query()
                ->where('amount', '<', 0)
                ->count()
        )->toBe(0);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $snapshot['gross'])->toBe(10_000_000.0)
        ->and((float) $snapshot['discount'])->toBe(2_000_000.0)
        ->and((float) $snapshot['credit'])->toBe(0.0)
        ->and((float) $snapshot['net'])->toBe(8_000_000.0)
        ->and((float) $snapshot['remaining'])->toBe(8_000_000.0)
        ->and(app(SettlementService::class)->getLineOutstandingAmount($line->fresh()))->toBe(8_000_000.0);
});

it('is idempotent for the same voucher discount source quad', function (): void {
    [$invoice] = seedTuitionDebitForVoucher($this->student, $this->semester);

    $intake = new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'voucher_application',
        source_ref: 'voucher-app:idempotent',
        financial_effect: FinancialEffect::Discount,
        obligation_type: FinanceCharge::TYPE_VOUCHER_CREDIT,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'amount' => 1_500_000,
            'invoice_id' => $invoice->id,
        ],
    );

    $contract = app(FinanceIntakeContract::class);
    $first = $contract->request($intake);
    $second = $contract->requestDiscount($intake);

    expect($second->finance_discount_entitlement_id)->toBe($first->finance_discount_entitlement_id)
        ->and(FinanceDiscountEntitlement::query()->count())->toBe(1)
        ->and(InvoiceDiscount::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(0);
});

it('converts legacy voucher_credit rows with discount carriers without double reduction', function (): void {
    [$invoice, $debitLine] = seedTuitionDebitForVoucher($this->student, $this->semester, 10_000_000);

    // Settlement truth already lives on the discount carrier.
    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'legacy_voucher',
        'description' => 'Legacy voucher discount',
        'amount' => 2_500_000,
        'reference_id' => 99,
        'status' => 'active',
    ]);

    DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $debitLine->id,
        'amount' => 2_500_000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    // Duplicate legacy representation: negative voucher_credit charge + line.
    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $creditCharge = FinanceCharge::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_VOUCHER_CREDIT,
            'amount' => -2_500_000,
            'description' => 'Legacy voucher credit',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -2_500_000,
        'description_snapshot' => 'Legacy voucher credit',
        'status' => 'active',
    ]);

    $settlement = app(SettlementService::class);
    $pre = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $pre['discount'])->toBe(2_500_000.0)
        ->and((float) $pre['credit'])->toBe(0.0)
        ->and((float) $pre['remaining'])->toBe(7_500_000.0);

    $this->artisan('finance:backfill-legacy-voucher-discount-entitlements')
        ->assertSuccessful();

    $entitlement = FinanceDiscountEntitlement::query()->firstOrFail();
    $creditCharge->refresh();
    $discount->refresh();
    $post = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect($creditCharge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_VOUCHER_CREDIT)
        ->and((float) $entitlement->amount)->toBe(2_500_000.0)
        ->and($discount->finance_discount_entitlement_id)->toBe($entitlement->id)
        ->and($entitlement->allocation_status)->toBe(FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED)
        // No credit applications for the converted voucher reduction.
        ->and(CreditApplication::query()->count())->toBe(0)
        ->and((float) $post['discount'])->toBe(2_500_000.0)
        ->and((float) $post['credit'])->toBe(0.0)
        ->and((float) $post['remaining'])->toBe((float) $pre['remaining'])
        ->and((float) $post['remaining'])->toBe(7_500_000.0)
        ->and(
            InvoiceLine::query()
                ->where('charge_id', $creditCharge->id)
                ->where('status', 'active')
                ->count()
        )->toBe(0);

    // Re-run is idempotent.
    $this->artisan('finance:backfill-legacy-voucher-discount-entitlements')
        ->assertSuccessful();

    expect(FinanceDiscountEntitlement::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(0)
        ->and(InvoiceDiscount::query()->where('finance_discount_entitlement_id', $entitlement->id)->count())->toBe(1);
});

it('skips legacy voucher_credit rows without a discount allocation carrier', function (): void {
    [$invoice] = seedTuitionDebitForVoucher($this->student, $this->semester, 10_000_000);

    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $creditCharge = FinanceCharge::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_VOUCHER_CREDIT,
            'amount' => -1_000_000,
            'description' => 'Orphan legacy voucher credit',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -1_000_000,
        'description_snapshot' => 'Orphan legacy voucher credit',
        'status' => 'active',
    ]);

    $this->artisan('finance:backfill-legacy-voucher-discount-entitlements')
        ->assertSuccessful();

    expect(FinanceDiscountEntitlement::query()->count())->toBe(0)
        ->and($creditCharge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});
