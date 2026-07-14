<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Egc\ApplyEgcMajorEntryCreditAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
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
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function makeEgcEntitlementStudent(array $state = []): Student
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    return Student::factory()->state(array_merge([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_pre_uni_gc',
    ], $state))->create();
}

/**
 * @return array{0: FinanceCharge, 1: StudentInvoice, 2: InvoiceLine}
 */
function makeEgcLevelChargeWithInvoice(Student $student, Semester $semester, int $level = 1, float $amount = 15_000_000): array
{
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => $amount,
        'description' => "EGC Level {$level} Fee",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'test_egc_charge',
        'source_ref' => 'test-egc-charge:'.$charge->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge->update(['finance_obligation_id' => $obligation->id]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'EGC-ENT-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => $amount,
        'discount_total' => 0,
        'total_amount' => $amount,
        'paid_amount' => 0,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);

    return [$charge, $invoice, $line];
}

function attachEgcChargeToBlock(EgcBlock $block, FinanceCharge $charge): EgcBlock
{
    $charge->financeObligation->update([
        'source_system' => 'finance',
        'source_kind' => 'egc_block',
        'source_ref' => app(EgcBlockFinanceResolver::class)->sourceRef($block),
    ]);

    return $block->fresh();
}

it('creates an EGC retake discount entitlement and targets the mapped retake line', function (): void {
    $semester = Semester::factory()->create();
    $student = makeEgcEntitlementStudent();

    [$sourceCharge] = makeEgcLevelChargeWithInvoice($student, $semester, 1);
    $sourceBlock = attachEgcChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'block_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create(), $sourceCharge);

    [$targetCharge, $targetInvoice, $targetLine] = makeEgcLevelChargeWithInvoice($student, $semester, 1);
    attachEgcChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $targetCharge);

    $discount = ApplyEgcRetakeDiscountAction::run($sourceBlock->id, $targetCharge->id);

    $entitlement = FinanceDiscountEntitlement::query()->findOrFail($discount->finance_discount_entitlement_id);
    $allocation = DiscountAllocation::query()
        ->where('invoice_discount_id', $discount->id)
        ->firstOrFail();

    expect($entitlement->entitlement_type)->toBe(ObligationTypeRegistry::TYPE_EGC_RETAKE)
        ->and($entitlement->lifecycle_status)->toBe(FinanceDiscountEntitlement::STATUS_APPROVED)
        ->and($entitlement->allocation_status)->toBe(FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED)
        ->and((float) $entitlement->amount)->toBe(7_500_000.0)
        ->and($entitlement->source_kind)->toBe(ApplyEgcRetakeDiscountAction::SOURCE_KIND)
        ->and($entitlement->source_ref)->toBe(ApplyEgcRetakeDiscountAction::mintSourceRef((int) $sourceBlock->id))
        ->and($discount->discount_type)->toBe('egc_retake')
        ->and($allocation->invoice_line_id)->toBe($targetLine->id)
        ->and($allocation->allocation_rule)->toBe('egc_retake_target')
        ->and((float) $allocation->amount)->toBe(7_500_000.0)
        ->and(EgcRetakeDiscountLink::where('target_invoice_line_id', $targetLine->id)->exists())->toBeTrue()
        ->and($sourceBlock->fresh()->retake_discount_id)->toBe($discount->id)
        ->and(CreditApplication::query()->count())->toBe(0)
        ->and(FinanceCharge::query()->where('amount', '<', 0)->count())->toBe(0);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($targetInvoice->fresh());

    expect((float) $snapshot['gross'])->toBe(15_000_000.0)
        ->and((float) $snapshot['discount'])->toBe(7_500_000.0)
        ->and((float) $snapshot['credit'])->toBe(0.0)
        ->and((float) $snapshot['net'])->toBe(7_500_000.0)
        ->and((float) $snapshot['remaining'])->toBe(7_500_000.0)
        ->and(app(SettlementService::class)->getLineOutstandingAmount($targetLine->fresh()))->toBe(7_500_000.0);
});

