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
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
function seedTuitionDebitForVoucher(Student $student, Semester $semester, float $amount = 10_000_000): array
{
    return seedDebitLedgerViaIntake(
        student: $student,
        semester: $semester,
        amount: $amount,
        description: 'Tuition',
    );
}

it('creates a voucher discount entitlement and allocations without a negative charge', function (): void {
    [$invoice, $line] = seedTuitionDebitForVoucher($this->student, $this->semester, 10_000_000);

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'voucher_application',
        source_ref: 'voucher-app:1',
        financial_effect: FinancialEffect::Discount,
        obligation_type: FinanceEntitlementType::VoucherCredit,
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
        ->and($entitlement->entitlement_type)->toBe(FinanceEntitlementType::VoucherCredit)
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
                ->where('charge_type', FinanceEntitlementType::VoucherCredit)
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
        obligation_type: FinanceEntitlementType::VoucherCredit,
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
