<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
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
function seedTuitionDebit(Student $student, Semester $semester, float $amount = 10_000_000): array
{
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-DEFER-CREDIT-'.uniqid(),
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

it('creates a defer credit entitlement and applications without a negative charge', function (): void {
    [$invoice, $line] = seedTuitionDebit($this->student, $this->semester, 10_000_000);

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'defer_settlement',
        source_ref: 'defer-case:1',
        financial_effect: FinancialEffect::Credit,
        obligation_type: FinanceCharge::TYPE_DEFER_CREDIT,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'amount' => 3_000_000,
            'invoice_line_id' => $line->id,
            'description' => 'Defer credit preserve',
        ],
    ));

    $entitlement = FinanceCreditEntitlement::query()->firstOrFail();
    $applications = CreditApplication::query()
        ->where('finance_credit_entitlement_id', $entitlement->id)
        ->get();

    expect($result->finance_credit_entitlement_id)->toBe($entitlement->id)
        ->and($result->finance_charge_id)->toBeNull()
        ->and($result->finance_obligation_id)->toBeNull()
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_DEFER_CREDIT)
        ->and($entitlement->lifecycle_status)->toBe(FinanceCreditEntitlement::STATUS_APPROVED)
        ->and($entitlement->allocation_status)->toBe(FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED)
        ->and((float) $entitlement->amount)->toBe(3_000_000.0)
        ->and($applications)->toHaveCount(1)
        ->and((float) $applications->first()->amount)->toBe(3_000_000.0)
        ->and($applications->first()->invoice_line_id)->toBe($line->id)
        ->and(
            FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
                ->count()
        )->toBe(0)
        ->and(
            FinanceCharge::query()
                ->where('amount', '<', 0)
                ->count()
        )->toBe(0);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $snapshot['gross'])->toBe(10_000_000.0)
        ->and((float) $snapshot['discount'])->toBe(0.0)
        ->and((float) $snapshot['credit'])->toBe(3_000_000.0)
        ->and((float) $snapshot['net'])->toBe(10_000_000.0)
        ->and((float) $snapshot['remaining'])->toBe(7_000_000.0)
        ->and(app(SettlementService::class)->getLineOutstandingAmount($line->fresh()))->toBe(7_000_000.0);
});

it('is idempotent for the same defer credit source quad', function (): void {
    [, $line] = seedTuitionDebit($this->student, $this->semester);

    $intake = new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'defer_settlement',
        source_ref: 'defer-case:idempotent',
        financial_effect: FinancialEffect::Credit,
        obligation_type: FinanceCharge::TYPE_DEFER_CREDIT,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'amount' => 1_500_000,
            'invoice_line_id' => $line->id,
        ],
    );

    $contract = app(FinanceIntakeContract::class);
    $first = $contract->request($intake);
    $second = $contract->requestCredit($intake);

    expect($second->finance_credit_entitlement_id)->toBe($first->finance_credit_entitlement_id)
        ->and(FinanceCreditEntitlement::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(1);
});

it('converts legacy defer_credit negative rows with unchanged remaining settlement', function (): void {
    [$invoice, $debitLine] = seedTuitionDebit($this->student, $this->semester, 10_000_000);

    // Legacy shape: negative defer_credit charge + invoice line. Sign CHECK forbids
    // new writes; disable for seed only.
    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $creditCharge = FinanceCharge::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_DEFER_CREDIT,
            'amount' => -3_000_000,
            'description' => 'Legacy defer credit',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -3_000_000,
        'description_snapshot' => 'Legacy defer credit',
        'status' => 'active',
    ]);

    $settlement = app(SettlementService::class);
    $pre = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $pre['discount'])->toBe(3_000_000.0)
        ->and((float) $pre['remaining'])->toBe(7_000_000.0);

    $this->artisan('finance:backfill-legacy-defer-credit-entitlements')
        ->assertSuccessful();

    $entitlement = FinanceCreditEntitlement::query()->firstOrFail();
    $creditCharge->refresh();
    $post = $settlement->deriveInvoiceSnapshot($invoice->fresh());

    expect($creditCharge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($entitlement->entitlement_type)->toBe(FinanceCharge::TYPE_DEFER_CREDIT)
        ->and((float) $entitlement->amount)->toBe(3_000_000.0)
        ->and(CreditApplication::query()->where('finance_credit_entitlement_id', $entitlement->id)->count())->toBe(1)
        ->and((float) CreditApplication::query()->sum('amount'))->toBe(3_000_000.0)
        ->and((float) $post['credit'])->toBe(3_000_000.0)
        ->and((float) $post['remaining'])->toBe((float) $pre['remaining'])
        ->and((float) $post['remaining'])->toBe(7_000_000.0)
        ->and(
            InvoiceLine::query()
                ->where('charge_id', $creditCharge->id)
                ->where('status', 'active')
                ->count()
        )->toBe(0)
        // No double reduction: discount fallback gone, credit applications carry it.
        ->and((float) $post['discount'])->toBe(0.0);

    // Re-run is idempotent.
    $this->artisan('finance:backfill-legacy-defer-credit-entitlements')
        ->assertSuccessful();

    expect(FinanceCreditEntitlement::query()->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(1);
});
