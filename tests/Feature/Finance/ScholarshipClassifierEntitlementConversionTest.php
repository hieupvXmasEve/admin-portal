<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
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
            'charge_type' => FinanceEntitlementType::ScholarshipCredit,
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