it('creates an EGC exempt credit entitlement and applications without a negative charge', function (): void {
    $semester = Semester::factory()->create();
    $student = makeEgcEntitlementStudent(['status' => 'intake_major']);

    [, $invoice, $line] = makeEgcLevelChargeWithInvoice($student, $semester, 1, 15_000_000);

    $entitlement = ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id);

    $applications = CreditApplication::query()
        ->where('finance_credit_entitlement_id', $entitlement->id)
        ->get();

    expect($entitlement->entitlement_type)->toBe(FinanceEntitlementType::EgcExemptCredit)
        ->and($entitlement->lifecycle_status)->toBe(FinanceCreditEntitlement::STATUS_APPROVED)
        ->and($entitlement->allocation_status)->toBe(FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED)
        ->and((float) $entitlement->amount)->toBe(15_000_000.0)
        ->and($entitlement->source_kind)->toBe(ApplyEgcMajorEntryCreditAction::SOURCE_KIND)
        ->and($entitlement->source_ref)->toBe(ApplyEgcMajorEntryCreditAction::mintSourceRef($student->id, $semester->id))
        ->and($applications)->toHaveCount(1)
        ->and($applications->first()->invoice_line_id)->toBe($line->id)
        ->and((float) $applications->first()->amount)->toBe(15_000_000.0)
        ->and(DiscountAllocation::query()->count())->toBe(0)
        ->and(InvoiceDiscount::query()->count())->toBe(0)
        ->and(
            FinanceCharge::query()
                ->where('charge_type', FinanceEntitlementType::EgcExemptCredit)
                ->count()
        )->toBe(0)
        ->and(FinanceCharge::query()->where('amount', '<', 0)->count())->toBe(0);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $snapshot['gross'])->toBe(15_000_000.0)
        ->and((float) $snapshot['discount'])->toBe(0.0)
        ->and((float) $snapshot['credit'])->toBe(15_000_000.0)
        ->and((float) $snapshot['net'])->toBe(15_000_000.0)
        ->and((float) $snapshot['remaining'])->toBe(0.0)
        ->and(app(SettlementService::class)->getLineOutstandingAmount($line->fresh()))->toBe(0.0);
});

it('never represents an EGC reduction on both discount allocations and credit applications', function (): void {
    $semester = Semester::factory()->create();
    $egcStudent = makeEgcEntitlementStudent();
    $majorStudent = makeEgcEntitlementStudent(['status' => 'intake_major']);

    // Retake path (discount only)
    [$sourceCharge] = makeEgcLevelChargeWithInvoice($egcStudent, $semester, 1);
    $sourceBlock = attachEgcChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $egcStudent->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'block_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 90.0,
        'retake_discount_id' => null,
    ])->create(), $sourceCharge);

    [$targetCharge] = makeEgcLevelChargeWithInvoice($egcStudent, $semester, 1);
    attachEgcChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $egcStudent->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $targetCharge);

    $retakeDiscount = ApplyEgcRetakeDiscountAction::run($sourceBlock->id, $targetCharge->id);

    // Exempt credit path (credit only)
    makeEgcLevelChargeWithInvoice($majorStudent, $semester, 2);
    $creditEntitlement = ApplyEgcMajorEntryCreditAction::run($majorStudent->id, $semester->id);

    $retakeDiscountIds = InvoiceDiscount::query()
        ->where('finance_discount_entitlement_id', $retakeDiscount->finance_discount_entitlement_id)
        ->pluck('id');

    // One of each aggregate; each reduction uses only its own carrier family.
    expect(FinanceDiscountEntitlement::query()->count())->toBe(1)
        ->and(FinanceCreditEntitlement::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->whereIn('invoice_discount_id', $retakeDiscountIds)->count())->toBe(1)
        ->and(CreditApplication::query()->where('finance_credit_entitlement_id', $creditEntitlement->id)->count())->toBe(1)
        ->and(CreditApplication::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->count())->toBe(1)
        ->and(InvoiceDiscount::query()->whereNotNull('finance_discount_entitlement_id')->count())->toBe(1)
        ->and(FinanceCharge::query()->where('amount', '<', 0)->count())->toBe(0);
});
